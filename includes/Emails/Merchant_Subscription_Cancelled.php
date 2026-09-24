<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;
use SubKit\Lifecycle\Cancellation_Survey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Merchant_Subscription_Cancelled extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_merchant_subscription_cancelled';
		$this->title          = __( 'Subscription cancelled (merchant)', 'subkit-subscriptions' );
		$this->description    = __( 'Sent to the store when a subscription is cancelled, with the reason the customer gave.', 'subkit-subscriptions' );
		$this->customer_email = false;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'A subscription was cancelled', 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( 'Subscription cancelled', 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: 1: customer, 2: recurring amount */
			__( '%1$s cancelled a subscription worth %2$s.', 'subkit-subscriptions' ),
			trim( $this->subscription->get_billing_first_name() . ' ' . $this->subscription->get_billing_last_name() ) ?: $this->subscription->get_billing_email(),
			$this->amount( $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		$facts = array(
			__( 'Subscription', 'subkit-subscriptions' ) => '#' . $this->subscription->get_id(),
			__( 'Customer', 'subkit-subscriptions' )     => (string) $this->subscription->get_billing_email(),
		);

		// The survey stores a key; only it knows the label, including any a filter added.
		$survey = \SubKit\Plugin::instance()->get( 'survey' );
		$reason = $survey instanceof Cancellation_Survey ? $survey->reason_for( $this->subscription ) : '';

		if ( '' !== $reason ) {
			$facts[ __( 'Reason given', 'subkit-subscriptions' ) ] = $reason;
		}

		$detail = (string) $this->subscription->get_meta( '_subkit_cancel_detail' );

		if ( '' !== $detail ) {
			$facts[ __( 'They said', 'subkit-subscriptions' ) ] = $detail;
		}

		$end = $this->subscription->get_end_date() ?: $this->subscription->get_next_payment();

		if ( ! empty( $end ) ) {
			$facts[ __( 'Access ends', 'subkit-subscriptions' ) ] = $this->date( $end );
		}

		return $facts;
	}
}
