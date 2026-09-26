/**
 * The Integrations route: what EasySubscription can connect to, and whether each connection is live.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Badge, Button, Card, CardContent, Skeleton } from '@subkit/ui';
import { registerRoute } from '@subkit/shell';

function Heading() {
	return (
		<div className="sk-mb-6">
			<h2 className="sk-text-2xl sk-font-semibold sk-tracking-tight">
				{ __( 'Integrations', 'subkit-subscriptions' ) }
			</h2>
			<p className="sk-mt-1 sk-text-sm sk-text-muted-foreground">
				{ __(
					'Hand a subscription to the plugin that delivers what it pays for, and take it back when it ends.',
					'subkit-subscriptions'
				) }
			</p>
		</div>
	);
}

function TileIcon( { title, icon } ) {
	const [ broken, setBroken ] = useState( false );
	const initial = ( title || '?' ).slice( 0, 1 ).toUpperCase();

	return (
		<span
			className="sk-grid sk-h-10 sk-w-10 sk-shrink-0 sk-place-items-center sk-overflow-hidden sk-rounded-md sk-bg-accent sk-font-semibold sk-text-primary"
			aria-hidden="true"
		>
			{ icon && ! broken ? (
				<img
					src={ icon }
					alt=""
					width="40"
					height="40"
					loading="lazy"
					onError={ () => setBroken( true ) }
				/>
			) : (
				initial
			) }
		</span>
	);
}

/**
 * Installs through the same admin-ajax action the server-rendered screen uses.
 *
 * @param {Object} installer
 * @param {string} slug
 * @return {Promise<Object>} What the installer answered.
 */
export function install( installer, slug ) {
	const body = new window.FormData();

	body.append( 'action', installer.action );
	body.append( '_wpnonce', installer.nonce );
	body.append( 'slug', slug );

	return window
		.fetch( installer.url, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} )
		.then( ( response ) => response.json() );
}

function Tile( { item, installer, onInstalled } ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const run = () => {
		setBusy( true );
		setError( '' );

		install( installer, item.install )
			.then( ( result ) => {
				if ( result?.success ) {
					return onInstalled();
				}

				setBusy( false );
				setError(
					result?.data?.message ||
						__( 'That did not work.', 'subkit-subscriptions' )
				);
			} )
			.catch( () => {
				setBusy( false );
				setError( __( 'That did not work.', 'subkit-subscriptions' ) );
			} );
	};

	return (
		<Card className="sk-flex sk-flex-col sk-gap-3 sk-p-5">
			<div className="sk-flex sk-items-center sk-gap-3">
				<TileIcon title={ item.title } icon={ item.icon } />
				<div className="sk-min-w-0">
					<h3 className="sk-text-sm sk-font-medium">
						{ item.title }
					</h3>
					<p className="sk-text-xs sk-text-muted-foreground">
						{ sprintf(
							/* translators: %s: the plugin an integration needs */
							__( 'Needs %s', 'subkit-subscriptions' ),
							item.requires
						) }
					</p>
				</div>
			</div>

			{ item.description ? (
				<p className="sk-text-sm sk-text-muted-foreground">
					{ item.description }
				</p>
			) : null }

			{ item.hint ? (
				<p className="sk-text-xs sk-text-muted-foreground">
					{ item.hint }
				</p>
			) : null }

			<div className="sk-mt-auto sk-flex sk-flex-wrap sk-items-center sk-justify-between sk-gap-2">
				<Badge variant={ item.active ? 'success' : 'secondary' }>
					{ item.active
						? __( 'Connected', 'subkit-subscriptions' )
						: __( 'Not active', 'subkit-subscriptions' ) }
				</Badge>
				<span className="sk-flex sk-flex-wrap sk-gap-2">
					{ item.configure_url ? (
						<Button asChild variant="outline" size="sm">
							<a href={ item.configure_url }>
								{ item.configure_label ||
									__( 'Settings', 'subkit-subscriptions' ) }
							</a>
						</Button>
					) : null }
					{ item.install && installer ? (
						<Button size="sm" disabled={ busy } onClick={ run }>
							{ busy
								? __( 'Installing…', 'subkit-subscriptions' )
								: __( 'Install', 'subkit-subscriptions' ) }
						</Button>
					) : null }
					{ ! item.install && item.get_url ? (
						<Button asChild variant="outline" size="sm">
							<a
								href={ item.get_url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __( 'Get it', 'subkit-subscriptions' ) }{ ' ' }
								<span aria-hidden="true">↗</span>
							</a>
						</Button>
					) : null }
				</span>
			</div>

			{ error ? (
				<p className="sk-text-xs sk-text-destructive" role="alert">
					{ error }
				</p>
			) : null }
		</Card>
	);
}

export function Integrations( { onFail } ) {
	const [ data, setData ] = useState( null );

	const load = useCallback(
		() => apiFetch( { path: '/subkit/v1/integrations' } ).then( setData ),
		[]
	);

	useEffect( () => {
		load().catch( () => onFail() );
		// Loaded once: onFail is a new closure on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( ! data ) {
		return (
			<div>
				<Heading />
				<div className="sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
					<Skeleton className="sk-h-40" />
					<Skeleton className="sk-h-40" />
					<Skeleton className="sk-h-40" />
				</div>
			</div>
		);
	}

	if ( ! data.integrations.length ) {
		return (
			<div>
				<Heading />
				<Card>
					<CardContent className="sk-flex sk-flex-col sk-items-center sk-gap-2 sk-p-10 sk-text-center">
						<p className="sk-font-medium">
							{ __(
								'Nothing to connect yet',
								'subkit-subscriptions'
							) }
						</p>
						<p className="sk-max-w-md sk-text-sm sk-text-muted-foreground">
							{ __(
								'Integrations hand a subscription to the plugin that grants what it pays for — a course, a mailing list, a licence key. EasySubscription Pro adds them.',
								'subkit-subscriptions'
							) }
						</p>
					</CardContent>
				</Card>
			</div>
		);
	}

	const groups = new Map();

	data.integrations.forEach( ( item ) => {
		groups.set( item.category, [
			...( groups.get( item.category ) || [] ),
			item,
		] );
	} );

	return (
		<div>
			<Heading />
			{ [ ...groups ].map( ( [ category, items ] ) => (
				<section key={ category } className="sk-mb-8">
					<h2 className="sk-mb-3 sk-text-base sk-font-semibold">
						{ category }
					</h2>
					<div className="sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
						{ items.map( ( item ) => (
							<Tile
								key={ item.title }
								item={ item }
								installer={ data.installer }
								onInstalled={ load }
							/>
						) ) }
					</div>
				</section>
			) ) }
			<p className="sk-text-sm sk-text-muted-foreground">
				{ __(
					'An integration only does anything while the plugin it connects to is active, and only for products you have configured it on.',
					'subkit-subscriptions'
				) }
			</p>
		</div>
	);
}

registerRoute( {
	page: 'subkit-subscriptions-integrations',
	title: __( 'Integrations', 'subkit-subscriptions' ),
	render: ( ctx ) => <Integrations onFail={ ctx.fail } />,
} );
