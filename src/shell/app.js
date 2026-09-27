/**
 * The app's frame: the header with its notification centre, the page's important notices, and the route.
 */
import {
	Component,
	Suspense,
	createRoot,
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Card, CardContent, Skeleton } from '@subkit/ui';
import {
	browser,
	current,
	getRoute,
	navigate,
	setParams,
	start,
	subscribe,
} from './router';
import { highlightMenu, interceptLinks } from './links';
import { createNotices } from './notices';

// Set by the server on pages the shell draws; the stylesheet hides their notices until they are moved.
const APP_PAGE = 'subkit-app-page';
const FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

class Boundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	componentDidCatch( error ) {
		this.props.onError( error );
	}

	render() {
		return this.state.failed ? null : this.props.children;
	}
}

function RouteView( { route, ctx } ) {
	return route.render( ctx );
}

function Loading() {
	return (
		<div className="sk-grid sk-gap-4">
			<Skeleton className="sk-h-10 sk-w-72" />
			<Skeleton className="sk-h-48" />
		</div>
	);
}

function Failed() {
	return (
		<Card>
			<CardContent className="sk-flex sk-flex-col sk-items-start sk-gap-3 sk-p-6">
				<p className="sk-font-medium">
					{ __( 'This page did not load.', 'subkit-subscriptions' ) }
				</p>
				<Button asChild variant="outline" size="sm">
					<a
						href={ window.location.href }
						onClick={ ( event ) => {
							event.preventDefault();
							browser.reload();
						} }
					>
						{ __( 'Reload', 'subkit-subscriptions' ) }
					</a>
				</Button>
			</CardContent>
		</Card>
	);
}

function Icon( { children } ) {
	return (
		<svg
			viewBox="0 0 24 24"
			width="16"
			height="16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			{ children }
		</svg>
	);
}

function NoticeCentre( { count, open, setOpen, listRef } ) {
	const wrapRef = useRef( null );
	const bellRef = useRef( null );
	const panelRef = useRef( null );
	const shown = open && count > 0;

	useEffect( () => {
		if ( ! shown ) {
			return;
		}

		const panel = panelRef.current;

		panel.focus();

		const trap = ( event ) => {
			const items = [ ...panel.querySelectorAll( FOCUSABLE ) ].filter(
				( el ) => ! el.closest( '[hidden]' )
			);
			const active = panel.ownerDocument.activeElement;

			if ( ! items.length ) {
				event.preventDefault();
			} else if (
				event.shiftKey &&
				( active === items[ 0 ] || active === panel )
			) {
				event.preventDefault();
				items.at( -1 ).focus();
			} else if ( ! event.shiftKey && active === items.at( -1 ) ) {
				event.preventDefault();
				items[ 0 ].focus();
			}
		};
		const onDown = ( event ) => {
			if ( ! wrapRef.current.contains( event.target ) ) {
				setOpen( false );
			}
		};
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				setOpen( false );
				bellRef.current?.focus();
			} else if (
				'Tab' === event.key &&
				panel.contains( panel.ownerDocument.activeElement )
			) {
				trap( event );
			}
		};

		document.addEventListener( 'mousedown', onDown );
		document.addEventListener( 'keydown', onKey );

		return () => {
			document.removeEventListener( 'mousedown', onDown );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ shown, setOpen ] );

	const label = sprintf(
		/* translators: %d: number of notifications */
		_n(
			'%d notification',
			'%d notifications',
			count,
			'subkit-subscriptions'
		),
		count
	);

	return (
		<div ref={ wrapRef } className="subkit-notify">
			{ count > 0 ? (
				<button
					ref={ bellRef }
					type="button"
					className="subkit-notify__bell"
					aria-label={ label }
					title={ label }
					aria-expanded={ shown }
					aria-controls="subkit-notify-panel"
					onClick={ () => setOpen( ! open ) }
				>
					<svg
						viewBox="0 0 24 24"
						width="18"
						height="18"
						fill="none"
						stroke="currentColor"
						strokeWidth="1.6"
						strokeLinecap="round"
						strokeLinejoin="round"
						aria-hidden="true"
						focusable="false"
					>
						<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0" />
					</svg>
					<span className="subkit-notify__count" aria-hidden="true">
						{ count }
					</span>
				</button>
			) : null }
			{ /* Always mounted: the notices live in the list whether or not it is open. */ }
			<div
				ref={ panelRef }
				id="subkit-notify-panel"
				className="subkit-notify__panel"
				role="dialog"
				aria-label={ __( 'Notifications', 'subkit-subscriptions' ) }
				tabIndex={ -1 }
				hidden={ ! shown }
			>
				<p className="subkit-notify__head">
					{ __( 'Notifications', 'subkit-subscriptions' ) }
				</p>
				<div ref={ listRef } className="subkit-notify__list" />
			</div>
		</div>
	);
}

let suffix = null;

function setTitle( title ) {
	if ( null === suffix ) {
		const at = document.title.indexOf( ' ‹ ' );
		suffix = at >= 0 ? document.title.slice( at ) : '';
	}

	document.title = title + suffix;
}

export function Shell( { fallback, links } ) {
	const [ loc, setLoc ] = useState( () => ( {
		...current(),
		nav: 0,
		mount: 0,
	} ) );
	const [ failedAt, setFailedAt ] = useState( null );
	const navRef = useRef( 0 );
	const mountRef = useRef( 0 );
	const failedRef = useRef( null );
	const notices = useRef( null );
	const alertsRef = useRef( null );
	const listRef = useRef( null );
	const routeRef = useRef( null );
	const [ counts, setCounts ] = useState( { more: 0, centre: 0 } );
	const [ centreOpen, setCentreOpen ] = useState( false );
	const route = getRoute( loc.page );

	useEffect(
		() =>
			subscribe( ( next ) =>
				setLoc( ( prev ) => ( {
					...next,
					nav: prev.nav + 1,
					mount:
						'navigate' === next.kind || next.page !== prev.page
							? prev.mount + 1
							: prev.mount,
				} ) )
			),
		[]
	);

	navRef.current = loc.nav;
	mountRef.current = loc.mount;

	const showing = !! route && ! ( 0 === failedAt && 0 === loc.nav );

	const fail = useCallback(
		( mount ) => {
			// A route that has already been replaced cannot fail the one on screen.
			if ( mount !== mountRef.current ) {
				return;
			}

			// The first page falls back to what the server drew for it, which is still on the page.
			if ( 0 === navRef.current && fallback && fallback.isConnected ) {
				notices.current?.restore();
				notices.current = null;
				document.body.classList.remove( APP_PAGE );
				fallback
					.querySelectorAll( '[data-subkit-header-end]' )
					.forEach( ( marker ) =>
						marker.classList.add( 'wp-header-end' )
					);
				fallback.hidden = false;
			}

			failedRef.current = navRef.current;
			setFailedAt( navRef.current );
		},
		[ fallback ]
	);

	useLayoutEffect( () => {
		// A first route that threw has already failed by the time this runs in the same commit.
		if ( ! showing || ( 0 === loc.nav && 0 === failedRef.current ) ) {
			return;
		}

		if ( ! notices.current ) {
			const root =
				document.getElementById( 'wpbody-content' ) || document.body;

			document.body.classList.add( APP_PAGE );
			notices.current = createNotices( {
				alerts: alertsRef.current,
				list: listRef.current,
				isRoute: ( node ) => !! routeRef.current?.contains( node ),
				onChange: setCounts,
			} );
			notices.current.collect( root );
			notices.current.watch( root );
		}

		if ( ! fallback || ! fallback.isConnected ) {
			return;
		}

		if ( 0 === loc.nav ) {
			fallback.hidden = true;
			return;
		}

		fallback.remove();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ showing, loc.nav ] );

	useEffect( () => () => notices.current?.stop(), [] );

	useLayoutEffect( () => {
		if ( 0 === loc.nav ) {
			return;
		}

		notices.current?.dropFeedback();

		if ( 'pop' !== loc.kind ) {
			window.scrollTo( 0, 0 );
		}
	}, [ loc.nav, loc.kind ] );

	useEffect( () => {
		if ( route ) {
			setTitle( route.title );
		}

		highlightMenu( loc.page );
	}, [ route, loc.page ] );

	const ctx = useMemo(
		() => ( {
			params: new URLSearchParams( loc.params ),
			navigate,
			setParams,
			fail: () => fail( loc.mount ),
		} ),
		[ loc, fail ]
	);

	if ( ! showing ) {
		return null;
	}

	return (
		<div className="subkit-shell">
			<header className="subkit-shell__bar">
				<div className="subkit-shell__inner">
					<nav
						className="subkit-crumbs"
						aria-label={ __(
							'Breadcrumb',
							'subkit-subscriptions'
						) }
					>
						<a className="subkit-crumbs__home" href={ links.home }>
							<img
								className="subkit-logo"
								src={ links.logo }
								width="178"
								height="28"
								alt={ __(
									'EasySubscription',
									'subkit-subscriptions'
								) }
							/>
						</a>
						<span className="subkit-crumbs__sep" aria-hidden="true">
							/
						</span>
						<span
							className="subkit-crumbs__current"
							aria-current="page"
						>
							{ route.title }
						</span>
					</nav>
					<div className="subkit-shell__meta">
						<NoticeCentre
							count={ counts.centre }
							open={ centreOpen }
							setOpen={ setCentreOpen }
							listRef={ listRef }
						/>
						<a href={ links.help }>
							<Icon>
								<rect
									x="3.5"
									y="3.5"
									width="17"
									height="17"
									rx="3"
								/>
								<path d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4M12 16.6h.01" />
							</Icon>
							{ __( 'Help', 'subkit-subscriptions' ) }
						</a>
						<a href={ links.settings }>
							<Icon>
								<circle cx="12" cy="12" r="3" />
								<path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z" />
							</Icon>
							{ __( 'Settings', 'subkit-subscriptions' ) }
						</a>
						{ links.upgrade ? (
							<a
								className="subkit-shell__upgrade"
								href={ links.upgrade }
								target="_blank"
								rel="noopener noreferrer"
							>
								<Icon>
									<path d="M3 8l4.5 4L12 6l4.5 6L21 8l-2 10H5z" />
								</Icon>
								{ __(
									'Upgrade to Pro',
									'subkit-subscriptions'
								) }
								<span className="screen-reader-text">
									{ ' ' }
									{ __(
										'(opens in a new tab)',
										'subkit-subscriptions'
									) }
								</span>
							</a>
						) : null }
					</div>
				</div>
			</header>
			<div className="wrap subkit-shell__body">
				<h1 className="screen-reader-text">{ route.title }</h1>
				<div className="subkit-app__landing">
					<hr className="wp-header-end" />
				</div>
				<div ref={ alertsRef } className="subkit-alerts" />
				{ counts.more > 0 ? (
					<button
						type="button"
						className="subkit-alerts__more"
						aria-controls="subkit-notify-panel"
						onClick={ () => setCentreOpen( true ) }
					>
						{ sprintf(
							/* translators: %d: number of important notices not shown */
							_n(
								'and %d more',
								'and %d more',
								counts.more,
								'subkit-subscriptions'
							),
							counts.more
						) }
					</button>
				) : null }
				<div ref={ routeRef } className="subkit-app__route">
					{ failedAt === loc.nav ? (
						<Failed />
					) : (
						<Boundary key={ loc.mount } onError={ ctx.fail }>
							<Suspense fallback={ <Loading /> }>
								<RouteView route={ route } ctx={ ctx } />
							</Suspense>
						</Boundary>
					) }
				</div>
			</div>
		</div>
	);
}

/**
 * Draws the route the server named, and takes over navigation between routes.
 */
export function boot() {
	const host = document.getElementById( 'subkit-app' );

	if ( ! host ) {
		return null;
	}

	const fallback = document.getElementById( 'subkit-fallback' );

	if ( ! getRoute( host.dataset.page ) ) {
		document.body.classList.remove( APP_PAGE );
	} else if ( fallback ) {
		// WordPress moves notices under this marker; ours sits under the app's header instead.
		fallback.querySelectorAll( '.wp-header-end' ).forEach( ( marker ) => {
			marker.classList.remove( 'wp-header-end' );
			marker.setAttribute( 'data-subkit-header-end', '' );
		} );
	}

	start( host.dataset.page );
	interceptLinks();

	const root = createRoot( host );

	root.render(
		<Shell
			fallback={ fallback }
			links={ window.subkitShellData?.links || {} }
		/>
	);

	return root;
}
