<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;
use SubKit\Gateways\Charge_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers SubKit's emails with WooCommerce and connects them to pipeline events.
 *
 * Every message is a WC_Email so the merchant's existing branding, template overrides
 * and enable/disable toggles apply without extra settings of our own.
 */
class Mailer {

	private const CLASSES = array(
		'Subscription_Started'      => Subscription_Started::class,
		'Renewal_Receipt'           => Renewal_Receipt::class,
		'Payment_Failed'            => Payment_Failed::class,
		'Confirm_Payment'           => Confirm_Payment::class,
		'Subscription_Cancelled'    => Subscription_Cancelled::class,
		'Merchant_New_Subscription' => Merchant_New_Subscription::class,
	);

	/**
	 * WooCommerce's own order emails, suppressed for renewal orders.
	 *
	 * A renewal is not a new order from the customer's point of view, and SubKit already
	 * sends a receipt. Without this the customer gets two emails per renewal.
	 */
	private const WOO_ORDER_EMAILS = array(
		'new_order',
		'customer_processing_order',
		'customer_completed_order',
		'customer_on_hold_order',
		'customer_invoice',
		'failed_order',
		'customer_failed_order',
	);

	public function register(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'add_emails' ) );

		foreach ( self::WOO_ORDER_EMAILS as $subkit_email_id ) {
			add_filter( "woocommerce_email_enabled_{$subkit_email_id}", array( $this, 'suppress_for_renewals' ), 10, 2 );
		}

		add_action( 'subkit_subscription_activated', array( $this, 'on_activated' ), 10, 1 );
		add_action( 'subkit_subscription_created', array( $this, 'on_created' ), 10, 2 );
		add_action( 'subkit_renewal_succeeded', array( $this, 'on_renewal_paid' ), 10, 2 );
		add_action( 'subkit_renewal_failed', array( $this, 'on_renewal_failed' ), 10, 3 );
		add_action( 'subkit_renewal_requires_action', array( $this, 'on_requires_action' ), 10, 3 );
		add_action( 'subkit_subscription_cancelled', array( $this, 'on_cancelled' ), 10, 1 );
	}

	/**
	 * @param bool  $enabled
	 * @param mixed $order
	 */
	public function suppress_for_renewals( $enabled, $order ) {
		if ( $order instanceof \WC_Order && 'subkit_renewal' === $order->get_created_via() ) {
			return false;
		}

		return $enabled;
	}

	public function add_emails( array $emails ): array {
		foreach ( self::CLASSES as $key => $class ) {
			$emails[ 'SubKit_' . $key ] = new $class();
		}

		return $emails;
	}

	public function on_activated( Subscription $subscription ): void {
		$this->dispatch( 'Subscription_Started', array( $subscription ) );
	}

	public function on_created( Subscription $subscription, \WC_Order $order ): void {
		$this->dispatch( 'Merchant_New_Subscription', array( $subscription ) );
	}

	public function on_renewal_paid( Subscription $subscription, \WC_Order $order ): void {
		$this->dispatch( 'Renewal_Receipt', array( $subscription, $order ) );
	}

	public function on_renewal_failed( Subscription $subscription, \WC_Order $order, Charge_Result $result ): void {
		$this->dispatch( 'Payment_Failed', array( $subscription, $order ) );
	}

	public function on_requires_action( Subscription $subscription, \WC_Order $order, Charge_Result $result ): void {
		$this->dispatch( 'Confirm_Payment', array( $subscription, $order, (string) $result->action_url ) );
	}

	public function on_cancelled( Subscription $subscription ): void {
		$this->dispatch( 'Subscription_Cancelled', array( $subscription ) );
	}

	/**
	 * Look the email up through WooCommerce so merchant settings are respected.
	 */
	private function dispatch( string $key, array $args ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = $emails[ 'SubKit_' . $key ] ?? null;

		// Each email declares its own trigger() with its own arguments, so there is no
		// shared signature to put on the base class and no method to call blindly.
		if ( $email instanceof Subscription_Email && is_callable( array( $email, 'trigger' ) ) ) {
			call_user_func_array( array( $email, 'trigger' ), $args );
		}
	}
}
