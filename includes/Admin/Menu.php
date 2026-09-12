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

	/**
	 * Extensions add their screens under this, not under WooCommerce.
	 */
	public const PARENT = self::SLUG;

	public function add_menu(): void {
		// Menu label is the product; the page title stays what the page actually shows.
		add_menu_page(
			__( 'Subscriptions', 'subkit-subscriptions' ),
			__( 'SubKit', 'subkit-subscriptions' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-update',
			// Directly below WooCommerce, which sits at 55.6.
			56
		);

		// Without this the top-level entry repeats itself as its own first child.
		add_submenu_page(
			self::SLUG,
			__( 'Subscriptions', 'subkit-subscriptions' ),
			__( 'All subscriptions', 'subkit-subscriptions' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);

		add_action( 'admin_menu', array( $this, 'add_settings_link' ), 99 );
	}

	/**
	 * Last, so it sits below whatever Pro has added.
	 */
	public function add_settings_link(): void {
		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'subkit-subscriptions' ),
			__( 'Settings', 'subkit-subscriptions' ),
			self::CAPABILITY,
			'admin.php?page=wc-settings&tab=subkit'
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

		echo '<div class="wrap subkit-page"><h1>' . esc_html__( 'Subscriptions', 'subkit-subscriptions' ) . '</h1>';

		$this->render_test_result();

		// The checklist replaces the empty table until the merchant has a real subscription.
		if ( ! $this->setup->has_subscriptions() || ! $this->setup->is_complete() ) {
			$this->render_checklist();
		}

		if ( ! $this->setup->has_subscriptions() ) {
			echo '</div>';
			return;
		}

		$this->render_summary();

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
		$this->render_product_notice();

		echo '<div class="subkit-card"><h2>'
			. esc_html__( 'Get your first subscription running', 'subkit-subscriptions' )
			. '</h2><ol class="subkit-steps">';

		$number = 0;

		foreach ( $this->setup->steps() as $step ) {
			++$number;
			$done = ! empty( $step['done'] );

			printf(
				'<li class="subkit-step%s"><span class="subkit-step__mark">%s</span><div class="subkit-step__body">'
					. '<span class="subkit-step__title">%s</span><span class="subkit-step__detail">%s</span>%s',
				$done ? ' subkit-step--done' : '',
				$done ? '&#10003;' : esc_html( (string) $number ),
				esc_html( (string) $step['title'] ),
				esc_html( (string) $step['detail'] ),
				$step['action']
					? sprintf(
						'<div class="subkit-step__action"><a class="button button-small" href="%s">%s</a></div>',
						esc_url( (string) $step['action']['url'] ),
						esc_html( (string) $step['action']['label'] )
					)
					: ''
			);

			if ( 'create_product' === ( $step['form'] ?? '' ) ) {
				$this->render_product_form();
			}

			echo '</div></li>';
		}

		echo '</ol></div>';
	}

	private function render_product_notice(): void {
		$result = isset( $_GET['subkit_product'] ) ? sanitize_key( wp_unslash( $_GET['subkit_product'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$message = match ( $result ) {
			'created' => __( 'Your subscription product is published and ready to sell.', 'subkit-subscriptions' ),
			'invalid' => __( 'Check the name, the price and the interval: the price must be above zero and the interval a whole number of periods.', 'subkit-subscriptions' ),
			'failed'  => __( 'The product could not be created.', 'subkit-subscriptions' ),
			default   => '',
		};

		if ( '' !== $message ) {
			printf(
				'<div class="notice notice-%s"><p>%s</p></div>',
				'created' === $result ? 'success' : 'error',
				esc_html( $message )
			);
		}
	}

	/**
	 * Creating the first product here rather than sending the merchant to find the
	 * Product data panel, which is the step people get stuck on.
	 */
	private function render_product_form(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="subkit-inline-form">
			<?php wp_nonce_field( 'subkit_create_product' ); ?>
			<input type="hidden" name="action" value="subkit_create_product" />

			<label class="subkit-field">
				<span><?php esc_html_e( 'Name', 'subkit-subscriptions' ); ?></span>
				<input type="text" name="subkit_name" required style="width:14rem" />
			</label>

			<label class="subkit-field">
				<span><?php esc_html_e( 'Price', 'subkit-subscriptions' ); ?></span>
				<input type="text" name="subkit_price" required style="width:6rem" />
			</label>

			<label class="subkit-field">
				<span><?php esc_html_e( 'Every', 'subkit-subscriptions' ); ?></span>
				<input type="number" name="subkit_interval" value="1" min="1" max="365" style="width:4.5rem" />
			</label>

			<label class="subkit-field">
				<span class="screen-reader-text"><?php esc_html_e( 'Billing period', 'subkit-subscriptions' ); ?></span>
				<select name="subkit_period">
					<option value="day"><?php esc_html_e( 'Days', 'subkit-subscriptions' ); ?></option>
					<option value="week"><?php esc_html_e( 'Weeks', 'subkit-subscriptions' ); ?></option>
					<option value="month" selected><?php esc_html_e( 'Months', 'subkit-subscriptions' ); ?></option>
					<option value="year"><?php esc_html_e( 'Years', 'subkit-subscriptions' ); ?></option>
				</select>
			</label>

			<label class="subkit-field">
				<span><?php esc_html_e( 'Free trial (days)', 'subkit-subscriptions' ); ?></span>
				<input type="number" name="subkit_trial" value="0" min="0" max="365" style="width:5.5rem" />
			</label>

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create it', 'subkit-subscriptions' ); ?></button>
		</form>
		<?php
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

		echo '<div class="wrap subkit-page">';
		printf(
			'<h1>%s <span class="subkit-pill subkit-pill--%s">%s</span></h1><p class="subkit-lede"><a href="%s">&larr; %s</a></p>',
			esc_html( $heading ),
			esc_attr( (string) $subscription->get_status() ),
			esc_html( $state['label'] ),
			esc_url( $back ),
			esc_html__( 'All subscriptions', 'subkit-subscriptions' )
		);

		echo '<table class="widefat striped subkit-table subkit-facts"><tbody>';
		$this->row( __( 'Customer', 'subkit-subscriptions' ), trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ) ?: (string) $subscription->get_billing_email() );
		$this->row( __( 'Recurring total', 'subkit-subscriptions' ), wp_strip_all_tags( $subscription->get_formatted_order_total() ) );
		$this->row( __( 'Billing', 'subkit-subscriptions' ), sprintf( '%d / %s', $subscription->get_billing_interval(), $subscription->get_billing_period() ) );
		$this->row( __( 'Next payment', 'subkit-subscriptions' ), (string) $subscription->get_next_payment() ?: '-' );
		$this->row( __( 'Trial ends', 'subkit-subscriptions' ), (string) $subscription->get_trial_end() ?: '-' );
		$this->row( __( 'Payment method', 'subkit-subscriptions' ), $subscription->get_payment_method_title() ?: (string) $subscription->get_payment_method() );
		$this->row( __( 'Parent order', 'subkit-subscriptions' ), $subscription->get_parent_order_id() ? '#' . $subscription->get_parent_order_id() : '-' );
		echo '</tbody></table>';

		$this->render_process_button( $subscription );
		/**
		 * Renders on the subscription detail screen, below its facts.
		 *
		 * @param Subscription $subscription
		 */
		do_action( 'subkit_admin_subscription_detail', $subscription );

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
				array(
					'action'       => 'subkit_process_renewal',
					'subscription' => $subscription->get_id(),
				),
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

	/**
	 * Recurring revenue at a glance, above the list.
	 */
	private function render_summary(): void {
		$stats = \SubKit\Plugin::instance()->get( 'stats' );

		if ( ! $stats instanceof \SubKit\Data\Stats ) {
			return;
		}

		$mrr     = $stats->mrr();
		$history = $stats->history( 31 );
		$oldest  = $history ? reset( $history ) : null;
		$change  = $oldest && isset( $oldest['mrr'] ) && $oldest['mrr'] > 0
			? round( ( ( $mrr->minor() - $oldest['mrr'] ) / $oldest['mrr'] ) * 100, 1 )
			: null;

		echo '<div class="subkit-stats">';

		printf(
			'<div class="subkit-stat"><span class="subkit-stat__label">%s</span><span class="subkit-stat__value">%s</span>%s</div>',
			esc_html__( 'Monthly recurring revenue', 'subkit-subscriptions' ),
			wp_kses_post( $mrr->format() ),
			null === $change
				? '<span class="subkit-stat__meta">' . esc_html__( 'Tracking starts today', 'subkit-subscriptions' ) . '</span>'
				: sprintf(
					'<span class="subkit-stat__meta subkit-delta--%s">%s%s%% %s</span>',
					$change < 0 ? 'down' : 'up',
					$change < 0 ? '' : '+',
					esc_html( (string) $change ),
					esc_html__( 'over 30 days', 'subkit-subscriptions' )
				)
		);

		printf(
			'<div class="subkit-stat"><span class="subkit-stat__label">%s</span><span class="subkit-stat__value">%s</span></div>',
			esc_html__( 'Live subscriptions', 'subkit-subscriptions' ),
			esc_html( number_format_i18n( $stats->active_count() ) )
		);

		$excluded = $stats->excluded_currency_count();

		if ( $excluded ) {
			printf(
				'<div class="subkit-stat"><span class="subkit-stat__label">%s</span><span class="subkit-stat__value">%s</span><span class="subkit-stat__meta">%s</span></div>',
				esc_html__( 'Not counted', 'subkit-subscriptions' ),
				esc_html( number_format_i18n( $excluded ) ),
				esc_html__( 'in another currency', 'subkit-subscriptions' )
			);
		}

		echo '</div>';
	}

	private function render_activity( int $id ): void {
		$entries = $this->activity->for_subscription( $id, 30 );

		echo '<h2>' . esc_html__( 'Activity', 'subkit-subscriptions' ) . '</h2>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing recorded yet.', 'subkit-subscriptions' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped subkit-table subkit-facts"><tbody>';
		foreach ( $entries as $entry ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
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

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::SLUG,
					'subscription' => $id,
					'processed'    => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function row( string $label, string $value ): void {
		printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
	}
}
