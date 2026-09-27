<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Warns the customer before the card is charged.
 *
 * A charge nobody expected is the most common cause of a chargeback, and of a
 * cancellation that would not otherwise have happened.
 */
class Renewal_Reminder extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_renewal_reminder';
		$this->title          = __( 'Upcoming renewal', 'subkit-subscriptions' );
		$this->description    = __( 'Sent to the customer before a subscription is charged. How far ahead is set under EasySubscription → Settings → Notifications.', 'subkit-subscriptions' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription renews soon', 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription renews soon', 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		$trialling = 'sk-trialling' === $this->subscription->get_status();

		return sprintf(
			$trialling
				/* translators: 1: amount, 2: date */
				? __( 'Your free trial ends on %2$s, and your first payment of %1$s will be taken that day.', 'subkit-subscriptions' )
				/* translators: 1: amount, 2: date */
				: __( "We'll charge %1\$s on %2\$s to renew your subscription.", 'subkit-subscriptions' ),
			$this->amount( $this->subscription->get_total() ),
			$this->date( $this->subscription->get_next_payment() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subkit-subscriptions' ) => '#' . $this->subscription->get_id(),
			__( 'Amount', 'subkit-subscriptions' )       => $this->amount( $this->subscription->get_total() ),
			__( 'Payment date', 'subkit-subscriptions' ) => $this->date( $this->subscription->get_next_payment() ),
			__( 'Paid with', 'subkit-subscriptions' )    => (string) $this->subscription->get_payment_method_title(),
		);
	}

	protected function call_to_action(): ?array {
		$url = $this->manage_url();

		return $url ? array(
			'label' => __( 'Manage your subscription', 'subkit-subscriptions' ),
			'url'   => $url,
		) : null;
	}

	protected function outro(): string {
		return __( 'Nothing to do if you want it to continue. You can change or cancel it any time before that date.', 'subkit-subscriptions' );
	}
}
