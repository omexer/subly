<?php

namespace Subly\Emails;

use Subly\Domain\Subscription;
use Subly\Lifecycle\Cancellation_Survey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Merchant_Subscription_Cancelled extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subly_merchant_subscription_cancelled';
		$this->title          = __( 'Subscription cancelled (merchant)', 'subly' );
		$this->description    = __( 'Sent to the store when a subscription is cancelled, with the reason the customer gave.', 'subly' );
		$this->customer_email = false;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'A subscription was cancelled', 'subly' );
	}

	public function get_default_heading(): string {
		return __( 'Subscription cancelled', 'subly' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: 1: customer, 2: recurring amount */
			__( '%1$s cancelled a subscription worth %2$s.', 'subly' ),
			trim( $this->subscription->get_billing_first_name() . ' ' . $this->subscription->get_billing_last_name() ) ?: $this->subscription->get_billing_email(),
			$this->amount( $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		$facts = array(
			__( 'Subscription', 'subly' ) => '#' . $this->subscription->get_id(),
			__( 'Customer', 'subly' )     => (string) $this->subscription->get_billing_email(),
		);

		// The survey stores a key; only it knows the label, including any a filter added.
		$survey = \Subly\Plugin::instance()->get( 'survey' );
		$reason = $survey instanceof Cancellation_Survey ? $survey->reason_for( $this->subscription ) : '';

		if ( '' !== $reason ) {
			$facts[ __( 'Reason given', 'subly' ) ] = $reason;
		}

		$detail = (string) $this->subscription->get_meta( '_subly_cancel_detail' );

		if ( '' !== $detail ) {
			$facts[ __( 'They said', 'subly' ) ] = $detail;
		}

		$end = $this->subscription->get_end_date() ?: $this->subscription->get_next_payment();

		if ( ! empty( $end ) ) {
			$facts[ __( 'Access ends', 'subly' ) ] = $this->date( $end );
		}

		return $facts;
	}
}
