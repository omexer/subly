<?php

namespace Subly\Admin;

use Subly\Billing\Renewal_Scheduler;
use Subly\Billing\Renewal_Tax_Repair;
use Subly\Data\Charge_Slot_Repository;
use Subly\Data\Migrator;
use Subly\Emails\Notification_Settings;
use Subly\Lifecycle\Cancellation_Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce → Settings → Subscriptions.
 *
 * A tab inside WooCommerce settings rather than a screen of our own, so it inherits
 * Woo's markup, save handling and nonce checks. UX Spec 3.
 */
class Settings extends \WC_Settings_Page {

	public function __construct() {
		$this->id    = 'subly';
		$this->label = __( 'Subscriptions', 'subly' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_subly_status', array( $this, 'render_status' ) );
		add_action( 'woocommerce_admin_field_subly_tax_repair', array( $this, 'render_tax_repair' ) );
	}

	public function get_sections(): array {
		$sections = array(
			''                  => __( 'General', 'subly' ),
			'customer_controls' => __( 'Customer controls', 'subly' ),
			'renewal'           => __( 'Renewal & billing', 'subly' ),
			'checkout'          => __( 'Cart & checkout', 'subly' ),
			'notifications'     => __( 'Email notifications', 'subly' ),
			'paypal'            => __( 'PayPal', 'subly' ),
		);

		// Core applies this in the method we are overriding; without it an extension can
		// add settings but has no tab to put them on.
		return (array) apply_filters( 'woocommerce_get_sections_' . $this->id, $sections ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own settings hook.
	}

	public function get_settings_for_default_section(): array {
		$settings = array(
			array(
				'title' => __( 'Health', 'subly' ),
				'type'  => 'title',
				'desc'  => __( 'Renewals run on the WooCommerce queue. If the queue stops, subscriptions stop billing — usually the first thing to check.', 'subly' ),
				'id'    => 'subly_health_title',
			),
			array( 'type' => 'subly_status' ),
			array( 'type' => 'subly_tax_repair' ),
			array(
				'type' => 'sectionend',
				'id'   => 'subly_health_title',
			),

			array(
				'title' => __( 'Access', 'subly' ),
				'type'  => 'title',
				'desc'  => __( 'What a live subscription grants, and what lapsing takes away.', 'subly' ),
				'id'    => 'subly_access_title',
			),
			array(
				'title'    => __( 'Role while subscribed', 'subly' ),
				'desc_tip' => __( 'Assigned when a subscription becomes active. Administrators are never changed.', 'subly' ),
				'type'     => 'select',
				'id'       => 'subly_active_role',
				'default'  => '',
				'options'  => self::role_options(),
			),
			array(
				'title'    => __( 'Role once it ends', 'subly' ),
				'desc_tip' => __( 'Assigned only when the customer has no other live subscription.', 'subly' ),
				'type'     => 'select',
				'id'       => 'subly_inactive_role',
				'default'  => '',
				'options'  => self::role_options(),
			),
			array(
				'title'    => __( 'Delete data when the plugin is deleted', 'subly' ),
				'desc'     => __( 'Remove Subly\'s settings and its own database tables on uninstall', 'subly' ),
				'type'     => 'checkbox',
				'id'       => 'subly_delete_data_on_uninstall',
				'default'  => 'no',
				'desc_tip' => __( 'Off by default, so deactivating or deleting the plugin to try something else loses nothing. Subscriptions and their orders are never deleted either way — they are WooCommerce orders and part of your financial record.', 'subly' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subly_access_title',
			),
		);

		return $settings;
	}

	public function get_settings_for_customer_controls_section(): array {
		return array(
			array(
				'title' => __( 'Customer controls', 'subly' ),
				'type'  => 'title',
				'id'    => 'subly_customer_controls_title',
			),
			array(
				'title'    => __( 'Allow customers to renew early', 'subly' ),
				'desc'     => __( 'Customers can pay their next renewal before it is due. The renewal date stays the same.', 'subly' ),
				'type'     => 'checkbox',
				'id'       => \Subly\Lifecycle\Early_Renewal::OPTION,
				'default'  => 'no',
				'desc_tip' => __( 'Only offered for cards Subly charges itself, never for PayPal, which bills from a plan of its own.', 'subly' ),
			),
			array(
				'title'   => __( 'Allow customers to cancel', 'subly' ),
				'desc'    => __( 'Customers can cancel from My Account. When off, they are asked to contact you. In the UK and EU, customers must be able to cancel as easily as they signed up.', 'subly' ),
				'type'    => 'checkbox',
				'id'      => Cancellation_Policy::OPTION,
				'default' => 'yes',
			),
			array(
				'title'          => __( 'Cancellation takes effect', 'subly' ),
				'desc'           => __( 'At the end of the billing cycle, the customer keeps access until the date they have paid for. Immediately ends access at once, with no refund for the time left.', 'subly' ),
				'type'           => 'select',
				'id'             => Cancellation_Policy::OPTION_WHEN,
				'default'        => Cancellation_Policy::AT_PERIOD_END,
				'options'        => array(
					Cancellation_Policy::AT_PERIOD_END => __( 'End of billing cycle', 'subly' ),
					Cancellation_Policy::IMMEDIATELY   => __( 'Immediately', 'subly' ),
				),
				'subly_show_if' => Cancellation_Policy::OPTION,
			),
			array(
				'title'   => __( 'Allow customers to turn off auto-renew', 'subly' ),
				'desc'    => __( 'Customers can stop their subscription renewing. It then ends at the end of the current period, unless they turn renewal back on.', 'subly' ),
				'type'    => 'checkbox',
				'id'      => 'subly_allow_auto_renew_toggle',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subly_customer_controls_title',
			),
		);
	}

	public function get_settings_for_renewal_section(): array {
		return array(
			array(
				'title' => __( 'Renewal', 'subly' ),
				'type'  => 'title',
				'id'    => 'subly_renewals_title',
			),
			array(
				'title'   => __( 'Missed renewals', 'subly' ),
				'desc'    => __( 'What to do when a subscription falls more than one period behind, usually because the site stopped running its scheduled tasks. Charging for every missed period bills the customer several times at once, so only choose it if you deliver every period anyway.', 'subly' ),
				'id'      => 'subly_catch_up_policy',
				'type'    => 'select',
				'default' => 'rebase',
				'options' => array(
					'rebase'     => __( 'Charge once and move the schedule forward (recommended)', 'subly' ),
					'charge_all' => __( 'Charge for every missed period', 'subly' ),
				),
			),
			array(
				'title'   => __( 'Free first payments', 'subly' ),
				'desc'    => __( 'Ask for a payment method even when the first payment is zero, such as a free trial with no sign-up fee. Without one there is nothing to charge when the first renewal is due. Offline methods like bank transfer store nothing to charge either.', 'subly' ),
				'id'      => \Subly\Checkout\Trial_Payment::OPTION,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subly_renewals_title',
			),
		);
	}

	public function get_settings_for_checkout_section(): array {
		return array(
			array(
				'title' => __( 'Cart & checkout', 'subly' ),
				'type'  => 'title',
				'id'    => 'subly_checkout_title',
			),
			array(
				'title'    => __( 'Buying without an account', 'subly' ),
				'desc_tip' => __( 'A subscription has to belong to someone, so a guest who buys one gets an account created at checkout.', 'subly' ),
				'type'     => 'select',
				'id'       => 'subly_guest_checkout',
				'default'  => 'create_account',
				'options'  => array(
					'create_account' => __( 'Create an account for them automatically', 'subly' ),
					'require_login'  => __( 'Require them to log in first', 'subly' ),
				),
			),
			array(
				'title'   => __( 'Allow mixed checkout', 'subly' ),
				'desc'    => __( 'Customers can buy a subscription and one-time products in the same order. When off, a subscription is checked out on its own.', 'subly' ),
				'type'    => 'checkbox',
				'id'      => \Subly\Checkout\Cart_Validation::OPTION_MIXED,
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subly_checkout_title',
			),
		);
	}

	public function get_settings_for_notifications_section(): array {
		return Notification_Settings::fields();
	}

	public function get_settings_for_paypal_section(): array {
		return array(
			array(
				'title' => __( 'PayPal Subscriptions', 'subly' ),
				'type'  => 'title',
				'desc'  => __( 'PayPal manages the billing schedule itself and tells Subly about each payment by webhook. Add the webhook URL below to your PayPal app before going live, or renewals will not be recorded.', 'subly' ),
				'id'    => 'subly_paypal_title',
			),
			array(
				'title'   => __( 'Enable PayPal', 'subly' ),
				'desc'    => __( 'Offer PayPal for subscription purchases', 'subly' ),
				'id'      => 'subly_paypal_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Environment', 'subly' ),
				'id'      => 'subly_paypal_live',
				'type'    => 'select',
				'default' => 'no',
				'options' => array(
					'no'  => __( 'Sandbox (testing)', 'subly' ),
					'yes' => __( 'Live', 'subly' ),
				),
			),
			array(
				'title' => __( 'Client ID', 'subly' ),
				'id'    => 'subly_paypal_client_id',
				'type'  => 'text',
				'css'   => 'width:26rem',
			),
			array(
				'title' => __( 'Secret', 'subly' ),
				'id'    => 'subly_paypal_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'title'    => __( 'Webhook ID', 'subly' ),
				'desc'     => __( 'From your PayPal app, after you add the webhook URL below. Without it Subly cannot verify that a webhook really came from PayPal, and will reject every one.', 'subly' ),
				'id'       => 'subly_paypal_webhook_id',
				'type'     => 'text',
				'css'      => 'width:26rem',
				'desc_tip' => false,
			),
			array(
				'title'             => __( 'Webhook URL', 'subly' ),
				'desc'              => __( 'Add this URL to your PayPal app and subscribe it to the billing subscription and payment sale events.', 'subly' ),
				'id'                => 'subly_paypal_webhook_url',
				'type'              => 'text',
				'value'             => rest_url( 'subly/v1/webhook/paypal' ),
				'custom_attributes' => array(
					'readonly' => 'readonly',
					'onclick'  => 'this.select()',
				),
				'css'               => 'width:26rem',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subly_paypal_title',
			),
		);
	}

	/**
	 * A plain readout of whether renewals can actually run.
	 */
	/**
	 * WooCommerce refuses a saved value outside these options, so this list is also the save-time guard.
	 *
	 * @return array<string, string>
	 */
	private static function role_options(): array {
		return array( '' => __( 'Leave the role alone', 'subly' ) ) + \Subly\Lifecycle\Role_Management::grantable_roles();
	}

	public function render_status(): void {
		echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html__( 'Status', 'subly' ) . '</th><td class="forminp"><div class="subly-checks">';

		foreach ( self::status_checks() as $check ) {
			self::render_check( $check );
		}

		echo '</div></td></tr>';
	}

	/**
	 * One status line, with the rows behind it when it has any.
	 *
	 * @param array{label: string, ok: bool, good: string, bad: string, items?: string[]} $check Items are HTML, escaped where they were built.
	 */
	public static function render_check( array $check ): void {
		printf(
			'<div class="subly-check subly-check--%s"><span class="subly-check__dot"></span><div>'
				. '<span class="subly-check__label">%s</span><span class="subly-check__detail">%s</span>',
			$check['ok'] ? 'ok' : 'bad',
			esc_html( $check['label'] ),
			esc_html( $check['ok'] ? $check['good'] : $check['bad'] )
		);

		if ( ! $check['ok'] && ! empty( $check['items'] ) ) {
			echo '<ul class="subly-check__items">';

			foreach ( $check['items'] as $item ) {
				echo '<li>' . wp_kses( $item, array( 'a' => array( 'href' => true ) ) ) . '</li>';
			}

			echo '</ul>';
		}

		echo '</div></div>';
	}

	public function render_tax_repair(): void {
		$repair = \Subly\Plugin::instance()->get( 'tax_repair' );
		$ids    = $repair instanceof Renewal_Tax_Repair ? $repair->affected_ids() : array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only; the repair itself was verified.
		$done   = absint( $_GET['subly_tax_repaired'] ?? 0 );
		$failed = absint( $_GET['subly_tax_repair_failed'] ?? 0 );
		$from   = Settings_Page::SLUG === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ? Renewal_Tax_Repair::FROM_SUBLY : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $ids && ! $done && ! $failed ) {
			return;
		}

		printf(
			'<tr valign="top" id="%s"><th scope="row" class="titledesc">%s</th><td class="forminp">',
			esc_attr( Renewal_Tax_Repair::NOTICE ),
			esc_html__( 'Subscriptions renewing with tax added twice', 'subly' )
		);

		if ( $done ) {
			/* translators: %d: subscription number */
			echo '<p>' . esc_html( sprintf( __( 'Subscription #%d repaired. Its renewals now charge the price without adding tax again.', 'subly' ), $done ) ) . '</p>';
		}

		if ( $failed ) {
			/* translators: %d: subscription number */
			echo '<p><strong>' . esc_html( sprintf( __( 'Subscription #%d was not repaired: it has changed since this list was made. The list has been refreshed.', 'subly' ), $failed ) ) . '</strong></p>';
		}

		if ( $ids ) {
			echo '<p class="description">' . esc_html__( 'These subscriptions were created before Subly stored prices without tax. Because this store enters prices with tax, each renewal adds tax on top of a price that already includes it. Repair stores the price without tax, so renewals charge what the customer paid at checkout. Customers already charged extra may be owed a refund: check their past renewal orders.', 'subly' ) . '</p>';

			printf(
				'<table class="widefat striped"><thead><tr><th>%s</th><th>%s</th><th>%s</th><th></th></tr></thead><tbody>',
				esc_html__( 'Subscription', 'subly' ),
				esc_html__( 'Customer', 'subly' ),
				esc_html__( 'Recurring price', 'subly' )
			);

			foreach ( $ids as $id ) {
				$subscription = wc_get_order( $id );

				if ( ! $subscription instanceof \Subly\Domain\Subscription ) {
					continue;
				}

				$detail = add_query_arg(
					array(
						'page'         => Menu::LIST_SLUG,
						'subscription' => $id,
					),
					admin_url( 'admin.php' )
				);

				printf(
					'<tr><td><a href="%s">#%d</a></td><td>%s</td><td>%s</td><td><a class="button" href="%s" onclick="return confirm(%s)">%s</a></td></tr>',
					esc_url( $detail ),
					(int) $id,
					esc_html( (string) $subscription->get_billing_email() ),
					wp_kses_post( $subscription->get_formatted_order_total() ),
					esc_url( Renewal_Tax_Repair::repair_url( $id, $from ) ),
					esc_attr( (string) wp_json_encode( __( 'Store this subscription\'s price without tax, so its renewals stop adding tax twice?', 'subly' ) ) ),
					esc_html__( 'Repair', 'subly' )
				);
			}

			echo '</tbody></table>';
		}

		echo '</td></tr>';
	}

	/**
	 * Whether renewals can actually run. Shared with the Settings screen's status card.
	 *
	 * @return array<int, array{label: string, ok: bool, good: string, bad: string, items?: string[]}>
	 */
	public static function status_checks(): array {
		$scheduler = \Subly\Plugin::instance()->get( 'scheduler' );
		$migrator  = new Migrator();
		$slots     = \Subly\Plugin::instance()->get( 'charge_slots' );

		// An hour, not the repository's 15 minutes: a charge still being retried is not
		// stuck, and a warning that clears itself teaches merchants to ignore it.
		$stuck = $slots instanceof Charge_Slot_Repository ? $slots->stuck_charging( 60 ) : array();

		// Direct Debit takes days, not this long.
		$stale = $slots instanceof Charge_Slot_Repository ? $slots->stale_pending( 10 ) : array();

		$repair = \Subly\Plugin::instance()->get( 'tax_repair' );
		$twice  = $repair instanceof Renewal_Tax_Repair ? count( $repair->affected_ids() ) : 0;

		$checks = array(
			array(
				'label' => __( 'Renewal queue', 'subly' ),
				'ok'    => $scheduler instanceof Renewal_Scheduler ? $scheduler->queue_is_healthy() : false,
				'good'  => __( 'Processing normally', 'subly' ),
				'bad'   => __( 'Overdue tasks are piling up. Check that WordPress cron is running.', 'subly' ),
			),
			array(
				'label' => __( 'Unresolved charges', 'subly' ),
				'ok'    => empty( $stuck ),
				'good'  => __( 'None', 'subly' ),
				'bad'   => sprintf(
					/* translators: %d: number of subscriptions */
					_n(
						'%d subscription has a charge whose outcome is still unknown and is not billing. Open it and check the gateway.',
						'%d subscriptions have a charge whose outcome is still unknown and are not billing. Open them and check the gateway.',
						count( $stuck ),
						'subly'
					),
					count( $stuck )
				),
			),
			array(
				'label' => __( 'Payments awaiting confirmation', 'subly' ),
				'ok'    => empty( $stale ),
				'good'  => __( 'None overdue', 'subly' ),
				'bad'   => sprintf(
					/* translators: %d: number of renewals */
					_n(
						'%d renewal has waited more than 10 days for its payment provider to confirm it. Check that the provider\'s webhook reaches this site, and the payment in its dashboard.',
						'%d renewals have waited more than 10 days for their payment provider to confirm them. Check that the provider\'s webhook reaches this site, and the payments in its dashboard.',
						count( $stale ),
						'subly'
					),
					count( $stale )
				),
				'items' => array_map( array( self::class, 'awaiting_item' ), $stale ),
			),
			array(
				'label' => __( 'Double-charge protection', 'subly' ),
				'ok'    => $migrator->charge_slot_guard_intact(),
				'good'  => __( 'Active', 'subly' ),
				'bad'   => __( 'The unique index on the charge ledger is missing. Deactivate and reactivate Subly.', 'subly' ),
			),
			array(
				'label' => __( 'Renewal tax', 'subly' ),
				'ok'    => 0 === $twice,
				'good'  => __( 'Charged once', 'subly' ),
				'bad'   => sprintf(
					/* translators: %d: number of subscriptions */
					_n(
						'%d subscription renews with tax added twice. Repair it under WooCommerce → Settings → Subscriptions.',
						'%d subscriptions renew with tax added twice. Repair them under WooCommerce → Settings → Subscriptions.',
						$twice,
						'subly'
					),
					$twice
				),
			),
		);

		return $checks;
	}

	/**
	 * A renewal waiting on its payment provider, as HTML: the subscription, the amount, how long, and the renewal order.
	 */
	private static function awaiting_item( object $slot ): string {
		$subscription_id = (int) $slot->subscription_id;
		$order           = $slot->renewal_order_id ? wc_get_order( (int) $slot->renewal_order_id ) : null;
		$days            = (int) floor( ( time() - (int) strtotime( Charge_Slot_Repository::pending_since( $slot ) . ' UTC' ) ) / DAY_IN_SECONDS );
		$subscription    = sprintf(
			'<a href="%s">#%d</a>',
			esc_url( admin_url( 'admin.php?page=' . Menu::LIST_SLUG . '&subscription=' . $subscription_id ) ),
			$subscription_id
		);

		if ( ! $order instanceof \WC_Order ) {
			/* translators: 1: subscription number, 2: number of days */
			return sprintf( esc_html( _n( 'Subscription %1$s, waiting %2$d day', 'Subscription %1$s, waiting %2$d days', $days, 'subly' ) ), $subscription, $days );
		}

		return sprintf(
			/* translators: 1: subscription number, 2: amount, 3: number of days, 4: renewal order number */
			esc_html( _n( 'Subscription %1$s, %2$s, waiting %3$d day, renewal order %4$s', 'Subscription %1$s, %2$s, waiting %3$d days, renewal order %4$s', $days, 'subly' ) ),
			$subscription,
			wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			$days,
			sprintf( '<a href="%s">#%s</a>', esc_url( $order->get_edit_order_url() ), esc_html( $order->get_order_number() ) )
		);
	}
}
