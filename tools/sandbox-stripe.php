<?php
/**
 * Proves the renewal pipeline against a real Stripe test account.
 *
 * The one test that cannot be faked: a charge whose answer we never receive. We abort
 * the HTTP read after Stripe has already taken the request, which is a genuine unknown
 * outcome, then let reconciliation settle it and ask Stripe itself how many charges
 * exist. Anything but exactly one is a double charge.
 *
 * Reads the key from the SUBKIT_STRIPE_TEST_KEY environment variable. It is never
 * written to the database, printed, or logged.
 *
 * Usage, from the wp-docker directory:
 *   docker compose exec -T -e SUBKIT_STRIPE_TEST_KEY="$SUBKIT_STRIPE_TEST_KEY" wordpress \
 *     php /var/www/html/wp-content/plugins/subkit-subscriptions/tools/sandbox-stripe.php
 */

require_once '/var/www/html/wp-load.php';

use SubKit\Data\Charge_Slot_Repository;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Gateways\Stripe\Stripe_Gateway;

$key = (string) getenv( 'SUBKIT_STRIPE_TEST_KEY' );

if ( '' === $key ) {
	fwrite( STDERR, "SUBKIT_STRIPE_TEST_KEY is not set.\n" );
	exit( 1 );
}

if ( 0 !== strpos( $key, 'sk_test_' ) ) {
	// A live key here would charge real cards. Refuse rather than trust the caller.
	fwrite( STDERR, "Refusing to run: that is not a Stripe test key (expected sk_test_...).\n" );
	exit( 1 );
}

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	printf( "  [%s] %-52s %s\n", $ok ? 'PASS' : 'FAIL', $label, $detail );
}

/** Direct Stripe call, separate from the plugin, so assertions are independent of it. */
function stripe( string $key, string $method, string $path, array $params = array(), int $timeout = 30 ) {
	$args = array(
		'method'  => $method,
		'timeout' => $timeout,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		),
	);

	if ( $params ) {
		$args['body'] = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	$response = wp_remote_request( 'https://api.stripe.com' . $path, $args );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'transport', $response->get_error_message() );
	}

	return json_decode( wp_remote_retrieve_body( $response ), true );
}

echo "\n=== Reaching Stripe ===\n";
$account = stripe( $key, 'GET', '/v1/balance' );
check( 'test key authenticates', is_array( $account ) && ! isset( $account['error'] ), is_array( $account ) && isset( $account['error'] ) ? (string) $account['error']['message'] : 'ok' );

if ( ! is_array( $account ) || isset( $account['error'] ) ) {
	exit( 1 );
}

echo "\n=== Building a real customer with a saved card ===\n";
$customer = stripe( $key, 'POST', '/v1/customers', array( 'description' => 'SubKit sandbox run' ) );
check( 'customer created', isset( $customer['id'] ), (string) ( $customer['id'] ?? '' ) );

// pm_card_visa is Stripe's own test payment method; no card number is ever handled here.
$method = stripe( $key, 'POST', '/v1/payment_methods', array( 'type' => 'card', 'card' => array( 'token' => 'tok_visa' ) ) );
check( 'test payment method created', isset( $method['id'] ), (string) ( $method['id'] ?? '' ) );

$attached = stripe( $key, 'POST', '/v1/payment_methods/' . $method['id'] . '/attach', array( 'customer' => $customer['id'] ) );
check( 'payment method attached', isset( $attached['id'] ) && ! isset( $attached['error'] ) );

echo "\n=== Wiring SubKit to it ===\n";
update_option( 'subkit_stripe_enabled', 'yes' );
update_option( 'subkit_stripe_live', 'no' );
update_option( 'subkit_stripe_test_secret', $key );

$plugin  = SubKit\Plugin::instance();
$gateway = new Stripe_Gateway( SubKit\Gateways\Stripe\Stripe_Client::from_settings() );
$plugin->get( 'gateways' )->add( $gateway );

$subscription = new Subscription();
$subscription->set_currency( 'USD' );
$subscription->set_billing_period( 'month' );
$subscription->set_billing_interval( 1 );
$subscription->set_payment_method( Stripe_Gateway::ID );
$subscription->set_next_payment( gmdate( 'Y-m-d H:i:s', time() - 60 ) );
$subscription->transition_to( Subscription_Status::Active );
$subscription->save();

$subscription = wc_get_order( $subscription->get_id() );
$subscription->update_meta_data( Stripe_Gateway::META_CUSTOMER, $customer['id'] );
$subscription->update_meta_data( Stripe_Gateway::META_METHOD, $method['id'] );

$fee = new WC_Order_Item_Fee();
$fee->set_name( 'Sandbox renewal' );
$fee->set_total( '12.00' );
$subscription->add_item( $fee );
$subscription->calculate_totals( false );
$subscription->save();

$id = $subscription->get_id();
check( 'subscription ready', '12.00' === wc_get_order( $id )->get_total(), '#' . $id . ' total ' . wc_get_order( $id )->get_total() );

$slots = $plugin->get( 'charge_slots' );
$count_intents = static function () use ( $key, $id ): int {
	$search = stripe( $key, 'GET', '/v1/payment_intents/search?query=' . rawurlencode( sprintf( 'metadata["subkit_subscription"]:"%d"', $id ) ) . '&limit=100' );

	return is_array( $search ) && isset( $search['data'] ) ? count( $search['data'] ) : -1;
};
$slot_states = static function () use ( $id ): string {
	global $wpdb;

	return implode( ',', (array) $wpdb->get_col( $wpdb->prepare( "SELECT CONCAT(period_index,':',state) FROM {$wpdb->prefix}subkit_charge_slot WHERE subscription_id = %d ORDER BY period_index", $id ) ) );
};

echo "\n=== The timeout: abort the read after Stripe has the request ===\n";

// One second is enough to send and for Stripe to act, never enough to read the answer.
$cut = static function ( $seconds ) {
	return 1;
};
add_filter( 'http_request_timeout', $cut, 999 );
add_filter( 'http_request_args', static function ( $args ) {
	$args['timeout'] = 1;

	return $args;
}, 999 );

$plugin->get( 'processor' )->process( $id );

remove_all_filters( 'http_request_timeout', 999 );
remove_all_filters( 'http_request_args', 999 );

$after_timeout = $slot_states();
check( 'slot left unsettled, not failed', str_contains( $after_timeout, 'charging' ), $after_timeout );

sleep( 3 );
$landed = $count_intents();
check( 'Stripe really did take a charge', $landed >= 1, $landed . ' payment intent(s) at Stripe' );

echo "\n=== Reconciliation settles it without charging again ===\n";
$plugin->get( 'processor' )->process( $id );

sleep( 3 );
$final_intents = $count_intents();
$final_states  = $slot_states();

check( 'slot settled', str_contains( $final_states, 'paid' ), $final_states );
check( 'EXACTLY ONE charge exists at Stripe', 1 === $final_intents, $final_intents . ' payment intent(s) — anything but 1 is a double charge' );
check( 'subscription is active again', 'sk-active' === wc_get_order( $id )->get_status(), (string) wc_get_order( $id )->get_status() );
check( 'next payment moved forward', '' !== (string) wc_get_order( $id )->get_next_payment(), (string) wc_get_order( $id )->get_next_payment() );

echo "\n=== A second run changes nothing ===\n";
$plugin->get( 'processor' )->process( $id );
sleep( 2 );
check( 'still exactly one charge', 1 === $count_intents(), $count_intents() . ' payment intent(s)' );

echo "\n=== Cleaning up ===\n";
global $wpdb;
$wpdb->delete( $wpdb->prefix . 'subkit_charge_slot', array( 'subscription_id' => $id ) );
$wpdb->delete( $wpdb->prefix . 'subkit_activity', array( 'subscription_id' => $id ) );

foreach ( wc_get_orders( array( 'parent' => $id, 'limit' => -1, 'type' => 'shop_order' ) ) as $child ) {
	$child->delete( true );
}

wc_get_order( $id )->delete( true );
stripe( $key, 'DELETE', '/v1/customers/' . $customer['id'] );

delete_option( 'subkit_stripe_test_secret' );
delete_option( 'subkit_stripe_enabled' );
check( 'key removed from the database', '' === (string) get_option( 'subkit_stripe_test_secret', '' ) );

printf( "\n%d passed, %d failed\n\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
