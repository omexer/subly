<?php
/**
 * A customer can act on their own subscription and nobody else's, even holding a valid nonce.
 *
 * @package Subly
 */

use Subly\Domain\Subscription;
use Subly\Domain\Subscription_Status;

require __DIR__ . '/bootstrap.php';

if ( ! class_exists( 'Subly_Test_Redirected' ) ) {
	// Handlers redirect and exit once they act; stop at the redirect and read the state.
	final class Subly_Test_Redirected extends Exception {}
}
add_filter(
	'wp_redirect',
	static function ( $to ) {
		throw new Subly_Test_Redirected( (string) $to );
	},
	1
);

$user = static function ( string $name ): int {
	return wp_insert_user(
		array(
			'user_login' => 'sk_' . $name . '_' . wp_generate_password( 5, false, false ),
			'user_pass'  => wp_generate_password( 32 ),
			'user_email' => 'sb-' . $name . '-' . wp_generate_password( 5, false, false ) . '@example.test',
			'role'       => 'customer',
		)
	);
};

$alice = $user( 'alice' );
$bob   = $user( 'bob' );

$subscription_for = static function ( int $owner ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( $owner );
	$s->set_billing_period( 'month' );
	$s->set_billing_interval( 1 );
	$s->set_next_payment( gmdate( 'Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS ) );
	$s->transition_to( Subscription_Status::Pending );
	$s->save();
	$s->transition_to( Subscription_Status::Active );
	$s->save();

	return $s;
};

$state = static function ( int $id ): array {
	$s = wc_get_order( $id );

	return array( $s->get_status(), $s->get_next_payment(), $s->get_meta( '_subly_cancel_reason' ), $s->get_meta( '_subly_auto_renew' ) );
};

$attempt = static function ( callable $handler, array $post ): void {
	$_POST    = $post;
	$_REQUEST = $post;

	try {
		$handler();
	} catch ( Subly_Test_Redirected $e ) {
		unset( $e );
	}

	$_POST    = array();
	$_REQUEST = array();
};

$account = \Subly\Plugin::instance()->get( 'account' );
$survey  = \Subly\Plugin::instance()->get( 'survey' );

update_option( 'subly_allow_early_renewal', 'yes' );
update_option( 'subly_allow_auto_renew_toggle', 'yes' );

$actions = static function ( int $id ): array {
	return array(
		'cancel'              => array( 'subly_action' => 'cancel', 'subly_when' => 'immediate', 'subly_subscription' => $id, '_wpnonce' => wp_create_nonce( "subly_cancel_{$id}" ) ),
		'turn off auto-renew' => array( 'subly_action' => 'auto_renew', 'subly_auto_renew' => 'off', 'subly_subscription' => $id, '_wpnonce' => wp_create_nonce( "subly_auto_renew_{$id}" ) ),
		'pay a period early'  => array( 'subly_action' => 'renew_early', 'subly_subscription' => $id, '_wpnonce' => wp_create_nonce( "subly_renew_early_{$id}" ) ),
	);
};

foreach ( array( 'another customer' => $bob, 'a logged-out visitor' => 0 ) as $who => $attacker ) {
	$target = $subscription_for( $alice );
	$id     = $target->get_id();
	wp_set_current_user( $attacker );

	foreach ( $actions( $id ) as $what => $post ) {
		$before = $state( $id );
		$attempt( array( $account, 'handle_actions' ), $post );
		$check( "{$who} cannot {$what} somebody else's subscription", $before === $state( $id ), array( $before, $state( $id ) ) );
	}

	$before = $state( $id );
	$attempt( array( $survey, 'handle_submission' ), array( 'subly_action' => 'cancel_survey', 'subly_reason' => 'too_expensive', 'subly_subscription' => $id, '_wpnonce' => wp_create_nonce( "subly_survey_{$id}" ) ) );
	$check( "{$who} cannot write somebody else's cancellation reason", $before === $state( $id ), array( $before, $state( $id ) ) );

	$target->delete( true );
}

// And the owner still can: a check that refuses everybody would pass everything above.
$own  = $subscription_for( $alice );
$when = get_option( 'subly_cancellation_effective', null );
update_option( 'subly_cancellation_effective', 'immediate' );
wp_set_current_user( $alice );
$attempt( array( $account, 'handle_actions' ), $actions( $own->get_id() )['cancel'] );
$check( 'the owner can cancel their own subscription', str_contains( wc_get_order( $own->get_id() )->get_status(), 'cancelled' ), wc_get_order( $own->get_id() )->get_status() );
$own->delete( true );
null === $when ? delete_option( 'subly_cancellation_effective' ) : update_option( 'subly_cancellation_effective', $when );

delete_option( 'subly_allow_early_renewal' );
delete_option( 'subly_allow_auto_renew_toggle' );
wp_delete_user( $alice );
wp_delete_user( $bob );

subly_test_done( $fail );
