<?php
/**
 * The PayPal setup notices and the guest checkout warning appear only on Subly's screens and
 * WooCommerce → Settings, and Dismiss keeps them away until the problem changes.
 *
 * @package Subly
 */

use Subly\Admin\Notice_Dismissals;
use Subly\Checkout\Guest_Checkout;

require __DIR__ . '/bootstrap.php';

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

if ( ! class_exists( 'Subly_Test_Redirected' ) ) {
	final class Subly_Test_Redirected extends Exception {}
}
$stop_at_redirect = static function ( $to ) {
	throw new Subly_Test_Redirected( (string) $to );
};
add_filter( 'wp_redirect', $stop_at_redirect, 1 );

$plugin  = \Subly\Plugin::instance();
$options = array(
	'subly_paypal_enabled'                              => 'yes',
	'subly_paypal_client_id'                            => 'client-harness',
	'subly_paypal_secret'                               => '',
	'subly_paypal_webhook_id'                           => '',
	'subly_guest_checkout'                              => Guest_Checkout::REQUIRE,
	'woocommerce_enable_signup_and_login_from_checkout' => 'no',
	'woocommerce_enable_checkout_login_reminder'        => 'no',
);
$saved   = array();
foreach ( $options as $name => $value ) {
	$saved[ $name ] = get_option( $name, null );
	update_option( $name, $value );
}
$saved_screen   = $GLOBALS['current_screen'] ?? null;
$dismissed_meta = get_user_meta( 1, 'subly_dismissed_notices', true );
delete_user_meta( 1, 'subly_dismissed_notices' );

$text  = static function ( callable $callback ): string {
	ob_start();
	$callback();
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ob_get_clean() ) ) );
};
$html  = static function ( callable $callback ): string {
	ob_start();
	$callback();
	return (string) ob_get_clean();
};
$gateway = array( $plugin->get( 'gateway_notice' ), 'render' );
$guest   = array( $plugin->get( 'guest_checkout' ), 'warn_if_unreachable' );

$not_offered = static fn( string $out ): bool => str_contains( $out, 'not offering a payment method at checkout' );
$unrecorded  = static fn( string $out ): bool => str_contains( $out, 'renewals will not be recorded' );
$turned_away = static fn( string $out ): bool => str_contains( $out, 'turned away' );

// Follows the notice's own Dismiss link through the real handler.
$dismiss = static function ( string $notice_html ) use ( $plugin ): bool {
	if ( ! preg_match( '#href="([^"]*action=' . Notice_Dismissals::ACTION . '[^"]*)"#', $notice_html, $link ) ) {
		return false;
	}
	wp_parse_str( (string) wp_parse_url( html_entity_decode( $link[1] ), PHP_URL_QUERY ), $query );
	$_GET     = $query;
	$_REQUEST = $query;
	try {
		$plugin->get( 'notice_dismissals' )->handle();
	} catch ( Subly_Test_Redirected $redirected ) {
		$_GET     = array();
		$_REQUEST = array();
		return true;
	}
	$_GET     = array();
	$_REQUEST = array();
	return false;
};

echo "\n1. Only where they are about\n";

foreach ( array( 'dashboard', 'edit-post', 'plugins', 'product' ) as $screen ) {
	set_current_screen( $screen );
	$check( "nothing on {$screen}", '' === $text( $gateway ) && '' === $text( $guest ), $screen );
}

foreach ( array( 'toplevel_page_subly', 'subly_page_subly-settings', 'woocommerce_page_wc-settings' ) as $screen ) {
	set_current_screen( $screen );
	$check( "both on {$screen}", $not_offered( $text( $gateway ) ) && $turned_away( $text( $guest ) ), $screen );
}

$check( 'no notice offers a Dismiss that only hides it until the next page', ! str_contains( $html( $gateway ) . $html( $guest ), 'is-dismissible' ) );

echo "\n2. Dismiss persists, until the problem changes\n";
set_current_screen( 'toplevel_page_subly' );

$check( 'the gateway notice dismisses', $dismiss( $html( $gateway ) ) );
$check( '  and stays dismissed', ! $not_offered( $text( $gateway ) ) );
$check( '  on WooCommerce → Settings too', ( static function () use ( $gateway, $text, $not_offered ) {
	set_current_screen( 'woocommerce_page_wc-settings' );
	$out = $text( $gateway );
	set_current_screen( 'toplevel_page_subly' );
	return ! $not_offered( $out );
} )() );

update_option( 'subly_paypal_client_id', '' );
$check( 'another missing field brings it back', $not_offered( $text( $gateway ) ) );
$check( '  and it dismisses again', $dismiss( $html( $gateway ) ) && ! $not_offered( $text( $gateway ) ) );

update_option( 'subly_paypal_client_id', 'client-harness' );
update_option( 'subly_paypal_secret', 'secret-harness' );
$check( 'with credentials in, the webhook notice shows', $unrecorded( $text( $gateway ) ) && ! $not_offered( $text( $gateway ) ) );
$check( '  and dismisses', $dismiss( $html( $gateway ) ) && '' === $text( $gateway ) );

update_option( 'subly_paypal_secret', '' );
$check( 'credentials lost again: that is news', $not_offered( $text( $gateway ) ) );
update_option( 'subly_paypal_secret', 'secret-harness' );

update_option( 'subly_paypal_webhook_id', 'WH-1' );
$check( 'fixed, nothing shows', '' === $text( $gateway ) );
update_option( 'subly_paypal_webhook_id', '' );
$check( 'broken again after being fixed, it shows again', $unrecorded( $text( $gateway ) ) );

$check( 'the guest checkout warning dismisses', $dismiss( $html( $guest ) ) && '' === $text( $guest ) );
update_option( 'woocommerce_enable_checkout_login_reminder', 'yes' );
$check( 'fixed, nothing shows', '' === $text( $guest ) );
update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
$check( 'broken again, it shows again', $turned_away( $text( $guest ) ) );

wp_set_current_user( 0 );
$check( 'nobody without the capability sees either', '' === $text( $gateway ) && '' === $text( $guest ) );
wp_set_current_user( 1 );

remove_filter( 'wp_redirect', $stop_at_redirect, 1 );
$GLOBALS['current_screen'] = $saved_screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
'' === $dismissed_meta ? delete_user_meta( 1, 'subly_dismissed_notices' ) : update_user_meta( 1, 'subly_dismissed_notices', $dismissed_meta );
foreach ( $saved as $name => $value ) {
	null === $value ? delete_option( $name ) : update_option( $name, $value );
}

subly_test_done( $fail );
