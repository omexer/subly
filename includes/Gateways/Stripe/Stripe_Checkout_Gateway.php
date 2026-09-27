<?php

namespace EasySubscription\Gateways\Stripe;

use EasySubscription\Domain\Money;
use EasySubscription\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe at checkout, via a hosted Checkout Session in setup-and-pay mode.
 *
 * Hosted rather than an inline card field on purpose: the card never touches this site,
 * which keeps PCI scope at SAQ-A and means SCA is Stripe's problem at signup. The saved
 * payment method is what the recurring adapter charges later.
 */
class Stripe_Checkout_Gateway extends \WC_Payment_Gateway {

	public const ID = 'easysubscription_stripe';

	public function __construct( private readonly Stripe_Client $client ) {
		$this->id                 = self::ID;
		$this->method_title       = __( 'Stripe Subscriptions (EasySubscription)', 'easysubscription' );
		$this->method_description = __( 'Take card payments for subscriptions. Renewals are charged automatically against the saved card. Configure keys under WooCommerce → Settings → Subscriptions → Stripe.', 'easysubscription' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Credit or debit card', 'easysubscription' ) );
		$this->description = $this->get_option( 'description', __( 'You will be redirected to Stripe to pay securely.', 'easysubscription' ) );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . self::ID . '_return', array( $this, 'handle_return' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'easysubscription' ),
				'type'    => 'checkbox',
				'label'   => __( 'Offer card payments for subscription purchases', 'easysubscription' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'Title', 'easysubscription' ),
				'type'    => 'text',
				'default' => __( 'Credit or debit card', 'easysubscription' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'easysubscription' ),
				'type'    => 'textarea',
				'default' => __( 'You will be redirected to Stripe to pay securely.', 'easysubscription' ),
			),
		);
	}

	public function is_available(): bool {
		if ( 'yes' !== $this->enabled || ! $this->client->is_configured() ) {
			return false;
		}

		// Paying an existing order, such as a declined renewal, has nothing to do with what is in the cart.
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return parent::is_available();
		}

		// Deliberately the cart, not is_checkout(): the block checkout asks over the Store
		// API, where is_checkout() is false, and the answer there has to be the same one
		// the classic checkout gets or the gateway appears for carts it cannot serve.
		$cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;

		if ( ! $cart || $cart->is_empty() ) {
			return parent::is_available();
		}

		return Subscription_Product::cart_has_subscription() && parent::is_available();
	}

	/**
	 * @param int $order_id
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return array( 'result' => 'failure' );
		}

		$response = $this->client->post( '/v1/checkout/sessions', $this->session_args( $order ) );

		if ( ! $response['ok'] || empty( $response['body']['url'] ) ) {
			$order->add_order_note( sprintf( 'Stripe: %s', $response['error'] ) );
			wc_add_notice( __( 'We could not start the card payment. Please try again or use another method.', 'easysubscription' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_easysubscription_stripe_session', $response['body']['id'] );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => (string) $response['body']['url'],
		);
	}

	/**
	 * A trial with no sign-up fee costs nothing today, and Stripe rejects a zero-amount
	 * payment. Setup mode stores the card without charging it, which is what a trial needs.
	 *
	 * @return array<string, mixed>
	 */
	private function session_args( \WC_Order $order ): array {
		$common = array(
			'customer_email'      => $order->get_billing_email(),
			// Stripe's default creates no customer, and a card without one cannot be charged again.
			'customer_creation'   => 'always',
			'client_reference_id' => (string) $order->get_id(),
			'success_url'         => $this->return_url( $order, 'success' ),
			'cancel_url'          => $this->return_url( $order, 'cancel' ),
			'metadata'            => array( 'easysubscription_order' => (string) $order->get_id() ),
		);

		$amount = Money::from_decimal( $order->get_total(), $order->get_currency() );

		if ( $amount->minor() <= 0 ) {
			return $common + array(
				'mode'              => 'setup',
				'currency'          => strtolower( $order->get_currency() ),
				'setup_intent_data' => array( 'metadata' => array( 'easysubscription_order' => (string) $order->get_id() ) ),
			);
		}

		return $common + array(
			'mode'                => 'payment',
			// Keep the card on file so renewals can be charged off-session later.
			'payment_intent_data' => array( 'setup_future_usage' => 'off_session' ),
			'line_items'          => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => strtolower( $order->get_currency() ),
						'unit_amount'  => $amount->minor(),
						'product_data' => array(
							'name' => sprintf(
								/* translators: %s: order number */
								__( 'Order %s', 'easysubscription' ),
								$order->get_order_number()
							),
						),
					),
				),
			),
		);
	}

	public function handle_return(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Stripe controls this redirect; the HMAC below is the check.
		$order_id = absint( $_GET['order_id'] ?? 0 );
		$result   = sanitize_key( $_GET['easysubscription_result'] ?? '' );
		$token    = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$order = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof \WC_Order || ! hash_equals( $this->return_token( $order ), $token ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		if ( 'success' !== $result && 'easysubscription_renewal' === $order->get_created_via() ) {
			wc_add_notice( __( 'The card payment was cancelled, so this renewal is still unpaid.', 'easysubscription' ), 'notice' );
			wp_safe_redirect( $order->get_checkout_payment_url() );
			exit;
		}

		if ( 'success' !== $result ) {
			wc_add_notice( __( 'The card payment was cancelled, so no subscription was started.', 'easysubscription' ), 'notice' );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		$this->complete_from_session( $order );

		wp_safe_redirect( $this->get_return_url( $order ) );
		exit;
	}

	/**
	 * Confirm with Stripe and capture the saved payment method for future renewals.
	 */
	public function complete_from_session( \WC_Order $order ): void {
		$session_id = (string) $order->get_meta( '_easysubscription_stripe_session' );

		if ( '' === $session_id ) {
			return;
		}

		$session = $this->client->get( '/v1/checkout/sessions/' . rawurlencode( $session_id ) . '?expand[]=payment_intent&expand[]=setup_intent' );

		if ( ! $session['ok'] || ! $this->session_is_settled( $session['body'] ?? array() ) ) {
			$order->update_status( 'on-hold', __( 'Waiting for Stripe to confirm the payment.', 'easysubscription' ) );
			return;
		}

		// Setup mode has no payment intent; the card lives on the setup intent instead.
		$intent   = $session['body']['payment_intent'] ?? $session['body']['setup_intent'] ?? array();
		$customer = (string) ( $session['body']['customer'] ?? '' );
		$method   = (string) ( $intent['payment_method'] ?? '' );

		// The recurring adapter charges these later; without them renewals cannot happen.
		$order->update_meta_data( Stripe_Gateway::META_CUSTOMER, $customer );
		$order->update_meta_data( Stripe_Gateway::META_METHOD, $method );
		$order->save();

		$this->attach_mandate( $order );

		// A retry charged it off-session while the customer was at Stripe, so this is a second capture.
		if ( $order->is_paid() ) {
			$this->flag_second_capture( $order, (string) ( $intent['id'] ?? $session_id ) );
			return;
		}

		$order->payment_complete( (string) ( $intent['id'] ?? $session_id ) );
	}

	private function flag_second_capture( \WC_Order $order, string $reference ): void {
		$message = sprintf( 'Stripe took a second payment (%1$s) for order #%2$s, which was already paid. Refund it in Stripe.', $reference, $order->get_order_number() );

		$order->add_order_note( $message );

		$activity     = \EasySubscription\Plugin::instance()->get( 'activity' );
		$subscription = (int) $order->get_meta( '_easysubscription_subscription_id' );

		if ( $subscription && $activity instanceof \EasySubscription\Data\Activity_Repository ) {
			$activity->log(
				$subscription,
				\EasySubscription\Data\Activity_Repository::TYPE_CHARGE_ATTEMPT,
				$message,
				array(
					'reference' => $reference,
					'refund'    => true,
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function session_is_settled( array $body ): bool {
		if ( 'setup' === ( $body['mode'] ?? '' ) ) {
			return 'complete' === ( $body['status'] ?? '' );
		}

		return 'paid' === ( $body['payment_status'] ?? '' );
	}

	private function attach_mandate( \WC_Order $order ): void {
		$id = (int) $order->get_meta( '_easysubscription_subscription_id' );

		if ( ! $id ) {
			return;
		}

		$subscription = wc_get_order( $id );

		if ( ! $subscription instanceof \EasySubscription\Domain\Subscription ) {
			return;
		}

		$result = ( new Stripe_Gateway( $this->client ) )->create_mandate( $subscription, $order );

		if ( ! $result->is_success() ) {
			$order->add_order_note( sprintf( 'Stripe: could not store the card for renewals — %s', $result->describe() ) );
		}
	}

	private function return_url( \WC_Order $order, string $result ): string {
		return add_query_arg(
			array(
				'order_id'      => $order->get_id(),
				'easysubscription_result' => $result,
				'token'         => $this->return_token( $order ),
			),
			WC()->api_request_url( self::ID . '_return' )
		);
	}

	private function return_token( \WC_Order $order ): string {
		return hash_hmac( 'sha256', 'easysubscription_stripe_return_' . $order->get_id() . $order->get_order_key(), wp_salt( 'auth' ) );
	}
}
