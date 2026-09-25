<?php
/**
 * Removes SubKit's own data when the plugin is deleted, and only when the store asked for
 * that under WooCommerce → Settings → Subscriptions.
 *
 * Subscriptions and their orders are deliberately left alone either way. They are
 * WooCommerce orders and part of the store's financial record; deleting a plugin is not
 * consent to destroy them.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'subkit_delete_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'subkit_schedule', 'subkit_activity', 'subkit_charge_slot' ) as $subkit_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables is what uninstall is for.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $subkit_table ) );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no API deletes options by prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'subkit_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_subkit_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_timeout_subkit_' ) . '%' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

delete_metadata( 'user', 0, 'subkit_dismissed_notices', '', true );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	foreach ( array( 'subkit_scheduled_renewal', 'subkit_renewal_reminder', 'subkit_sweep_overdue', 'subkit_settle_paid_renewal', 'subkit_resolve_pending_renewal', 'subkit_daily_snapshot', 'subkit_paypal_process_webhook' ) as $subkit_action ) {
		// Hook alone: given a group as well, Action Scheduler only matches actions queued with no arguments.
		as_unschedule_all_actions( $subkit_action );
	}
}

wp_cache_flush();
