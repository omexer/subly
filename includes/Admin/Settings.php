<?php

namespace EasySubscription\Admin;

use EasySubscription\Billing\Renewal_Scheduler;
use EasySubscription\Billing\Renewal_Tax_Repair;
use EasySubscription\Data\Charge_Slot_Repository;
use EasySubscription\Data\Migrator;
use EasySubscription\Emails\Notification_Settings;
use EasySubscription\Lifecycle\Cancellation_Policy;

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
		$this->id    = 'easysubscription';
		$this->label = __( 'Subscriptions', 'easysubscription' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_easysubscription_status', array( $this, 'render_status' ) );
		add_action( 'woocommerce_admin_field_easysubscription_tax_repair', array( $this, 'render_tax_repair' ) );
	}

	public function get_sections(): array {
		$sections = array(
			''                  => __( 'General', 'easysubscription' ),
			'customer_controls' => __( 'Customer controls', 'easysubscription' ),
			'renewal'           => __( 'Renewal & billing', 'easysubscription' ),
			'checkout'          => __( 'Cart & checkout', 'easysubscription' ),
			'notifications'     => __( 'Notifications', 'easysubscription' ),
			'paypal'            => __( 'PayPal', 'easysubscription' ),
			'stripe'            => __( 'Stripe', 'easysubscription' ),
		);

		// Core applies this in the method we are overriding; without it an extension can
		// add settings but has no tab to put them on.
		return (array) apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	public function get_settings_for_default_section(): array {
		$settings = array(
			array(
				'title' => __( 'Health', 'easysubscription' ),
				'type'  => 'title',
				'desc'  => __( 'Renewals run on the WooCommerce queue. If the queue stops, subscriptions stop billing — usually the first thing to check.', 'easysubscription' ),
				'id'    => 'easysubscription_health_title',
			),
			array( 'type' => 'easysubscription_status' ),
			array( 'type' => 'easysubscription_tax_repair' ),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_health_title',
			),

			array(
				'title' => __( 'Access', 'easysubscription' ),
				'type'  => 'title',
				'desc'  => __( 'What a live subscription grants, and what lapsing takes away.', 'easysubscription' ),
				'id'    => 'easysubscription_access_title',
			),
			array(
				'title'    => __( 'Role while subscribed', 'easysubscription' ),
				'desc_tip' => __( 'Assigned when a subscription becomes active. Administrators are never changed.', 'easysubscription' ),
				'type'     => 'select',
				'id'       => 'easysubscription_active_role',
				'default'  => '',
				'options'  => self::role_options(),
			),
			array(
				'title'    => __( 'Role once it ends', 'easysubscription' ),
				'desc_tip' => __( 'Assigned only when the customer has no other live subscription.', 'easysubscription' ),
				'type'     => 'select',
				'id'       => 'easysubscription_inactive_role',
				'default'  => '',
				'options'  => self::role_options(),
			),
			array(
				'title'    => __( 'Delete data when the plugin is deleted', 'easysubscription' ),
				'desc'     => __( 'Remove EasySubscription\'s settings and its own database tables on uninstall', 'easysubscription' ),
				'type'     => 'checkbox',
				'id'       => 'easysubscription_delete_data_on_uninstall',
				'default'  => 'no',
				'desc_tip' => __( 'Off by default, so deactivating or deleting the plugin to try something else loses nothing. Subscriptions and their orders are never deleted either way — they are WooCommerce orders and part of your financial record.', 'easysubscription' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_access_title',
			),
		);

		return $settings;
	}

	public function get_settings_for_customer_controls_section(): array {
		return array(
			array(
				'title' => __( 'Customer controls', 'easysubscription' ),
				'type'  => 'title',
				'id'    => 'easysubscription_customer_controls_title',
			),
			array(
				'title'    => __( 'Allow customers to renew early', 'easysubscription' ),
				'desc'     => __( 'Customers can pay their next renewal before it is due. The renewal date stays the same.', 'easysubscription' ),
				'type'     => 'checkbox',
				'id'       => \EasySubscription\Lifecycle\Early_Renewal::OPTION,
				'default'  => 'no',
				'desc_tip' => __( 'Only offered for cards EasySubscription charges itself, never for PayPal, which bills from a plan of its own.', 'easysubscription' ),
			),
			array(
				'title'   => __( 'Allow customers to cancel', 'easysubscription' ),
				'desc'    => __( 'Customers can cancel from My Account. When off, they are asked to contact you. In the UK and EU, customers must be able to cancel as easily as they signed up.', 'easysubscription' ),
				'type'    => 'checkbox',
				'id'      => Cancellation_Policy::OPTION,
				'default' => 'yes',
			),
			array(
				'title'          => __( 'Cancellation takes effect', 'easysubscription' ),
				'desc'           => __( 'At the end of the billing cycle, the customer keeps access until the date they have paid for. Immediately ends access at once, with no refund for the time left.', 'easysubscription' ),
				'type'           => 'select',
				'id'             => Cancellation_Policy::OPTION_WHEN,
				'default'        => Cancellation_Policy::AT_PERIOD_END,
				'options'        => array(
					Cancellation_Policy::AT_PERIOD_END => __( 'End of billing cycle', 'easysubscription' ),
					Cancellation_Policy::IMMEDIATELY   => __( 'Immediately', 'easysubscription' ),
				),
				'easysubscription_show_if' => Cancellation_Policy::OPTION,
			),
			array(
				'title'   => __( 'Allow customers to turn off auto-renew', 'easysubscription' ),
				'desc'    => __( 'Customers can stop their subscription renewing. It then ends at the end of the current period, unless they turn renewal back on.', 'easysubscription' ),
				'type'    => 'checkbox',
				'id'      => 'easysubscription_allow_auto_renew_toggle',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_customer_controls_title',
			),
		);
	}

	public function get_settings_for_renewal_section(): array {
		$settings = array(
			array(
				'title' => __( 'Grace period', 'easysubscription' ),
				'type'  => 'title',
				'id'    => 'easysubscription_grace_title',
			),
			array(
				'title'             => __( 'Grace period after due date (Days)', 'easysubscription' ),
				'desc_tip'          => __( 'The customer keeps access for this many days after a renewal payment fails. Retries happen within this window; when it ends, the action above applies.', 'easysubscription' ),
				'id'                => 'easysubscription_grace_period_days',
				'type'              => 'number',
				'default'           => 7,
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_grace_title',
			),

			array(
				'title' => __( 'Renewal', 'easysubscription' ),
				'type'  => 'title',
				'id'    => 'easysubscription_renewals_title',
			),
			array(
				'title'   => __( 'Missed renewals', 'easysubscription' ),
				'desc'    => __( 'What to do when a subscription falls more than one period behind, usually because the site stopped running its scheduled tasks. Charging for every missed period bills the customer several times at once, so only choose it if you deliver every period anyway.', 'easysubscription' ),
				'id'      => 'easysubscription_catch_up_policy',
				'type'    => 'select',
				'default' => 'rebase',
				'options' => array(
					'rebase'     => __( 'Charge once and move the schedule forward (recommended)', 'easysubscription' ),
					'charge_all' => __( 'Charge for every missed period', 'easysubscription' ),
				),
			),
			array(
				'title'   => __( 'Free first payments', 'easysubscription' ),
				'desc'    => __( 'Ask for a payment method even when the first payment is zero, such as a free trial with no sign-up fee. Without one there is nothing to charge when the first renewal is due. Offline methods like bank transfer store nothing to charge either.', 'easysubscription' ),
				'id'      => \EasySubscription\Checkout\Trial_Payment::OPTION,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_renewals_title',
			),
		);

		// Only Pro's payment retries read it; without Pro a failed renewal goes on hold at once, and its card has nothing else.
		if ( ! did_action( 'easysubscription_pro_loaded' ) ) {
			$settings = array_values( array_filter( $settings, static fn( array $setting ): bool => ! in_array( $setting['id'], array( 'easysubscription_grace_period_days', 'easysubscription_grace_title' ), true ) ) );
		}

		return $settings;
	}

	public function get_settings_for_checkout_section(): array {
		return array(
			array(
				'title' => __( 'Cart & checkout', 'easysubscription' ),
				'type'  => 'title',
				'id'    => 'easysubscription_checkout_title',
			),
			array(
				'title'    => __( 'Buying without an account', 'easysubscription' ),
				'desc_tip' => __( 'A subscription has to belong to someone, so a guest who buys one gets an account created at checkout.', 'easysubscription' ),
				'type'     => 'select',
				'id'       => 'easysubscription_guest_checkout',
				'default'  => 'create_account',
				'options'  => array(
					'create_account' => __( 'Create an account for them automatically', 'easysubscription' ),
					'require_login'  => __( 'Require them to log in first', 'easysubscription' ),
				),
			),
			array(
				'title'   => __( 'Allow mixed checkout', 'easysubscription' ),
				'desc'    => __( 'Customers can buy a subscription and one-time products in the same order. When off, a subscription is checked out on its own.', 'easysubscription' ),
				'type'    => 'checkbox',
				'id'      => \EasySubscription\Checkout\Cart_Validation::OPTION_MIXED,
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Enable one-click checkout', 'easysubscription' ),
				'desc'    => __( 'Adding a subscription to the cart takes the customer straight to checkout.', 'easysubscription' ),
				'type'    => 'checkbox',
				'id'      => \EasySubscription\Checkout\One_Click_Checkout::OPTION,
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_checkout_title',
			),

			array(
				'title' => __( 'Custom labels', 'easysubscription' ),
				'type'  => 'title',
				'id'    => 'easysubscription_labels_title',
			),
			array(
				'title'       => __( 'Subscribe button text', 'easysubscription' ),
				'desc_tip'    => __( 'The add to cart button on subscription products, on the product page and in product lists. Leave empty to use "Subscribe".', 'easysubscription' ),
				'type'        => 'text',
				'id'          => \EasySubscription\Frontend\Product_Display::OPTION_BUTTON_TEXT,
				'default'     => '',
				'placeholder' => __( 'Subscribe', 'easysubscription' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_labels_title',
			),
		);
	}

	public function get_settings_for_notifications_section(): array {
		return Notification_Settings::fields();
	}

	public function get_settings_for_paypal_section(): array {
		return array(
			array(
				'title' => __( 'PayPal Subscriptions', 'easysubscription' ),
				'type'  => 'title',
				'desc'  => __( 'PayPal manages the billing schedule itself and tells EasySubscription about each payment by webhook. Add the webhook URL below to your PayPal app before going live, or renewals will not be recorded.', 'easysubscription' ),
				'id'    => 'easysubscription_paypal_title',
			),
			array(
				'title'   => __( 'Enable PayPal', 'easysubscription' ),
				'desc'    => __( 'Offer PayPal for subscription purchases', 'easysubscription' ),
				'id'      => 'easysubscription_paypal_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Environment', 'easysubscription' ),
				'id'      => 'easysubscription_paypal_live',
				'type'    => 'select',
				'default' => 'no',
				'options' => array(
					'no'  => __( 'Sandbox (testing)', 'easysubscription' ),
					'yes' => __( 'Live', 'easysubscription' ),
				),
			),
			array(
				'title' => __( 'Client ID', 'easysubscription' ),
				'id'    => 'easysubscription_paypal_client_id',
				'type'  => 'text',
				'css'   => 'width:26rem',
			),
			array(
				'title' => __( 'Secret', 'easysubscription' ),
				'id'    => 'easysubscription_paypal_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'title'    => __( 'Webhook ID', 'easysubscription' ),
				'desc'     => __( 'From your PayPal app, after you add the webhook URL below. Without it EasySubscription cannot verify that a webhook really came from PayPal, and will reject every one.', 'easysubscription' ),
				'id'       => 'easysubscription_paypal_webhook_id',
				'type'     => 'text',
				'css'      => 'width:26rem',
				'desc_tip' => false,
			),
			array(
				'title'             => __( 'Webhook URL', 'easysubscription' ),
				'desc'              => __( 'Add this URL to your PayPal app and subscribe it to the billing subscription and payment sale events.', 'easysubscription' ),
				'id'                => 'easysubscription_paypal_webhook_url',
				'type'              => 'text',
				'value'             => rest_url( 'easysubscription/v1/webhook/paypal' ),
				'custom_attributes' => array(
					'readonly' => 'readonly',
					'onclick'  => 'this.select()',
				),
				'css'               => 'width:26rem',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_paypal_title',
			),
		);
	}

	public function get_settings_for_stripe_section(): array {
		return array(
			array(
				'title' => __( 'Stripe', 'easysubscription' ),
				'type'  => 'title',
				'desc'  => __( 'Cards are collected by Stripe and charged again automatically when a renewal falls due. EasySubscription keeps the schedule; Stripe only stores the card.', 'easysubscription' ),
				'id'    => 'easysubscription_stripe_title',
			),
			array(
				'title'   => __( 'Enable Stripe', 'easysubscription' ),
				'desc'    => __( 'Offer card payments for subscription purchases', 'easysubscription' ),
				'id'      => 'easysubscription_stripe_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Environment', 'easysubscription' ),
				'id'      => 'easysubscription_stripe_live',
				'type'    => 'select',
				'default' => 'no',
				'options' => array(
					'no'  => __( 'Test mode', 'easysubscription' ),
					'yes' => __( 'Live', 'easysubscription' ),
				),
			),
			array(
				'title' => __( 'Test secret key', 'easysubscription' ),
				'id'    => 'easysubscription_stripe_test_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'title' => __( 'Live secret key', 'easysubscription' ),
				'id'    => 'easysubscription_stripe_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'easysubscription_stripe_title',
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
		return array( '' => __( 'Leave the role alone', 'easysubscription' ) ) + \EasySubscription\Lifecycle\Role_Management::grantable_roles();
	}

	public function render_status(): void {
		echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html__( 'Status', 'easysubscription' ) . '</th><td class="forminp"><div class="easysubscription-checks">';

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
			'<div class="easysubscription-check easysubscription-check--%s"><span class="easysubscription-check__dot"></span><div>'
				. '<span class="easysubscription-check__label">%s</span><span class="easysubscription-check__detail">%s</span>',
			$check['ok'] ? 'ok' : 'bad',
			esc_html( $check['label'] ),
			esc_html( $check['ok'] ? $check['good'] : $check['bad'] )
		);

		if ( ! $check['ok'] && ! empty( $check['items'] ) ) {
			echo '<ul class="easysubscription-check__items">';

			foreach ( $check['items'] as $item ) {
				echo '<li>' . wp_kses( $item, array( 'a' => array( 'href' => true ) ) ) . '</li>';
			}

			echo '</ul>';
		}

		echo '</div></div>';
	}

	public function render_tax_repair(): void {
		$repair = \EasySubscription\Plugin::instance()->get( 'tax_repair' );
		$ids    = $repair instanceof Renewal_Tax_Repair ? $repair->affected_ids() : array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only; the repair itself was verified.
		$done   = absint( $_GET['easysubscription_tax_repaired'] ?? 0 );
		$failed = absint( $_GET['easysubscription_tax_repair_failed'] ?? 0 );
		$from   = Settings_Page::SLUG === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ? Renewal_Tax_Repair::FROM_EASYSUBSCRIPTION : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $ids && ! $done && ! $failed ) {
			return;
		}

		printf(
			'<tr valign="top" id="%s"><th scope="row" class="titledesc">%s</th><td class="forminp">',
			esc_attr( Renewal_Tax_Repair::NOTICE ),
			esc_html__( 'Subscriptions renewing with tax added twice', 'easysubscription' )
		);

		if ( $done ) {
			/* translators: %d: subscription number */
			echo '<p>' . esc_html( sprintf( __( 'Subscription #%d repaired. Its renewals now charge the price without adding tax again.', 'easysubscription' ), $done ) ) . '</p>';
		}

		if ( $failed ) {
			/* translators: %d: subscription number */
			echo '<p><strong>' . esc_html( sprintf( __( 'Subscription #%d was not repaired: it has changed since this list was made. The list has been refreshed.', 'easysubscription' ), $failed ) ) . '</strong></p>';
		}

		if ( $ids ) {
			echo '<p class="description">' . esc_html__( 'These subscriptions were created before EasySubscription stored prices without tax. Because this store enters prices with tax, each renewal adds tax on top of a price that already includes it. Repair stores the price without tax, so renewals charge what the customer paid at checkout. Customers already charged extra may be owed a refund: check their past renewal orders.', 'easysubscription' ) . '</p>';

			printf(
				'<table class="widefat striped"><thead><tr><th>%s</th><th>%s</th><th>%s</th><th></th></tr></thead><tbody>',
				esc_html__( 'Subscription', 'easysubscription' ),
				esc_html__( 'Customer', 'easysubscription' ),
				esc_html__( 'Recurring price', 'easysubscription' )
			);

			foreach ( $ids as $id ) {
				$subscription = wc_get_order( $id );

				if ( ! $subscription instanceof \EasySubscription\Domain\Subscription ) {
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
					esc_attr( (string) wp_json_encode( __( 'Store this subscription\'s price without tax, so its renewals stop adding tax twice?', 'easysubscription' ) ) ),
					esc_html__( 'Repair', 'easysubscription' )
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
		$scheduler = \EasySubscription\Plugin::instance()->get( 'scheduler' );
		$migrator  = new Migrator();
		$slots     = \EasySubscription\Plugin::instance()->get( 'charge_slots' );

		// An hour, not the repository's 15 minutes: a charge still being retried is not
		// stuck, and a warning that clears itself teaches merchants to ignore it.
		$stuck = $slots instanceof Charge_Slot_Repository ? $slots->stuck_charging( 60 ) : array();

		// Direct Debit takes days, not this long.
		$stale = $slots instanceof Charge_Slot_Repository ? $slots->stale_pending( 10 ) : array();

		$repair = \EasySubscription\Plugin::instance()->get( 'tax_repair' );
		$twice  = $repair instanceof Renewal_Tax_Repair ? count( $repair->affected_ids() ) : 0;

		$checks = array(
			array(
				'label' => __( 'Renewal queue', 'easysubscription' ),
				'ok'    => $scheduler instanceof Renewal_Scheduler ? $scheduler->queue_is_healthy() : false,
				'good'  => __( 'Processing normally', 'easysubscription' ),
				'bad'   => __( 'Overdue tasks are piling up. Check that WordPress cron is running.', 'easysubscription' ),
			),
			array(
				'label' => __( 'Unresolved charges', 'easysubscription' ),
				'ok'    => empty( $stuck ),
				'good'  => __( 'None', 'easysubscription' ),
				'bad'   => sprintf(
					/* translators: %d: number of subscriptions */
					_n(
						'%d subscription has a charge whose outcome is still unknown and is not billing. Open it and check the gateway.',
						'%d subscriptions have a charge whose outcome is still unknown and are not billing. Open them and check the gateway.',
						count( $stuck ),
						'easysubscription'
					),
					count( $stuck )
				),
			),
			array(
				'label' => __( 'Payments awaiting confirmation', 'easysubscription' ),
				'ok'    => empty( $stale ),
				'good'  => __( 'None overdue', 'easysubscription' ),
				'bad'   => sprintf(
					/* translators: %d: number of renewals */
					_n(
						'%d renewal has waited more than 10 days for its payment provider to confirm it. Check that the provider\'s webhook reaches this site, and the payment in its dashboard.',
						'%d renewals have waited more than 10 days for their payment provider to confirm them. Check that the provider\'s webhook reaches this site, and the payments in its dashboard.',
						count( $stale ),
						'easysubscription'
					),
					count( $stale )
				),
				'items' => array_map( array( self::class, 'awaiting_item' ), $stale ),
			),
			array(
				'label' => __( 'Double-charge protection', 'easysubscription' ),
				'ok'    => $migrator->charge_slot_guard_intact(),
				'good'  => __( 'Active', 'easysubscription' ),
				'bad'   => __( 'The unique index on the charge ledger is missing. Deactivate and reactivate EasySubscription.', 'easysubscription' ),
			),
			array(
				'label' => __( 'Renewal tax', 'easysubscription' ),
				'ok'    => 0 === $twice,
				'good'  => __( 'Charged once', 'easysubscription' ),
				'bad'   => sprintf(
					/* translators: %d: number of subscriptions */
					_n(
						'%d subscription renews with tax added twice. Repair it under WooCommerce → Settings → Subscriptions.',
						'%d subscriptions renew with tax added twice. Repair them under WooCommerce → Settings → Subscriptions.',
						$twice,
						'easysubscription'
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
			return sprintf( esc_html( _n( 'Subscription %1$s, waiting %2$d day', 'Subscription %1$s, waiting %2$d days', $days, 'easysubscription' ) ), $subscription, $days );
		}

		return sprintf(
			/* translators: 1: subscription number, 2: amount, 3: number of days, 4: renewal order number */
			esc_html( _n( 'Subscription %1$s, %2$s, waiting %3$d day, renewal order %4$s', 'Subscription %1$s, %2$s, waiting %3$d days, renewal order %4$s', $days, 'easysubscription' ) ),
			$subscription,
			wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			$days,
			sprintf( '<a href="%s">#%s</a>', esc_url( $order->get_edit_order_url() ), esc_html( $order->get_order_number() ) )
		);
	}
}
