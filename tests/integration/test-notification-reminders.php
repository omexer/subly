<?php
/**
 * The trial ending and expiring soon reminders and the reactivated email: queued once, sent
 * once, nothing when switched off or when the subscription has moved on, and a renewal is
 * never mistaken for a reactivation.
 *
 * @package EasySubscription
 */

use EasySubscription\Billing\Renewal_Scheduler;
use EasySubscription\Domain\Subscription;
use EasySubscription\Domain\Subscription_Status;
use EasySubscription\Emails\Mailer;
use EasySubscription\Gateways\Test_Gateway;

require __DIR__ . '/bootstrap.php';

\EasySubscription\Plugin::instance()->get( 'gateways' )->add(
	new class() implements \EasySubscription\Gateways\Recurring_Gateway {
		public function id(): string { return Test_Gateway::ID; }
		public function title(): string { return 'Harness approver'; }
		public function model(): \EasySubscription\Gateways\Gateway_Model { return \EasySubscription\Gateways\Gateway_Model::Tokenized; }
		public function supports( string $f ): bool { return true; }
		public function create_mandate( Subscription $s, \WC_Order $o ): \EasySubscription\Gateways\Charge_Result { return \EasySubscription\Gateways\Charge_Result::success( 'mandate-' . $s->get_id() ); }
		public function charge_renewal( Subscription $s, \WC_Order $r, string $k ): \EasySubscription\Gateways\Charge_Result { return \EasySubscription\Gateways\Charge_Result::success( 'charge-' . $r->get_id() ); }
		public function reconcile( Subscription $s, string $k ): ?\EasySubscription\Gateways\Charge_Result { return null; }
		public function cancel_mandate( Subscription $s ): bool { return true; }
		public function update_payment_method( Subscription $s, string $t ): bool { return true; }
	}
);

$scheduler = \EasySubscription\Plugin::instance()->get( 'scheduler' );
$mailer    = WC()->mailer();
$emails    = $mailer->get_emails();

$absent  = new stdClass();
$options = array( Renewal_Scheduler::OPTION_REMINDER_HOURS, Renewal_Scheduler::OPTION_EXPIRY_HOURS );
foreach ( array( 'EasySubscription_Trial_Ending', 'EasySubscription_Expiring_Soon', 'EasySubscription_Subscription_Reactivated', 'EasySubscription_Renewal_Reminder' ) as $key ) {
	$options[] = $emails[ $key ]->get_option_key();
}
$was = array();
foreach ( $options as $name ) {
	$was[ $name ] = get_option( $name, $absent );
}
update_option( Renewal_Scheduler::OPTION_REMINDER_HOURS, 24 );
update_option( Renewal_Scheduler::OPTION_EXPIRY_HOURS, 24 );

// As a new request would see it: the mailer reads each email's switch once, when it is built.
$switch = static function ( string $key, string $on ) use ( $emails ): void {
	$option = $emails[ $key ]->get_option_key();
	update_option( $option, array_merge( (array) get_option( $option, array() ), array( 'enabled' => $on ) ) );
	WC()->mailer()->init();
};
foreach ( array( 'EasySubscription_Trial_Ending', 'EasySubscription_Expiring_Soon', 'EasySubscription_Subscription_Reactivated', 'EasySubscription_Renewal_Reminder' ) as $key ) {
	$switch( $key, 'yes' );
}

$mail = array();
add_filter(
	'wp_mail',
	static function ( $args ) use ( &$mail ) {
		if ( str_ends_with( (string) $args['to'], '@notify.example.test' ) ) {
			$mail[] = array( 'to' => $args['to'], 'subject' => $args['subject'], 'body' => wp_strip_all_tags( $args['message'] ) );
		}
		return $args;
	},
	999
);
$to = static function ( Subscription $s ) use ( &$mail ): array {
	return array_values( array_filter( $mail, static fn( $m ) => $m['to'] === $s->get_billing_email() ) );
};

$product = easysubscription_test_product();
$made    = array();
$make    = static function ( Subscription_Status $status, array $dates ) use ( $product, &$made ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( 1 );
	$s->set_currency( get_woocommerce_currency() );
	$s->set_billing_period( 'month' );
	$s->set_billing_interval( 1 );
	$s->set_payment_method( Test_Gateway::ID );
	$s->set_address( array( 'first_name' => 'Notify', 'email' => 'sub' . wp_generate_password( 6, false, false ) . '@notify.example.test', 'country' => 'US' ), 'billing' );
	$s->update_meta_data( '_easysubscription_site_url', get_option( 'siteurl' ) );
	$i = new WC_Order_Item_Product();
	$i->set_props( array( 'name' => 'Harness', 'product_id' => $product->get_id(), 'quantity' => 1, 'subtotal' => '20', 'total' => '20' ) );
	$s->add_item( $i );
	foreach ( $dates as $prop => $offset ) {
		$s->{"set_$prop"}( gmdate( 'Y-m-d H:i:s', time() + $offset ) );
	}
	$s->transition_to( Subscription_Status::Pending );
	$s->calculate_totals( false );
	$s->save();
	if ( in_array( $status, array( Subscription_Status::OnHold, Subscription_Status::PendingCancel ), true ) ) {
		$s->transition_to( Subscription_Status::Active );
		$s->save();
	}
	$s->transition_to( $status );
	$s->save();
	$made[] = $s->get_id();
	return wc_get_order( $s->get_id() );
};

$pending = static function ( string $hook, ?int $subscription_id = null ): array {
	$found = array();
	foreach ( as_get_scheduled_actions( array( 'hook' => $hook, 'group' => Renewal_Scheduler::GROUP, 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => -1 ) ) as $id => $action ) {
		if ( null === $subscription_id || (int) ( $action->get_args()['subscription_id'] ?? 0 ) === $subscription_id ) {
			$found[ $id ] = $action;
		}
	}
	return $found;
};
$run = static function ( array $actions ): void {
	foreach ( array_keys( $actions ) as $id ) {
		ActionScheduler_QueueRunner::instance()->process_action( $id, 'easysubscription-test' );
	}
};
$near = static fn( $at, int $want ): bool => is_int( $at ) && abs( $at - $want ) < 120;

$day  = DAY_IN_SECONDS;
$hour = HOUR_IN_SECONDS;

echo "\n1. Trial ending\n";
$long = $make( Subscription_Status::Trialling, array( 'trial_end' => 10 * $day, 'next_payment' => 10 * $day ) );
$scheduler->schedule_next( $long );
$scheduler->schedule_next( $long );
$queued = $pending( Renewal_Scheduler::ACTION_TRIAL_REMINDER, $long->get_id() );
$check( 'queued once, 72 hours before the trial ends', 1 === count( $queued ) && $near( reset( $queued )->get_schedule()->get_date()->getTimestamp(), time() + 10 * $day - 72 * $hour ), count( $queued ) );

$mail = array();
$run( $queued );
$again = $pending( Renewal_Scheduler::ACTION_TRIAL_REMINDER, $long->get_id() );
$check( 'run early, it sends nothing and waits for its time', array() === $mail && 1 === count( $again ) && $near( reset( $again )->get_schedule()->get_date()->getTimestamp(), time() + 10 * $day - 72 * $hour ), $mail );

$mail = array();
$due  = did_action( 'easysubscription_renewal_due_soon' );
$scheduler->remind( $long->get_id() );
$check( 'the renewal reminder leaves a trial to the trial reminder', array() === $to( $long ), $to( $long ) );
$check( 'while the renewal still announces itself, for a pay-by-link gateway\'s own email', $due + 1 === did_action( 'easysubscription_renewal_due_soon' ) );

$soon = $make( Subscription_Status::Trialling, array( 'trial_end' => 2 * $day, 'next_payment' => 2 * $day ) );
$mail = array();
$scheduler->remind_trial( $soon->get_id() );
$got = $to( $soon );
$check( 'sent to the customer when it is due', 1 === count( $got ) && 'Your free trial ends soon' === $got[0]['subject'], $got );
$check( 'with the date and the first payment', isset( $got[0] ) && str_contains( $got[0]['body'], date_i18n( get_option( 'date_format' ), time() + 2 * $day ) ) && str_contains( $got[0]['body'], '20.00' ), $got[0]['body'] ?? '' );
$scheduler->remind_trial( $soon->get_id() );
$check( 'a second run sends nothing', 1 === count( $to( $soon ) ), count( $to( $soon ) ) );

$mail = array();
$scheduler->remind( $soon->get_id() );
$check( 'a trial already reminded is not reminded again of the same payment', array() === $to( $soon ), $to( $soon ) );

$short = $make( Subscription_Status::Trialling, array( 'trial_end' => 2 * $day, 'next_payment' => 2 * $day ) );
$scheduler->schedule_next( $short );
$mail = array();
$scheduler->remind( $short->get_id() );
$check( 'a trial too short for the trial reminder still gets the renewal reminder', array() === $pending( Renewal_Scheduler::ACTION_TRIAL_REMINDER, $short->get_id() ) && 1 === count( $to( $short ) ), $to( $short ) );

$in_flight = $make( Subscription_Status::Trialling, array( 'trial_end' => 10 * $day, 'next_payment' => 10 * $day ) );
$mail      = array();
$scheduler->remind( $in_flight->get_id() );
$check( 'a trial with no trial reminder coming, such as one begun before this release, still gets the renewal reminder', 1 === count( $to( $in_flight ) ), $to( $in_flight ) );

$switch( 'EasySubscription_Trial_Ending', 'no' );
$off  = $make( Subscription_Status::Trialling, array( 'trial_end' => 2 * $day, 'next_payment' => 2 * $day ) );
$mail = array();
$scheduler->remind_trial( $off->get_id() );
$check( 'switched off, nothing is sent', array() === $to( $off ), $to( $off ) );
$scheduler->remind( $long->get_id() );
$check( 'and the renewal reminder tells the trial instead', 1 === count( $to( $long ) ) && str_contains( $to( $long )[0]['body'], 'free trial ends' ), $to( $long ) );
$switch( 'EasySubscription_Trial_Ending', 'yes' );

$converted = $make( Subscription_Status::Trialling, array( 'trial_end' => 2 * $day, 'next_payment' => 2 * $day ) );
$converted->transition_to( Subscription_Status::Active );
$converted->save();
$cancelled = $make( Subscription_Status::Trialling, array( 'trial_end' => 2 * $day, 'next_payment' => 2 * $day ) );
$cancelled->transition_to( Subscription_Status::Cancelled );
$cancelled->save();
$mail = array();
$scheduler->remind_trial( $converted->get_id() );
$scheduler->remind_trial( $cancelled->get_id() );
$check( 'no longer trialling, nothing is sent', array() === $mail, $mail );

echo "\n2. Expiring soon\n";
$ending = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $day, 'end_date' => 5 * $day ) );
$scheduler->schedule_next( $ending );
$scheduler->schedule_next( $ending );
$queued = $pending( Renewal_Scheduler::ACTION_EXPIRY_REMINDER, $ending->get_id() );
$check( 'queued once, the set hours before its last paid day', 1 === count( $queued ) && $near( reset( $queued )->get_schedule()->get_date()->getTimestamp(), time() + 10 * $day - 24 * $hour ), count( $queued ) );
$check( 'with no renewal reminder for a charge that will not come', array() === $pending( Renewal_Scheduler::ACTION_REMINDER, $ending->get_id() ) );
$mail = array();
$scheduler->remind( $ending->get_id() );
$check( 'nor does a queued one send', array() === $to( $ending ) );

$renewing = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $day, 'end_date' => 60 * $day ) );
$scheduler->schedule_next( $renewing );
$check( 'a subscription with renewals left is not told it ends', array() === $pending( Renewal_Scheduler::ACTION_EXPIRY_REMINDER, $renewing->get_id() ) && 1 === count( $pending( Renewal_Scheduler::ACTION_REMINDER, $renewing->get_id() ) ) );

update_option( Renewal_Scheduler::OPTION_EXPIRY_HOURS, 48 );
$scheduler->schedule_next( $ending );
$queued = $pending( Renewal_Scheduler::ACTION_EXPIRY_REMINDER, $ending->get_id() );
$check( 'the hours set are the hours used', 1 === count( $queued ) && $near( reset( $queued )->get_schedule()->get_date()->getTimestamp(), time() + 10 * $day - 48 * $hour ) );
update_option( Renewal_Scheduler::OPTION_EXPIRY_HOURS, 24 );

$last = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $hour, 'end_date' => 5 * $hour ) );
$mail = array();
$scheduler->remind_expiry( $last->get_id() );
$got = $to( $last );
$check( 'sent when it is due', 1 === count( $got ) && 'Your subscription ends soon' === $got[0]['subject'], $got );
$scheduler->remind_expiry( $last->get_id() );
$check( 'a second run sends nothing', 1 === count( $to( $last ) ) );

$not_last = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $hour, 'end_date' => 30 * $day ) );
$leaving  = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $hour, 'end_date' => 5 * $hour ) );
$leaving->transition_to( Subscription_Status::PendingCancel );
$leaving->save();
$mail = array();
$scheduler->remind_expiry( $not_last->get_id() );
$scheduler->remind_expiry( $leaving->get_id() );
$check( 'an end date moved later, or a cancellation, sends nothing', array() === $mail, $mail );

$switch( 'EasySubscription_Expiring_Soon', 'no' );
$quiet = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $hour, 'end_date' => 5 * $hour ) );
$mail  = array();
$scheduler->remind_expiry( $quiet->get_id() );
$check( 'switched off, nothing is sent', array() === $mail, $mail );
$switch( 'EasySubscription_Expiring_Soon', 'yes' );

echo "\n3. Reactivated\n";
$reactivations = static fn( Subscription $s ): array => $pending( Mailer::ACTION_REACTIVATED, $s->get_id() );

$undone = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $day ) );
$undone->transition_to( Subscription_Status::PendingCancel );
$undone->save();
$request = new WP_REST_Request( 'POST', '/easysubscription/v1/subscriptions/' . $undone->get_id() . '/actions' );
$request->set_param( 'action', 'reactivate' );
$check( 'fixture: the store reactivates a cancelling subscription', 200 === rest_do_request( $request )->get_status() );
$queued = $reactivations( $undone );
$check( 'it is queued once', 1 === count( $queued ), count( $queued ) );

$mail = array();
$run( $queued );
$got = $to( $undone );
$check( 'the customer is told once', 1 === count( $got ) && 'Your subscription is active again' === $got[0]['subject'], $got );
$again = reset( $queued );
$mailer_service = \EasySubscription\Plugin::instance()->get( 'mailer' );
$mailer_service->send_reactivated( $again->get_args()['subscription_id'], $again->get_args()['at'] );
$check( 'running it again sends nothing', 1 === count( $to( $undone ) ) );

// Each reactivation is known by the second it happened in.
sleep( 1 );
$undone->transition_to( Subscription_Status::PendingCancel );
$undone->save();
$undone->transition_to( Subscription_Status::Active );
$undone->save();
$mail = array();
$run( $reactivations( $undone ) );
$check( 'a later reactivation is its own email', 1 === count( $to( $undone ) ), $to( $undone ) );

$switched = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $day ) );
$switched->transition_to( Subscription_Status::PendingCancel );
$switched->save();
$switched->transition_to( Subscription_Status::Active );
$switched->save();
$switched->transition_to( Subscription_Status::Switched );
$switched->save();
$mail   = array();
$queued = $reactivations( $switched );
$run( $queued );
$check( 'withdrawn and then switched away in the same request: nothing is sent', 1 === count( $queued ) && array() === $mail, array( count( $queued ), $mail ) );

$resumed = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $day ) );
do_action( 'easysubscription_subscription_resumed', $resumed );
$mail = array();
$run( $reactivations( $resumed ) );
$check( 'a paused subscription resuming is told', 1 === count( $to( $resumed ) ), $to( $resumed ) );

$switch( 'EasySubscription_Subscription_Reactivated', 'no' );
$muted = $make( Subscription_Status::Active, array( 'next_payment' => 10 * $day ) );
$muted->transition_to( Subscription_Status::PendingCancel );
$muted->save();
$muted->transition_to( Subscription_Status::Active );
$muted->save();
$mail   = array();
$queued = $reactivations( $muted );
$run( $queued );
$check( 'switched off, nothing is sent', 1 === count( $queued ) && array() === $mail, array( count( $queued ), $mail ) );
$switch( 'EasySubscription_Subscription_Reactivated', 'yes' );

echo "\n4. Renewals are not reactivations\n";
$trial = $make( Subscription_Status::Trialling, array( 'trial_end' => -60, 'next_payment' => -60 ) );
\EasySubscription\Plugin::instance()->get( 'processor' )->process( $trial->get_id() );
$check( 'fixture: the trial converted by renewing', Subscription_Status::Active === wc_get_order( $trial->get_id() )->get_status_enum(), wc_get_order( $trial->get_id() )->get_status() );
$active = $make( Subscription_Status::Active, array( 'next_payment' => -60 ) );
\EasySubscription\Plugin::instance()->get( 'processor' )->process( $active->get_id() );
$check( 'fixture: an active subscription renewed', strtotime( (string) wc_get_order( $active->get_id() )->get_next_payment() . ' UTC' ) > time() );
$held = $make( Subscription_Status::OnHold, array( 'next_payment' => -60 ) );
$held->transition_to( Subscription_Status::Active, 'Retrying the failed payment.' );
$held->save();
$check( 'no reactivated email for a trial converting, a renewal, or a retry after a failed payment', array() === $reactivations( $trial ) && array() === $reactivations( $active ) && array() === $reactivations( $held ) );

echo "\n5. Leaving\n";
foreach ( $made as $id ) {
	$scheduler->unschedule( $id );
}
$left = array();
foreach ( array( Renewal_Scheduler::ACTION_TRIAL_REMINDER, Renewal_Scheduler::ACTION_EXPIRY_REMINDER ) as $hook ) {
	foreach ( $made as $id ) {
		$left = array_merge( $left, array_keys( $pending( $hook, $id ) ) );
	}
}
$check( 'a subscription that stops billing takes its reminders with it', array() === $left, $left );

foreach ( $made as $id ) {
	foreach ( $pending( Mailer::ACTION_REACTIVATED, $id ) as $action_id => $action ) {
		as_unschedule_action( Mailer::ACTION_REACTIVATED, $action->get_args(), Renewal_Scheduler::GROUP );
	}
	$order = wc_get_order( $id );
	if ( $order ) {
		$order->delete( true );
	}
}
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}easysubscription_charge_slot WHERE subscription_id IN (" . implode( ',', array_map( 'intval', $made ) ) . ')' );
foreach ( $was as $name => $value ) {
	if ( $absent === $value ) {
		delete_option( $name );
	} else {
		update_option( $name, $value );
	}
}

easysubscription_test_done( $fail );
