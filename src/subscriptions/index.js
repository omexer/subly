/**
 * The subscriptions screen: the list, or one subscription.
 *
 * Which one is decided by the same query argument the server reads, and moving between
 * them pushes history, so the browser's back button still works.
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import { List } from './list';
import { Detail } from './detail';

function currentId() {
	return (
		Number(
			new URLSearchParams( window.location.search ).get( 'subscription' )
		) || 0
	);
}

export function Screen( { onReady, onFail } ) {
	const [ id, setId ] = useState( currentId );

	useEffect( () => {
		const onPop = () => setId( currentId() );

		window.addEventListener( 'popstate', onPop );

		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	const go = ( next ) => {
		const url = new URL( window.location.href );

		if ( next ) {
			url.searchParams.set( 'subscription', String( next ) );
		} else {
			url.searchParams.delete( 'subscription' );
		}

		window.history.pushState( {}, '', url );
		setId( next );
	};

	return id ? (
		<Detail
			id={ id }
			onBack={ () => go( 0 ) }
			onReady={ onReady }
			onFail={ onFail }
		/>
	) : (
		<List onOpen={ go } onReady={ onReady } onFail={ onFail } />
	);
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
		<Screen
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
	mount( document.getElementById( 'subkit-subscriptions-fallback' ) );
} );
