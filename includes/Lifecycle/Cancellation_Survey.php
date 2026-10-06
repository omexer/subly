<?php

namespace Subly\Lifecycle;

use Subly\Data\Activity_Repository;
use Subly\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks why, after the cancellation has already happened.
 *
 * Sequencing is the whole design: the subscription is cancelled first and the question
 * appears on the confirmation screen. A survey that runs before the cancel is a barrier,
 * and making it one is structurally impossible here. UX Spec 8.1.
 */
class Cancellation_Survey {

	private const META_REASON = '_subly_cancel_reason';
	private const META_DETAIL = '_subly_cancel_detail';

	public function __construct( private readonly Activity_Repository $activity ) {}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_submission' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public function reasons(): array {
		return (array) apply_filters(
			'subly_cancellation_reasons',
			array(
				'too_expensive' => __( 'Too expensive', 'subly' ),
				'not_using'     => __( "I wasn't using it", 'subly' ),
				'found_better'  => __( 'I found something better', 'subly' ),
				'quality'       => __( "It wasn't what I expected", 'subly' ),
				'temporary'     => __( 'Only pausing for now', 'subly' ),
				'other'         => __( 'Something else', 'subly' ),
			)
		);
	}

	public function handle_submission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['subly_action'] ) ? sanitize_key( wp_unslash( $_POST['subly_action'] ) ) : '';

		if ( 'cancel_survey' !== $action ) {
			return;
		}

		$id = absint( $_POST['subly_subscription'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'subly_survey_' . $id ) ) {
			return;
		}

		$subscription = wc_get_order( $id );

		if ( ! $subscription instanceof Subscription || ! $this->owns( $subscription ) ) {
			return;
		}

		$reason = sanitize_key( wp_unslash( $_POST['subly_reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$detail = sanitize_textarea_field( wp_unslash( $_POST['subly_detail'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! array_key_exists( $reason, $this->reasons() ) ) {
			return;
		}

		$subscription->update_meta_data( self::META_REASON, $reason );

		if ( '' !== $detail ) {
			$subscription->update_meta_data( self::META_DETAIL, $detail );
		}

		$subscription->save();

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_NOTE,
			sprintf( 'Cancellation reason: %s', $this->reasons()[ $reason ] ),
			array( 'reason' => $reason )
		);

		do_action( 'subly_cancellation_reason_recorded', $subscription, $reason, $detail );

		wp_safe_redirect( add_query_arg( 'subly_thanks', '1', wc_get_account_endpoint_url( 'subscriptions' ) ) );
		exit;
	}

	public function reason_for( Subscription $subscription ): string {
		$reason = (string) $subscription->get_meta( self::META_REASON );

		return $this->reasons()[ $reason ] ?? '';
	}

	/**
	 * Aggregate counts for the admin. Reads subscriptions rather than a separate table:
	 * the reason belongs to the subscription and duplicating it would let them diverge.
	 *
	 * @return array<string, int>
	 */
	public function tally(): array {
		$counts = array_fill_keys( array_keys( $this->reasons() ), 0 );

		foreach ( \Subly\Data\Subscription_Query::get(
			array(
				'limit'  => -1,
				'status' => null,
			)
		) as $subscription ) {
			$reason = (string) $subscription->get_meta( self::META_REASON );

			if ( isset( $counts[ $reason ] ) ) {
				++$counts[ $reason ];
			}
		}

		return $counts;
	}

	private function owns( Subscription $subscription ): bool {
		return get_current_user_id() && (int) $subscription->get_customer_id() === get_current_user_id();
	}
}
