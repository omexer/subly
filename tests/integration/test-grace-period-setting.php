<?php
/**
 * The grace period is only offered where something reads it: EasySubscription Pro's payment retries.
 *
 * @package EasySubscription
 */

require __DIR__ . '/bootstrap.php';

global $wp_actions;

$page = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'easysubscription' === $candidate->get_id() ) {
		$page = $candidate;
	}
}
if ( ! $page ) {
	easysubscription_test_abort( 'the Subscriptions settings tab is not registered' );
}

$ids     = static fn(): array => array_column( (array) $page->get_settings_for_section( 'renewal' ), 'id' );
$was_pro = $wp_actions['easysubscription_pro_loaded'] ?? null;

unset( $wp_actions['easysubscription_pro_loaded'] );
$check( 'without EasySubscription Pro the grace period is not offered', ! in_array( 'easysubscription_grace_period_days', $ids(), true ), $ids() );
$check( 'while the settings around it still are', in_array( 'easysubscription_catch_up_policy', $ids(), true ) );
$check( 'and its card, with nothing else in it, goes too', ! in_array( 'easysubscription_grace_title', $ids(), true ), $ids() );

$wp_actions['easysubscription_pro_loaded'] = 1;
$check( 'with EasySubscription Pro it is', in_array( 'easysubscription_grace_period_days', $ids(), true ) );

if ( null === $was_pro ) {
	unset( $wp_actions['easysubscription_pro_loaded'] );
} else {
	$wp_actions['easysubscription_pro_loaded'] = $was_pro;
}

easysubscription_test_done( $fail );
