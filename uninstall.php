<?php
/**
 * Removes EasySubscription's own data when the plugin is deleted, and only when the store asked for
 * that under WooCommerce → Settings → Subscriptions.
 *
 * Subscriptions and their orders are deliberately left alone either way. They are
 * WooCommerce orders and part of the store's financial record; deleting a plugin is not
 * consent to destroy them.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'easysubscription_delete_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'easysubscription_schedule', 'easysubscription_activity', 'easysubscription_charge_slot' ) as $easysubscription_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own tables is what uninstall is for.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $easysubscription_table ) );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no API deletes options by prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'easysubscription_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_easysubscription_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_timeout_easysubscription_' ) . '%' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

foreach ( array( 'easysubscription_dismissed_notices', '_easysubscription_fluentcrm_added_tags', '_easysubscription_fluentcrm_added_lists' ) as $easysubscription_user_meta ) {
	delete_metadata( 'user', 0, $easysubscription_user_meta, '', true );
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	foreach ( array( 'easysubscription_scheduled_renewal', 'easysubscription_renewal_reminder', 'easysubscription_reactivated_email', 'easysubscription_sweep_overdue', 'easysubscription_settle_paid_renewal', 'easysubscription_resolve_pending_renewal', 'easysubscription_daily_snapshot', 'easysubscription_paypal_process_webhook' ) as $easysubscription_action ) {
		// Hook alone: given a group as well, Action Scheduler only matches actions queued with no arguments.
		as_unschedule_all_actions( $easysubscription_action );
	}
}

wp_cache_flush();
