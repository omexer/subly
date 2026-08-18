<?php

namespace SubKit\Admin;

use SubKit\Billing\Renewal_Processor;
use SubKit\Billing\Renewal_Scheduler;
use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Gateways\PayPal\PayPal_Client;
use SubKit\Gateways\Test_Gateway;
use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The merchant's first ten minutes.
 *
 * A checklist and one activation notice, not a wizard. WordPress.org retention is decided
 * here: a plugin that activates and changes nothing gets uninstalled. UX Spec 5.
 */
class Setup_Guide {

	private const DISMISSED = 'subkit_setup_notice_dismissed';

	public function __construct( private readonly Renewal_Scheduler $scheduler ) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
		add_action( 'admin_post_subkit_dismiss_setup', array( $this, 'dismiss' ) );
		add_action( 'admin_post_subkit_test_renewal', array( $this, 'run_test_renewal' ) );
	}

	/**
	 * One dismissible notice. No redirect on activation, no dashboard-wide banner.
	 */
	public function activation_notice(): void {
		if ( get_option( self::DISMISSED ) || ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'plugins' !== $screen->id ) {
			return;
		}

		printf(
			'<div class="notice notice-success"><p><strong>%s</strong> %s</p><p><a class="button button-primary" href="%s">%s</a> <a href="%s">%s</a></p></div>',
			esc_html__( 'SubKit is active.', 'subkit-subscriptions' ),
			esc_html__( 'Turn any product into a subscription in about two minutes.', 'subkit-subscriptions' ),
			esc_url( add_query_arg( array( 'page' => Menu::SLUG ), admin_url( 'admin.php' ) ) ),
			esc_html__( 'Set up subscriptions', 'subkit-subscriptions' ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=subkit_dismiss_setup' ), 'subkit_dismiss_setup' ) ),
			esc_html__( 'Dismiss', 'subkit-subscriptions' )
		);
	}

	public function dismiss(): void {
		if ( current_user_can( Menu::CAPABILITY ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'subkit_dismiss_setup' ) ) {
			update_option( self::DISMISSED, 1, false );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * @return array<int, array{done: bool, title: string, detail: string, action: array{label: string, url: string}|null}>
	 */
	public function steps(): array {
		$gateway_ready = PayPal_Client::from_settings()->is_enabled();
		$product       = $this->first_subscription_product();
		$queue_ok      = $this->scheduler->queue_is_healthy();

		return array(
			array(
				'done'   => $gateway_ready,
				'title'  => __( 'Connect a payment method', 'subkit-subscriptions' ),
				'detail' => $gateway_ready
					? __( 'PayPal is connected.', 'subkit-subscriptions' )
					: __( 'Subscriptions can renew automatically once a gateway is connected. Manual renewal works without one.', 'subkit-subscriptions' ),
				'action' => array(
					'label' => $gateway_ready ? __( 'Change', 'subkit-subscriptions' ) : __( 'Connect PayPal', 'subkit-subscriptions' ),
					'url'   => admin_url( 'admin.php?page=wc-settings&tab=subkit&section=paypal' ),
				),
			),
			array(
				'done'   => (bool) $product,
				'title'  => __( 'Create a subscription product', 'subkit-subscriptions' ),
				'detail' => $product
					/* translators: %s: product name */
					? sprintf( __( '"%s" is ready to sell.', 'subkit-subscriptions' ), $product->get_name() )
					: __( 'Edit any simple product and tick Subscription in the Product data panel.', 'subkit-subscriptions' ),
				'action' => array(
					'label' => $product ? __( 'Edit', 'subkit-subscriptions' ) : __( 'Add a product', 'subkit-subscriptions' ),
					'url'   => $product ? get_edit_post_link( $product->get_id(), '' ) : admin_url( 'post-new.php?post_type=product' ),
				),
			),
			array(
				'done'   => $queue_ok,
				'title'  => __( 'Check automatic renewals can run', 'subkit-subscriptions' ),
				'detail' => $queue_ok
					? __( 'Renewals are processing normally.', 'subkit-subscriptions' )
					: __( 'Scheduled tasks are overdue. On a quiet site WordPress cron may not fire often enough — a real server cron fixes it.', 'subkit-subscriptions' ),
				'action' => null,
			),
			array(
				'done'   => (bool) get_option( 'subkit_test_renewal_passed' ),
				'title'  => __( 'Run a test renewal', 'subkit-subscriptions' ),
				'detail' => __( 'See a renewal happen end to end, on a throwaway subscription. Nobody is charged and everything is deleted afterwards.', 'subkit-subscriptions' ),
				'action' => array(
					'label' => __( 'Run test renewal', 'subkit-subscriptions' ),
					'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=subkit_test_renewal' ), 'subkit_test_renewal' ),
				),
			),
		);
	}

	/**
	 * Create a throwaway subscription, renew it, then delete the lot.
	 *
	 * The most useful control in the plugin: a merchant who has watched a renewal happen
	 * will trust it with real customers, and one who hasn't opens a support ticket.
	 */
	public function run_test_renewal(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'subkit_test_renewal' ) ) {
			wp_die( esc_html__( 'That request could not be verified.', 'subkit-subscriptions' ) );
		}

		$result = $this->perform_test_renewal();

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => Menu::SLUG, 'subkit_test' => $result ? 'pass' : 'fail' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function perform_test_renewal(): bool {
		Test_Gateway::sanction();

		$processor = \SubKit\Plugin::instance()->get( 'processor' );
		$registry  = \SubKit\Plugin::instance()->get( 'gateways' );

		if ( ! $processor instanceof Renewal_Processor || ! $registry ) {
			return false;
		}

		$registry->add( new Test_Gateway() );

		$subscription = new Subscription();
		$subscription->set_currency( get_woocommerce_currency() );
		$subscription->set_billing_period( 'month' );
		$subscription->set_billing_interval( 1 );
		$subscription->set_payment_method( Test_Gateway::ID );
		$subscription->set_payment_method_title( __( 'SubKit self-test', 'subkit-subscriptions' ) );
		$subscription->set_next_payment( gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) );
		$subscription->update_meta_data( '_subkit_site_url', get_option( 'siteurl' ) );
		$subscription->update_meta_data( '_subkit_is_test', 'yes' );
		$subscription->transition_to( Subscription_Status::Active );
		$subscription->save();

		$id = $subscription->get_id();
		Test_Gateway::script_subscription( wc_get_order( $id ), array( 'success' ) );

		$processor->process( $id );

		$renewed = ! empty( array_filter(
			$this->slots_for( $id ),
			static fn( $slot ): bool => 'paid' === $slot->state
		) );

		$this->cleanup_test( $id );

		update_option( 'subkit_test_renewal_passed', $renewed ? 1 : 0, false );

		return $renewed;
	}

	/**
	 * @return object[]
	 */
	private function slots_for( int $id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( "SELECT state FROM {$wpdb->prefix}subkit_charge_slot WHERE subscription_id = %d", $id ) );
	}

	private function cleanup_test( int $id ): void {
		global $wpdb;

		foreach ( wc_get_orders( array( 'limit' => -1, 'meta_key' => '_subkit_subscription_id', 'meta_value' => $id ) ) as $order ) {
			$order->delete( true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'subkit_charge_slot', array( 'subscription_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'subkit_activity', array( 'subscription_id' => $id ), array( '%d' ) );

		$subscription = wc_get_order( $id );
		if ( $subscription ) {
			$subscription->delete( true );
		}
	}

	private function first_subscription_product(): ?\WC_Product {
		$products = wc_get_products( array(
			'limit'      => 1,
			'status'     => 'publish',
			'meta_key'   => Subscription_Product::META_ENABLED,
			'meta_value' => 'yes',
		) );

		return $products[0] ?? null;
	}

	public function is_complete(): bool {
		foreach ( $this->steps() as $step ) {
			if ( ! $step['done'] ) {
				return false;
			}
		}

		return true;
	}

	public function has_subscriptions(): bool {
		return ! empty( Subscription_Query::ids( array( 'limit' => 1 ) ) );
	}
}
