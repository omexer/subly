<?php
/**
 * Renewal and billing rules the free plugin owns: the renewal reminder switch, where the
 * grace and renewal settings sit, and what a grace period means for roles, files and the
 * words a customer reads. EasySubscription alone gives no grace period; one is supplied here the way
 * EasySubscription Pro supplies it, through subkit_grace_ends_at.
 *
 * @package EasySubscription
 */

use SubKit\Billing\Renewal_Scheduler;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Frontend\MyAccount\Status_Presenter;

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$absent = '__subkit_absent__';
$saved  = array();
$set    = function ( string $name, $value ) use ( &$saved, $absent ): void {
	if ( ! array_key_exists( $name, $saved ) ) { $saved[ $name ] = get_option( $name, $absent ); }
	update_option( $name, $value );
};

$product = subkit_test_product();
$made    = array();
$make    = function ( Subscription_Status $status, int $customer = 1, int $due_in_days = 10 ) use ( $product, &$made ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( $customer ); $s->set_currency( get_woocommerce_currency() );
	$s->set_billing_period( 'month' ); $s->set_billing_interval( 1 );
	$s->set_address( array( 'first_name' => 'Rules', 'email' => 'billing-rules@example.test', 'country' => 'US' ), 'billing' );
	$i = new WC_Order_Item_Product();
	$i->set_props( array( 'name' => 'Harness', 'product_id' => $product->get_id(), 'quantity' => 1, 'subtotal' => '20', 'total' => '20' ) );
	$s->add_item( $i );
	$s->set_next_payment( gmdate( 'Y-m-d H:i:s', time() + $due_in_days * DAY_IN_SECONDS ) );
	$s->transition_to( Subscription_Status::Pending ); $s->calculate_totals( false ); $s->save();
	$s->transition_to( $status ); $s->save();
	$made[] = $s->get_id();
	return $s;
};

$sent = array();
add_filter(
	'wp_mail',
	function ( $mail ) use ( &$sent ) {
		if ( 'billing-rules@example.test' === $mail['to'] ) {
			$sent[] = wp_strip_all_tags( $mail['message'] );
		}
		return $mail;
	},
	999
);

echo "\nRenewal reminder\n";
$scheduler = \SubKit\Plugin::instance()->get( 'scheduler' );
$set( 'subkit_renewal_reminder_days', 3 );
$r = $make( Subscription_Status::Active );
$scheduler->schedule_next( $r );
$check( 'fixture: a reminder is queued', (bool) as_next_scheduled_action( Renewal_Scheduler::ACTION_REMINDER, array( 'subscription_id' => $r->get_id() ), Renewal_Scheduler::GROUP ) );

$sent = array();
$scheduler->remind( $r->get_id() );
$check( 'fixture: while on, it is sent', 1 === count( $sent ), count( $sent ) );

foreach ( array( '0' => 0, 'empty' => '' ) as $label => $off ) {
	$set( 'subkit_renewal_reminder_days', $off );
	$sent = array();
	$scheduler->remind( $r->get_id() );
	$check( "switched off ($label) after it was queued, it sends nothing", array() === $sent, count( $sent ) );
	$scheduler->schedule_next( $r );
	$check( "and nothing new is queued ($label)", ! as_next_scheduled_action( Renewal_Scheduler::ACTION_REMINDER, array( 'subscription_id' => $r->get_id() ), Renewal_Scheduler::GROUP ) );
}
$set( 'subkit_renewal_reminder_days', 3 );

echo "\nWhere the settings sit\n";
global $wp_actions;
$page = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'subkit' === $candidate->get_id() ) {
		$page = $candidate;
	}
}
if ( ! $page ) {
	subkit_test_abort( 'the Subscriptions settings tab is not registered' );
}
$was_pro                         = $wp_actions['subkit_pro_loaded'] ?? null;
$wp_actions['subkit_pro_loaded'] = 1;
$groups                          = array();
$current                         = '';
foreach ( $page->get_settings_for_section( 'renewal' ) as $field ) {
	if ( 'title' === ( $field['type'] ?? '' ) ) {
		$current = (string) $field['title'];
	} elseif ( isset( $field['id'] ) && 'sectionend' !== ( $field['type'] ?? '' ) ) {
		$groups[ $current ][] = $field['id'];
	}
}
if ( null === $was_pro ) {
	unset( $wp_actions['subkit_pro_loaded'] );
} else {
	$wp_actions['subkit_pro_loaded'] = $was_pro;
}
$check( 'the grace period and the reminder share the Grace period card', array( 'subkit_grace_period_days', 'subkit_renewal_reminder_days' ) === ( $groups['Grace period'] ?? null ), $groups );
$check( 'missed renewals and free first payments share the Renewal card', array( 'subkit_catch_up_policy', \SubKit\Checkout\Trial_Payment::OPTION ) === ( $groups['Renewal'] ?? null ), $groups );

echo "\nWithout a grace period\n";
$user = wp_insert_user(
	array(
		'user_login' => 'sk_rules_' . strtolower( wp_generate_password( 8, false, false ) ),
		'user_pass'  => wp_generate_password( 32 ),
		'user_email' => 'sk-rules-' . strtolower( wp_generate_password( 8, false, false ) ) . '@example.test',
		'role'       => 'customer',
	)
);
if ( is_wp_error( $user ) ) {
	subkit_test_abort( 'could not create a customer: ' . $user->get_error_message() );
}
foreach ( array( 'sk_rules_member', 'sk_rules_lapsed' ) as $role ) {
	add_role( $role, $role, array( 'read' => true ) );
}
$set( 'subkit_active_role', 'sk_rules_member' );
$set( 'subkit_inactive_role', 'sk_rules_lapsed' );
$roles     = static function () use ( $user ): array {
	clean_user_cache( $user );
	return ( new WP_User( $user ) )->roles;
};
$downloads = function () use ( $user, $product ): int {
	wp_set_current_user( $user );
	$left = apply_filters( 'woocommerce_customer_get_downloadable_products', array( array( 'product_id' => $product->get_id() ) ) );
	wp_set_current_user( 1 );
	return count( (array) $left );
};

$plain = $make( Subscription_Status::Active, $user );
$check( 'fixture: an active subscription gives the role and the files', in_array( 'sk_rules_member', $roles(), true ) && 1 === $downloads(), $roles() );
$plain->transition_to( Subscription_Status::OnHold, 'Declined.' );
$plain->save();
$check( 'on hold with no grace period, it is not in grace', ! $plain->in_grace() && null === $plain->grace_ends_at() );
$check( 'and the role goes at once, as before', in_array( 'sk_rules_lapsed', $roles(), true ) && ! in_array( 'sk_rules_member', $roles(), true ), $roles() );
$check( 'so do the files', 0 === $downloads() );
$detail = Status_Presenter::for( $plain )['detail'];
$check( 'My Account says only that the payment failed', "We couldn't take your last payment." === $detail, $detail );

echo "\nWith a grace period\n";
$ends  = time() + 5 * DAY_IN_SECONDS;
$grace = static fn( $given, $subscription ) => $subscription instanceof Subscription && 'yes' === $subscription->get_meta( '_sk_rules_grace' ) ? $ends : $given;
add_filter( 'subkit_grace_ends_at', $grace, 20, 2 );

$plain->transition_to( Subscription_Status::Active );
$plain->save();
$graced = wc_get_order( $plain->get_id() );
$graced->update_meta_data( '_sk_rules_grace', 'yes' );
$graced->transition_to( Subscription_Status::OnHold, 'Declined.' );
$graced->save();
$check( 'on hold inside its grace period', $graced->in_grace() && $ends === $graced->grace_ends_at() );
$check( 'keeps the role', in_array( 'sk_rules_member', $roles(), true ) && ! in_array( 'sk_rules_lapsed', $roles(), true ), $roles() );
$check( 'and the files', 1 === $downloads() );

$date   = wp_date( (string) get_option( 'date_format' ), $ends );
$detail = Status_Presenter::for( $graced )['detail'];
$check( 'My Account says until when access lasts', "We couldn't take your last payment. You keep access until $date." === $detail, $detail );

$email = WC()->mailer()->get_emails()['SubKit_Payment_Failed'] ?? null;
if ( $email && $email->is_enabled() ) {
	$sent = array();
	$email->trigger( $graced, $graced );
	$check( 'the payment failed email says the same date', isset( $sent[0] ) && str_contains( $sent[0], "You keep access until $date. Pay before then to keep your subscription going." ), $sent[0] ?? '' );
	$sent = array();
	$email->trigger( wc_get_order( $made[0] ), wc_get_order( $made[0] ) );
	$check( 'and without a grace period, that it is on hold until paid', isset( $sent[0] ) && str_contains( $sent[0], 'Your subscription is on hold until it is paid.' ) && ! str_contains( $sent[0], 'You keep access' ), $sent[0] ?? '' );
} else {
	echo "SKIP the payment failed email is switched off on this site\n";
}

$active = $make( Subscription_Status::Active );
$active->update_meta_data( '_sk_rules_grace', 'yes' );
$active->save();
$check( 'a grace period only counts while on hold', ! $active->in_grace() );

$expired = static fn( $given, $subscription ) => $subscription instanceof Subscription && 'yes' === $subscription->get_meta( '_sk_rules_grace' ) ? time() - 1 : $given;
add_filter( 'subkit_grace_ends_at', $expired, 30, 2 );
$check( 'and only until it ends', ! wc_get_order( $graced->get_id() )->in_grace() );
remove_filter( 'subkit_grace_ends_at', $expired, 30 );

echo "\nWhen the grace period ends\n";
$demoted = 0;
$count   = function () use ( &$demoted ): void { ++$demoted; };
add_action( 'set_user_role', $count );
do_action( 'subkit_subscription_grace_ended', wc_get_order( $graced->get_id() ) );
$check( 'the role goes', in_array( 'sk_rules_lapsed', $roles(), true ) && ! in_array( 'sk_rules_member', $roles(), true ), $roles() );
do_action( 'subkit_subscription_grace_ended', wc_get_order( $graced->get_id() ) );
$check( 'once, however often it is announced', 1 === $demoted, $demoted );
remove_action( 'set_user_role', $count );

$other = $make( Subscription_Status::Active, $user );
$other->update_meta_data( '_sk_rules_grace', 'yes' );
$other->transition_to( Subscription_Status::OnHold, 'Declined.' );
$other->save();
$ending = $make( Subscription_Status::Active, $user );
$ending->transition_to( Subscription_Status::Cancelled, 'Cancelled.' );
$ending->save();
$check( 'another subscription ending keeps the role another in its grace period still gives', in_array( 'sk_rules_member', $roles(), true ), $roles() );

remove_filter( 'subkit_grace_ends_at', $grace, 20 );

// ---- Clean up --------------------------------------------------------------------------
global $wpdb;
foreach ( $made as $id ) {
	$scheduler->unschedule( $id );
	as_unschedule_all_actions( Renewal_Scheduler::ACTION_REMINDER, array( 'subscription_id' => $id ), Renewal_Scheduler::GROUP );
	$wpdb->delete( $wpdb->prefix . 'subkit_activity', array( 'subscription_id' => $id ) );
	wc_get_order( $id )?->delete( true );
}
wp_delete_user( $user );
foreach ( array( 'sk_rules_member', 'sk_rules_lapsed' ) as $role ) {
	remove_role( $role );
}
foreach ( $saved as $name => $old ) {
	$absent === $old ? delete_option( $name ) : update_option( $name, $old );
}
subkit_test_done( $fail );
