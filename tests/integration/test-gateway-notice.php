<?php
/**
 * The setup notice names the consequence that actually follows.
 *
 * A missing PayPal webhook ID was once reported as "PayPal is not offered at checkout",
 * which is not what happens: PayPal is offered and charges the customer, and every
 * webhook is then rejected, so no renewal is ever recorded.
 *
 * @package EasySubscription
 */

require __DIR__ . '/bootstrap.php';

$keys   = array( 'subkit_paypal_enabled', 'subkit_paypal_client_id', 'subkit_paypal_secret', 'subkit_paypal_webhook_id', 'subkit_stripe_enabled' );
$before = array();
foreach ( $keys as $key ) {
	$before[ $key ] = get_option( $key, null );
}

$render = static function (): string {
	ob_start();
	( new \SubKit\Admin\Gateway_Notice() )->render();

	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ob_get_clean() ) ) );
};

update_option( 'subkit_stripe_enabled', 'no' );
update_option( 'subkit_paypal_enabled', 'yes' );

update_option( 'subkit_paypal_client_id', 'AX' );
update_option( 'subkit_paypal_secret', 'SX' );
update_option( 'subkit_paypal_webhook_id', '' );
$text = $render();
$check( 'a missing webhook ID says renewals go unrecorded', str_contains( $text, 'renewals will not be recorded' ), $text );
$check( 'and does not claim PayPal is hidden at checkout', ! str_contains( $text, 'not offered at checkout' ), $text );

update_option( 'subkit_paypal_client_id', '' );
update_option( 'subkit_paypal_secret', '' );
$text = $render();
$check( 'missing credentials say PayPal is not offered', str_contains( $text, 'not offered at checkout' ), $text );
$check( 'and do not also claim customers can pay', ! str_contains( $text, 'Customers can pay' ), $text );

update_option( 'subkit_paypal_client_id', 'AX' );
update_option( 'subkit_paypal_secret', 'SX' );
update_option( 'subkit_paypal_webhook_id', 'WH-1' );
$check( 'a complete setup shows nothing', '' === $render(), $render() );

foreach ( $before as $key => $value ) {
	null === $value ? delete_option( $key ) : update_option( $key, $value );
}

subkit_test_done( $fail );
