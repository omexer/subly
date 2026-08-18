<?php

namespace SubKit\Admin;

use SubKit\Billing\Renewal_Processor;
use SubKit\Data\Activity_Repository;
use SubKit\Domain\Subscription;
use SubKit\Frontend\MyAccount\Status_Presenter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens, nested under WooCommerce.
 *
 * Not a top-level menu: Woo convention is to nest, it puts us next to Orders where
 * merchants already look, and stores running a dozen extensions resent the land grab.
 * UX Spec 4.
 */
class Menu {

	public const SLUG       = 'subkit-subscriptions';
	public const CAPABILITY = 'manage_woocommerce';

	public function __construct(
		private readonly Activity_Repository $activity,
		private readonly Renewal_Processor $processor,
		private readonly Setup_Guide $setup
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_post_subkit_process_renewal', array( $this, 'process_renewal_now' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Subscriptions', 'subkit-subscriptions' ),
			__( 'Subscriptions', 'subkit-subscriptions' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'subkit-subscriptions' ) );
		}

		$id = absint( $_GET['subscription'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $id ) {
			$this->render_detail( $id );
			return;
		}

		$table = new Subscriptions_Table();
		$table->prepare_items();

		echo '<div class="wrap"><h1>' . esc_html__( 'Subscriptions', 'subkit-subscriptions' ) . '</h1>';

		$this->render_test_result();

		// The checklist replaces the empty table until the merchant has a real subscription.
		if ( ! $this->setup->has_subscriptions() || ! $this->setup->is_complete() ) {
			$this->render_checklist();
		}

		if ( ! $this->setup->has_subscriptions() ) {
			echo '</div>';
			return;
		}

		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		$table->display();
		echo '</form></div>';
	}

	private function render_test_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$result = isset( $_GET['subkit_test'] ) ? sanitize_key( wp_unslash( $_GET['subkit_test'] ) ) : '';

		if ( '' === $result ) {
			return;
		}

		printf(
			'<div class="notice notice-%s"><p>%s</p></div>',
			'pass' === $result ? 'success' : 'error',
			'pass' === $result
				? esc_html__( 'Test renewal succeeded. A subscription was created, renewed and cleaned up — automatic billing works on this site.', 'subkit-subscriptions' )
				: esc_html__( 'The test renewal did not complete. Check that scheduled tasks are running, then try again.', 'subkit-subscriptions' )
		);
	}

	private function render_checklist(): void {
		echo '<div class="card" style="max-width:46rem;padding:1rem 1.25rem"><h2 style="margin-top:.5rem">'
			. esc_html__( 'Get your first subscription running', 'subkit-subscriptions' ) . '</h2><ol style="margin:0;padding-left:1.25rem">';

		foreach ( $this->setup->steps() as $step ) {
			printf(
				'<li style="margin:0 0 .9rem"><strong>%s %s</strong><br><span class="description">%s</span>%s</li>',
				$step['done'] ? '&#10003;' : '&#9675;',
				esc_html( $step['title'] ),
				esc_html( $step['detail'] ),
				$step['action']
					? sprintf( ' <a href="%s">%s</a>', esc_url( (string) $step['action']['url'] ), esc_html( (string) $step['action']['label'] ) )
					: ''
			);
		}

		echo '</ol></div>';
	}

	private function render_detail( int $id ): void {
		$subscription = wc_get_order( $id );

		if ( ! $subscription instanceof Subscription ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Subscription not found', 'subkit-subscriptions' ) . '</h1></div>';
			return;
		}

		$state = Status_Presenter::for( $subscription );
		$back  = add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) );

		/* translators: %d: subscription ID */
		$heading = sprintf( __( 'Subscription #%d', 'subkit-subscriptions' ), $id );

		echo '<div class="wrap">';
		printf(
			'<h1>%s <span class="subkit-admin-status">%s</span></h1><p><a href="%s">%s</a></p>',
			esc_html( $heading ),
			esc_html( $state['label'] ),
			esc_url( $back ),
			esc_html__( 'All subscriptions', 'subkit-subscriptions' )
		);

		echo '<table class="widefat striped" style="max-width:46rem"><tbody>';
		$this->row( __( 'Customer', 'subkit-subscriptions' ), trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ) ?: (string) $subscription->get_billing_email() );
		$this->row( __( 'Recurring total', 'subkit-subscriptions' ), wp_strip_all_tags( $subscription->get_formatted_order_total() ) );
		$this->row( __( 'Billing', 'subkit-subscriptions' ), sprintf( '%d / %s', $subscription->get_billing_interval(), $subscription->get_billing_period() ) );
		$this->row( __( 'Next payment', 'subkit-subscriptions' ), (string) $subscription->get_next_payment() ?: '-' );
		$this->row( __( 'Trial ends', 'subkit-subscriptions' ), (string) $subscription->get_trial_end() ?: '-' );
		$this->row( __( 'Payment method', 'subkit-subscriptions' ), $subscription->get_payment_method_title() ?: (string) $subscription->get_payment_method() );
		$this->row( __( 'Parent order', 'subkit-subscriptions' ), $subscription->get_parent_order_id() ? '#' . $subscription->get_parent_order_id() : '-' );
		echo '</tbody></table>';

		$this->render_process_button( $subscription );
		$this->render_activity( $id );

		echo '</div>';
	}

	/**
	 * The most useful support tool in the plugin, and the most dangerous button - it
	 * charges a real customer, so it names the amount and asks first.
	 */
	private function render_process_button( Subscription $subscription ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'subkit_process_renewal', 'subscription' => $subscription->get_id() ),
				admin_url( 'admin-post.php' )
			),
			'subkit_process_renewal_' . $subscription->get_id()
		);

		$confirm = sprintf(
			/* translators: %s: recurring total */
			__( 'This charges the customer %s right now. Continue?', 'subkit-subscriptions' ),
			wp_strip_all_tags( $subscription->get_formatted_order_total() )
		);

		printf(
			'<p><a href="%s" class="button" onclick="return confirm(%s)">%s</a></p>',
			esc_url( $url ),
			esc_attr( wp_json_encode( $confirm ) ),
			esc_html__( 'Process renewal now', 'subkit-subscriptions' )
		);
	}

	private function render_activity( int $id ): void {
		$entries = $this->activity->for_subscription( $id, 30 );

		echo '<h2>' . esc_html__( 'Activity', 'subkit-subscriptions' ) . '</h2>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing recorded yet.', 'subkit-subscriptions' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:46rem"><tbody>';
		foreach ( $entries as $entry ) {
			printf(
				'<tr><td style="width:12rem">%s</td><td>%s</td><td style="width:9rem"><code>%s</code></td></tr>',
				esc_html( $entry->created_gmt ),
				esc_html( $entry->message ),
				esc_html( $entry->actor )
			);
		}
		echo '</tbody></table>';
	}

	public function process_renewal_now(): void {
		$id = absint( $_GET['subscription'] ?? 0 );

		if ( ! current_user_can( self::CAPABILITY ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'subkit_process_renewal_' . $id ) ) {
			wp_die( esc_html__( 'That request could not be verified.', 'subkit-subscriptions' ) );
		}

		$this->processor->process( $id );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'subscription' => $id, 'processed' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function row( string $label, string $value ): void {
		printf( '<tr><th style="width:12rem;text-align:left">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
	}
}
