import { hasRoute, navigate, parse } from './router';

/**
 * The route a click should open in place, or null to let the browser have it.
 *
 * @param {MouseEvent} event
 * @return {{page: string, params: URLSearchParams}|null} The route.
 */
export function routeFromClick( event ) {
	if (
		event.defaultPrevented ||
		0 !== event.button ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey
	) {
		return null;
	}

	const anchor =
		event.target instanceof window.Element
			? event.target.closest( 'a[href]' )
			: null;

	if ( ! anchor || anchor.hasAttribute( 'download' ) ) {
		return null;
	}

	const target = anchor.getAttribute( 'target' );

	if ( target && '_self' !== target ) {
		return null;
	}

	const next = parse( anchor.href );

	return next && ! next.hash && hasRoute( next.page ) ? next : null;
}

export function interceptLinks() {
	document.addEventListener( 'click', ( event ) => {
		const next = routeFromClick( event );

		if ( next ) {
			event.preventDefault();
			navigate( next.page, next.params );
		}
	} );
}

/**
 * Moves WordPress's current-item marks to the route on screen, within the submenu that lists it.
 *
 * @param {string} page
 */
export function highlightMenu( page ) {
	document
		.querySelectorAll( '#adminmenu .wp-submenu' )
		.forEach( ( submenu ) => {
			const items = [ ...submenu.querySelectorAll( 'a[href]' ) ].map(
				( link ) => [ link, parse( link.href )?.page === page ]
			);

			if ( ! items.some( ( [ , on ] ) => on ) ) {
				return;
			}

			items.forEach( ( [ link, on ] ) => {
				link.classList.toggle( 'current', on );
				link.parentElement.classList.toggle( 'current', on );

				if ( on ) {
					link.setAttribute( 'aria-current', 'page' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );
		} );
}
