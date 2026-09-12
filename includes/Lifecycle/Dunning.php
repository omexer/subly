<?php

namespace SubKit\Lifecycle;

use SubKit\Billing\Renewal_Scheduler;
use SubKit\Data\Activity_Repository;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Gateways\Charge_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What happens between a failed renewal and giving up.
 *
 * Only soft declines enter the ladder. A permanent decline is not worth retrying, and a
 * 3DS challenge is not a failure at all — retrying either just annoys the customer while
 * the real problem goes unaddressed.
 */
class Dunning {

	public const ACTION_RETRY = 'subkit_dunning_retry';

	private const META_ATTEMPT = '_subkit_dunning_attempt';
	private const META_STARTED = '_subkit_dunning_started';

	public function __construct(
		private readonly Activity_Repository $activity,
		private readonly Renewal_Scheduler $scheduler
	) {}

	public function register(): void {
		add_action( 'subkit_renewal_failed', array( $this, 'on_failure' ), 10, 3 );
		add_action( 'subkit_renewal_succeeded', array( $this, 'on_success' ), 10, 2 );
		add_action( self::ACTION_RETRY, array( $this, 'retry' ), 10, 1 );
	}

	/**
	 * Hours after the failure for each attempt. Widening on purpose: banks commonly clear
	 * a soft decline within a few days, and hammering the card does not help.
	 *
	 * @return int[]
	 */
	public function schedule_hours(): array {
		return (array) apply_filters( 'subkit_dunning_schedule', array( 24, 72, 120 ) );
	}

	public function grace_days(): int {
		return (int) apply_filters( 'subkit_grace_period_days', (int) get_option( 'subkit_grace_period_days', 7 ) );
	}

	public function on_failure( Subscription $subscription, \WC_Order $order, Charge_Result $result ): void {
		if ( ! $result->should_enter_dunning() ) {
			$this->activity->log(
				$subscription->get_id(),
				Activity_Repository::TYPE_CHARGE_ATTEMPT,
				sprintf( 'No retry scheduled: %s is not a recoverable decline.', $result->describe() )
			);
			return;
		}

		$attempt = (int) $subscription->get_meta( self::META_ATTEMPT );
		$steps   = $this->schedule_hours();

		if ( ! $subscription->get_meta( self::META_STARTED ) ) {
			$subscription->update_meta_data( self::META_STARTED, gmdate( 'Y-m-d H:i:s' ) );
		}

		if ( $attempt >= count( $steps ) ) {
			$this->give_up( $subscription );
			return;
		}

		$delay = $steps[ $attempt ] * HOUR_IN_SECONDS;

		$subscription->update_meta_data( self::META_ATTEMPT, $attempt + 1 );
		$subscription->save();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + $delay,
				self::ACTION_RETRY,
				array( 'subscription_id' => $subscription->get_id() ),
				Renewal_Scheduler::GROUP
			);
		}

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_CHARGE_ATTEMPT,
			sprintf( 'Retry %d of %d scheduled in %d hours.', $attempt + 1, count( $steps ), $steps[ $attempt ] )
		);
	}

	/**
	 * @param int $subscription_id
	 */
	public function retry( $subscription_id ): void {
		$subscription = wc_get_order( (int) $subscription_id );

		if ( ! $subscription instanceof Subscription ) {
			return;
		}

		if ( Subscription_Status::OnHold !== $subscription->get_status_enum() ) {
			return;
		}

		if ( $this->grace_expired( $subscription ) ) {
			$this->give_up( $subscription );
			return;
		}

		// Put it back in a billable state so the pipeline will resume the open slot.
		$subscription->transition_to( Subscription_Status::Active, __( 'Retrying the failed payment.', 'subkit-subscriptions' ) );
		$subscription->save();

		$this->scheduler->schedule_at( $subscription->get_id(), time() );
	}

	public function on_success( Subscription $subscription, \WC_Order $order ): void {
		if ( ! $subscription->get_meta( self::META_ATTEMPT ) && ! $subscription->get_meta( self::META_STARTED ) ) {
			return;
		}

		$subscription->delete_meta_data( self::META_ATTEMPT );
		$subscription->delete_meta_data( self::META_STARTED );
		$subscription->save();

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_RETRY, array( 'subscription_id' => $subscription->get_id() ), Renewal_Scheduler::GROUP );
		}

		$this->activity->log( $subscription->get_id(), Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Payment recovered; retry sequence cleared.' );
	}

	/**
	 * Grace runs from the first failure, so a merchant lengthening the ladder cannot
	 * accidentally keep a customer in limbo indefinitely.
	 */
	public function grace_expired( Subscription $subscription ): bool {
		$started = (string) $subscription->get_meta( self::META_STARTED );

		if ( '' === $started ) {
			return false;
		}

		return ( time() - strtotime( $started . ' UTC' ) ) > ( $this->grace_days() * DAY_IN_SECONDS );
	}

	private function give_up( Subscription $subscription ): void {
		$status = $subscription->get_status_enum();

		if ( $status && $status->can_transition_to( Subscription_Status::Cancelled ) ) {
			$subscription->transition_to( Subscription_Status::Cancelled, __( 'Payment could not be recovered.', 'subkit-subscriptions' ) );
			$subscription->save();
		}

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_STATUS_CHANGE,
			'Recovery gave up: retries exhausted or the grace period ended.'
		);

		do_action( 'subkit_dunning_exhausted', $subscription );
	}
}
