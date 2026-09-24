<?php
/**
 * Deleting the plugin removes its own data only when asked, and never touches subscriptions.
 *
 * @package SubKit
 */

require __DIR__ . '/bootstrap.php';

global $wpdb;

$tables = array( 'subkit_schedule', 'subkit_activity', 'subkit_charge_slot' );
$exists = function ( string $t ) use ( $wpdb ): bool {
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $t ) );
};

// --- back up -------------------------------------------------------------------------
$backup = array();
foreach ( $tables as $t ) {
	$backup[ $t ] = $exists( $t ) ? $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$t}", ARRAY_A ) : null;
}
$options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'subkit\_%'", ARRAY_A );
$planted = new \SubKit\Domain\Subscription();
$planted->set_customer_id( 1 );
$planted->transition_to( \SubKit\Domain\Subscription_Status::Pending );
$planted->save();

printf( "backed up: %s rows across %d tables, %d options\n\n", implode( '/', array_map( fn( $r ) => null === $r ? 'x' : count( $r ), $backup ) ), count( $tables ), count( $options ) );

$delivery_rows = $exists( 'subkit_delivery' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}subkit_delivery" ) : -1;

$run = function () { define( 'WP_UNINSTALL_PLUGIN', 'subkit-subscriptions/subkit-subscriptions.php' ); include SUBKIT_PATH . 'uninstall.php'; };

// --- 1. the guard --------------------------------------------------------------------
update_option( 'subkit_delete_data_on_uninstall', 'no' );
$run();
$check( 'leaves everything alone when the store did not ask', $exists( 'subkit_schedule' ) && $exists( 'subkit_activity' ) && $exists( 'subkit_charge_slot' ) );
$check( 'and keeps the settings', (bool) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'subkit\_%'" ) );

// --- 2. the real thing ---------------------------------------------------------------
update_option( 'subkit_delete_data_on_uninstall', 'yes' );
// WP_UNINSTALL_PLUGIN is already defined, so run the file's body a second time directly.
include SUBKIT_PATH . 'uninstall.php';

foreach ( $tables as $t ) {
	$check( "drops {$t}", ! $exists( $t ) );
}
$check( 'removes every setting', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'subkit\_%'" ) );
$check( "leaves Pro's delivery table alone", $delivery_rows === ( $exists( 'subkit_delivery' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}subkit_delivery" ) : -1 ) );
$check( 'leaves subscriptions alone', wc_get_order( $planted->get_id() ) instanceof \SubKit\Domain\Subscription );

// --- 3. put it back ------------------------------------------------------------------
delete_option( 'subkit_db_version' );
( new \SubKit\Data\Migrator() )->install();

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
$check( 'restored the settings', count( $options ) === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'subkit\_%'" ) );

$planted->delete( true );
subkit_test_done( $fail );
