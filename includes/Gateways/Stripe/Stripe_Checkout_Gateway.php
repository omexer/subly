<?php

namespace SubKit\Gateways\Stripe;

use SubKit\Domain\Money;
use SubKit\Product\Subscription_Product;

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

	public const ID = 'subkit_stripe';

	public function __construct( private readonly Stripe_Client $client ) {
		$this->id                 = self::ID;
		$this->method_title       = __( 'Stripe Subscriptions (SubKit)', 'subkit-subscriptions' );
		$this->method_description = __( 'Take card payments for subscriptions. Renewals are charged automatically against the saved card. Configure keys under WooCommerce → Settings → Subscriptions → Stripe.', 'subkit-subscriptions' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Credit or debit card', 'subkit-subscriptions' ) );
		$this->description = $this->get_option( 'description', __( 'You will be redirected to Stripe to pay securely.', 'subkit-subscriptions' ) );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . self::ID . '_return', array( $this, 'handle_return' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'subkit-subscriptions' ),
				'type'    => 'checkbox',
				'label'   => __( 'Offer card payments for subscription purchases', 'subkit-subscriptions' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'Title', 'subkit-subscriptions' ),
				'type'    => 'text',
				'default' => __( 'Credit or debit card', 'subkit-subscriptions' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'subkit-subscriptions' ),
				'type'    => 'textarea',
				'default' => __( 'You will be redirected to Stripe to pay securely.', 'subkit-subscriptions' ),
			),
		);
	}

	public function is_available(): bool {
		if ( 'yes' !== $this->enabled || ! $this->client->is_configured() ) {
			return false;
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

		$amount = Money::from_decimal( $order->get_total(), $order->get_currency() );

		$response = $this->client->post(
			'/v1/checkout/sessions',
			array(
				'mode'                => 'payment',
				'customer_email'      => $order->get_billing_email(),
				'client_reference_id' => (string) $order->get_id(),
				'success_url'         => $this->return_url( $order, 'success' ),
				'cancel_url'          => $this->return_url( $order, 'cancel' ),
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
									__( 'Order %s', 'subkit-subscriptions' ),
									$order->get_order_number()
								),
							),
						),
					),
				),
				'metadata'            => array( 'subkit_order' => (string) $order->get_id() ),
			)
		);

		if ( ! $response['ok'] || empty( $response['body']['url'] ) ) {
			$order->add_order_note( sprintf( 'Stripe: %s', $response['error'] ) );
			wc_add_notice( __( 'We could not start the card payment. Please try again or use another method.', 'subkit-subscriptions' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_subkit_stripe_session', $response['body']['id'] );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => (string) $response['body']['url'],
		);
	}

	public function handle_return(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Stripe controls this redirect; the HMAC below is the check.
		$order_id = absint( $_GET['order_id'] ?? 0 );
		$result   = sanitize_key( $_GET['subkit_result'] ?? '' );
		$token    = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$order = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof \WC_Order || ! hash_equals( $this->return_token( $order ), $token ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		if ( 'success' !== $result ) {
			wc_add_notice( __( 'The card payment was cancelled, so no subscription was started.', 'subkit-subscriptions' ), 'notice' );
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
		$session_id = (string) $order->get_meta( '_subkit_stripe_session' );

		if ( '' === $session_id ) {
			return;
		}

		$session = $this->client->get( '/v1/checkout/sessions/' . rawurlencode( $session_id ) . '?expand[]=payment_intent' );

		if ( ! $session['ok'] || 'paid' !== ( $session['body']['payment_status'] ?? '' ) ) {
			$order->update_status( 'on-hold', __( 'Waiting for Stripe to confirm the payment.', 'subkit-subscriptions' ) );
			return;
		}

		$intent   = $session['body']['payment_intent'] ?? array();
		$customer = (string) ( $session['body']['customer'] ?? '' );
		$method   = (string) ( $intent['payment_method'] ?? '' );

		// The recurring adapter charges these later; without them renewals cannot happen.
		$order->update_meta_data( Stripe_Gateway::META_CUSTOMER, $customer );
		$order->update_meta_data( Stripe_Gateway::META_METHOD, $method );
		$order->save();

		$this->attach_mandate( $order );

		$order->payment_complete( (string) ( $intent['id'] ?? $session_id ) );
	}

	private function attach_mandate( \WC_Order $order ): void {
		$id = (int) $order->get_meta( '_subkit_subscription_id' );

		if ( ! $id ) {
			return;
		}

		$subscription = wc_get_order( $id );

		if ( ! $subscription instanceof \SubKit\Domain\Subscription ) {
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
				'subkit_result' => $result,
				'token'         => $this->return_token( $order ),
			),
			WC()->api_request_url( self::ID . '_return' )
		);
	}

	private function return_token( \WC_Order $order ): string {
		return hash_hmac( 'sha256', 'subkit_stripe_return_' . $order->get_id() . $order->get_order_key(), wp_salt( 'auth' ) );
	}
}
