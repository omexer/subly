<?php

namespace SubKit\Reports;

use SubKit\Data\Subscription_Query;
use SubKit\Domain\Money;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription metrics: MRR, ARR, active counts, churn and lifetime value.
 *
 * MRR normalises every billing period to a monthly figure, because a yearly plan at 120
 * and a monthly plan at 10 are the same recurring revenue and reporting them differently
 * makes the number useless.
 */
class Metrics {

	/** Days used as the denominator when normalising a period to a month. */
	private const DAYS_IN_MONTH = 30.4375;

	/**
	 * Monthly recurring revenue across every live subscription.
	 */
	public function mrr(): Money {
		$total = 0;

        $currency = get_woocommerce_currency();

		foreach ( $this->live() as $subscription ) {
			$total += $this->monthly_value( $subscription )->minor();
		}

		return Money::from_minor( $total, $currency );
	}

	public function arr(): Money {
		return $this->mrr()->multiply( 12 );
	}

	/**
	 * One subscription's recurring value expressed per month.
	 */
	public function monthly_value( Subscription $subscription ): Money {
		$amount = Money::from_decimal( $subscription->get_total(), $subscription->get_currency() );
		$days   = $this->period_days( $subscription );

		if ( $days <= 0 ) {
			return Money::from_minor( 0, $subscription->get_currency() );
		}

		return Money::from_minor(
			(int) round( $amount->minor() * ( self::DAYS_IN_MONTH / $days ) ),
			$subscription->get_currency()
		);
	}

	/**
	 * @return array<string, int>
	 */
	public function counts_by_status(): array {
		$counts = array();

		foreach ( Subscription_Status::cases() as $status ) {
			$counts[ $status->value ] = count( Subscription_Query::ids( array( 'limit' => -1, 'status' => $status->value ) ) );
		}

		return $counts;
	}

	public function active_count(): int {
		return count( $this->live() );
	}

	/**
	 * Cancellations in a window as a share of what was live at its start.
	 *
	 * Uses the cohort that existed at the beginning rather than today's total, otherwise
	 * a month of strong growth flatters the churn rate.
	 */
	public function churn_rate( int $days = 30 ): float {
		$since     = time() - ( $days * DAY_IN_SECONDS );
		$cancelled = 0;
		$at_start  = 0;

		foreach ( Subscription_Query::get( array( 'limit' => -1, 'status' => null ) ) as $subscription ) {
			$created = $subscription->get_date_created();

			if ( ! $created || $created->getTimestamp() > $since ) {
				continue;
			}

			$at_start++;

			$status = $subscription->get_status_enum();

			if ( ! in_array( $status, array( Subscription_Status::Cancelled, Subscription_Status::Expired ), true ) ) {
				continue;
			}

			$modified = $subscription->get_date_modified();

			if ( $modified && $modified->getTimestamp() >= $since ) {
				$cancelled++;
			}
		}

		return $at_start > 0 ? round( ( $cancelled / $at_start ) * 100, 2 ) : 0.0;
	}

	/**
	 * Average revenue collected per subscription over its life so far.
	 */
	public function lifetime_value(): Money {
		global $wpdb;

		$currency = get_woocommerce_currency();
		$subs     = Subscription_Query::ids( array( 'limit' => -1, 'status' => null ) );

		if ( empty( $subs ) ) {
			return Money::from_minor( 0, $currency );
		}

		$collected = 0;

		foreach ( $subs as $id ) {
			foreach ( $this->paid_orders_for( (int) $id ) as $order ) {
				$collected += Money::from_decimal( $order->get_total(), $currency )->minor();
			}
		}

		return Money::from_minor( (int) round( $collected / count( $subs ) ), $currency );
	}

	/**
	 * Total revenue actually collected through subscriptions.
	 */
	public function revenue_collected(): Money {
		$currency  = get_woocommerce_currency();
		$collected = 0;

		foreach ( Subscription_Query::ids( array( 'limit' => -1, 'status' => null ) ) as $id ) {
			foreach ( $this->paid_orders_for( (int) $id ) as $order ) {
				$collected += Money::from_decimal( $order->get_total(), $currency )->minor();
			}
		}

		return Money::from_minor( $collected, $currency );
	}

	/**
	 * New subscriptions per day for a sparkline.
	 *
	 * @return array<string, int>
	 */
	public function signups_by_day( int $days = 30 ): array {
		$series = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$series[ gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) ) ] = 0;
		}

		foreach ( Subscription_Query::get( array( 'limit' => -1, 'status' => null ) ) as $subscription ) {
			$created = $subscription->get_date_created();

			if ( ! $created ) {
				continue;
			}

			$key = gmdate( 'Y-m-d', $created->getTimestamp() );

			if ( isset( $series[ $key ] ) ) {
				$series[ $key ]++;
			}
		}

		return $series;
	}

	/**
	 * @return Subscription[]
	 */
	private function live(): array {
		$live = array();

		foreach ( array( Subscription_Status::Active, Subscription_Status::Trialling, Subscription_Status::PendingCancel ) as $status ) {
			foreach ( Subscription_Query::get( array( 'limit' => -1, 'status' => $status->value ) ) as $subscription ) {
				$live[ $subscription->get_id() ] = $subscription;
			}
		}

		return array_values( $live );
	}

	private function period_days( Subscription $subscription ): float {
		$interval = max( 1, $subscription->get_billing_interval() );

		return $interval * match ( $subscription->get_billing_period() ) {
			'day'   => 1,
			'week'  => 7,
			'year'  => 365.25,
			default => self::DAYS_IN_MONTH,
		};
	}

	/**
	 * @return \WC_Order[]
	 */
	private function paid_orders_for( int $subscription_id ): array {
		$orders = wc_get_orders( array(
			'limit'      => -1,
			'status'     => array( 'processing', 'completed' ),
			'meta_key'   => '_subkit_subscription_id',
			'meta_value' => $subscription_id,
		) );

		return array_filter( $orders, static fn( $order ): bool => $order instanceof \WC_Order );
	}
}
