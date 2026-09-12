<?php

namespace SubKit\Lifecycle;

use SubKit\Billing\Renewal_Processor;
use SubKit\Billing\Renewal_Scheduler;
use SubKit\Data\Activity_Repository;
use SubKit\Domain\Billing_Schedule;
use SubKit\Domain\Money;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Things a customer or merchant can do to a live subscription: pause it, resume it,
 * renew early, or switch plan.
 *
 * Each one moves the schedule, so each one has to respect the charge-slot rules. Pausing
 * consumes no slot; an early renewal consumes the next one immediately, which is what
 * makes the already-queued scheduled run harmlessly abort.
 */
class Subscription_Actions {

	private const META_PAUSED_AT    = '_subkit_paused_at';
	private const META_PAUSED_LEFT  = '_subkit_paused_remaining';
	private const META_SWITCHED_TO  = '_subkit_switched_to';
	private const META_SWITCHED_FROM = '_subkit_switched_from';

	public function __construct(
		private readonly Activity_Repository $activity,
		private readonly Renewal_Scheduler $scheduler,
		private readonly Renewal_Processor $processor
	) {}

	// ------------------------------------------------------------------ pause / resume

	public function can_pause( Subscription $subscription ): bool {
		$status = $subscription->get_status_enum();

		return Subscription_Status::Active === $status || Subscription_Status::Trialling === $status;
	}

	/**
	 * Pause without losing the customer's place.
	 *
	 * Time already paid for is banked and handed back on resume, so a pause is never a
	 * refund and never a free extension.
	 */
	public function pause( Subscription $subscription, string $actor = 'customer' ): bool {
		if ( ! $this->can_pause( $subscription ) ) {
			return false;
		}

		$next = $subscription->get_next_payment();

		if ( $next ) {
			$remaining = max( 0, strtotime( $next . ' UTC' ) - time() );
			$subscription->update_meta_data( self::META_PAUSED_LEFT, $remaining );
		}

		$subscription->update_meta_data( self::META_PAUSED_AT, gmdate( 'Y-m-d H:i:s' ) );
		$subscription->transition_to( Subscription_Status::OnHold, __( 'Paused.', 'subkit-subscriptions' ) );
		$subscription->save();

		$this->scheduler->unschedule( $subscription->get_id() );
		$this->activity->log( $subscription->get_id(), Activity_Repository::TYPE_SCHEDULE_CHANGE, 'Subscription paused.', array(), $actor );

		do_action( 'subkit_subscription_paused', $subscription );

		return true;
	}

	public function is_paused( Subscription $subscription ): bool {
		return '' !== (string) $subscription->get_meta( self::META_PAUSED_AT )
			&& Subscription_Status::OnHold === $subscription->get_status_enum();
	}

	public function resume( Subscription $subscription, string $actor = 'customer' ): bool {
		if ( ! $this->is_paused( $subscription ) ) {
			return false;
		}

		$banked = (int) $subscription->get_meta( self::META_PAUSED_LEFT );
		$next   = $banked > 0
			? gmdate( 'Y-m-d H:i:s', time() + $banked )
			: Billing_Schedule::from_subscription( $subscription )
				->next_date_from( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )
				->format( 'Y-m-d H:i:s' );

		$subscription->set_next_payment( $next );
		$subscription->delete_meta_data( self::META_PAUSED_AT );
		$subscription->delete_meta_data( self::META_PAUSED_LEFT );
		$subscription->transition_to( Subscription_Status::Active, __( 'Resumed.', 'subkit-subscriptions' ) );
		$subscription->save();

		$this->scheduler->schedule_next( $subscription );
		$this->activity->log( $subscription->get_id(), Activity_Repository::TYPE_SCHEDULE_CHANGE, sprintf( 'Resumed; next payment %s.', $next ), array(), $actor );

		do_action( 'subkit_subscription_resumed', $subscription );

		return true;
	}

	// ------------------------------------------------------------------ early renewal

	public function can_renew_early( Subscription $subscription ): bool {
		return Subscription_Status::Active === $subscription->get_status_enum();
	}

	/**
	 * Bill the next period now.
	 *
	 * Runs the ordinary pipeline rather than a shortcut, so the slot is claimed the same
	 * way. The scheduled run for that period then loses the unique insert and aborts,
	 * which is exactly what should happen.
	 */
	public function renew_early( Subscription $subscription, string $actor = 'customer' ): bool {
		if ( ! $this->can_renew_early( $subscription ) ) {
			return false;
		}

		$subscription->set_next_payment( gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) );
		$subscription->save();

		$this->activity->log( $subscription->get_id(), Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Early renewal requested.', array(), $actor );

		$this->processor->process( $subscription->get_id() );

		return true;
	}

	// ------------------------------------------------------------------ switching

	/**
	 * Unused value left on the current period, for crediting against a switch.
	 */
	public function unused_credit( Subscription $subscription ): Money {
		$next = $subscription->get_next_payment();

		if ( ! $next ) {
			return Money::from_minor( 0, $subscription->get_currency() );
		}

		$schedule = Billing_Schedule::from_subscription( $subscription );
		$end      = strtotime( $next . ' UTC' );
		$start    = $schedule->next_date_from( ( new \DateTimeImmutable( '@' . $end ) )->modify( '-1 second' ) )->getTimestamp();
		$period   = max( 1, $end - ( $start - ( $end - $start ) ) );

		$remaining = max( 0, $end - time() );
		$paid      = Money::from_decimal( $subscription->get_total(), $subscription->get_currency() );
		$length    = max( 1, $end - strtotime( $this->period_start( $subscription ) . ' UTC' ) );

		$ratio = min( 1.0, $remaining / $length );

		return Money::from_minor( (int) round( $paid->minor() * $ratio ), $subscription->get_currency() );
	}

	/**
	 * Move to a different product.
	 *
	 * Terminates the old subscription in sk-switched and starts a new one, rather than
	 * mutating in place. Charge-slot indices are per-subscription, so a fresh record keeps
	 * the old billing history intact and the new plan's indices unambiguous.
	 *
	 * @return Subscription|null The new subscription, or null if the switch was refused.
	 */
	public function switch_to( Subscription $subscription, \WC_Product $product, string $actor = 'customer' ): ?Subscription {
		$status = $subscription->get_status_enum();

		if ( ! $status || $status->is_terminal() ) {
			return null;
		}

		if ( ! \SubKit\Product\Subscription_Product::is_subscription( $product ) ) {
			return null;
		}

		$credit   = $this->unused_credit( $subscription );
		$schedule = \SubKit\Product\Subscription_Product::schedule( $product );
		$now      = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		$new = new Subscription();
		$new->set_customer_id( $subscription->get_customer_id() );
		$new->set_currency( $subscription->get_currency() );
		$new->set_payment_method( $subscription->get_payment_method() );
		$new->set_payment_method_title( $subscription->get_payment_method_title() );
		$new->set_address( $subscription->get_address( 'billing' ), 'billing' );
		$new->set_address( $subscription->get_address( 'shipping' ), 'shipping' );
		$new->set_billing_period( $schedule->period() );
		$new->set_billing_interval( $schedule->interval() );
		$new->set_parent_order_id( $subscription->get_parent_order_id() );
		$new->update_meta_data( '_subkit_site_url', get_option( 'siteurl' ) );
		$new->update_meta_data( self::META_SWITCHED_FROM, $subscription->get_id() );

		// Carry the mandate across so the new plan can bill without re-authorisation.
		foreach ( $this->mandate_meta( $subscription ) as $key => $value ) {
			$new->update_meta_data( $key, $value );
		}

		$new->add_product( $product, 1 );

		// Credit unused time by discounting the first period of the new plan.
		if ( ! $credit->is_zero() ) {
			$fee = new \WC_Order_Item_Fee();
			$fee->set_name( __( 'Credit for unused time', 'subkit-subscriptions' ) );
			$fee->set_total( '-' . $credit->decimal() );
			$new->add_item( $fee );
		}

		$new->set_next_payment( $schedule->next_date_from( $now )->format( 'Y-m-d H:i:s' ) );
		$new->transition_to( Subscription_Status::Active );
		$new->calculate_totals( false );
		$new->save();

		$subscription->update_meta_data( self::META_SWITCHED_TO, $new->get_id() );
		$subscription->transition_to( Subscription_Status::Switched, __( 'Switched to a different plan.', 'subkit-subscriptions' ) );
		$subscription->save();

		$this->scheduler->unschedule( $subscription->get_id() );
		$this->scheduler->schedule_next( $new );

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_STATUS_CHANGE,
			sprintf( 'Switched to subscription #%d with %s credited.', $new->get_id(), wp_strip_all_tags( $credit->format() ) ),
			array(),
			$actor
		);

		$this->activity->log( $new->get_id(), Activity_Repository::TYPE_STATUS_CHANGE, sprintf( 'Created by switching from subscription #%d.', $subscription->get_id() ), array(), $actor );

		do_action( 'subkit_subscription_switched', $subscription, $new, $credit );

		return $new;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mandate_meta( Subscription $subscription ): array {
		$keys = apply_filters( 'subkit_mandate_meta_keys', array(
			\SubKit\Gateways\PayPal\PayPal_Gateway::META_SUBSCRIPTION_ID,
			\SubKit\Gateways\Stripe\Stripe_Gateway::META_CUSTOMER,
			\SubKit\Gateways\Stripe\Stripe_Gateway::META_METHOD,
		) );

		$carried = array();

		foreach ( $keys as $key ) {
			$value = $subscription->get_meta( $key );

			if ( '' !== $value && null !== $value ) {
				$carried[ $key ] = $value;
			}
		}

		return $carried;
	}

	private function period_start( Subscription $subscription ): string {
		$next = $subscription->get_next_payment();

		if ( ! $next ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		$schedule = Billing_Schedule::from_subscription( $subscription );
		$end      = new \DateTimeImmutable( $next, new \DateTimeZone( 'UTC' ) );

		// One period back from the next payment is where the current one began.
		return match ( $schedule->period() ) {
			'day'   => $end->modify( sprintf( '-%d days', $schedule->interval() ) )->format( 'Y-m-d H:i:s' ),
			'week'  => $end->modify( sprintf( '-%d weeks', $schedule->interval() ) )->format( 'Y-m-d H:i:s' ),
			'year'  => $end->modify( sprintf( '-%d years', $schedule->interval() ) )->format( 'Y-m-d H:i:s' ),
			default => $end->modify( sprintf( '-%d months', $schedule->interval() ) )->format( 'Y-m-d H:i:s' ),
		};
	}
}
