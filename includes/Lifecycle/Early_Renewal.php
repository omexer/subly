<?php

namespace SubKit\Lifecycle;

use SubKit\Billing\Renewal_Processor;
use SubKit\Domain\Subscription;
use SubKit\Gateways\Gateway_Model;
use SubKit\Gateways\Gateway_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets a customer pay the next period before it is due.
 *
 * The period paid for does not move: paying early settles the charge that was coming,
 * so the schedule is unchanged and nobody gains or loses a day.
 */
final class Early_Renewal {

	public const OPTION = 'subkit_allow_early_renewal';

	public static function is_offered(): bool {
		return 'yes' === get_option( self::OPTION, 'no' );
	}

	/**
	 * Only a gateway SubKit charges itself can be asked to charge today. A gateway that
	 * bills from a plan of its own, such as PayPal, has no way to bring a payment forward.
	 */
	public static function is_available_for( Subscription $subscription ): bool {
		if ( ! self::is_offered() ) {
			return false;
		}

		$status = $subscription->get_status_enum();

		if ( ! $status || ! $status->is_billable() || empty( $subscription->get_next_payment() ) ) {
			return false;
		}

		$registry = \SubKit\Plugin::instance()->get( 'gateways' );

		if ( ! $registry instanceof Gateway_Registry ) {
			return false;
		}

		return Gateway_Model::Tokenized === $registry->for_subscription( $subscription )->model();
	}

	/**
	 * @return array{ok: bool, message: string}
	 */
	public static function charge( Subscription $subscription ): array {
		$processor = \SubKit\Plugin::instance()->get( 'processor' );

		if ( ! $processor instanceof Renewal_Processor || ! self::is_available_for( $subscription ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'This subscription cannot be paid early.', 'subkit-subscriptions' ),
			);
		}

		// The pipeline reports itself through actions rather than a return value, and the
		// charge slot is what actually decides, so listen instead of assuming.
		$outcome = null;
		$id      = $subscription->get_id();

		$watch = static function ( Subscription $charged ) use ( &$outcome, $id ): void {
			if ( $charged->get_id() === $id && null === $outcome ) {
				$outcome = current_action();
			}
		};

		foreach ( array( 'subkit_renewal_succeeded', 'subkit_renewal_failed', 'subkit_renewal_requires_action' ) as $hook ) {
			add_action( $hook, $watch, 1, 1 );
		}

		$processor->process( $id, true );

		foreach ( array( 'subkit_renewal_succeeded', 'subkit_renewal_failed', 'subkit_renewal_requires_action' ) as $hook ) {
			remove_action( $hook, $watch, 1 );
		}

		return self::describe( $outcome );
	}

	/**
	 * @return array{ok: bool, message: string}
	 */
	private static function describe( ?string $outcome ): array {
		return match ( $outcome ) {
			'subkit_renewal_succeeded'       => array(
				'ok'      => true,
				'message' => __( 'Thank you — your next period is paid. Your renewal date has not changed.', 'subkit-subscriptions' ),
			),
			'subkit_renewal_requires_action' => array(
				'ok'      => false,
				'message' => __( 'Your bank needs to confirm that payment. Check your email for the link.', 'subkit-subscriptions' ),
			),
			'subkit_renewal_failed'          => array(
				'ok'      => false,
				'message' => __( 'That payment was declined, so nothing has been charged. Your subscription is unaffected.', 'subkit-subscriptions' ),
			),
			default                          => array(
				'ok'      => false,
				'message' => __( 'We could not take that payment just now. Nothing has been charged.', 'subkit-subscriptions' ),
			),
		};
	}
}
