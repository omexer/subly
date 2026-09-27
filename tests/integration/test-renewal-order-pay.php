<?php
/**
 * A declined renewal paid through its payment link settles once, keeps the new card, and is never charged again.
 *
 * @package EasySubscription
 */

use EasySubscription\Billing\Renewal_Processor;
use EasySubscription\Data\Charge_Slot_Repository;
use EasySubscription\Domain\Subscription;
use EasySubscription\Domain\Subscription_Status;
use EasySubscription\Gateways\Charge_Result;
use EasySubscription\Gateways\Gateway_Model;
use EasySubscription\Gateways\Stripe\Stripe_Checkout_Gateway;
use EasySubscription\Gateways\Stripe\Stripe_Client;
use EasySubscription\Gateways\Test_Gateway;

require __DIR__ . '/bootstrap.php';

$harness = new class() implements \EasySubscription\Gateways\Recurring_Gateway {
	public string $next     = 'hard_decline';
	public int $charges     = 0;
	public array $mandates  = array();
	public bool $keeps_card = true;

	public function id(): string { return Test_Gateway::ID; }
	public function title(): string { return 'Harness'; }
	public function model(): Gateway_Model { return Gateway_Model::Tokenized; }
	public function supports( string $f ): bool { return true; }
	public function create_mandate( Subscription $s, \WC_Order $o ): Charge_Result {
		$this->mandates[] = $o->get_id();
		$s->update_meta_data( '_easysubscription_harness_card', $this->keeps_card ? 'card-from-' . $o->get_id() : 'half-written' );
		if ( ! $this->keeps_card ) { return Charge_Result::error( 'That order carries no reusable card.' ); }
		$s->save();
		return Charge_Result::success( 'card-from-' . $o->get_id() );
	}
	public function charge_renewal( Subscription $s, \WC_Order $r, string $k ): Charge_Result {
		++$this->charges;
		return 'success' === $this->next ? Charge_Result::success( 'ch_' . $this->charges ) : Charge_Result::hard_decline( 'card_declined', 'Declined by the harness.' );
	}
	public function reconcile( Subscription $s, string $k ): ?Charge_Result { return null; }
	public function cancel_mandate( Subscription $s ): bool { return true; }
	public function update_payment_method( Subscription $s, string $t ): bool { return true; }
};

$plan_billed = new class() implements \EasySubscription\Gateways\Recurring_Gateway {
	public function id(): string { return 'easysubscription_harness_plan'; }
	public function title(): string { return 'Plan-billed harness'; }
	public function model(): Gateway_Model { return Gateway_Model::GatewayManaged; }
	public function supports( string $f ): bool { return false; }
	public function create_mandate( Subscription $s, \WC_Order $o ): Charge_Result { return Charge_Result::success( 'plan' ); }
	public function charge_renewal( Subscription $s, \WC_Order $r, string $k ): Charge_Result { return Charge_Result::error( 'n/a' ); }
	public function reconcile( Subscription $s, string $k ): ?Charge_Result { return null; }
	public function cancel_mandate( Subscription $s ): bool { return true; }
	public function update_payment_method( Subscription $s, string $t ): bool { return false; }
};

$free = \EasySubscription\Plugin::instance();
$free->get( 'gateways' )->add( $harness );
$free->get( 'gateways' )->add( $plan_billed );

$processor = $free->get( 'processor' );
$slots     = $free->get( 'charge_slots' );
$lock      = $free->get( 'lock' );
$product   = easysubscription_test_product();
$made      = array();

$succeeded = array();
add_action( 'easysubscription_renewal_succeeded', function ( $s ) use ( &$succeeded ) { $succeeded[ $s->get_id() ] = ( $succeeded[ $s->get_id() ] ?? 0 ) + 1; }, 10, 1 );

$receipts = array();
add_filter( 'wp_mail', function ( $args ) use ( &$receipts ) {
	if ( 'Your payment went through' === $args['subject'] ) { $receipts[] = $args['to']; }
	return $args;
}, 999 );

$make = function ( string $email, string $method = Test_Gateway::ID ) use ( $product, &$made ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( 1 );
	$s->set_currency( 'USD' );
	$s->set_billing_period( 'month' );
	$s->set_billing_interval( 1 );
	$s->set_payment_method( $method );
	$s->set_address( array( 'first_name' => 'Pay', 'email' => $email, 'country' => 'US' ), 'billing' );
	$item = new WC_Order_Item_Product();
	$item->set_props( array( 'name' => 'Pay link probe', 'product_id' => $product->get_id(), 'quantity' => 1, 'subtotal' => '20', 'total' => '20' ) );
	$s->add_item( $item );
	$s->set_next_payment( gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
	$s->update_meta_data( '_easysubscription_site_url', get_option( 'siteurl' ) );
	$s->transition_to( Subscription_Status::Pending );
	$s->calculate_totals( false );
	$s->save();
	$s->transition_to( Subscription_Status::Active );
	$s->save();
	$made[] = $s->get_id();
	return $s;
};

// Declined by the harness; returns the failed slot and its renewal order.
$decline = function ( Subscription $s ) use ( $processor, $slots, $harness ): array {
	$harness->next = 'hard_decline';
	$processor->process( $s->get_id() );
	$slot  = $slots->latest_unsettled( $s->get_id() );
	$order = $slot ? wc_get_order( (int) $slot->renewal_order_id ) : null;
	if ( ! $slot || ! $order instanceof WC_Order || Charge_Slot_Repository::STATE_FAILED !== $slot->state ) {
		easysubscription_test_abort( 'the harness decline did not leave a failed slot with a renewal order' );
	}
	return array( $slot, $order );
};

// What a Dunning retry does: back to active, then run the pipeline.
$retry = function ( int $id ) use ( $processor ): void {
	$s = wc_get_order( $id );
	if ( Subscription_Status::OnHold === $s->get_status_enum() ) {
		$s->transition_to( Subscription_Status::Active, 'Retrying, as the harness.' );
		$s->save();
	}
	$processor->process( $id );
};

echo "\n1. Paying the declined renewal settles it, once\n";
$s1                  = $make( 'paylink-1@example.test' );
list( $slot, $order ) = $decline( $s1 );
$check( 'a decline puts the subscription on hold', Subscription_Status::OnHold === wc_get_order( $s1->get_id() )->get_status_enum() );
$check( 'and its renewal can be paid', wc_get_order( $order->get_id() )->needs_payment() );

$receipts = array();
wc_get_order( $order->get_id() )->payment_complete( 'paylink_txn_1' );

$s1      = wc_get_order( $s1->get_id() );
$settled = $slots->find( $s1->get_id(), (int) $slot->period_index );
$check( 'the slot is paid', Charge_Slot_Repository::STATE_PAID === $settled->state && (int) $settled->renewal_order_id === $order->get_id(), $settled );
$check( 'the subscription is active again', Subscription_Status::Active === $s1->get_status_enum(), $s1->get_status() );
$check( 'the next payment is the end of the period just paid for', strtotime( (string) $s1->get_next_payment() . ' UTC' ) === strtotime( $slot->covers_to_gmt . ' UTC' ), array( $s1->get_next_payment(), $slot->covers_to_gmt ) );
$check( 'easysubscription_renewal_succeeded fired once', 1 === ( $succeeded[ $s1->get_id() ] ?? 0 ), $succeeded );
$check( 'one receipt was sent', array( 'paylink-1@example.test' ) === $receipts, $receipts );
$check( 'the card it was paid with is kept for renewals', array( $order->get_id() ) === $harness->mandates && 'card-from-' . $order->get_id() === $s1->get_meta( '_easysubscription_harness_card' ), $harness->mandates );

$next_before = $s1->get_next_payment();
$retry( $s1->get_id() );
$check( 'a retry afterwards charges nothing', 1 === $harness->charges, $harness->charges );

do_action( 'woocommerce_order_status_completed', $order->get_id() );
do_action( 'woocommerce_payment_complete', $order->get_id() );
$processor->settle_paid_order( $order->get_id() );
$check( 'the hooks firing again settle nothing twice', 1 === $succeeded[ $s1->get_id() ] && 1 === count( $receipts ) && wc_get_order( $s1->get_id() )->get_next_payment() === $next_before, array( $succeeded, $receipts ) );

echo "\n2. Paid while a renewal run holds the subscription\n";
$s2                   = $make( 'paylink-2@example.test' );
list( $slot2, $order2 ) = $decline( $s2 );
$charges_before       = $harness->charges;

$lock->acquire( $s2->get_id() );
wc_get_order( $order2->get_id() )->payment_complete( 'paylink_txn_2' );
$check( 'nothing is settled under somebody else\'s lock', Charge_Slot_Repository::STATE_FAILED === $slots->find( $s2->get_id(), (int) $slot2->period_index )->state );
$check( 'settling is queued for later', (bool) as_next_scheduled_action( Renewal_Processor::ACTION_SETTLE, array( 'order_id' => $order2->get_id() ), 'easysubscription' ) );
$lock->release( $s2->get_id() );

$retry( $s2->get_id() );
$s2 = wc_get_order( $s2->get_id() );
$check( 'the retry settles the paid order instead of charging it', $charges_before === $harness->charges && Charge_Slot_Repository::STATE_PAID === $slots->find( $s2->get_id(), (int) $slot2->period_index )->state, array( $charges_before, $harness->charges ) );
$check( 'and restarts the subscription once', Subscription_Status::Active === $s2->get_status_enum() && 1 === ( $succeeded[ $s2->get_id() ] ?? 0 ), array( $s2->get_status(), $succeeded ) );

$processor->settle_paid_order( $order2->get_id() );
$check( 'the queued settle then finds nothing to do', 1 === $succeeded[ $s2->get_id() ] );
as_unschedule_all_actions( Renewal_Processor::ACTION_SETTLE, array( 'order_id' => $order2->get_id() ), 'easysubscription' );

echo "\n3. A renewal the pipeline charges itself\n";
$s3             = $make( 'paylink-3@example.test' );
$receipts       = array();
$harness->next  = 'success';
$processor->process( $s3->get_id() );
$check( 'still fires easysubscription_renewal_succeeded exactly once', 1 === ( $succeeded[ $s3->get_id() ] ?? 0 ), $succeeded );
$check( 'and sends one receipt', array( 'paylink-3@example.test' ) === $receipts, $receipts );
$paid3 = $slots->find( $s3->get_id(), 0 );
$check( 'without queueing a settle of its own order', $paid3 && ! as_next_scheduled_action( Renewal_Processor::ACTION_SETTLE, array( 'order_id' => (int) $paid3->renewal_order_id ), 'easysubscription' ) );

$pp            = $make( 'paylink-pp@example.test', 'easysubscription_harness_plan' );
$pp->update_meta_data( \EasySubscription\Gateways\PayPal\PayPal_Gateway::META_SUBSCRIPTION_ID, 'I-HARNESS-' . $pp->get_id() );
$pp->save();
$pp_event      = 'WH-HARNESS-' . $pp->get_id();
$receipts      = array();
set_transient( 'easysubscription_pp_evt_body_' . md5( $pp_event ), array( 'id' => $pp_event, 'event_type' => 'PAYMENT.SALE.COMPLETED', 'resource' => array( 'id' => 'SALE-' . $pp->get_id(), 'billing_agreement_id' => 'I-HARNESS-' . $pp->get_id() ) ), HOUR_IN_SECONDS );
$free->get( 'paypal_webhooks' )->process( $pp_event );
delete_transient( 'easysubscription_pp_evt_body_' . md5( $pp_event ) );
$check( 'a PayPal sale is recorded once', 1 === ( $succeeded[ $pp->get_id() ] ?? 0 ) && array( 'paylink-pp@example.test' ) === $receipts, array( $succeeded, $receipts ) );

echo "\n4. Refusals\n";
$s4                   = $make( 'paylink-4@example.test' );
list( $slot4, $order4 ) = $decline( $s4 );
$harness->mandates    = array();
wc_get_order( $order4->get_id() )->update_status( 'processing', 'Bank transfer received.' );
$check( 'marked paid by the store, it settles', Subscription_Status::Active === wc_get_order( $s4->get_id() )->get_status_enum() && Charge_Slot_Repository::STATE_PAID === $slots->find( $s4->get_id(), (int) $slot4->period_index )->state );
$check( 'but no card is taken from an order no gateway charged', array() === $harness->mandates, $harness->mandates );

$s5                   = $make( 'paylink-5@example.test' );
list( $slot5, $order5 ) = $decline( $s5 );
$harness->keeps_card  = false;
wc_get_order( $order5->get_id() )->payment_complete( 'paylink_txn_5' );
$harness->keeps_card  = true;
$s5                   = wc_get_order( $s5->get_id() );
$check( 'a gateway that will not keep the card leaves the old one alone', '' === (string) $s5->get_meta( '_easysubscription_harness_card' ), $s5->get_meta( '_easysubscription_harness_card' ) );
$check( 'and the payment still settles', Subscription_Status::Active === $s5->get_status_enum() );

$s6                   = $make( 'paylink-6@example.test' );
list( $slot6, $order6 ) = $decline( $s6 );
$s6                   = wc_get_order( $s6->get_id() );
$s6->transition_to( Subscription_Status::Cancelled, 'Recovery gave up, as the harness.' );
$s6->save();
$check( 'an ended subscription\'s renewal cannot be paid', ! wc_get_order( $order6->get_id() )->needs_payment() );
$next6 = $s6->get_next_payment();
wc_get_order( $order6->get_id() )->update_status( 'processing', 'Marked paid anyway.' );
$s6 = wc_get_order( $s6->get_id() );
$check( 'marked paid anyway, the money is recorded against its period', Charge_Slot_Repository::STATE_PAID === $slots->find( $s6->get_id(), (int) $slot6->period_index )->state );
$check( 'but the subscription is not brought back', Subscription_Status::Cancelled === $s6->get_status_enum() && $next6 === $s6->get_next_payment() && ! isset( $succeeded[ $s6->get_id() ] ), $s6->get_status() );

$shop = wc_create_order();
$shop->set_total( '10' );
$shop->save();
$check( 'an ordinary order can still be paid', $shop->needs_payment() );

echo "\n5. Gateways offered on a renewal's pay page\n";
global $wp;
$query_vars = $wp->query_vars;
$woo        = WC()->payment_gateways()->payment_gateways();
$stand_in   = reset( $woo );
$offered    = array(
	Test_Gateway::ID      => $stand_in,
	'easysubscription_paypal'       => $stand_in,
	'easysubscription_harness_plan' => $stand_in,
	'bacs'                => $stand_in,
);

$s7                   = $make( 'paylink-7@example.test' );
list( $slot7, $order7 ) = $decline( $s7 );
$wp->query_vars['order-pay'] = $order7->get_id();
$filtered = apply_filters( 'woocommerce_available_payment_gateways', $offered );
$check( 'only the gateway that renews the subscription is offered', array( Test_Gateway::ID ) === array_keys( (array) $filtered ), array_keys( (array) $filtered ) );

$s8 = $make( 'paylink-8@example.test', 'easysubscription_harness_plan' );
$s8->transition_to( Subscription_Status::OnHold, 'Held by the harness.' );
$s8->save();
$claimed = $slots->claim_next( $s8->get_id(), new DateTimeImmutable(), new DateTimeImmutable(), new DateTimeImmutable( '+1 month' ) );
$order8  = $free->get( 'order_factory' )->create( $s8, $claimed );
$slots->attach_order( (int) $claimed->id, $order8->get_id() );
$wp->query_vars['order-pay'] = $order8->get_id();
$check( 'a plan-billed gateway is never offered, even for its own subscription', array() === apply_filters( 'woocommerce_available_payment_gateways', $offered ) );

$wp->query_vars['order-pay'] = $shop->get_id();
$check( 'an ordinary order keeps every gateway', array_keys( $offered ) === array_keys( (array) apply_filters( 'woocommerce_available_payment_gateways', $offered ) ) );

$plain = new WC_Product_Simple();
$plain->set_name( 'SK pay link plain product' );
$plain->set_regular_price( '5' );
$plain->set_status( 'publish' );
$plain->save();
wc_load_cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $plain->get_id() );

$stripe          = new Stripe_Checkout_Gateway( new Stripe_Client( 'sk_test_harness' ) );
$stripe->enabled = 'yes';
$wp->query_vars  = $query_vars;
$check( 'at checkout, card payments still need a subscription in the cart', ! $stripe->is_available() );
$wp->query_vars['order-pay'] = $order7->get_id();
$check( 'on a pay page, whatever is in the cart does not hide them', $stripe->is_available() );

echo "\n6. Renewals waiting on the customer\n";
$wp->query_vars = $query_vars;
$confirmations  = array();
add_filter( 'wp_mail', function ( $args ) use ( &$confirmations ) {
	if ( 'Confirm your payment' === $args['subject'] ) { $confirmations[] = $args['message']; }
	return $args;
}, 999 );

// No recurring gateway renews BACS, so the manual invoice fallback bills it and waits.
$bacs                  = $make( 'paylink-bacs@example.test', 'bacs' );
$processor->process( $bacs->get_id() );
$bacs_slot             = $slots->latest_unsettled( $bacs->get_id() );
$bacs_order            = $bacs_slot ? wc_get_order( (int) $bacs_slot->renewal_order_id ) : null;
if ( ! $bacs_order instanceof WC_Order || 'on-hold' !== $bacs_order->get_status() ) {
	easysubscription_test_abort( 'the manual fallback did not leave an on-hold renewal order' );
}
$check( 'an invoice renewal held for the customer can be paid', $bacs_order->needs_payment() );
$wp->query_vars['order-pay'] = $bacs_order->get_id();
$check( 'with the subscription\'s own method', array( 'bacs' ) === array_keys( (array) apply_filters( 'woocommerce_available_payment_gateways', array( 'bacs' => $stand_in, 'easysubscription_paypal' => $stand_in ) ) ) );
$wp->query_vars = $query_vars;
wc_get_order( $bacs_order->get_id() )->update_status( 'processing', 'Bank transfer received.' );
wc_get_order( $bacs_order->get_id() )->update_status( 'completed' );
$check( 'marked paid, it settles once', Subscription_Status::Active === wc_get_order( $bacs->get_id() )->get_status_enum() && 1 === ( $succeeded[ $bacs->get_id() ] ?? 0 ) && Charge_Slot_Repository::STATE_PAID === $slots->find( $bacs->get_id(), (int) $bacs_slot->period_index )->state, $succeeded[ $bacs->get_id() ] ?? 0 );

$held_plain = wc_get_order( $order7->get_id() );
$held_plain->set_status( 'on-hold' );
$held_plain->save();
$check( 'an on-hold renewal that is not waiting on the customer still cannot be paid', ! wc_get_order( $order7->get_id() )->needs_payment() );

$stripe_api = new class( 'sk_test_harness' ) extends Stripe_Client {
	public array $routes = array();
	public function post( string $path, array $params, string $idempotency_key = '' ): array { return $this->answer( 'POST ' . $path ); }
	public function get( string $path ): array { return $this->answer( 'GET ' . $path ); }
	private function answer( string $request ): array {
		foreach ( $this->routes as $prefix => $response ) {
			if ( str_starts_with( $request, $prefix ) ) { return $response; }
		}
		return array( 'ok' => false, 'status' => 404, 'body' => array(), 'error' => 'unrouted ' . $request, 'code' => '' );
	}
};
$to_stripe = static function ( $gateway, $subscription ) use ( $stripe_api ) {
	return \EasySubscription\Gateways\Stripe\Stripe_Gateway::ID === $subscription->get_payment_method() ? new \EasySubscription\Gateways\Stripe\Stripe_Gateway( $stripe_api ) : $gateway;
};
add_filter( 'easysubscription_gateway_for_subscription', $to_stripe, 10, 2 );

$secret             = 'pi_3ds_secret_harness';
$stripe_api->routes = array(
	'POST /v1/payment_intents' => array(
		'ok'     => false,
		'status' => 402,
		'code'   => 'authentication_required',
		'error'  => 'This payment requires authentication.',
		'body'   => array( 'error' => array( 'code' => 'authentication_required', 'payment_intent' => array( 'id' => 'pi_3ds', 'client_secret' => $secret ) ) ),
	),
);
$sca = $make( 'paylink-3ds@example.test', \EasySubscription\Gateways\Stripe\Stripe_Gateway::ID );
$sca->update_meta_data( \EasySubscription\Gateways\Stripe\Stripe_Gateway::META_CUSTOMER, 'cus_3ds' );
$sca->update_meta_data( \EasySubscription\Gateways\Stripe\Stripe_Gateway::META_METHOD, 'pm_3ds' );
$sca->save();
$confirmations = array();
$processor->process( $sca->get_id() );
$sca_slot  = $slots->latest_unsettled( $sca->get_id() );
$sca_order = $sca_slot ? wc_get_order( (int) $sca_slot->renewal_order_id ) : null;
if ( ! $sca_order instanceof WC_Order ) {
	easysubscription_test_abort( 'the 3-D Secure renewal left no order' );
}
$action = (string) $sca_order->get_meta( '_easysubscription_action_url' );
$check( 'a bank challenge sends the customer to the renewal\'s pay page', $sca_order->get_checkout_payment_url() === $action, $action );
$check( 'the link carries no client secret', ! str_contains( $action, $secret ) && ! str_contains( implode( '', $confirmations ), $secret ), $action );
$check( 'the confirmation email and My Account both use it', 1 === count( $confirmations ) && str_contains( html_entity_decode( $confirmations[0] ), $action ) && $action === ( \EasySubscription\Frontend\MyAccount\Status_Presenter::primary_action( wc_get_order( $sca->get_id() ) )['url'] ?? '' ) );
$check( 'and it can be paid there', $sca_order->needs_payment() );
wc_get_order( $sca_order->get_id() )->payment_complete( 'pi_checkout_3ds' );
wc_get_order( $sca_order->get_id() )->update_status( 'completed' );
$check( 'paying it settles once', Subscription_Status::Active === wc_get_order( $sca->get_id() )->get_status_enum() && 1 === ( $succeeded[ $sca->get_id() ] ?? 0 ), $succeeded[ $sca->get_id() ] ?? 0 );

echo "\n7. Money that arrives the wrong way\n";
$paypal = WC()->payment_gateways()->payment_gateways()['easysubscription_paypal'] ?? null;
if ( ! $paypal instanceof WC_Payment_Gateway ) {
	easysubscription_test_abort( 'PayPal checkout is not registered' );
}
$refused_pp = $paypal->process_payment( $order7->get_id() );
$pp_notes   = implode( ' | ', wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order7->get_id() ) ), 'content' ) );
$check( 'PayPal refuses to pay a renewal, whatever route reached it', 'failure' === ( $refused_pp['result'] ?? '' ) && str_contains( $pp_notes, 'cannot be paid with PayPal' ), $pp_notes );

$paid_twice = wc_get_order( $order->get_id() );
$paid_twice->update_meta_data( '_easysubscription_stripe_session', 'cs_second' );
$paid_twice->save();
$stripe_api->routes = array(
	'GET /v1/checkout/sessions/cs_second' => array( 'ok' => true, 'status' => 200, 'error' => '', 'code' => '', 'body' => array( 'id' => 'cs_second', 'mode' => 'payment', 'payment_status' => 'paid', 'customer' => 'cus_twice', 'payment_intent' => array( 'id' => 'pi_second_capture', 'payment_method' => 'pm_twice' ) ) ),
);
( new Stripe_Checkout_Gateway( $stripe_api ) )->complete_from_session( $paid_twice );
$logged = implode( ' | ', wp_list_pluck( $free->get( 'activity' )->for_subscription( $s1->get_id(), 20 ), 'message' ) );
$check( 'a second Stripe capture of a paid renewal is flagged for refund', str_contains( $logged, 'pi_second_capture' ) && str_contains( $logged, 'Refund' ), $logged );
$check( 'and the order keeps the payment it was settled with', 'paylink_txn_1' === wc_get_order( $order->get_id() )->get_transaction_id() && 1 === $succeeded[ $s1->get_id() ] );

$ending               = $make( 'paylink-ending@example.test' );
list( $slot9, $order9 ) = $decline( $ending );
$ending               = wc_get_order( $ending->get_id() );
$ending->transition_to( Subscription_Status::PendingCancel, 'Cancelled at period end, as the harness.' );
$ending->save();
wc_get_order( $order9->get_id() )->update_status( 'processing', 'Bank transfer received.' );
$ending = wc_get_order( $ending->get_id() );
$check( 'a subscription that is ending keeps what it paid for', strtotime( (string) $ending->get_next_payment() . ' UTC' ) === strtotime( $slot9->covers_to_gmt . ' UTC' ) && Subscription_Status::PendingCancel === $ending->get_status_enum(), array( $ending->get_status(), $ending->get_next_payment(), $slot9->covers_to_gmt ) );
// Receipts, licence extensions, webhooks and Dunning all hang off this; the money was taken.
$check( 'and the payment is announced once, like any other renewal', 1 === ( $succeeded[ $ending->get_id() ] ?? 0 ) && Subscription_Status::PendingCancel === wc_get_order( $ending->get_id() )->get_status_enum(), $succeeded[ $ending->get_id() ] ?? 0 );

remove_filter( 'easysubscription_gateway_for_subscription', $to_stripe, 10 );

// ---- clean up -----------------------------------------------------------------------
$wp->query_vars = $query_vars;
WC()->cart->empty_cart();
$plain->delete( true );
$shop->delete( true );

global $wpdb;
foreach ( $made as $id ) {
	$renewals = $wpdb->get_col( $wpdb->prepare( 'SELECT renewal_order_id FROM %i WHERE subscription_id = %d AND renewal_order_id IS NOT NULL', $wpdb->prefix . 'easysubscription_charge_slot', $id ) );
	foreach ( $renewals as $renewal_id ) {
		$r = wc_get_order( (int) $renewal_id );
		$r && $r->delete( true );
	}
	$free->get( 'scheduler' )->unschedule( $id );
	$s = wc_get_order( $id );
	$s && $s->delete( true );
}

easysubscription_test_done( $fail );
