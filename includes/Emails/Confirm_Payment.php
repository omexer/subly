<?php

namespace EasySubscription\Emails;

use EasySubscription\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The bank wants the customer to confirm (3DS/SCA).
 *
 * This is NOT a failure and must never read like one. Calling a bank challenge a
 * "failed payment" is the most common avoidable churn cause in subscriptions, so the
 * word "failed" does not appear anywhere in this email.
 */
class Confirm_Payment extends Subscription_Email {

	private string $action_url = '';

	public function __construct() {
		$this->id             = 'easysubscription_confirm_payment';
		$this->title          = __( 'Confirm your payment', 'easysubscription' );
		$this->description    = __( 'Sent when the customer\'s bank asks them to confirm a renewal payment.', 'easysubscription' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Confirm your payment', 'easysubscription' );
	}

	public function get_default_heading(): string {
		return __( 'One quick confirmation needed', 'easysubscription' );
	}

	public function trigger( Subscription $subscription, \WC_Order $order, string $action_url ): void {
		$this->action_url = $action_url;

		if ( $this->prepare( $subscription, $order ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: %s: amount */
			__( 'Your bank needs you to confirm the %s payment for your subscription. It takes a few seconds and nothing has been charged yet.', 'easysubscription' ),
			$this->amount( $this->related_order ? $this->related_order->get_total() : $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'easysubscription' ) => '#' . $this->subscription->get_id(),
			__( 'Amount', 'easysubscription' )       => $this->amount( $this->related_order ? $this->related_order->get_total() : 0 ),
		);
	}

	protected function call_to_action(): ?array {
		$url = $this->action_url ?: ( $this->related_order ? $this->related_order->get_checkout_payment_url() : '' );

		return $url ? array(
			'label' => __( 'Confirm payment', 'easysubscription' ),
			'url'   => $url,
		) : null;
	}

	protected function outro(): string {
		return __( 'If the link has expired, open your account and pay from there - a fresh one is generated automatically.', 'easysubscription' );
	}
}
