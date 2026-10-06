<?php
/**
 * The grace period is only offered where something reads it: Subly Pro's payment retries, which add it.
 *
 * @package Subly
 */

require __DIR__ . '/bootstrap.php';

$page = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'subly' === $candidate->get_id() ) {
		$page = $candidate;
	}
}
if ( ! $page ) {
	subly_test_abort( 'the Subscriptions settings tab is not registered' );
}

$own   = array_column( $page->get_settings_for_renewal_section(), 'id' );
$shown = array_column( (array) $page->get_settings_for_section( 'renewal' ), 'id' );

$check( 'Subly itself never offers the grace period', ! array_intersect( array( 'subly_grace_period_days', 'subly_grace_title' ), $own ), $own );
$check( 'while the settings around it still are', in_array( 'subly_catch_up_policy', $own, true ) );
$check(
	did_action( 'subly_pro_loaded' ) ? 'with Subly Pro it is on the page' : 'nor is it on the page without Subly Pro',
	(bool) did_action( 'subly_pro_loaded' ) === in_array( 'subly_grace_period_days', $shown, true ),
	$shown
);

subly_test_done( $fail );
