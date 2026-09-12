<?php

namespace SubKit\Lifecycle;

use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives a subscriber a WordPress role while their subscription is live.
 *
 * Roles are how most membership setups gate content, so this is the join between billing
 * and access for everything that is not a WooCommerce download.
 */
class Role_Management {

	private const OPTION_ACTIVE   = 'subkit_active_role';
	private const OPTION_INACTIVE = 'subkit_inactive_role';

	public function register(): void {
		add_action( 'subkit_subscription_status_changed', array( $this, 'on_status_change' ), 10, 3 );
	}

	/**
	 * @param string $from
	 * @param string $to
	 */
	public function on_status_change( Subscription $subscription, $from, $to ): void {
		$user = $this->user_for( $subscription );

		if ( ! $user ) {
			return;
		}

		$status = Subscription_Status::tryFrom( (string) $to );

		if ( ! $status ) {
			return;
		}

		if ( $status->is_billable() ) {
			$this->apply( $user, (string) get_option( self::OPTION_ACTIVE, '' ) );
			return;
		}

		// Only demote once nothing else is keeping them in: a customer with two
		// subscriptions must not lose access because one of them lapsed.
		if ( ! $this->has_another_live_subscription( $user->ID, $subscription->get_id() ) ) {
			$this->apply( $user, (string) get_option( self::OPTION_INACTIVE, '' ) );
		}
	}

	private function apply( \WP_User $user, string $role ): void {
		// An administrator who buys a subscription must not be demoted out of their own
		// site. Nothing here is worth that failure mode.
		if ( '' === $role || ! get_role( $role ) || user_can( $user, 'manage_options' ) ) {
			return;
		}

		if ( in_array( $role, (array) $user->roles, true ) ) {
			return;
		}

		$user->set_role( $role );
	}

	private function has_another_live_subscription( int $user_id, int $exclude_id ): bool {
		$ids = Subscription_Query::ids(
			array(
				'customer_id' => $user_id,
				'status'      => array( Subscription_Status::Active->value, Subscription_Status::Trialling->value, Subscription_Status::PendingCancel->value ),
				'limit'       => 5,
			)
		);

		return (bool) array_diff( $ids, array( $exclude_id ) );
	}

	private function user_for( Subscription $subscription ): ?\WP_User {
		$user = $subscription->get_customer_id() ? get_userdata( $subscription->get_customer_id() ) : false;

		return $user instanceof \WP_User ? $user : null;
	}
}
