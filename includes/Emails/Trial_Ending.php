<?php

namespace EasySubscription\Emails;

use EasySubscription\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Before a free trial turns into the first payment.
 */
class Trial_Ending extends Subscription_Email {

	public function __construct() {
		$this->id             = 'easysubscription_trial_ending';
		$this->title          = __( 'Trial ending', 'easysubscription' );
		$this->description    = __( 'Sent to the customer 3 days before a free trial ends and the first payment is taken.', 'easysubscription' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your free trial ends soon', 'easysubscription' );
	}

	public function get_default_heading(): string {
		return __( 'Your free trial ends soon', 'easysubscription' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: 1: date, 2: amount */
			__( 'Your free trial ends on %1$s, and your first payment of %2$s is due that day.', 'easysubscription' ),
			$this->date( $this->subscription->get_trial_end() ?: $this->subscription->get_next_payment() ),
			$this->amount( $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'easysubscription' )  => '#' . $this->subscription->get_id(),
			__( 'Free trial ends', 'easysubscription' ) => $this->date( $this->subscription->get_trial_end() ),
			__( 'First payment', 'easysubscription' ) => $this->amount( $this->subscription->get_total() ),
			__( 'Paid with', 'easysubscription' )     => (string) $this->subscription->get_payment_method_title(),
		);
	}

	protected function call_to_action(): ?array {
		$url = $this->manage_url();

		return $url ? array(
			'label' => __( 'Manage your subscription', 'easysubscription' ),
			'url'   => $url,
		) : null;
	}

	protected function outro(): string {
		return __( 'To stop before your first payment, cancel before that date.', 'easysubscription' );
	}
}
