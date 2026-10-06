<?php

namespace Subly\Admin;

use Subly\Billing\Renewal_Processor;
use Subly\Data\Activity_Repository;
use Subly\Domain\Subscription;
use Subly\Frontend\MyAccount\Status_Presenter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Subly menu: Home, and the subscriptions list with its detail screen.
 */
class Menu {

	public const SLUG       = 'subly';
	public const LIST_SLUG  = 'subly-list';
	public const CAPABILITY = 'manage_woocommerce';

	public function __construct(
		private readonly Activity_Repository $activity,
		private readonly Renewal_Processor $processor,
		private readonly Setup_Guide $setup
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_post_subly_process_renewal', array( $this, 'process_renewal_now' ) );
	}

	/**
	 * Extensions add their screens under this, not under WooCommerce.
	 */
	public const PARENT = self::SLUG;

	/**
	 * The brand glyph alone, as a data URI, so WordPress recolours it like its own menu icons.
	 */
	private static function menu_icon(): string {
		$svg = (string) file_get_contents( SUBLY_PATH . 'assets/images/menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a bundled file.

		return 'data:image/svg+xml;base64,' . base64_encode( trim( $svg ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress reads menu icons this way.
	}

	public function add_menu(): void {
		// Menu label is the product; the page title stays what the page actually shows.
		add_menu_page(
			__( 'Subly', 'subly' ),
			__( 'Subly', 'subly' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			self::menu_icon(),
			// Directly below WooCommerce, which sits at 55.6.
			56
		);

		// Without this the top-level entry repeats itself as its own first child.
		add_submenu_page(
			self::SLUG,
			__( 'Subly home', 'subly' ),
			__( 'Home', 'subly' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'All subscriptions', 'subly' ),
			__( 'All subscriptions', 'subly' ),
			self::CAPABILITY,
			self::LIST_SLUG,
			array( $this, 'render_list' )
		);

		add_action( 'load-toplevel_page_' . self::SLUG, array( $this, 'redirect_legacy_links' ) );
	}

	/**
	 * The list used to live at the top-level address, so bookmarks, emails and other
	 * plugins still link there with a subscription id or a filter. Those go to where the
	 * list is now, rather than to a home screen that would ignore what they asked for.
	 */
	public function redirect_legacy_links(): void {
		$carry = array( 'subscription', 'status', 's', 'paged', 'orderby', 'order', 'subly_changed', 'subly_asked', 'processed' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a redirect carrying read-only view state.
		$args = array_intersect_key( wp_unslash( $_GET ), array_flip( $carry ) );

		if ( ! $args ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array_map( 'sanitize_text_field', (array) $args ) + array( 'page' => self::LIST_SLUG ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Home: setup until it is done, then how the business is doing.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'subly' ) );
		}

		App_Host::start( self::SLUG );

		Page_Shell::open( __( 'Home', 'subly' ), '', array(), '', false );

		$this->render_test_result();
		$this->render_product_notice();

		if ( ! $this->setup->is_complete() ) {
			$this->render_checklist();
		}

		if ( $this->setup->has_subscriptions() ) {
			$this->render_summary();
		}

		Page_Shell::close();

		App_Host::end();
	}

	public function render_list(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'subly' ) );
		}

		App_Host::start( self::LIST_SLUG );
		$this->render_list_screen();
		App_Host::end();
	}

	private function render_list_screen(): void {
		$id = absint( $_GET['subscription'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $id ) {
			$this->render_detail( $id );
			return;
		}

		$table = new Subscriptions_Table();
		$table->prepare_items();

		Page_Shell::open(
			__( 'All subscriptions', 'subly' ),
			__( 'Everyone who pays you on a schedule.', 'subly' ),
			array(),
			sprintf(
				'<a class="subly-btn subly-btn--primary" href="%s">%s</a>',
				esc_url( admin_url( 'post-new.php?post_type=product' ) ),
				esc_html__( 'New subscription product', 'subly' )
			)
		);

		$this->render_bulk_notice();

		if ( ! $this->setup->has_subscriptions() ) {
			printf(
				'<div class="subly-card"><div class="subly-empty"><p class="subly-empty__title">%s</p><p>%s</p><p><a class="subly-btn subly-btn--primary" href="%s">%s</a></p></div></div>',
				esc_html__( 'No subscriptions yet', 'subly' ),
				esc_html__( 'They will be listed here the moment someone buys a subscription product.', 'subly' ),
				esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
				esc_html__( 'Finish setting up', 'subly' )
			);

			Page_Shell::close();
			return;
		}

		$table->views();

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::LIST_SLUG ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only list filter; nothing is changed.
		if ( isset( $_GET['status'] ) ) {
			printf( '<input type="hidden" name="status" value="%s" />', esc_attr( sanitize_text_field( wp_unslash( $_GET['status'] ) ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$table->search_box( __( 'Search subscriptions', 'subly' ), 'subly-search' );
		$table->display();

		echo '</form>';

		Page_Shell::close();
	}

	private function render_test_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$result = isset( $_GET['subly_test'] ) ? sanitize_key( wp_unslash( $_GET['subly_test'] ) ) : '';

		if ( '' === $result ) {
			return;
		}

		printf(
			'<div class="%s"><p>%s</p></div>',
			esc_attr( Notices::feedback( 'pass' === $result ? 'success' : 'error', true ) ),
			'pass' === $result
				? esc_html__( 'Test renewal succeeded. A subscription was created, renewed and cleaned up — automatic billing works on this site.', 'subly' )
				: esc_html__( 'The test renewal did not complete. Check that scheduled tasks are running, then try again.', 'subly' )
		);
	}

	private function render_checklist(): void {

		echo '<div class="subly-card"><h2>'
			. esc_html__( 'Get your first subscription running', 'subly' )
			. '</h2><ol class="subly-steps">';

		$number = 0;

		foreach ( $this->setup->steps() as $step ) {
			++$number;
			$done = ! empty( $step['done'] );

			printf(
				'<li class="subly-step%s"><span class="subly-step__mark">%s</span><div class="subly-step__body">'
					. '<span class="subly-step__title">%s</span><span class="subly-step__detail">%s</span>%s',
				$done ? ' subly-step--done' : '',
				$done ? '&#10003;' : esc_html( (string) $number ),
				esc_html( (string) $step['title'] ),
				esc_html( (string) $step['detail'] ),
				$step['action']
					? sprintf(
						'<div class="subly-step__action"><a class="button button-small" href="%s">%s</a></div>',
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

	private function render_bulk_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['subly_changed'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$changed = max( 0, (int) sanitize_text_field( wp_unslash( $_GET['subly_changed'] ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$asked = isset( $_GET['subly_asked'] ) ? max( 0, (int) sanitize_text_field( wp_unslash( $_GET['subly_asked'] ) ) ) : 0;
		$held  = $asked - $changed;

		printf(
			'<div class="%s"><p>%s%s</p></div>',
			esc_attr( Notices::feedback( $changed ? 'success' : 'warning', true ) ),
			esc_html(
				sprintf(
					/* translators: %d: number of subscriptions */
					_n( '%d subscription updated.', '%d subscriptions updated.', $changed, 'subly' ),
					$changed
				)
			),
			$held > 0
				? ' ' . esc_html(
					sprintf(
						/* translators: %d: number of subscriptions left alone */
						_n(
							'%d was left alone because that change is not allowed from its current status.',
							'%d were left alone because that change is not allowed from their current status.',
							$held,
							'subly'
						),
						$held
					)
				)
				: ''
		);
	}

	private function render_product_notice(): void {
		$result = isset( $_GET['subly_product'] ) ? sanitize_key( wp_unslash( $_GET['subly_product'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$message = match ( $result ) {
			'created' => __( 'Your subscription product is published and ready to sell.', 'subly' ),
			'invalid' => __( 'Check the name, the price and the interval: the price must be above zero and the interval a whole number of periods.', 'subly' ),
			'failed'  => __( 'The product could not be created.', 'subly' ),
			default   => '',
		};

		if ( '' !== $message ) {
			printf(
				'<div class="%s"><p>%s</p></div>',
				esc_attr( Notices::feedback( 'created' === $result ? 'success' : 'error', true ) ),
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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="subly-inline-form">
			<?php wp_nonce_field( 'subly_create_product' ); ?>
			<input type="hidden" name="action" value="subly_create_product" />

			<label class="subly-field">
				<span><?php esc_html_e( 'Name', 'subly' ); ?></span>
				<input type="text" name="subly_name" required style="width:14rem" />
			</label>

			<label class="subly-field">
				<span><?php esc_html_e( 'Price', 'subly' ); ?></span>
				<input type="text" name="subly_price" required style="width:6rem" />
			</label>

			<label class="subly-field">
				<span><?php esc_html_e( 'Every', 'subly' ); ?></span>
				<input type="number" name="subly_interval" value="1" min="1" max="365" style="width:4.5rem" />
			</label>

			<label class="subly-field">
				<span class="screen-reader-text"><?php esc_html_e( 'Billing period', 'subly' ); ?></span>
				<select name="subly_period">
					<option value="day"><?php esc_html_e( 'Days', 'subly' ); ?></option>
					<option value="week"><?php esc_html_e( 'Weeks', 'subly' ); ?></option>
					<option value="month" selected><?php esc_html_e( 'Months', 'subly' ); ?></option>
					<option value="year"><?php esc_html_e( 'Years', 'subly' ); ?></option>
				</select>
			</label>

			<label class="subly-field">
				<span><?php esc_html_e( 'Free trial (days)', 'subly' ); ?></span>
				<input type="number" name="subly_trial" value="0" min="0" max="365" style="width:5.5rem" />
			</label>

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create it', 'subly' ); ?></button>
		</form>
		<?php
	}

	private function render_detail( int $id ): void {
		$subscription = wc_get_order( $id );

		if ( ! $subscription instanceof Subscription ) {
			Page_Shell::open( __( 'Subscription not found', 'subly' ) );
			Page_Shell::close();
			return;
		}

		$state = Status_Presenter::for( $subscription );
		$back  = add_query_arg( array( 'page' => self::LIST_SLUG ), admin_url( 'admin.php' ) );

		/* translators: %d: subscription ID */
		$heading = sprintf( __( 'Subscription #%d', 'subly' ), $id );

		// The heading below carries the status pill, so the shell's is for screen readers.
		Page_Shell::open(
			$heading,
			'',
			array(
				array(
					'label' => __( 'All subscriptions', 'subly' ),
					'url'   => $back,
				),
				array( 'label' => '#' . $id ),
			),
			'',
			false
		);

		printf(
			'<h2 class="subly-detail-heading">%s <span class="subly-pill subly-pill--%s">%s</span></h2><p class="subly-lede"><a href="%s">&larr; %s</a></p>',
			esc_html( $heading ),
			esc_attr( (string) $subscription->get_status() ),
			esc_html( $state['label'] ),
			esc_url( $back ),
			esc_html__( 'All subscriptions', 'subly' )
		);

		echo '<table class="widefat striped subly-table subly-facts"><tbody>';
		$this->row( __( 'Customer', 'subly' ), trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ) ?: (string) $subscription->get_billing_email() );
		$this->row( __( 'Recurring total', 'subly' ), wp_strip_all_tags( $subscription->get_formatted_order_total() ) );
		$this->row( __( 'Billing', 'subly' ), sprintf( '%d / %s', $subscription->get_billing_interval(), $subscription->get_billing_period() ) );
		$this->row( __( 'Next payment', 'subly' ), (string) $subscription->get_next_payment() ?: '-' );
		$this->pending_row( $subscription );
		$this->row( __( 'Trial ends', 'subly' ), (string) $subscription->get_trial_end() ?: '-' );
		$this->row( __( 'Payment method', 'subly' ), $subscription->get_payment_method_title() ?: (string) $subscription->get_payment_method() );
		$this->row( __( 'Parent order', 'subly' ), $subscription->get_parent_order_id() ? '#' . $subscription->get_parent_order_id() : '-' );
		echo '</tbody></table>';

		$this->render_process_button( $subscription );

		$this->render_activity( $id );

		/**
		 * Renders on the subscription detail screen, below its facts.
		 *
		 * @param Subscription $subscription
		 */
		do_action( 'subly_admin_subscription_detail', $subscription );

		Page_Shell::close();
	}

	/**
	 * The most useful support tool in the plugin, and the most dangerous button - it
	 * charges a real customer, so it names the amount and asks first.
	 */
	private function render_process_button( Subscription $subscription ): void {
		// Processing would only resume the charge already waiting on the payment provider.
		if ( Status_Presenter::pending_charge( $subscription ) ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'       => 'subly_process_renewal',
					'subscription' => $subscription->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			'subly_process_renewal_' . $subscription->get_id()
		);

		$confirm = sprintf(
			/* translators: %s: recurring total */
			__( 'This charges the customer %s right now. Continue?', 'subly' ),
			wp_strip_all_tags( $subscription->get_formatted_order_total() )
		);

		printf(
			'<p><a href="%s" class="button" onclick="return confirm(%s)">%s</a></p>',
			esc_url( $url ),
			esc_attr( wp_json_encode( $confirm ) ),
			esc_html__( 'Process renewal now', 'subly' )
		);
	}

	/**
	 * Recurring revenue at a glance, above the list.
	 */
	private function render_summary(): void {
		$stats = \Subly\Plugin::instance()->get( 'stats' );

		if ( ! $stats instanceof \Subly\Data\Stats ) {
			return;
		}

		$mrr     = $stats->mrr();
		$history = $stats->history( 31 );
		$oldest  = $history ? reset( $history ) : null;
		$change  = $oldest && isset( $oldest['mrr'] ) && $oldest['mrr'] > 0
			? round( ( ( $mrr->minor() - $oldest['mrr'] ) / $oldest['mrr'] ) * 100, 1 )
			: null;

		echo '<div id="subly-overview-fallback"><div class="subly-stats">';

		printf(
			'<div class="subly-stat"><span class="subly-stat__label">%s</span><span class="subly-stat__value">%s</span>%s</div>',
			esc_html__( 'Monthly recurring revenue', 'subly' ),
			wp_kses_post( $mrr->format() ),
			null === $change
				? '<span class="subly-stat__meta">' . esc_html__( 'Tracking starts today', 'subly' ) . '</span>'
				: sprintf(
					'<span class="subly-stat__meta subly-delta--%s">%s%s%% %s</span>',
					$change < 0 ? 'down' : 'up',
					$change < 0 ? '' : '+',
					esc_html( (string) $change ),
					esc_html__( 'over 30 days', 'subly' )
				)
		);

		printf(
			'<div class="subly-stat"><span class="subly-stat__label">%s</span><span class="subly-stat__value">%s</span></div>',
			esc_html__( 'Live subscriptions', 'subly' ),
			esc_html( number_format_i18n( $stats->active_count() ) )
		);

		$excluded = $stats->excluded_currency_count();

		if ( $excluded ) {
			printf(
				'<div class="subly-stat"><span class="subly-stat__label">%s</span><span class="subly-stat__value">%s</span><span class="subly-stat__meta">%s</span></div>',
				esc_html__( 'Not counted', 'subly' ),
				esc_html( number_format_i18n( $excluded ) ),
				esc_html__( 'in another currency', 'subly' )
			);
		}

		echo '</div></div>';
	}

	private function render_activity( int $id ): void {
		$entries = $this->activity->for_subscription( $id, 30 );

		echo '<h2>' . esc_html__( 'Activity', 'subly' ) . '</h2>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing recorded yet.', 'subly' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped subly-table subly-facts"><tbody>';
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

		if ( ! current_user_can( self::CAPABILITY ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'subly_process_renewal_' . $id ) ) {
			wp_die( esc_html__( 'That request could not be verified.', 'subly' ) );
		}

		$this->processor->process( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::LIST_SLUG,
					'subscription' => $id,
					'processed'    => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function pending_row( Subscription $subscription ): void {
		$pending = Status_Presenter::pending_charge( $subscription );

		if ( ! $pending ) {
			return;
		}

		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html__( 'Payment processing', 'subly' ),
			sprintf(
				/* translators: 1: renewal order number, linked, 2: how long ago it was submitted, such as "3 days" */
				esc_html__( 'Renewal order %1$s was submitted %2$s ago and is waiting for the payment provider to confirm it.', 'subly' ),
				sprintf( '<a href="%s">#%s</a>', esc_url( $pending['order']->get_edit_order_url() ), esc_html( $pending['order']->get_order_number() ) ),
				esc_html( human_time_diff( $pending['since'] ) )
			)
		);
	}

	private function row( string $label, string $value ): void {
		printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
	}
}
