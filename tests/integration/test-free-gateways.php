<?php
/**
 * Free takes payment through PayPal: it has no Stripe gateway, settings section or block checkout registration of its own,
 * even on a store whose Stripe options are still filled in. With Pro active, anything Stripe must come from Pro.
 *
 * @package EasySubscription
 */

use EasySubscription\Admin\Gateway_Notice;
use EasySubscription\Gateways\Gateway_Registry;

require __DIR__ . '/bootstrap.php';

$pro = defined( 'EASYSUBSCRIPTION_PRO_VERSION' );
echo $pro ? "\n(EasySubscription Pro is active: whatever is Stripe must be Pro's)\n" : "\n(EasySubscription Pro is not active: nothing Stripe at all)\n";

// Absent, or Pro's when Pro is active; never free's.
$not_free = static function ( $thing ) use ( $pro ): bool {
	return null === $thing || ( $pro && is_object( $thing ) && str_starts_with( get_class( $thing ), 'EasySubscriptionPro\\' ) );
};

$options = array(
	'easysubscription_stripe_enabled'     => 'yes',
	'easysubscription_stripe_live'        => 'no',
	'easysubscription_stripe_test_secret' => 'sk_test_left_behind',
	'easysubscription_paypal_enabled'     => 'no',
);
$saved = array();
foreach ( $options as $name => $value ) {
	$saved[ $name ] = get_option( $name, null );
	update_option( $name, $value );
}

echo "\n1. No Stripe code in free\n";
$check( 'no Stripe classes', ! class_exists( 'EasySubscription\Gateways\Stripe\Stripe_Gateway' ) && ! class_exists( 'EasySubscription\Gateways\Stripe\Stripe_Checkout_Gateway' ) && ! class_exists( 'EasySubscription\Gateways\Stripe\Stripe_Client' ) );
$check( 'no Stripe folder', ! file_exists( EASYSUBSCRIPTION_PATH . 'includes/Gateways/Stripe' ) );
$check( 'no Stripe client service', null === \EasySubscription\Plugin::instance()->get( 'stripe_client' ) );

echo "\n2. Checkout\n";
$checkout = array();
foreach ( (array) apply_filters( 'woocommerce_payment_gateways', array() ) as $gateway ) {
	$gateway = is_string( $gateway ) && class_exists( $gateway ) ? new $gateway() : $gateway;
	if ( $gateway instanceof WC_Payment_Gateway ) {
		$checkout[ $gateway->id ] = $gateway;
	}
}
$check( 'PayPal is a checkout method', isset( $checkout['easysubscription_paypal'] ) && str_starts_with( get_class( $checkout['easysubscription_paypal'] ), 'EasySubscription\\' ) );
$check( 'Stripe is not one of free\'s', $not_free( $checkout['easysubscription_stripe'] ?? null ), isset( $checkout['easysubscription_stripe'] ) ? get_class( $checkout['easysubscription_stripe'] ) : null );

echo "\n3. Renewals\n";
$registry = new Gateway_Registry();
$registry->load_gateways();
$check( 'nothing in free renews through Stripe', $not_free( $registry->get( 'easysubscription_stripe' ) ), $registry->get( 'easysubscription_stripe' ) ? get_class( $registry->get( 'easysubscription_stripe' ) ) : null );

echo "\n4. Settings\n";
$woo = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'easysubscription' === $candidate->get_id() ) {
		$woo = $candidate;
	}
}
if ( ! $woo ) {
	easysubscription_test_abort( 'the settings tab is not registered' );
}
$sections = $woo->get_sections();
$check( 'free\'s settings have no Stripe section', ! method_exists( $woo, 'get_settings_for_stripe_section' ) );
$check( 'Payments has PayPal', isset( $sections['paypal'] ) );
$check( $pro ? 'Stripe is in Payments with Pro' : 'and no Stripe without Pro', $pro === isset( $sections['stripe'] ) && ( $pro || array() === $woo->get_settings_for_section( 'stripe' ) ), array_keys( $sections ) );

$menu     = rest_do_request( new WP_REST_Request( 'GET', '/easysubscription/v1/settings' ) );
$payments = array_column( array_column( $menu->get_data()['groups'] ?? array(), null, 'id' )['payments']['sections'] ?? array(), 'id' );
$check( 'the settings API agrees', in_array( 'paypal', $payments, true ) && $pro === in_array( 'stripe', $payments, true ), $payments );

echo "\n5. Block checkout\n";
$blocks = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class );
$check( 'PayPal is registered by free', $blocks->get_registered( 'easysubscription_paypal' ) instanceof \EasySubscription\Blocks\Gateway_Support );
$registered = $blocks->get_registered( 'easysubscription_stripe' );
$check( 'Stripe is not', $not_free( $registered ?: null ), $registered ? get_class( $registered ) : null );
$check( 'nor named in free\'s block script', ! str_contains( (string) file_get_contents( EASYSUBSCRIPTION_PATH . 'build/blocks.js' ), 'stripe' ) );

echo "\n6. Notices\n";
update_option( 'easysubscription_stripe_test_secret', '' );
ob_start();
( new Gateway_Notice() )->render();
$text = (string) ob_get_clean();
$check( 'free says nothing about a Stripe with no key', ! str_contains( $text, 'Stripe' ), $text );

foreach ( $saved as $name => $value ) {
	null === $value ? delete_option( $name ) : update_option( $name, $value );
}

easysubscription_test_done( $fail );
