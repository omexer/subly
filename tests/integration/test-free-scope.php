<?php
/**
 * What EasySubscription alone offers, for WordPress.org: nothing that only Pro can switch on.
 * Without Pro there is no one-click checkout, button text, trial or expiry reminder, email
 * editor, API Settings, grace period, product Shipping section or upgrade button; with Pro
 * each is back under the ids it always had.
 *
 * @package EasySubscription
 */

use EasySubscription\Admin\Page_Shell;
use EasySubscription\Admin\Settings_Page;
use EasySubscription\Domain\Subscription;
use EasySubscription\Domain\Subscription_Status;
use EasySubscription\Emails\Notification_Settings;
use EasySubscription\Product\Product_Meta_Fields;

require __DIR__ . '/bootstrap.php';

$pro   = (bool) did_action( 'easysubscription_pro_loaded' );
$label = $pro ? 'with Pro' : 'without Pro';
$page  = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'easysubscription' === $candidate->get_id() ) {
		$page = $candidate;
	}
}
if ( ! $page ) {
	easysubscription_test_abort( 'the Subscriptions settings tab is not registered' );
}
$ids = static fn( string $section ): array => array_values( array_filter( array_column( (array) $page->get_settings_for_section( $section ), 'id' ) ) );

echo "\n1. Settings ({$label})\n";
$checkout = $ids( 'checkout' );
$check( "{$label}, one-click checkout is " . ( $pro ? 'offered' : 'not offered' ), $pro === in_array( 'easysubscription_one_click_checkout', $checkout, true ), $checkout );
$check( "{$label}, the subscribe button text is " . ( $pro ? 'offered' : 'not offered' ), $pro === in_array( 'easysubscription_subscribe_button_text', $checkout, true ) && $pro === in_array( 'easysubscription_labels_title', $checkout, true ), $checkout );
$check( 'mixed checkout and guest checkout are offered either way', in_array( \EasySubscription\Checkout\Cart_Validation::OPTION_MIXED, $checkout, true ) && in_array( 'easysubscription_guest_checkout', $checkout, true ) );

$notifications = $ids( 'notifications' );
$check( "{$label}, the trial ending reminder is " . ( $pro ? 'listed' : 'not listed' ), $pro === in_array( Notification_Settings::switch_id( 'easysubscription_trial_ending' ), $notifications, true ), $notifications );
$check( "{$label}, the expiring soon reminder and its hours are " . ( $pro ? 'listed' : 'not listed' ), $pro === in_array( Notification_Settings::switch_id( 'easysubscription_expiring_soon' ), $notifications, true ) && $pro === in_array( 'easysubscription_expiry_reminder_hours', $notifications, true ), $notifications );
$registered = array_map( static fn( $email ) => $email->id, WC()->mailer()->get_emails() );
$check( "{$label}, WooCommerce has " . ( $pro ? 'both reminder emails' : 'neither reminder email' ), $pro === in_array( 'easysubscription_trial_ending', $registered, true ) && $pro === in_array( 'easysubscription_expiring_soon', $registered, true ) );

$check( "{$label}, the grace period is " . ( $pro ? 'offered' : 'not offered' ), $pro === in_array( 'easysubscription_grace_period_days', $ids( 'renewal' ), true ) );

$sections = $page->get_sections();
$menu     = array_column( (array) ( rest_do_request( new WP_REST_Request( 'GET', '/easysubscription/v1/settings' ) )->get_data()['groups'] ?? array() ), 'label', 'id' );
$check( "{$label}, API Settings " . ( $pro ? 'is' : 'is not' ) . ' a section and a menu group', $pro === isset( $sections['api'] ) && $pro === isset( $menu['api'] ) && $pro === isset( Settings_Page::groups()['api'] ), $menu );
$check( "{$label}, API Settings " . ( $pro ? 'opens' : 'is not found' ), ( $pro ? 200 : 404 ) === rest_do_request( new WP_REST_Request( 'GET', '/easysubscription/v1/settings/api' ) )->get_status() );
$check( 'Email Notifications is the menu group either way', 'Email Notifications' === ( $menu['notifications'] ?? '' ), $menu );

echo "\n2. The email editor ({$label})\n";
$routes = rest_get_server()->get_routes( 'easysubscription/v1' );
$check( "{$label}, the email settings route " . ( $pro ? 'exists' : 'does not exist' ), $pro === isset( $routes['/easysubscription/v1/settings/emails/(?P<id>[\w-]+)'] ), array_keys( $routes ) );
$renewal = array();
foreach ( rest_do_request( new WP_REST_Request( 'GET', '/easysubscription/v1/settings/notifications' ) )->get_data()['cards'] ?? array() as $card ) {
	foreach ( $card['rows'] as $row ) {
		if ( Notification_Settings::switch_id( 'easysubscription_renewal_reminder' ) === ( $row['id'] ?? '' ) ) {
			$renewal = $row;
		}
	}
}
$check( 'every email row carries its preview either way', str_contains( (string) ( $renewal['preview_url'] ?? '' ), 'preview_woocommerce_mail=true' ), $renewal );

echo "\n3. Reminders ({$label})\n";
$scheduler = \EasySubscription\Plugin::instance()->get( 'scheduler' );
$pending   = static function ( string $hook, int $id ): int {
	return count( as_get_scheduled_actions( array( 'hook' => $hook, 'args' => array( 'subscription_id' => $id ), 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => -1 ), 'ids' ) );
};
$make = static function ( array $dates, Subscription_Status $status ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( 1 );
	$s->set_billing_period( 'month' );
	$s->set_billing_interval( 1 );
	foreach ( $dates as $prop => $offset ) {
		$s->{"set_$prop"}( gmdate( 'Y-m-d H:i:s', time() + $offset ) );
	}
	$s->transition_to( Subscription_Status::Pending );
	$s->save();
	$s->transition_to( $status );
	$s->save();
	return wc_get_order( $s->get_id() );
};
$trial  = $make( array( 'trial_end' => 10 * DAY_IN_SECONDS, 'next_payment' => 10 * DAY_IN_SECONDS ), Subscription_Status::Trialling );
$ending = $make( array( 'next_payment' => 10 * DAY_IN_SECONDS, 'end_date' => 5 * DAY_IN_SECONDS ), Subscription_Status::Active );
$scheduler->schedule_next( $trial );
$scheduler->schedule_next( $ending );
$check( "{$label}, a trial " . ( $pro ? 'gets a' : 'gets no' ) . ' trial ending reminder', ( $pro ? 1 : 0 ) === $pending( 'easysubscription_trial_reminder', $trial->get_id() ) );
$check( "{$label}, a last period " . ( $pro ? 'gets an' : 'gets no' ) . ' expiring soon reminder', ( $pro ? 1 : 0 ) === $pending( 'easysubscription_expiry_reminder', $ending->get_id() ) );
$check( 'the renewal is queued either way', 1 === $pending( \EasySubscription\Billing\Renewal_Scheduler::ACTION_RENEWAL, $trial->get_id() ) );
foreach ( array( $trial, $ending ) as $s ) {
	$scheduler->unschedule( $s->get_id() );
	$s->delete( true );
}

echo "\n4. The product panel ({$label})\n";
global $product_object;
if ( ! function_exists( 'woocommerce_wp_select' ) ) {
	require_once WC()->plugin_path() . '/includes/admin/wc-meta-box-functions.php';
}
$was_product    = $product_object ?? null;
$product_object = easysubscription_test_product();
ob_start();
\EasySubscription\Plugin::instance()->get( 'product_fields' )->render();
$panel          = (string) ob_get_clean();
$product_object = $was_product;
$check( "{$label}, the Shipping settings section is " . ( $pro ? 'drawn' : 'not drawn' ), $pro === str_contains( $panel, 'easysubscription-section--shipping' ), $pro ? '' : $panel );
$check( 'there is no Shipping required box either way; WooCommerce\'s Virtual box says it', ! str_contains( $panel, 'easysubscription_shipping_required' ) );
$check( 'Billing settings are drawn either way', str_contains( $panel, 'easysubscription-section--billing' ) );
$check( "{$label}, " . ( $pro ? 'Pro fills' : 'nothing fills' ) . ' the Shipping section free keeps for extensions', has_action( Product_Meta_Fields::ACTION_SHIPPING ) === $pro );

echo "\n5. The header ({$label})\n";
$check( 'no upgrade button, with or without Pro', '' === Page_Shell::header_links()['upgrade'] );

easysubscription_test_done( $fail );
