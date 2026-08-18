<?php

namespace SubKit\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When a subscription bills, and what the next date is.
 *
 * All arithmetic happens in UTC. A renewal date stored in local time either skips or
 * fires twice across a DST boundary, and the failure is silent.
 */
final class Billing_Schedule {

	public const PERIODS = array( 'day', 'week', 'month', 'year' );

	public function __construct(
		private readonly string $period = 'month',
		private readonly int $interval = 1,
		private readonly int $trial_days = 0,
		private readonly ?int $max_renewals = null,
		private readonly int $sync_day = 0
	) {
		if ( ! in_array( $period, self::PERIODS, true ) ) {
			throw new \InvalidArgumentException( esc_html( 'Unsupported billing period: ' . $period ) );
		}
		if ( $interval < 1 ) {
			throw new \InvalidArgumentException( 'Billing interval must be at least 1.' );
		}
		if ( $trial_days < 0 ) {
			throw new \InvalidArgumentException( 'Trial length cannot be negative.' );
		}
	}

	public static function from_subscription( Subscription $subscription ): self {
		return new self(
			$subscription->get_billing_period() ?: 'month',
			max( 1, $subscription->get_billing_interval() ),
			0,
			null,
			$subscription->get_schedule_sync_day()
		);
	}

	public function period(): string {
		return $this->period;
	}

	public function interval(): int {
		return $this->interval;
	}

	public function trial_days(): int {
		return $this->trial_days;
	}

	public function max_renewals(): ?int {
		return $this->max_renewals;
	}

	public function has_trial(): bool {
		return $this->trial_days > 0;
	}

	public function trial_end_from( \DateTimeImmutable $start ): ?\DateTimeImmutable {
		return $this->has_trial() ? $start->modify( sprintf( '+%d days', $this->trial_days ) ) : null;
	}

	/**
	 * The next billing date after $from.
	 *
	 * $anchor_day is the day-of-month the subscription started on. Without it, a
	 * subscription that starts on the 31st walks backwards: Jan 31 -> Feb 28 -> Mar 28.
	 * Carrying the anchor keeps it Jan 31 -> Feb 28 -> Mar 31.
	 */
	public function next_date_from( \DateTimeImmutable $from, ?int $anchor_day = null ): \DateTimeImmutable {
		$from = $from->setTimezone( new \DateTimeZone( 'UTC' ) );

		return match ( $this->period ) {
			'day', 'week' => $from->modify( sprintf( '+%d %s', $this->interval, $this->period ) ),
			'month'       => $this->add_months( $from, $this->interval, $anchor_day ),
			'year'        => $this->add_months( $from, $this->interval * 12, $anchor_day ),
		};
	}

	/**
	 * Advance whole periods until the date is in the future.
	 *
	 * Used by the catch-up policy: a subscription whose cron was dead for three months
	 * is re-based forward rather than billed three times.
	 */
	public function rebase_forward( \DateTimeImmutable $missed, \DateTimeImmutable $now, ?int $anchor_day = null ): \DateTimeImmutable {
		$next  = $missed;
		$guard = 0;

		while ( $next <= $now && $guard < 1000 ) {
			$next = $this->next_date_from( $next, $anchor_day );
			$guard++;
		}

		return $next;
	}

	/**
	 * Add months while clamping to the end of short months.
	 */
	private function add_months( \DateTimeImmutable $from, int $months, ?int $anchor_day ): \DateTimeImmutable {
		$anchor = $anchor_day ?? (int) $from->format( 'j' );

		$target     = $from->modify( 'first day of this month' )->modify( sprintf( '+%d months', $months ) );
		$days_in    = (int) $target->format( 't' );
		$safe_day   = min( $anchor, $days_in );

		return $target->setDate(
			(int) $target->format( 'Y' ),
			(int) $target->format( 'n' ),
			$safe_day
		)->setTime(
			(int) $from->format( 'G' ),
			(int) $from->format( 'i' ),
			(int) $from->format( 's' )
		);
	}

	/**
	 * Human description used by the recurring disclosure. One whole string per period,
	 * never "every %d %s" — that does not survive translation.
	 */
	public function describe(): string {
		if ( 1 === $this->interval ) {
			return match ( $this->period ) {
				'day'   => __( 'every day', 'subkit-subscriptions' ),
				'week'  => __( 'every week', 'subkit-subscriptions' ),
				'month' => __( 'every month', 'subkit-subscriptions' ),
				'year'  => __( 'every year', 'subkit-subscriptions' ),
			};
		}

		return match ( $this->period ) {
			/* translators: %d: number of days */
			'day'   => sprintf( _n( 'every %d day', 'every %d days', $this->interval, 'subkit-subscriptions' ), $this->interval ),
			/* translators: %d: number of weeks */
			'week'  => sprintf( _n( 'every %d week', 'every %d weeks', $this->interval, 'subkit-subscriptions' ), $this->interval ),
			/* translators: %d: number of months */
			'month' => sprintf( _n( 'every %d month', 'every %d months', $this->interval, 'subkit-subscriptions' ), $this->interval ),
			/* translators: %d: number of years */
			'year'  => sprintf( _n( 'every %d year', 'every %d years', $this->interval, 'subkit-subscriptions' ), $this->interval ),
		};
	}
}
