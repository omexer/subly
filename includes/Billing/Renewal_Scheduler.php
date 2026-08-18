<?php

namespace SubKit\Billing;

use SubKit\Data\Activity_Repository;
use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues renewal work through Action Scheduler, and sweeps up whatever WP-Cron missed.
 *
 * Actions carry only the subscription ID. The period index is allocated at claim time,
 * so an early renewal that consumed the next slot simply causes the queued action to
 * find nothing to do rather than billing a stale period.
 */
class Renewal_Scheduler {

	public const ACTION_RENEWAL = 'subkit_scheduled_renewal';
	public const ACTION_SWEEP   = 'subkit_sweep_overdue';
	public const GROUP          = 'subkit';

	/** Cap per sweep so a site dark for a month does not fire thousands of charges at once. */
	private const SWEEP_BATCH = 50;

	public function __construct( private readonly Activity_Repository $activity ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'ensure_sweeper' ), 30 );
		add_action( self::ACTION_SWEEP, array( $this, 'sweep' ) );
	}

	/**
	 * WP-Cron does not fire on a site with no visitors, and a store can go a week without
	 * one. The sweeper is what stops that being silent revenue loss.
	 */
	public function ensure_sweeper(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::ACTION_SWEEP, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, self::ACTION_SWEEP, array(), self::GROUP );
		}
	}

	public function schedule_next( Subscription $subscription ): void {
		$next = $subscription->get_next_payment();
		if ( empty( $next ) ) {
			return;
		}

		$this->schedule_at( $subscription->get_id(), strtotime( $next . ' UTC' ) );
	}

	/**
	 * Re-enqueue after an unknown outcome, with backoff. Capped so a permanently broken
	 * gateway cannot loop forever.
	 */
	public function schedule_retry( int $subscription_id, int $period_index, int $attempt = 1 ): void {
		if ( $attempt > 5 ) {
			$this->activity->log( $subscription_id, Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Gave up re-trying an unknown gateway outcome; needs reconciliation.' );
			return;
		}

		$this->schedule_at( $subscription_id, time() + ( ( 2 ** $attempt ) * MINUTE_IN_SECONDS ) );
	}

	public function schedule_at( int $subscription_id, int $timestamp ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$args = array( 'subscription_id' => $subscription_id );

		if ( as_next_scheduled_action( self::ACTION_RENEWAL, $args, self::GROUP ) ) {
			return;
		}

		as_schedule_single_action( max( $timestamp, time() ), self::ACTION_RENEWAL, $args, self::GROUP );
	}

	public function unschedule( int $subscription_id ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_RENEWAL, array( 'subscription_id' => $subscription_id ), self::GROUP );
		}
	}

	/**
	 * Find active subscriptions that are past due with nothing queued, and queue them.
	 */
	public function sweep(): void {
		$due = Subscription_Query::ids( array(
			'status'  => Subscription_Status::Active->value,
			'limit'   => self::SWEEP_BATCH,
			'orderby' => 'date',
			'order'   => 'ASC',
		) );

		$now     = time();
		$queued  = 0;

		foreach ( $due as $subscription_id ) {
			$subscription = wc_get_order( $subscription_id );
			if ( ! $subscription instanceof Subscription ) {
				continue;
			}

			$next = $subscription->get_next_payment();
			if ( empty( $next ) || strtotime( $next . ' UTC' ) > $now ) {
				continue;
			}

			if ( as_next_scheduled_action( self::ACTION_RENEWAL, array( 'subscription_id' => $subscription_id ), self::GROUP ) ) {
				continue;
			}

			$this->schedule_at( $subscription_id, $now );
			$queued++;
		}

		if ( $queued > 0 ) {
			do_action( 'subkit_swept_overdue', $queued );
		}
	}

	/**
	 * Is the queue actually running? Surfaced in Site Health — "renewals are not running"
	 * is the single most common support case for this class of plugin.
	 */
	public function queue_is_healthy(): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		$overdue = as_get_scheduled_actions( array(
			'group'        => self::GROUP,
			'status'       => \ActionScheduler_Store::STATUS_PENDING,
			'date'         => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			'date_compare' => '<',
			'per_page'     => 1,
			'return'       => 'ids',
		) );

		return empty( $overdue );
	}
}
