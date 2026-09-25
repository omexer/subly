<?php
/**
 * The merchant is told about subscriptions renewing with tax added twice, and about renewals waiting too long on their payment provider.
 *
 * @package SubKit
 */

use SubKit\Admin\Notice_Dismissals;
use SubKit\Admin\Settings;
use SubKit\Admin\Settings_Page;
use SubKit\Billing\Renewal_Tax_Repair;
use SubKit\Data\Charge_Slot_Repository;
use SubKit\Data\Migrator;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Gateways\Charge_Result;
use SubKit\Gateways\Gateway_Model;
use SubKit\Gateways\Test_Gateway;

require __DIR__ . '/bootstrap.php';

global $wpdb;

$free      = \SubKit\Plugin::instance();
$repair    = $free->get( 'tax_repair' );
$processor = $free->get( 'processor' );
$slots     = $free->get( 'charge_slots' );

if ( ! $repair instanceof Renewal_Tax_Repair || ! $slots instanceof Charge_Slot_Repository ) {
	subkit_test_abort( 'the tax repair or charge slot service is not registered' );
}

$since = strtotime( (string) get_option( Renewal_Tax_Repair::OPTION_SINCE, '' ) . ' UTC' );
if ( ! $since ) {
	subkit_test_abort( 'the cut-over time was never recorded' );
}

if ( WC_Tax::find_rates( array( 'country' => 'GB' ) ) ) {
	subkit_test_abort( 'this site already has tax rates for GB; the repaired totals would be wrong' );
}

if ( ! class_exists( 'WC_Settings_Page' ) ) {
	include_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
}

$options = array(
	'woocommerce_calc_taxes'         => 'yes',
	'woocommerce_prices_include_tax' => 'yes',
	'woocommerce_default_country'    => 'GB',
	'woocommerce_tax_based_on'       => 'billing',
);
$saved = array();
foreach ( $options as $name => $value ) {
	$saved[ $name ] = get_option( $name, null );
	update_option( $name, $value );
}
$rate = WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'GB', 'tax_rate' => '20.0000', 'tax_rate_name' => 'VAT', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => '' ) );
WC_Cache_Helper::invalidate_cache_group( 'taxes' );

$dismissed_meta = get_user_meta( 1, 'subkit_dismissed_notices', true );
delete_user_meta( 1, 'subkit_dismissed_notices' );
delete_transient( Renewal_Tax_Repair::CACHE );

$harness = new class() implements \SubKit\Gateways\Recurring_Gateway {
	public string $next = 'pending';
	public function id(): string { return Test_Gateway::ID; }
	public function title(): string { return 'Harness'; }
	public function model(): Gateway_Model { return Gateway_Model::Tokenized; }
	public function supports( string $f ): bool { return true; }
	public function create_mandate( Subscription $s, \WC_Order $o ): Charge_Result { return Charge_Result::success( 'mandate' ); }
	public function charge_renewal( Subscription $s, \WC_Order $r, string $k ): Charge_Result {
		return 'unknown' === $this->next ? Charge_Result::error( 'Timed out, as the harness.' ) : Charge_Result::pending( 'dd_' . $r->get_id(), 'Submitted to the bank.' );
	}
	public function reconcile( Subscription $s, string $k ): ?Charge_Result { return null; }
	public function cancel_mandate( Subscription $s ): bool { return true; }
	public function update_payment_method( Subscription $s, string $t ): bool { return true; }
};
$free->get( 'gateways' )->add( $harness );

$p = new \SubKit\Product\Simple_Subscription();
$p->set_name( 'SK merchant notices probe' );
$p->set_status( 'publish' );
$p->set_regular_price( '12.00' );
$p->update_meta_data( \SubKit\Product\Subscription_Product::META_PERIOD, 'month' );
$p->update_meta_data( \SubKit\Product\Subscription_Product::META_INTERVAL, 1 );
$p->save();

$made = array();

// Old-style (created before the cut-over) subscriptions are what the tax list finds.
$make = static function ( string $email, bool $old = false ) use ( $p, $since, &$made ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( 1 );
	$s->set_currency( 'USD' );
	$s->set_billing_period( 'month' );
	$s->set_billing_interval( 1 );
	$s->set_payment_method( Test_Gateway::ID );
	$s->set_address( array( 'first_name' => 'Notice', 'email' => $email, 'country' => 'GB' ), 'billing' );
	$item = new WC_Order_Item_Product();
	$item->set_props( array( 'name' => 'Notice probe', 'product_id' => $p->get_id(), 'quantity' => 1, 'subtotal' => '12.00', 'total' => '12.00' ) );
	$s->add_item( $item );
	$s->set_next_payment( gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
	$s->update_meta_data( '_subkit_site_url', get_option( 'siteurl' ) );
	$s->transition_to( Subscription_Status::Pending );
	$s->calculate_totals( false );
	$s->save();
	$s->transition_to( Subscription_Status::Active );
	if ( $old ) {
		$s->set_date_created( $since - DAY_IN_SECONDS );
	}
	$s->save();
	$made[] = $s->get_id();
	return wc_get_order( $s->get_id() );
};

$notice = static function () use ( $repair ): string {
	ob_start();
	$repair->notice();
	return (string) ob_get_clean();
};

// A handler that got past its checks would redirect and exit, which would end this test as a pass.
$died       = static fn() => static function () {
	throw new RuntimeException( 'died' );
};
$redirected = static function ( $location ) {
	throw new RuntimeException( 'redirect:' . $location );
};
add_filter( 'wp_die_handler', $died );
add_filter( 'wp_redirect', $redirected, 1 );

$call = static function ( callable $handler, array $get, int $user_id = 1 ): string {
	wp_set_current_user( $user_id );
	$_GET     = $get;
	$_REQUEST = $get;
	try {
		$handler();
		return 'ran';
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	} finally {
		$_GET     = array();
		$_REQUEST = array();
		wp_set_current_user( 1 );
	}
};

$dismissals = new Notice_Dismissals();
$dismiss    = static function ( string $notice, int $count, int $user_id = 1, ?string $nonce = null ) use ( $call, $dismissals ): string {
	wp_set_current_user( $user_id );
	$nonce = $nonce ?? wp_create_nonce( Notice_Dismissals::ACTION . '_' . $notice );
	return $call(
		array( $dismissals, 'handle' ),
		array(
			'notice'   => $notice,
			'count'    => (string) $count,
			'_wpnonce' => $nonce,
		),
		$user_id
	);
};

$customer = wp_insert_user( array( 'user_login' => 'sk_notices_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'customer' ) );

echo "\n1. Tax added twice: the notice\n";
$check( 'with nothing listed there is no notice', '' === $notice() );

$first = $make( 'notices-1@example.test', true );
delete_transient( Renewal_Tax_Repair::CACHE );
$html = $notice();
$check( 'setup: the old subscription is listed', array( $first->get_id() ) === $repair->affected_ids(), $repair->affected_ids() );
$check( 'an admin is told how many, and that customers may be owed refunds', str_contains( $html, '1 subscription renews with tax added twice' ) && str_contains( $html, 'may be owed a refund' ), $html );
$check( 'with a link to the list', str_contains( $html, esc_url( Renewal_Tax_Repair::list_url() . '#' . Renewal_Tax_Repair::NOTICE ) ), $html );

wp_set_current_user( $customer );
$check( 'a user who cannot manage WooCommerce sees nothing', '' === $notice() );
wp_set_current_user( 1 );

echo "\n2. Dismissing it\n";
$_SERVER['HTTP_REFERER'] = admin_url( 'index.php' );
$check( 'a customer cannot dismiss it', 'died' === $dismiss( Renewal_Tax_Repair::NOTICE, 1, $customer ) );
$check( 'nor an admin without the nonce', 'died' === $dismiss( Renewal_Tax_Repair::NOTICE, 1, 1, 'not-a-nonce' ) );
$check( 'so it still shows', '' !== $notice() );
$back = $dismiss( Renewal_Tax_Repair::NOTICE, 1 );
$check( 'dismissing returns to the screen it was dismissed on', 'redirect:' . admin_url( 'index.php' ) === $back, $back );
$check( 'and it is gone', '' === $notice() );
delete_transient( Renewal_Tax_Repair::CACHE );
$check( 'and stays gone on the next load, while the count is unchanged', '' === $notice() );

$second = $make( 'notices-2@example.test', true );
delete_transient( Renewal_Tax_Repair::CACHE );
$html = $notice();
$check( 'it comes back when the count grows', str_contains( $html, '2 subscriptions renew with tax added twice' ), $html );
$dismiss( Renewal_Tax_Repair::NOTICE, 2 );
$check( 'dismissed again at two', '' === $notice() );

echo "\n3. Repairing\n";
$repaired = $call( array( $repair, 'handle' ), array( 'subscription' => (string) $second->get_id(), 'from' => 'subkit', '_wpnonce' => wp_create_nonce( Renewal_Tax_Repair::ACTION . '_' . $second->get_id() ) ) );
$check( 'a repair from SubKit → Settings returns there', 'redirect:' . add_query_arg( array( 'subkit_tax_repaired' => $second->get_id(), 'subkit_tax_repair_failed' => 0 ), Settings_Page::section_url() ) === $repaired, $repaired );
$check( 'and it was repaired', ! in_array( $second->get_id(), $repair->affected_ids(), true ) && (float) wc_get_order( $second->get_id() )->get_total_tax() > 0 );
$check( 'with one left, the notice stays dismissed', '' === $notice() );

$third = $make( 'notices-3@example.test', true );
delete_transient( Renewal_Tax_Repair::CACHE );
$html = $notice();
$check( 'a new one after a repair brings it back, though the count only returned to two', str_contains( $html, '2 subscriptions renew with tax added twice' ), $html );

// Changed since the list was cached, so the repair must refuse it.
$repair->affected_ids();
$changed = wc_get_order( $third->get_id() );
foreach ( $changed->get_items() as $line ) {
	$line->set_subtotal( '15.00' );
	$line->set_total( '15.00' );
	$line->save();
}
$changed->calculate_totals( false );
$changed->save();
$check( 'setup: the stale list still has the changed subscription', in_array( $third->get_id(), array_map( 'intval', (array) get_transient( Renewal_Tax_Repair::CACHE ) ), true ) );

$failed = $call( array( $repair, 'handle' ), array( 'subscription' => (string) $third->get_id(), 'from' => 'woocommerce', '_wpnonce' => wp_create_nonce( Renewal_Tax_Repair::ACTION . '_' . $third->get_id() ) ) );
$check( 'a failed repair from WooCommerce → Settings returns there, saying it failed', 'redirect:' . add_query_arg( array( 'subkit_tax_repaired' => 0, 'subkit_tax_repair_failed' => $third->get_id() ), admin_url( 'admin.php?page=wc-settings&tab=subkit' ) ) === $failed, $failed );
$check( 'and drops the cached list', false === get_transient( Renewal_Tax_Repair::CACHE ) );

$_GET = array( 'subkit_tax_repair_failed' => (string) $third->get_id() );
ob_start();
( new Settings() )->render_tax_repair();
$screen = (string) ob_get_clean();
$_GET   = array();
$check( 'the list says the repair did not happen', str_contains( $screen, sprintf( 'Subscription #%d was not repaired', $third->get_id() ) ), $screen );
$check( 'and no longer offers it', ! str_contains( $screen, 'subscription=' . $third->get_id() . '&amp;_wpnonce' ) && ! in_array( $third->get_id(), $repair->affected_ids(), true ) );
$check( 'while still listing the one left', in_array( $first->get_id(), $repair->affected_ids(), true ) && str_contains( $screen, 'from=woocommerce' ) );

echo "\n4. Payments awaiting confirmation\n";
$check( 'the ledger records when a charge went pending', (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $wpdb->prefix . 'subkit_charge_slot', 'pending_gmt' ) ) && Migrator::DB_VERSION <= (int) get_option( 'subkit_db_version' ) );
( new Migrator() )->install();
$check( 'installing again changes nothing and keeps the double-charge guard', ( new Migrator() )->charge_slot_guard_intact() );

$late = $make( 'notices-late@example.test' );
$harness->next = 'unknown';
$processor->process( $late->get_id() );
$slot = $slots->latest_unsettled( $late->get_id() );
$check( 'setup: the first attempt left the slot charging', $slot && Charge_Slot_Repository::STATE_CHARGING === $slot->state, $slot );
$wpdb->update( $wpdb->prefix . 'subkit_charge_slot', array( 'created_gmt' => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ), array( 'id' => (int) $slot->id ) );

$harness->next = 'pending';
$processor->process( $late->get_id() );
$slot = $slots->find( $late->get_id(), (int) $slot->period_index );
$check( 'a late retry goes pending on the slot claimed a month ago', Charge_Slot_Repository::STATE_PENDING === $slot->state && strtotime( $slot->created_gmt . ' UTC' ) < time() - 29 * DAY_IN_SECONDS, $slot );
$check( 'its wait is measured from going pending', abs( strtotime( Charge_Slot_Repository::pending_since( $slot ) . ' UTC' ) - time() ) < HOUR_IN_SECONDS, $slot );
$stale_ids = static fn(): array => array_map( 'intval', wp_list_pluck( $slots->stale_pending( 10 ), 'id' ) );
$check( 'so it is not reported as waiting too long', ! in_array( (int) $slot->id, $stale_ids(), true ) );

$card = static function (): array {
	return array_values( array_filter( Settings::status_checks(), static fn( $c ) => 'Payments awaiting confirmation' === $c['label'] ) )[0] ?? array();
};
$drawn = static function ( array $check ): string {
	ob_start();
	Settings::render_check( $check );
	return (string) ob_get_clean();
};
$check( 'and the status card is not raised for it', ! in_array( (int) $slot->id, $stale_ids(), true ) && ! str_contains( $drawn( $card() ), 'subscription=' . $late->get_id() . '"' ) );

$wpdb->update( $wpdb->prefix . 'subkit_charge_slot', array( 'pending_gmt' => gmdate( 'Y-m-d H:i:s', time() - 12 * DAY_IN_SECONDS ) ), array( 'id' => (int) $slot->id ) );
$order = wc_get_order( (int) $slot->renewal_order_id );
$html  = $drawn( $card() );
$check( 'twelve days after going pending, it is listed', in_array( (int) $slot->id, $stale_ids(), true ) && ! $card()['ok'], $card() );
$check( 'with a link to the subscription', str_contains( $html, 'href="' . str_replace( '&#038;', '&amp;', esc_url( admin_url( 'admin.php?page=' . \SubKit\Admin\Menu::LIST_SLUG . '&subscription=' . $late->get_id() ) ) ) . '"' ), $html );
$check( 'its amount and how long it has waited', $order && str_contains( $html, number_format( (float) $order->get_total(), 2 ) ) && str_contains( $html, 'waiting 12 days' ), $html );
$check( 'and a link to its renewal order', $order && str_contains( $html, 'href="' . str_replace( '&#038;', '&amp;', esc_url( $order->get_edit_order_url() ) ) . '"' ) && str_contains( $html, '#' . $order->get_order_number() . '</a>' ), $html );

$wpdb->query( $wpdb->prepare( 'UPDATE %i SET pending_gmt = NULL, created_gmt = %s WHERE id = %d', $wpdb->prefix . 'subkit_charge_slot', gmdate( 'Y-m-d H:i:s', time() - 11 * DAY_IN_SECONDS ), (int) $slot->id ) );
$check( 'a slot that went pending before the moment was recorded falls back to when it was claimed', in_array( (int) $slot->id, $stale_ids(), true ) && str_contains( $drawn( $card() ), 'waiting 11 days' ) );

// ---- clean up -----------------------------------------------------------------------
remove_filter( 'wp_die_handler', $died );
remove_filter( 'wp_redirect', $redirected, 1 );
unset( $_SERVER['HTTP_REFERER'] );
foreach ( $made as $id ) {
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT renewal_order_id FROM %i WHERE subscription_id = %d AND renewal_order_id IS NOT NULL', $wpdb->prefix . 'subkit_charge_slot', $id ) ) as $renewal_id ) {
		$r = wc_get_order( (int) $renewal_id );
		$r && $r->delete( true );
	}
	$free->get( 'scheduler' )->unschedule( $id );
	as_unschedule_all_actions( 'subkit_dunning_retry', array( 'subscription_id' => $id ), 'subkit' );
	$s = wc_get_order( $id );
	$s && $s->delete( true );
	$wpdb->delete( $wpdb->prefix . 'subkit_charge_slot', array( 'subscription_id' => $id ) );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $customer );
wp_delete_post( $p->get_id(), true );
WC_Tax::_delete_tax_rate( $rate );
foreach ( $saved as $name => $value ) {
	null === $value ? delete_option( $name ) : update_option( $name, $value );
}
WC_Cache_Helper::invalidate_cache_group( 'taxes' );
delete_transient( Renewal_Tax_Repair::CACHE );
'' === $dismissed_meta ? delete_user_meta( 1, 'subkit_dismissed_notices' ) : update_user_meta( 1, 'subkit_dismissed_notices', $dismissed_meta );

subkit_test_done( $fail );
