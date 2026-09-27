/**
 * The app's frame: the header, the notices WordPress printed for this page, and the route.
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
import { __ } from '@wordpress/i18n';
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

const NOTICES = 'div.notice, div.error, div.updated';

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

/**
 * Moves nodes into a slot, returning how to put each one back where it was.
 *
 * @param {Element[]} nodes
 * @param {Element}   slot
 * @return {Function[]} Restorers, in the order the nodes were moved.
 */
function adopt( nodes, slot ) {
	return nodes.map( ( node ) => {
		const parent = node.parentNode;
		const next = node.nextSibling;

		slot.appendChild( node );

		return () => parent.insertBefore( node, next );
	} );
}

function pageNotices( fallback ) {
	return [ ...fallback.querySelectorAll( NOTICES ) ].filter(
		( node ) =>
			! node.matches( '.inline, .below-h2' ) &&
			! node.closest( '#subkit-other-notices' ) &&
			! node.parentElement.closest( NOTICES )
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
	const restorers = useRef( [] );
	const toggleSlot = useRef( null );
	const panelSlot = useRef( null );
	const noticeSlot = useRef( null );
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
				restorers.current.reverse().forEach( ( restore ) => restore() );
				restorers.current = [];
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
		if (
			! showing ||
			! fallback ||
			! fallback.isConnected ||
			( 0 === loc.nav && 0 === failedRef.current )
		) {
			return;
		}

		const chrome = [
			[
				fallback.querySelector( '[data-subkit-notices-toggle]' ),
				toggleSlot.current,
			],
			[
				fallback.querySelector( '#subkit-other-notices' ),
				panelSlot.current,
			],
		].filter( ( [ node ] ) => node );

		chrome.forEach( ( [ node, slot ] ) => {
			restorers.current.push( ...adopt( [ node ], slot ) );
		} );

		if ( 0 === loc.nav ) {
			restorers.current.push(
				...adopt( pageNotices( fallback ), noticeSlot.current )
			);
			fallback.hidden = true;
			return;
		}

		restorers.current = [];
		fallback.remove();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ showing, loc.nav ] );

	useLayoutEffect( () => {
		if ( 0 === loc.nav || ! noticeSlot.current ) {
			return;
		}

		// What WordPress said about the first page is not about this one.
		[ ...noticeSlot.current.children ]
			.filter( ( node ) => ! node.classList.contains( 'wp-header-end' ) )
			.forEach( ( node ) => node.remove() );

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
						<span ref={ toggleSlot } className="subkit-app__slot" />
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
			<div ref={ panelSlot } />
			<div className="wrap subkit-shell__body">
				<h1 className="screen-reader-text">{ route.title }</h1>
				<div ref={ noticeSlot } className="subkit-app__notices">
					<hr className="wp-header-end" />
				</div>
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

	// WordPress moves notices under this marker; ours sits under the app's header instead.
	if ( fallback && getRoute( host.dataset.page ) ) {
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
