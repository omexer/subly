<?php

namespace SubKit\Billing;

use SubKit\Data\Activity_Repository;
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

	public const ACTION_RENEWAL  = 'subkit_scheduled_renewal';
	public const ACTION_REMINDER = 'subkit_renewal_reminder';
	public const ACTION_SWEEP   = 'subkit_sweep_overdue';
	public const GROUP          = 'subkit';

	/** Cap per sweep so a site dark for a month does not fire thousands of charges at once. */
	private const SWEEP_BATCH = 50;

	// Due rows already holding a scheduled action are skipped; stop scanning past these.
	private const SWEEP_SCAN = 1000;

	public function __construct( private readonly Activity_Repository $activity ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'ensure_sweeper' ), 30 );
		add_action( self::ACTION_SWEEP, array( $this, 'sweep' ) );
		add_action( self::ACTION_REMINDER, array( $this, 'remind' ) );
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

		$due = strtotime( $next . ' UTC' );

		$this->schedule_at( $subscription->get_id(), $due );
		$this->schedule_reminder( $subscription->get_id(), $due );
	}

	/**
	 * Warn the customer before the card is charged. Skipped when the payment is already
	 * closer than the warning period — a reminder that arrives after the charge is worse
	 * than none at all.
	 */
	private function schedule_reminder( int $subscription_id, int $due ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$days = (int) get_option( 'subkit_renewal_reminder_days', 3 );
		$args = array( 'subscription_id' => $subscription_id );

		as_unschedule_all_actions( self::ACTION_REMINDER, $args, self::GROUP );

		$at = $due - ( $days * DAY_IN_SECONDS );

		if ( $days < 1 || $at <= time() ) {
			return;
		}

		as_schedule_single_action( $at, self::ACTION_REMINDER, $args, self::GROUP );
	}

	/**
	 * @param int|string $subscription_id
	 */
	public function remind( $subscription_id ): void {
		$subscription = wc_get_order( (int) $subscription_id );
		$status       = $subscription instanceof Subscription ? $subscription->get_status_enum() : null;

		if ( ! $subscription instanceof Subscription || ! $status || ! $status->is_billable() ) {
			return;
		}

		$next = $subscription->get_next_payment();

		// The date can have moved since this was queued — a payment taken early, a plan
		// switched — and warning about a charge that is no longer coming is a support call.
		if ( empty( $next ) || strtotime( $next . ' UTC' ) <= time() ) {
			return;
		}

		/**
		 * Fires a few days before a subscription is charged.
		 *
		 * @param Subscription $subscription
		 */
		do_action( 'subkit_renewal_due_soon', $subscription );
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
			as_unschedule_all_actions( self::ACTION_REMINDER, array( 'subscription_id' => $subscription_id ), self::GROUP );
		}
	}

	/**
	 * Find active subscriptions that are past due with nothing queued, and queue them.
	 */
	public function sweep(): void {
		$now    = time();
		$cutoff = gmdate( 'Y-m-d H:i:s', $now );
		$queued = 0;

		for ( $offset = 0; $queued < self::SWEEP_BATCH && $offset < self::SWEEP_SCAN; $offset += self::SWEEP_BATCH ) {
			$due = $this->due_ids( $cutoff, self::SWEEP_BATCH, $offset );

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
				++$queued;
			}

			if ( count( $due ) < self::SWEEP_BATCH ) {
				break;
			}
		}

		if ( $queued > 0 ) {
			do_action( 'subkit_swept_overdue', $queued );
		}
	}

	/**
	 * Active subscriptions whose next payment is at or before the cutoff, soonest first.
	 *
	 * Read from the meta table directly: wc_get_orders() drops meta queries on legacy post
	 * storage and would return every subscription.
	 *
	 * @return int[]
	 */
	private function due_ids( string $cutoff, int $limit, int $offset ): array {
		global $wpdb;

		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		// A trial is billable the day it ends, so it has to be swept up too — otherwise a
		// missed action leaves the conversion charge unqueued for ever.
		$active    = 'wc-' . Subscription_Status::Active->value;
		$trialling = 'wc-' . Subscription_Status::Trialling->value;

		if ( $hpos ) {
			$orders = $wpdb->prefix . 'wc_orders';
			$meta   = $wpdb->prefix . 'wc_orders_meta';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names come from $wpdb, values are prepared.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT o.id FROM {$orders} o INNER JOIN {$meta} m ON m.order_id = o.id AND m.meta_key = %s WHERE o.type = %s AND o.status IN ( %s, %s ) AND m.meta_value <> '' AND m.meta_value <= %s ORDER BY m.meta_value ASC, o.id ASC LIMIT %d OFFSET %d", '_subkit_next_payment', Subscription::TYPE, $active, $trialling, $cutoff, $limit, $offset ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no API filters orders by meta on legacy storage.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.post_status IN ( %s, %s ) AND m.meta_value <> '' AND m.meta_value <= %s ORDER BY m.meta_value ASC, p.ID ASC LIMIT %d OFFSET %d", '_subkit_next_payment', Subscription::TYPE, $active, $trialling, $cutoff, $limit, $offset ) );
		}

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Is the queue actually running? Surfaced in Site Health — "renewals are not running"
	 * is the single most common support case for this class of plugin.
	 */
	public function queue_is_healthy(): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		$overdue = as_get_scheduled_actions(
			array(
				'group'        => self::GROUP,
				'status'       => \ActionScheduler_Store::STATUS_PENDING,
				'date'         => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
				'date_compare' => '<',
				'per_page'     => 1,
				'return'       => 'ids',
			)
		);

		return empty( $overdue );
	}
}
