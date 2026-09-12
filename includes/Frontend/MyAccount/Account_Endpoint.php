<?php

namespace SubKit\Frontend\MyAccount;

use SubKit\Data\Activity_Repository;
use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The customer's subscription screens under My Account.
 *
 * Viewing and cancelling are free and cannot be switched off: a customer who cannot stop
 * their own recurring charge is a chargeback and, in the UK/EU, a compliance problem.
 * UX Spec 8 and 10.
 */
class Account_Endpoint {

	public const ENDPOINT = 'subscriptions';

	public function __construct( private readonly Activity_Repository $activity ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 10, 1 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_action( 'template_redirect', array( $this, 'handle_actions' ) );
	}

	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;

		return $vars;
	}

	public function add_menu_item( $items ) {
		$new = array();

		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;

			// Sit directly under Orders, where customers already look.
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = __( 'Subscriptions', 'subkit-subscriptions' );
			}
		}

		return isset( $new[ self::ENDPOINT ] ) ? $new : $new + array( self::ENDPOINT => __( 'Subscriptions', 'subkit-subscriptions' ) );
	}

	/**
	 * @param string $value Endpoint value: a subscription ID, or empty for the list.
	 */
	public function render( $value = '' ): void {
		$id = absint( $value );

		if ( $id ) {
			$subscription = $this->owned_subscription( $id );

			if ( ! $subscription ) {
				wc_print_notice( __( 'That subscription could not be found.', 'subkit-subscriptions' ), 'error' );
				return;
			}

			wc_get_template(
				'myaccount/subscription-details.php',
				array( 'subscription' => $subscription, 'endpoint' => self::ENDPOINT ),
				'',
				SUBKIT_PATH . 'templates/'
			);

			return;
		}

		wc_get_template(
			'myaccount/subscriptions.php',
			array(
				'subscriptions' => $this->for_current_customer(),
				'endpoint'      => self::ENDPOINT,
			),
			'',
			SUBKIT_PATH . 'templates/'
		);
	}

	/**
	 * Cancel first, ask why after. The cancellation is executed and confirmed before any
	 * survey is shown, which makes survey-as-a-barrier structurally impossible.
	 */
	public function handle_actions(): void {
		if ( ! isset( $_POST['subkit_action'], $_POST['subkit_subscription'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['subkit_action'] ) );
		$id     = absint( wp_unslash( $_POST['subkit_subscription'] ) );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'subkit_' . $action . '_' . $id ) ) {
			wc_add_notice( __( 'That request has expired. Please try again.', 'subkit-subscriptions' ), 'error' );
			return;
		}

		// Never trust the ID in the request: re-check ownership server side.
		$subscription = $this->owned_subscription( $id );
		if ( ! $subscription ) {
			wc_add_notice( __( 'That subscription could not be found.', 'subkit-subscriptions' ), 'error' );
			return;
		}

		if ( 'auto_renew' === $action ) {
			$this->set_auto_renew( $subscription, 'on' === ( isset( $_POST['subkit_auto_renew'] ) ? sanitize_key( wp_unslash( $_POST['subkit_auto_renew'] ) ) : 'on' ) );
			return;
		}

		if ( 'cancel' === $action ) {
			$when = isset( $_POST['subkit_when'] ) ? sanitize_key( wp_unslash( $_POST['subkit_when'] ) ) : 'period_end';

			$this->cancel( $subscription, 'immediate' === $when );
		}
	}

	private function set_auto_renew( Subscription $subscription, bool $on ): void {
		if ( ! \SubKit\Lifecycle\Auto_Renewal::is_offered() ) {
			return;
		}

		$auto = \SubKit\Plugin::instance()->get( 'auto_renewal' );

		if ( ! $auto instanceof \SubKit\Lifecycle\Auto_Renewal ) {
			return;
		}

		$auto->set( $subscription, $on );

		wc_add_notice(
			$on
				? __( 'Automatic renewal is back on. Your subscription will keep renewing.', 'subkit-subscriptions' )
				: __( 'Automatic renewal is off. Your subscription stays active until the end of the period you have paid for, and you will not be charged again.', 'subkit-subscriptions' )
		);

		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT . '/' . $subscription->get_id() ) );
		exit;
	}

	private function cancel( Subscription $subscription, bool $immediately ): void {
		if ( ! apply_filters( 'subkit_can_cancel', true, $subscription, get_current_user_id() ) ) {
			wc_add_notice( __( 'This subscription cannot be cancelled online. Please contact us.', 'subkit-subscriptions' ), 'error' );
			return;
		}

		$target = $immediately ? Subscription_Status::Cancelled : Subscription_Status::PendingCancel;

		try {
			$subscription->transition_to( $target, __( 'Cancelled by the customer.', 'subkit-subscriptions' ) );
			$subscription->save();
		} catch ( \InvalidArgumentException $e ) {
			wc_add_notice( __( 'This subscription can no longer be cancelled.', 'subkit-subscriptions' ), 'error' );
			return;
		}

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_STATUS_CHANGE,
			$immediately ? 'Cancelled immediately by the customer.' : 'Cancellation scheduled for the end of the period.'
		);

		do_action( 'subkit_subscription_cancelled', $subscription, 'customer' );

		wc_add_notice(
			$immediately
				? __( 'Your subscription has been cancelled.', 'subkit-subscriptions' )
				: __( 'Your subscription will end when the current period finishes. You will not be charged again.', 'subkit-subscriptions' )
		);

		wp_safe_redirect( add_query_arg( 'cancelled', '1', wc_get_account_endpoint_url( self::ENDPOINT . '/' . $subscription->get_id() ) ) );
		exit;
	}

	/**
	 * @return Subscription[]
	 */
	private function for_current_customer(): array {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return array();
		}

		$found = Subscription_Query::get( array(
			'customer_id' => $user_id,
			'limit'       => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		return array_filter( $found, static fn( $s ) => $s instanceof Subscription );
	}

	private function owned_subscription( int $id ): ?Subscription {
		$subscription = wc_get_order( $id );

		if ( ! $subscription instanceof Subscription ) {
			return null;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id || (int) $subscription->get_customer_id() !== $user_id ) {
			return null;
		}

		return $subscription;
	}
}
