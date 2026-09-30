<?php
/**
 * The grace period is only offered where something reads it: EasySubscription Pro's payment retries, which add it.
 *
 * @package EasySubscription
 */

require __DIR__ . '/bootstrap.php';

$page = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'easysubscription' === $candidate->get_id() ) {
		$page = $candidate;
	}
}
if ( ! $page ) {
	easysubscription_test_abort( 'the Subscriptions settings tab is not registered' );
}

$own   = array_column( $page->get_settings_for_renewal_section(), 'id' );
$shown = array_column( (array) $page->get_settings_for_section( 'renewal' ), 'id' );

$check( 'EasySubscription itself never offers the grace period', ! array_intersect( array( 'easysubscription_grace_period_days', 'easysubscription_grace_title' ), $own ), $own );
$check( 'while the settings around it still are', in_array( 'easysubscription_catch_up_policy', $own, true ) );
$check(
	did_action( 'easysubscription_pro_loaded' ) ? 'with EasySubscription Pro it is on the page' : 'nor is it on the page without EasySubscription Pro',
	(bool) did_action( 'easysubscription_pro_loaded' ) === in_array( 'easysubscription_grace_period_days', $shown, true ),
	$shown
);

easysubscription_test_done( $fail );
