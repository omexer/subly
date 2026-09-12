<?php

namespace SubKit\Frontend\MyAccount;

use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a status into something a customer can read.
 *
 * Never shows a raw slug, and every state carries a text label rather than relying on
 * colour alone. UX Spec 7.2 and 14.
 */
class Status_Presenter {

	/**
	 * @return array{label: string, tone: string, detail: string}
	 */
	public static function for( Subscription $subscription ): array {
		$status = $subscription->get_status_enum();
		$next   = $subscription->get_next_payment();
		$when   = $next ? date_i18n( (string) get_option( 'date_format' ), strtotime( $next . ' UTC' ) ) : '';

		return match ( $status ) {
			Subscription_Status::Trialling => array(
				'label'  => __( 'Free trial', 'subkit-subscriptions' ),
				'tone'   => 'neutral',
				/* translators: %s: date of first payment */
				'detail' => $when ? sprintf( __( 'First payment on %s.', 'subkit-subscriptions' ), $when ) : '',
			),
			Subscription_Status::Active => array(
				'label'  => __( 'Active', 'subkit-subscriptions' ),
				'tone'   => 'positive',
				/* translators: %s: date of next payment */
				'detail' => $when ? sprintf( __( 'Next payment on %s.', 'subkit-subscriptions' ), $when ) : '',
			),
			Subscription_Status::OnHold => array(
				'label'  => __( 'Payment needed', 'subkit-subscriptions' ),
				'tone'   => 'warning',
				'detail' => __( "We couldn't take your last payment.", 'subkit-subscriptions' ),
			),
			Subscription_Status::PendingCancel => array(
				'label'  => __( 'Cancelling', 'subkit-subscriptions' ),
				'tone'   => 'neutral',
				/* translators: %s: date access ends */
				'detail' => $when ? sprintf( __( 'Active until %s. You will not be charged again.', 'subkit-subscriptions' ), $when ) : '',
			),
			Subscription_Status::Cancelled => array(
				'label'  => __( 'Cancelled', 'subkit-subscriptions' ),
				'tone'   => 'muted',
				'detail' => '',
			),
			Subscription_Status::Expired => array(
				'label'  => __( 'Ended', 'subkit-subscriptions' ),
				'tone'   => 'muted',
				'detail' => '',
			),
			Subscription_Status::Switched => array(
				'label'  => __( 'Changed plan', 'subkit-subscriptions' ),
				'tone'   => 'muted',
				'detail' => '',
			),
			default => array(
				'label'  => __( 'Pending', 'subkit-subscriptions' ),
				'tone'   => 'neutral',
				'detail' => __( 'Waiting for the first payment to clear.', 'subkit-subscriptions' ),
			),
		};
	}

	/**
	 * The one thing the customer should do about this subscription right now.
	 *
	 * A "Payment needed" card that offers no way to pay is the whole failure mode this
	 * exists to prevent. UX Spec 9.2.
	 *
	 * @return array{label: string, url: string}|null
	 */
	public static function primary_action( Subscription $subscription ): ?array {
		if ( Subscription_Status::OnHold !== $subscription->get_status_enum() ) {
			return null;
		}

		$order = self::payable_order( $subscription );
		if ( ! $order ) {
			return null;
		}

		// A 3DS challenge is not a failure, so it gets its own wording and its own link.
		$authenticate = (string) $order->get_meta( '_subkit_action_url' );

		return $authenticate
			? array(
				'label' => __( 'Confirm payment', 'subkit-subscriptions' ),
				'url'   => $authenticate,
			)
			: array(
				'label' => __( 'Pay now', 'subkit-subscriptions' ),
				'url'   => $order->get_checkout_payment_url(),
			);
	}

	/**
	 * The most recent renewal order still awaiting payment.
	 */
	public static function payable_order( Subscription $subscription ): ?\WC_Order {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'status'     => array( 'pending', 'failed', 'on-hold' ),
				'meta_key'   => '_subkit_subscription_id',
				'meta_value' => $subscription->get_id(),
			)
		);

		$order = $orders[0] ?? null;

		return $order instanceof \WC_Order && ! $order->is_paid() ? $order : null;
	}

	/**
	 * Name the subscription by what it contains, not by its ID.
	 */
	public static function title( Subscription $subscription ): string {
		$names = array();

		foreach ( $subscription->get_items() as $item ) {
			$names[] = $item->get_name();
		}

		if ( empty( $names ) ) {
			/* translators: %s: subscription number */
			return sprintf( __( 'Subscription #%s', 'subkit-subscriptions' ), $subscription->get_id() );
		}

		return implode( ', ', $names );
	}
}
