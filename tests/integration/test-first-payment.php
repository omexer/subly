<?php
/**
 * What a subscription costs today: trials, sign-up fees, and the payment step a zero total would skip.
 *
 * @package SubKit
 */

require __DIR__ . '/bootstrap.php';

$equals = static function ( string $what, $got, $want ) use ( $check ): void {
	$check( $what, (string) $got === (string) $want, array( 'got' => $got, 'want' => $want ) );
};

$make = function ( float $price, int $trial, float $fee ) {
	$id = wp_insert_post( array( 'post_title' => 'SK probe', 'post_type' => 'product', 'post_status' => 'publish' ) );
	$p = wc_get_product( $id );
	$p->update_meta_data( '_subkit_enabled', 'yes' );
	$p->set_regular_price( (string) $price );
	$p->set_price( (string) $price );
	$p->update_meta_data( '_subkit_period', 'month' );
	$p->update_meta_data( '_subkit_interval', 1 );
	$p->update_meta_data( '_subkit_trial_days', $trial );
	$p->update_meta_data( '_subkit_trial_period', 'day' );
	$p->update_meta_data( '_subkit_signup_fee', (string) $fee );
	$p->save();
	return $id;
};

$cart_total = function ( int $id ) {
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $id );
	WC()->cart->calculate_totals();
	return array( wc_format_decimal( WC()->cart->get_total( 'edit' ), 2 ), WC()->cart->needs_payment() );
};

echo "1. 14-day trial, \$5 sign-up fee, \$20/month\n";
$a = $make( 20, 14, 5 );
list( $total, $needs ) = $cart_total( $a );
$equals( 'charged today', $total, '5.00' );
$equals( 'payment step shown', $needs, true );
$equals( 'recurring price still 20', \SubKit\Product\Subscription_Product::recurring_price( wc_get_product( $a ) )->decimal(), '20.00' );

echo "\n2. 14-day trial, no sign-up fee (the zero-total case)\n";
$b = $make( 20, 14, 0 );
list( $total, $needs ) = $cart_total( $b );
$equals( 'charged today', $total, '0.00' );
$equals( 'payment step still shown', $needs, true );

echo "\n3. Same, with the setting switched off\n";
update_option( \SubKit\Checkout\Trial_Payment::OPTION, 'no' );
list( $total, $needs ) = $cart_total( $b );
$equals( 'payment step skipped', $needs, false );
delete_option( \SubKit\Checkout\Trial_Payment::OPTION );

echo "\n4. No trial, \$5 sign-up fee\n";
$c = $make( 20, 0, 5 );
list( $total, $needs ) = $cart_total( $c );
$equals( 'charged today', $total, '25.00' );

echo "\n5. Totals recalculated twice in one request must not re-add the fee\n";
WC()->cart->calculate_totals();
WC()->cart->calculate_totals();
$equals( 'still', wc_format_decimal( WC()->cart->get_total( 'edit' ), 2 ), '25.00' );

echo "\n6. Plain non-subscription product is untouched\n";
$d = wp_insert_post( array( 'post_title' => 'SK plain', 'post_type' => 'product', 'post_status' => 'publish' ) );
$p = wc_get_product( $d ); $p->set_regular_price( '20' ); $p->set_price( '20' ); $p->save();
list( $total, $needs ) = $cart_total( $d );
$equals( 'charged today', $total, '20.00' );

WC()->cart->empty_cart();
foreach ( array( $a, $b, $c, $d ) as $id ) { wp_delete_post( $id, true ); }
subkit_test_done( $fail );
