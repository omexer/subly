<?php

namespace SubKit\Gateways;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of recurring-capable gateways.
 */
class Gateway_Registry {

	/** @var array<string, Recurring_Gateway> */
	private array $gateways = array();

	public function register(): void {
		add_action( 'init', array( $this, 'load_gateways' ), 20 );
	}

	public function load_gateways(): void {
		$this->add( new Manual_Gateway() );

		// Never present on a production site unless the constant is deliberately defined.
		if ( defined( 'SUBKIT_ENABLE_TEST_GATEWAY' ) && SUBKIT_ENABLE_TEST_GATEWAY ) {
			$this->add( new Test_Gateway() );
		}

		/**
		 * Register additional recurring gateways.
		 *
		 * @param Gateway_Registry $registry
		 */
		do_action( 'subkit_register_gateways', $this );
	}

	public function add( Recurring_Gateway $gateway ): void {
		$this->gateways[ $gateway->id() ] = $gateway;
	}

	public function get( string $id ): ?Recurring_Gateway {
		return $this->gateways[ $id ] ?? null;
	}

	/**
	 * @return array<string, Recurring_Gateway>
	 */
	public function all(): array {
		/**
		 * Filter the gateways SubKit will consider for recurring payments.
		 *
		 * @param array<string, Recurring_Gateway> $gateways
		 */
		return apply_filters( 'subkit_available_gateways', $this->gateways );
	}

	/**
	 * Resolve the gateway backing a subscription, falling back to manual invoicing so a
	 * missing or deactivated gateway never silently stops renewals.
	 */
	public function for_subscription( Subscription $subscription ): Recurring_Gateway {
		$gateway = $this->get( (string) $subscription->get_payment_method() );

		return $gateway ?? $this->get( Manual_Gateway::ID );
	}
}
