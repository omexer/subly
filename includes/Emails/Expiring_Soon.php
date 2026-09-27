<?php

namespace EasySubscription\Emails;

use EasySubscription\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Before a subscription with a fixed end date runs out, so the end is not a surprise.
 */
class Expiring_Soon extends Subscription_Email {

	public function __construct() {
		$this->id             = 'easysubscription_expiring_soon';
		$this->title          = __( 'Subscription ending soon', 'easysubscription' );
		$this->description    = __( 'Sent to the customer before a subscription with a fixed end date ends. How far ahead is set under EasySubscription → Settings → Notifications.', 'easysubscription' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription ends soon', 'easysubscription' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription ends soon', 'easysubscription' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: %s: date the subscription ends */
			__( 'Your subscription ends on %s. It will not renew, and you will not be charged again.', 'easysubscription' ),
			$this->date( $this->subscription->get_next_payment() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'easysubscription' ) => '#' . $this->subscription->get_id(),
			__( 'Ends on', 'easysubscription' )      => $this->date( $this->subscription->get_next_payment() ),
		);
	}

	protected function call_to_action(): ?array {
		$url = $this->manage_url();

		return $url ? array(
			'label' => __( 'View your subscription', 'easysubscription' ),
			'url'   => $url,
		) : null;
	}

	protected function outro(): string {
		return __( 'Want to carry on after that? You can start a new subscription at any time.', 'easysubscription' );
	}
}
