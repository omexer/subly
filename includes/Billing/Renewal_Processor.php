<?php

namespace SubKit\Billing;

use SubKit\Data\Activity_Repository;
use SubKit\Data\Charge_Slot_Repository;
use SubKit\Billing\Installment_Plan;
use SubKit\Domain\Billing_Schedule;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Gateways\Gateway_Model;
use SubKit\Gateways\Gateway_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The renewal pipeline. Everything else in SubKit is UI around this.
 *
 * Developer Guide 7. The ordering is not stylistic: nothing contacts a gateway before
 * the charge slot is claimed, and the lock is always released in a finally.
 */
class Renewal_Processor {

	public function __construct(
		private readonly Charge_Slot_Repository $slots,
		private readonly Activity_Repository $activity,
		private readonly Gateway_Registry $gateways,
		private readonly Renewal_Order_Factory $orders,
		private readonly Renewal_Scheduler $scheduler,
		private readonly Lock $lock
	) {}

	public function register(): void {
		add_action( Renewal_Scheduler::ACTION_RENEWAL, array( $this, 'process' ), 10, 1 );
	}

	/**
	 * @param int $subscription_id
	 */
	public function process( $subscription_id ): void {
		$subscription_id = (int) $subscription_id;

		if ( ! $this->lock->acquire( $subscription_id ) ) {
			return;
		}

		try {
			$this->run( $subscription_id );
		} catch ( \Throwable $e ) {
			$this->activity->log( $subscription_id, Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Renewal aborted: ' . $e->getMessage() );
		} finally {
			// A lock left set silently stops this subscription billing forever.
			$this->lock->release( $subscription_id );
		}
	}

	private function run( int $subscription_id ): void {
		$subscription = wc_get_order( $subscription_id );

		if ( ! $subscription instanceof Subscription ) {
			return;
		}

		$status = $subscription->get_status_enum();
		if ( ! $status || ! $status->is_billable() ) {
			return;
		}

		// An instalment plan is finished when the last payment lands, not when a date passes.
		if ( Installment_Plan::is_complete( $subscription ) ) {
			$this->complete_installments( $subscription );
			return;
		}

		// Staging clones must never charge a real customer.
		if ( ! $this->is_billing_site( $subscription ) ) {
			$this->activity->log( $subscription_id, Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Renewal halted: this site is not the one the subscription was created on.' );
			return;
		}

		$schedule = Billing_Schedule::from_subscription( $subscription );
		$gateway  = $this->gateways->for_subscription( $subscription );

		// 2a. Resume anything left unsettled before considering a new period. A slot stuck
		// in `charging` is an UNKNOWN outcome, not a failure, and must be reconciled with
		// the gateway before we ever charge again.
		$open = $this->slots->latest_unsettled( $subscription_id );
		if ( $open ) {
			$this->resume( $subscription, $gateway, $open, $schedule );
			return;
		}

		// 2b. Only bill a period that is actually due.
		$due_at = $this->as_date( $subscription->get_next_payment() );
		if ( $due_at && $due_at > new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) ) {
			return;
		}

		$covers_from = $due_at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$covers_to   = $schedule->next_date_from( $covers_from );

		// 2c. Claim the slot. Losing this insert means another worker already has it.
		$slot = $this->slots->claim_next( $subscription_id, $covers_from, $covers_from, $covers_to );
		if ( ! $slot ) {
			$this->activity->log( $subscription_id, Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Renewal skipped: this billing period was already claimed.' );
			return;
		}

		$order = $this->orders->create( $subscription, $slot );
		$this->slots->attach_order( (int) $slot->id, $order->get_id() );

		$this->charge( $subscription, $gateway, $order, $slot, $schedule );
	}

	/**
	 * Settle a slot left behind by an unknown outcome or a decline.
	 */
	private function resume( Subscription $subscription, $gateway, object $slot, Billing_Schedule $schedule ): void {
		$subscription_id = $subscription->get_id();
		$order           = $slot->renewal_order_id ? wc_get_order( (int) $slot->renewal_order_id ) : null;

		if ( ! $order ) {
			$order = $this->orders->create( $subscription, $slot );
			$this->slots->attach_order( (int) $slot->id, $order->get_id() );
		}

		if ( Charge_Slot_Repository::STATE_CHARGING === $slot->state ) {
			$verdict = $gateway->reconcile( $subscription, $this->slots->idempotency_key( $slot ) );

			if ( $verdict ) {
				$this->activity->log( $subscription_id, Activity_Repository::TYPE_CHARGE_ATTEMPT, 'Reconciled unknown charge: ' . $verdict->describe() );
				$this->apply( $subscription, $order, $slot, $verdict, $schedule );
				return;
			}

			// No record at the gateway, so the charge never landed. Same slot, same key.
			$this->activity->log( $subscription_id, Activity_Repository::TYPE_CHARGE_ATTEMPT, sprintf( 'Gateway has no record of period %d; retrying the same slot.', $slot->period_index ) );
		}

		if ( Charge_Slot_Repository::STATE_FAILED === $slot->state ) {
			$resumed = $this->slots->begin_retry( $subscription_id, (int) $slot->period_index );
			$slot    = $resumed ?? $slot;
		}

		$this->charge( $subscription, $gateway, $order, $slot, $schedule );
	}

	private function charge( Subscription $subscription, $gateway, \WC_Order $order, object $slot, Billing_Schedule $schedule ): void {
		// Gateway-managed plans bill themselves; we wait for the webhook.
		if ( Gateway_Model::GatewayManaged === $gateway->model() ) {
			$this->activity->log( $subscription->get_id(), Activity_Repository::TYPE_CHARGE_ATTEMPT, sprintf( 'Awaiting %s webhook for period %d.', $gateway->id(), $slot->period_index ) );
			return;
		}

		$this->slots->mark_charging( (int) $slot->id );
		$key = $this->slots->idempotency_key( $this->slots->find( $subscription->get_id(), (int) $slot->period_index ) ?? $slot );

		do_action( 'subkit_before_renewal_charge', $subscription, $order );

		$result = $gateway->charge_renewal( $subscription, $order, $key );

		$this->apply( $subscription, $order, $slot, $result, $schedule );
	}

	private function apply( Subscription $subscription, \WC_Order $order, object $slot, $result, Billing_Schedule $schedule ): void {
		$subscription_id = $subscription->get_id();

		$this->activity->log(
			$subscription_id,
			Activity_Repository::TYPE_CHARGE_ATTEMPT,
			sprintf( 'Charge attempt for period %d: %s', $slot->period_index, $result->describe() ),
			array( 'reference' => $result->reference, 'code' => $result->code, 'period_index' => (int) $slot->period_index )
		);

		if ( $result->is_success() ) {
			$order->payment_complete( (string) $result->reference );
			$this->slots->mark_paid( (int) $slot->id, $order->get_id() );
			$this->advance( $subscription, $schedule, $slot );

			do_action( 'subkit_renewal_succeeded', $subscription, $order );
			return;
		}

		if ( $result->needs_customer_action() ) {
			// Not a failure. No dunning, no attempt_group bump — the customer just has to confirm.
			$order->update_status( 'on-hold', __( 'Awaiting customer authentication.', 'subkit-subscriptions' ) );
			$order->update_meta_data( '_subkit_action_url', $result->action_url );
			$order->save();
			$this->hold( $subscription, __( 'Waiting for the customer to confirm payment.', 'subkit-subscriptions' ) );

			do_action( 'subkit_renewal_requires_action', $subscription, $order, $result );
			return;
		}

		if ( $result->is_transient() ) {
			// Outcome unknown. Leave the slot charging, keep the key, and try again later.
			$this->scheduler->schedule_retry( $subscription_id, (int) $slot->period_index );
			return;
		}

		// Definitive decline: bump attempt_group so the next attempt uses a fresh key.
		$this->slots->mark_failed( (int) $slot->id );
		$order->update_status( 'failed', $result->describe() );
		$this->hold( $subscription, $result->describe() );

		do_action( 'subkit_renewal_failed', $subscription, $order, $result );
	}

	/**
	 * The customer has paid in full, so the subscription ends rather than renewing.
	 */
	private function complete_installments( Subscription $subscription ): void {
		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_STATUS_CHANGE,
			sprintf( 'Instalment plan paid in full after %d payments.', Installment_Plan::count_on( $subscription ) )
		);

		$subscription->set_next_payment( null );
		$subscription->transition_to( Subscription_Status::Expired, __( 'Instalment plan paid in full.', 'subkit-subscriptions' ) );
		$subscription->save();

		$this->scheduler->unschedule( $subscription->get_id() );

		do_action( 'subkit_installments_completed', $subscription );
	}

	private function advance( Subscription $subscription, Billing_Schedule $schedule, object $slot ): void {
		$next = $schedule->next_date_from( new \DateTimeImmutable( $slot->covers_to_gmt, new \DateTimeZone( 'UTC' ) ) );

		$subscription->set_next_payment( $next->format( 'Y-m-d H:i:s' ) );
		$subscription->set_period_index( (int) $slot->period_index );

		if ( Subscription_Status::Active !== $subscription->get_status_enum() ) {
			$subscription->transition_to( Subscription_Status::Active );
		}

		$subscription->save();

		$this->scheduler->schedule_next( $subscription );
	}

	private function hold( Subscription $subscription, string $reason ): void {
		$current = $subscription->get_status_enum();

		if ( $current && $current->can_transition_to( Subscription_Status::OnHold ) ) {
			$subscription->transition_to( Subscription_Status::OnHold, $reason );
			$subscription->save();
		}
	}

	/**
	 * A cloned staging site inherits every subscription and will happily bill real people.
	 */
	private function is_billing_site( Subscription $subscription ): bool {
		$origin = (string) $subscription->get_meta( '_subkit_site_url' );

		return '' === $origin || $origin === get_option( 'siteurl' );
	}

	private function as_date( $value ): ?\DateTimeImmutable {
		if ( empty( $value ) ) {
			return null;
		}

		try {
			return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
