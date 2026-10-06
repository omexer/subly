<?php
/**
 * Deleting the plugin removes its own data only when asked, and never touches subscriptions.
 *
 * @package Subly
 */

require __DIR__ . '/bootstrap.php';

global $wpdb;

$tables = array( 'subly_schedule', 'subly_activity', 'subly_charge_slot' );
$exists = function ( string $t ) use ( $wpdb ): bool {
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $t ) );
};

// --- back up -------------------------------------------------------------------------
$backup = array();
foreach ( $tables as $t ) {
	$backup[ $t ] = $exists( $t ) ? $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$t}", ARRAY_A ) : null;
}
$options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'subly\_%'", ARRAY_A );
$planted = new \Subly\Domain\Subscription();
$planted->set_customer_id( 1 );
$planted->transition_to( \Subly\Domain\Subscription_Status::Pending );
$planted->save();

printf( "backed up: %s rows across %d tables, %d options\n\n", implode( '/', array_map( fn( $r ) => null === $r ? 'x' : count( $r ), $backup ) ), count( $tables ), count( $options ) );

$delivery_rows = $exists( 'subly_delivery' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}subly_delivery" ) : -1;

$run = function () { define( 'WP_UNINSTALL_PLUGIN', 'subly/subly.php' ); include SUBLY_PATH . 'uninstall.php'; };

// Every action free queues, including the ones that settle a pending renewal; none may fire into a deleted plugin.
$queued_actions = array(
	\Subly\Billing\Renewal_Scheduler::ACTION_RENEWAL,
	\Subly\Billing\Renewal_Scheduler::ACTION_REMINDER,
	\Subly\Emails\Mailer::ACTION_REACTIVATED,
	\Subly\Billing\Renewal_Scheduler::ACTION_SWEEP,
	\Subly\Billing\Renewal_Processor::ACTION_SETTLE,
	\Subly\Billing\Renewal_Processor::ACTION_RESOLVE,
	\Subly\Data\Stats::ACTION_SNAPSHOT,
	\Subly\Gateways\PayPal\Webhook_Controller::ACTION_PROCESS,
);
foreach ( $queued_actions as $hook ) {
	as_schedule_single_action( time() + DAY_IN_SECONDS, $hook, array( 'order_id' => 999999001 ), \Subly\Billing\Renewal_Scheduler::GROUP );
}
$still_queued = static fn(): array => array_values( array_filter( $queued_actions, static fn( string $hook ): bool => (bool) as_next_scheduled_action( $hook, null, \Subly\Billing\Renewal_Scheduler::GROUP ) ) );

// --- 1. the guard --------------------------------------------------------------------
update_option( 'subly_delete_data_on_uninstall', 'no' );
$run();
$check( 'leaves everything alone when the store did not ask', $exists( 'subly_schedule' ) && $exists( 'subly_activity' ) && $exists( 'subly_charge_slot' ) );
$check( 'and keeps the settings', (bool) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'subly\_%'" ) );
$check( 'and the queued work', $queued_actions === $still_queued(), $still_queued() );

// --- 2. the real thing ---------------------------------------------------------------
update_option( 'subly_delete_data_on_uninstall', 'yes' );
// WP_UNINSTALL_PLUGIN is already defined, so run the file's body a second time directly.
include SUBLY_PATH . 'uninstall.php';

foreach ( $tables as $t ) {
	$check( "drops {$t}", ! $exists( $t ) );
}
$check( 'removes every setting', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'subly\_%'" ) );
$check( "leaves Pro's delivery table alone", $delivery_rows === ( $exists( 'subly_delivery' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}subly_delivery" ) : -1 ) );
$check( 'leaves subscriptions alone', wc_get_order( $planted->get_id() ) instanceof \Subly\Domain\Subscription );
$check( 'unschedules everything it queued, pending-renewal settlement included', array() === $still_queued(), $still_queued() );

// --- 3. put it back ------------------------------------------------------------------
delete_option( 'subly_db_version' );
( new \Subly\Data\Migrator() )->install();

foreach ( $backup as $t => $rows ) {
	if ( null === $rows ) { continue; }
	foreach ( $rows as $row ) { $wpdb->insert( $wpdb->prefix . $t, $row ); }
}
foreach ( $options as $o ) {
	$wpdb->insert( $wpdb->options, array( 'option_name' => $o['option_name'], 'option_value' => $o['option_value'], 'autoload' => $o['autoload'] ) );
}
wp_cache_flush();

$restored = array();
foreach ( $tables as $t ) { $restored[ $t ] = $exists( $t ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$t}" ) : -1; }
foreach ( $backup as $t => $rows ) {
	$check( "restored {$t}", $restored[ $t ] === ( null === $rows ? -1 : count( $rows ) ), array( 'was' => null === $rows ? 'absent' : count( $rows ), 'now' => $restored[ $t ] ) );
}
$check( 'restored the settings', count( $options ) === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'subly\_%'" ) );

$planted->delete( true );
foreach ( $queued_actions as $hook ) {
	as_unschedule_all_actions( $hook, array( 'order_id' => 999999001 ), \Subly\Billing\Renewal_Scheduler::GROUP );
}
subly_test_done( $fail );
