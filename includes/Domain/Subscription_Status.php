<?php

namespace SubKit\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The subscription lifecycle.
 *
 * Slugs are prefixed `sk-` so they never collide with WooCommerce Subscriptions'
 * statuses if both plugins are ever active on the same store.
 */
enum Subscription_Status: string {

	case Pending       = 'sk-pending';
	case Trialling     = 'sk-trialling';
	case Active        = 'sk-active';
	case OnHold        = 'sk-on-hold';
	case PendingCancel = 'sk-pending-cancel';
	case Cancelled     = 'sk-cancelled';
	case Expired       = 'sk-expired';
	case Switched      = 'sk-switched';

	/**
	 * Human label, translated at call time rather than at file scope.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Pending       => __( 'Pending', 'subkit-subscriptions' ),
			self::Trialling     => __( 'Free trial', 'subkit-subscriptions' ),
			self::Active        => __( 'Active', 'subkit-subscriptions' ),
			self::OnHold        => __( 'On hold', 'subkit-subscriptions' ),
			self::PendingCancel => __( 'Cancelling', 'subkit-subscriptions' ),
			self::Cancelled     => __( 'Cancelled', 'subkit-subscriptions' ),
			self::Expired       => __( 'Ended', 'subkit-subscriptions' ),
			self::Switched      => __( 'Switched', 'subkit-subscriptions' ),
		};
	}

	/**
	 * Only an active subscription may claim a charge slot.
	 */
	public function is_billable(): bool {
		return self::Active === $this;
	}

	/**
	 * Terminal states never transition again.
	 */
	public function is_terminal(): bool {
		return in_array( $this, array( self::Cancelled, self::Expired, self::Switched ), true );
	}

	/**
	 * Allowed transitions. Anything not listed here is a bug, not a no-op.
	 */
	public function can_transition_to( self $to ): bool {
		if ( $this === $to ) {
			return false;
		}
		if ( $this->is_terminal() ) {
			return false;
		}

		return in_array( $to, $this->allowed_next(), true );
	}

	/**
	 * @return self[]
	 */
	public function allowed_next(): array {
		return match ( $this ) {
			self::Pending       => array( self::Trialling, self::Active, self::Cancelled, self::Expired ),
			self::Trialling     => array( self::Active, self::OnHold, self::PendingCancel, self::Cancelled, self::Expired, self::Switched ),
			self::Active        => array( self::OnHold, self::PendingCancel, self::Cancelled, self::Expired, self::Switched ),
			self::OnHold        => array( self::Active, self::PendingCancel, self::Cancelled, self::Expired, self::Switched ),
			self::PendingCancel => array( self::Active, self::Cancelled, self::Expired ),
			default             => array(),
		};
	}

	/**
	 * Status slug without the wc- prefix WooCommerce adds internally.
	 */
	public static function from_order_status( string $status ): ?self {
		return self::tryFrom( 'wc-' === substr( $status, 0, 3 ) ? substr( $status, 3 ) : $status );
	}

	/**
	 * @return array<string, string> slug => label, for registration and admin filters.
	 */
	public static function labels(): array {
		$labels = array();
		foreach ( self::cases() as $case ) {
			$labels[ $case->value ] = $case->label();
		}
		return $labels;
	}
}
