/**
 * The overview above the subscriptions table.
 *
 * Renders beside the PHP version rather than over it, and only hides that once real data
 * has arrived: a failed request leaves the server-rendered figures on screen instead of
 * replacing a working summary with an error.
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardHeader,
	CardTitle,
	CardContent,
	Stat,
	Sparkline,
	StatusBar,
	Skeleton,
} from '@subkit/ui';

function Overview( { data } ) {
	const { mrr, active, excluded, statuses, history } = data;
	const points = ( history || [] ).map( ( row ) => row.mrr );

	const meta =
		typeof mrr.change === 'number'
			? `${ mrr.change < 0 ? '' : '+' }${ mrr.change }% ${ __(
					'over 30 days',
					'subkit-subscriptions'
			  ) }`
			: __( 'Tracking starts today', 'subkit-subscriptions' );

	return (
		<>
			<div className="sk-my-4 sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
				<Stat
					label={ __(
						'Monthly recurring revenue',
						'subkit-subscriptions'
					) }
					value={ mrr.formatted }
					meta={ meta }
					delta={ mrr.change }
				>
					<Sparkline
						points={ points }
						label={ __(
							'Monthly recurring revenue over the last 30 days',
							'subkit-subscriptions'
						) }
					/>
				</Stat>

				<Stat
					label={ __( 'Live subscriptions', 'subkit-subscriptions' ) }
					value={ active }
				/>

				{ excluded ? (
					<Stat
						label={ __( 'Not counted', 'subkit-subscriptions' ) }
						value={ excluded }
						meta={ __(
							'in another currency',
							'subkit-subscriptions'
						) }
					/>
				) : null }
			</div>

			<Card className="sk-mb-4">
				<CardHeader>
					<CardTitle>
						{ __(
							'Where your subscriptions stand',
							'subkit-subscriptions'
						) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					<StatusBar parts={ statuses } />
				</CardContent>
			</Card>
		</>
	);
}

function Loading() {
	return (
		<div className="sk-my-4 sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
			<Skeleton className="sk-h-36" />
			<Skeleton className="sk-h-36" />
		</div>
	);
}

function App( { onReady, onFail } ) {
	const [ data, setData ] = useState( null );

	useEffect( () => {
		let live = true;

		apiFetch( { path: '/subkit/v1/overview' } )
			.then( ( result ) => {
				if ( ! live ) {
					return;
				}

				setData( result );
				onReady();
			} )
			.catch( () => live && onFail() );

		return () => {
			live = false;
		};
	}, [ onReady, onFail ] );

	return data ? <Overview data={ data } /> : <Loading />;
}

export function mount( fallback ) {
	if ( ! fallback ) {
		return null;
	}

	const host = document.createElement( 'div' );
	host.className = 'subkit-ui';
	fallback.parentNode.insertBefore( host, fallback );

	const root = createRoot( host );

	root.render(
		<App
			onReady={ () => {
				fallback.hidden = true;
			} }
			onFail={ () => {
				root.unmount();
				host.remove();
			} }
		/>
	);

	return host;
}

document.addEventListener( 'DOMContentLoaded', () => {
	mount( document.getElementById( 'subkit-overview-fallback' ) );
} );
