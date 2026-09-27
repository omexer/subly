/**
 * Renders the recurring-payment disclosure inside the Cart and Checkout blocks.
 *
 * Plain wp.element.createElement rather than JSX so the free plugin ships with no build
 * step and no npm dependency. The terms themselves come from the Store API, so the
 * wording is produced in one place for classic and block checkout alike.
 */
( function ( wp, wc ) {
	if ( ! wp || ! wp.plugins || ! wc || ! wc.blocksCheckout ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var ExperimentalOrderMeta = wc.blocksCheckout.ExperimentalOrderMeta;

	if ( ! ExperimentalOrderMeta ) {
		return;
	}

	function Disclosure( props ) {
		var data = ( props.extensions || {} ).easysubscription;

		if ( ! data || ! data.has_subscription ) {
			return null;
		}

		return el(
			ExperimentalOrderMeta,
			null,
			el(
				'div',
				{
					className: 'easysubscription-blocks-disclosure',
					role: 'group',
					'aria-label': __( 'Subscription terms', 'easysubscription' ),
				},
				el( 'p', { className: 'easysubscription-blocks-disclosure__price' }, data.price_line ),
				el(
					'ul',
					{ className: 'easysubscription-blocks-disclosure__facts' },
					( data.lines || [] ).map( function ( line, index ) {
						return el( 'li', { key: 'easysubscription-fact-' + index }, line );
					} )
				),
				data.consent
					? el( 'p', { className: 'easysubscription-blocks-disclosure__consent' }, data.consent )
					: null
			)
		);
	}

	wp.plugins.registerPlugin( 'easysubscription-subscription-terms', {
		render: Disclosure,
		scope: 'woocommerce-checkout',
	} );
} )( window.wp, window.wc );
