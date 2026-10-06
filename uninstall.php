<?php
/**
 * Removes Subly's own data when the plugin is deleted, and only when the store asked for
 * that under WooCommerce → Settings → Subscriptions.
 *
 * Subscriptions and their orders are deliberately left alone either way. They are
 * WooCommerce orders and part of the store's financial record; deleting a plugin is not
 * consent to destroy them.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'subly_delete_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'subly_schedule', 'subly_activity', 'subly_charge_slot' ) as $subly_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables is what uninstall is for.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $subly_table ) );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no API deletes options by prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'subly_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_subly_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_timeout_subly_' ) . '%' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

foreach ( array( 'subly_dismissed_notices', '_subly_fluentcrm_added_tags', '_subly_fluentcrm_added_lists' ) as $subly_user_meta ) {
	delete_metadata( 'user', 0, $subly_user_meta, '', true );
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	foreach ( array( 'subly_scheduled_renewal', 'subly_renewal_reminder', 'subly_reactivated_email', 'subly_sweep_overdue', 'subly_settle_paid_renewal', 'subly_resolve_pending_renewal', 'subly_daily_snapshot', 'subly_paypal_process_webhook' ) as $subly_action ) {
		// Hook alone: given a group as well, Action Scheduler only matches actions queued with no arguments.
		as_unschedule_all_actions( $subly_action );
	}
}

wp_cache_flush();
