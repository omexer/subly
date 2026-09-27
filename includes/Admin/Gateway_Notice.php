<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Say why a switched-on gateway is not being offered.
 *
 * A gateway with no credentials hides itself at checkout, which is right - a customer must
 * never pick a payment method that cannot work. But the merchant is left reading "Active"
 * on the payments screen while the checkout says there are no payment methods at all, with
 * nothing anywhere connecting the two. This is that connection.
 */
class Gateway_Notice {

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->notice(
			__( 'EasySubscription is not offering a payment method at checkout.', 'subkit-subscriptions' ),
			array_merge( $this->stripe(), $this->paypal_credentials() ),
			$this->stripe() ? 'stripe' : 'paypal'
		);

		// Separate on purpose: this one does not stop a customer paying, so saying it
		// alongside "not offered at checkout" would send the merchant looking for the
		// wrong thing entirely.
		$this->notice(
			__( 'PayPal renewals will not be recorded.', 'subkit-subscriptions' ),
			$this->paypal_webhook(),
			'paypal'
		);
	}

	/**
	 * @param string[] $lines
	 */
	private function notice( string $heading, array $lines, string $section ): void {
		if ( ! $lines ) {
			return;
		}

		echo '<div class="' . esc_attr( Notices::important( 'warning', true ) ) . '"><p><strong>' . esc_html( $heading ) . '</strong></p><ul style="list-style:disc;margin-left:20px">';

		foreach ( $lines as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}

		printf(
			'</ul><p><a class="button" href="%s">%s</a></p></div>',
			esc_url( Settings_Page::section_url( $section ) ),
			esc_html__( 'Finish setting it up', 'subkit-subscriptions' )
		);
	}

	/**
	 * @return string[]
	 */
	private function stripe(): array {
		if ( 'yes' !== get_option( 'subkit_stripe_enabled', 'no' ) ) {
			return array();
		}

		$live = 'yes' === get_option( 'subkit_stripe_live', 'no' );
		$key  = (string) get_option( $live ? 'subkit_stripe_secret' : 'subkit_stripe_test_secret', '' );

		if ( '' !== $key ) {
			return array();
		}

		// Naming the environment matters: the usual cause is keys in one box and the
		// environment set to the other, which looks like an empty field that is not empty.
		return array(
			$live
				? __( 'Stripe is switched on and set to Live, but its live secret key is empty. Until it has one, Stripe is not offered at checkout.', 'subkit-subscriptions' )
				: __( 'Stripe is switched on and set to Test, but its test secret key is empty. Until it has one, Stripe is not offered at checkout.', 'subkit-subscriptions' ),
		);
	}

	/**
	 * Credentials decide whether PayPal appears at checkout at all.
	 *
	 * @return string[]
	 */
	private function paypal_credentials(): array {
		if ( 'yes' !== get_option( 'subkit_paypal_enabled', 'no' ) ) {
			return array();
		}

		$missing = array();

		foreach ( array(
			'subkit_paypal_client_id' => __( 'client ID', 'subkit-subscriptions' ),
			'subkit_paypal_secret'    => __( 'secret', 'subkit-subscriptions' ),
		) as $option => $label ) {
			if ( '' === (string) get_option( $option, '' ) ) {
				$missing[] = $label;
			}
		}

		if ( ! $missing ) {
			return array();
		}

		return array(
			sprintf(
				/* translators: %s: the PayPal fields that are empty. */
				__( 'PayPal is switched on but has no %s. Until it does, PayPal is not offered at checkout.', 'subkit-subscriptions' ),
				implode( __( ' and ', 'subkit-subscriptions' ), $missing )
			),
		);
	}

	/**
	 * The webhook ID does not gate the checkout: PayPal bills on its own schedule and
	 * tells SubKit by webhook, and an unverifiable webhook is rejected, so every renewal
	 * goes unrecorded while the customer is charged.
	 *
	 * @return string[]
	 */
	private function paypal_webhook(): array {
		if ( 'yes' !== get_option( 'subkit_paypal_enabled', 'no' ) || '' !== (string) get_option( 'subkit_paypal_webhook_id', '' ) ) {
			return array();
		}

		// Nobody can pay at all without credentials, so promising charges would contradict
		// the notice above it.
		if ( $this->paypal_credentials() ) {
			return array();
		}

		return array(
			__( 'PayPal is switched on but has no webhook ID. Customers can pay, and PayPal will keep charging them, but EasySubscription cannot verify what PayPal sends back — so renewals are rejected and never show against the subscription.', 'subkit-subscriptions' ),
		);
	}
}
