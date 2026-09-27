/**
 * The Integrations route: what EasySubscription can connect to, and whether each connection is live.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Badge,
	Button,
	Card,
	CardContent,
	Skeleton,
} from '@easysubscription/ui';
import { registerRoute } from '@easysubscription/shell';

function Heading() {
	return (
		<div className="es-mb-6">
			<h2 className="es-text-2xl es-font-semibold es-tracking-tight">
				{ __( 'Integrations', 'easysubscription' ) }
			</h2>
			<p className="es-mt-1 es-text-sm es-text-muted-foreground">
				{ __(
					'Hand a subscription to the plugin that delivers what it pays for, and take it back when it ends.',
					'easysubscription'
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
			className="es-grid es-h-10 es-w-10 es-shrink-0 es-place-items-center es-overflow-hidden es-rounded-md es-bg-accent es-font-semibold es-text-primary"
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
						__( 'That did not work.', 'easysubscription' )
				);
			} )
			.catch( () => {
				setBusy( false );
				setError( __( 'That did not work.', 'easysubscription' ) );
			} );
	};

	return (
		<Card className="es-flex es-flex-col es-gap-3 es-p-5">
			<div className="es-flex es-items-center es-gap-3">
				<TileIcon title={ item.title } icon={ item.icon } />
				<div className="es-min-w-0">
					<h3 className="es-text-sm es-font-medium">
						{ item.title }
					</h3>
					<p className="es-text-xs es-text-muted-foreground">
						{ sprintf(
							/* translators: %s: the plugin an integration needs */
							__( 'Needs %s', 'easysubscription' ),
							item.requires
						) }
					</p>
				</div>
			</div>

			{ item.description ? (
				<p className="es-text-sm es-text-muted-foreground">
					{ item.description }
				</p>
			) : null }

			{ item.hint ? (
				<p className="es-text-xs es-text-muted-foreground">
					{ item.hint }
				</p>
			) : null }

			<div className="es-mt-auto es-flex es-flex-wrap es-items-center es-justify-between es-gap-2">
				<Badge variant={ item.active ? 'success' : 'secondary' }>
					{ item.active
						? __( 'Connected', 'easysubscription' )
						: __( 'Not active', 'easysubscription' ) }
				</Badge>
				<span className="es-flex es-flex-wrap es-gap-2">
					{ item.configure_url ? (
						<Button asChild variant="outline" size="sm">
							<a href={ item.configure_url }>
								{ item.configure_label ||
									__( 'Settings', 'easysubscription' ) }
							</a>
						</Button>
					) : null }
					{ item.install && installer ? (
						<Button size="sm" disabled={ busy } onClick={ run }>
							{ busy
								? __( 'Installing…', 'easysubscription' )
								: __( 'Install', 'easysubscription' ) }
						</Button>
					) : null }
					{ ! item.install && item.get_url ? (
						<Button asChild variant="outline" size="sm">
							<a
								href={ item.get_url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __( 'Get it', 'easysubscription' ) }{ ' ' }
								<span aria-hidden="true">↗</span>
							</a>
						</Button>
					) : null }
				</span>
			</div>

			{ error ? (
				<p className="es-text-xs es-text-destructive" role="alert">
					{ error }
				</p>
			) : null }
		</Card>
	);
}

export function Integrations( { onFail } ) {
	const [ data, setData ] = useState( null );

	const load = useCallback(
		() =>
			apiFetch( { path: '/easysubscription/v1/integrations' } ).then(
				setData
			),
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
				<div className="es-grid es-gap-4 es-grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
					<Skeleton className="es-h-40" />
					<Skeleton className="es-h-40" />
					<Skeleton className="es-h-40" />
				</div>
			</div>
		);
	}

	if ( ! data.integrations.length ) {
		return (
			<div>
				<Heading />
				<Card>
					<CardContent className="es-flex es-flex-col es-items-center es-gap-2 es-p-10 es-text-center">
						<p className="es-font-medium">
							{ __(
								'Nothing to connect yet',
								'easysubscription'
							) }
						</p>
						<p className="es-max-w-md es-text-sm es-text-muted-foreground">
							{ __(
								'Integrations hand a subscription to the plugin that grants what it pays for — a course, a mailing list, a licence key. EasySubscription Pro adds them.',
								'easysubscription'
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
				<section key={ category } className="es-mb-8">
					<h2 className="es-mb-3 es-text-base es-font-semibold">
						{ category }
					</h2>
					<div className="es-grid es-gap-4 es-grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
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
			<p className="es-text-sm es-text-muted-foreground">
				{ __(
					'An integration only does anything while the plugin it connects to is active, and only for products you have configured it on.',
					'easysubscription'
				) }
			</p>
		</div>
	);
}

registerRoute( {
	page: 'easysubscription-integrations',
	title: __( 'Integrations', 'easysubscription' ),
	render: ( ctx ) => <Integrations onFail={ ctx.fail } />,
} );
