/* global jQuery, easysubscriptionProductFields */
jQuery( function ( $ ) {
	var config = window.easysubscriptionProductFields || {},
		$panel = $( '#woocommerce-product-data' ),
		$options = $panel.find( '.easysubscription-product-options' ),
		$pricingSection = $panel.find( '.easysubscription-section--pricing' ),
		$pricingRows = $pricingSection.find( '.form-field' ),
		$wooPricing = $panel.find( '#general_product_data .options_group.pricing' ).first(),
		$pricingTitle = $( '<h4 class="easysubscription-section__title easysubscription-pricing-title"></h4>' ).text( config.pricingTitle || '' );

	function paymentType() {
		var $select = $panel.find( '.' + config.paymentTypeClass + ' select' );

		return $select.length ? $select.val() : config.defaultType;
	}

	// data-easysubscription-when="field_id=a|b" on any input in a row shows the row only for those values.
	function conditionsMet( $row ) {
		var met = true;

		$row.find( '[data-easysubscription-when]' ).each( function () {
			var rule = String( $( this ).attr( 'data-easysubscription-when' ) ).split( '=' ),
				$field = $( '#' + rule[ 0 ] ),
				allowed = ( rule[ 1 ] || '' ).split( '|' );

			if ( $field.length && allowed.indexOf( String( $field.val() ) ) === -1 ) {
				met = false;
			}
		} );

		return met;
	}

	// Hidden rows are disabled as well, so a value the merchant cannot see is never saved.
	function syncRows() {
		var current = 'easysubscription-for-' + paymentType();

		$options.find( '.form-field' ).each( function () {
			var $row = $( this ),
				typed = /(^|\s)easysubscription-for-/.test( this.className ),
				shown = ( ! typed || $row.hasClass( current ) ) && conditionsMet( $row );

			$row.toggleClass( 'easysubscription-type-hidden', ! shown );
			$row.find( 'input, select, textarea' ).each( function () {
				var $input = $( this );

				if ( ! shown && ! $input.prop( 'disabled' ) ) {
					$input.prop( 'disabled', true ).attr( 'data-easysubscription-disabled', '1' );
				} else if ( shown && $input.attr( 'data-easysubscription-disabled' ) ) {
					$input.prop( 'disabled', false ).removeAttr( 'data-easysubscription-disabled' );
				}
			} );
		} );
	}

	// A simple subscription's price fields are WooCommerce's; the sign-up fee joins them.
	function syncPricing() {
		var simple = $( '#product-type' ).val() === config.simpleType && $wooPricing.length;

		if ( simple ) {
			$wooPricing.prepend( $pricingTitle ).append( $pricingRows );
		} else {
			$pricingTitle.detach();
			$pricingSection.append( $pricingRows );
		}
	}

	function syncSections() {
		$panel.find( '.easysubscription-section' ).each( function () {
			var $section = $( this ),
				visible = $section.find( '.form-field' ).filter( function () {
					return $( this ).css( 'display' ) !== 'none';
				} );

			$section.toggleClass( 'easysubscription-section--empty', ! visible.length );
		} );
	}

	function syncShipping() {
		var $mirror = $panel.find( '[data-easysubscription-mirrors]' ),
			$virtual = $( '#' + $mirror.data( 'easysubscription-mirrors' ) );

		if ( $mirror.length && $virtual.length ) {
			$mirror.val( $virtual.is( ':checked' ) ? 'no' : 'yes' );
		}
	}

	function sync() {
		syncPricing();
		syncShipping();
		syncRows();
		syncSections();
	}

	$panel.on( 'click', '.easysubscription-section__toggle', function () {
		var $button = $( this ),
			open = $button.attr( 'aria-expanded' ) !== 'true';

		$button.attr( 'aria-expanded', open ? 'true' : 'false' );
		$button.closest( '.easysubscription-section' ).find( '.easysubscription-section__body' ).prop( 'hidden', ! open );
	} );

	$panel.on( 'change', '[data-easysubscription-mirrors]', function () {
		$( '#' + $( this ).data( 'easysubscription-mirrors' ) )
			.prop( 'checked', $( this ).val() === 'no' )
			.trigger( 'change' );
	} );

	$options.on( 'change', 'select, input', function () {
		syncRows();
		syncSections();
	} );

	$( '#_virtual' ).on( 'change', function () {
		syncShipping();
		syncRows();
		syncSections();
	} );

	// After WooCommerce's own show/hide for the new type, whichever handler ran first.
	$( document.body ).on( 'woocommerce-product-type-change', function () {
		window.setTimeout( sync, 0 );
	} );

	sync();
} );
