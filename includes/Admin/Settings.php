<?php

namespace SubKit\Admin;

use SubKit\Billing\Renewal_Scheduler;
use SubKit\Billing\Renewal_Tax_Repair;
use SubKit\Data\Charge_Slot_Repository;
use SubKit\Data\Migrator;

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
		$this->id    = 'subkit';
		$this->label = __( 'Subscriptions', 'subkit-subscriptions' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_subkit_status', array( $this, 'render_status' ) );
		add_action( 'woocommerce_admin_field_subkit_tax_repair', array( $this, 'render_tax_repair' ) );
	}

	public function get_sections(): array {
		$sections = array(
			''       => __( 'General', 'subkit-subscriptions' ),
			'paypal' => __( 'PayPal', 'subkit-subscriptions' ),
			'stripe' => __( 'Stripe', 'subkit-subscriptions' ),
		);

		// Core applies this in the method we are overriding; without it an extension can
		// add settings but has no tab to put them on.
		return (array) apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	public function get_settings_for_default_section(): array {
		return array(
			array(
				'title' => __( 'Health', 'subkit-subscriptions' ),
				'type'  => 'title',
				'desc'  => __( 'Renewals run on the WooCommerce queue. If the queue stops, subscriptions stop billing — usually the first thing to check.', 'subkit-subscriptions' ),
				'id'    => 'subkit_health_title',
			),
			array( 'type' => 'subkit_status' ),
			array( 'type' => 'subkit_tax_repair' ),
			array(
				'type' => 'sectionend',
				'id'   => 'subkit_health_title',
			),

			array(
				'title' => __( 'Renewals', 'subkit-subscriptions' ),
				'type'  => 'title',
				'id'    => 'subkit_renewals_title',
			),
			array(
				'title'    => __( 'Missed renewals', 'subkit-subscriptions' ),
				'desc'     => __( 'What to do when a subscription is overdue by more than one period, usually because the site had no traffic and the queue stalled.', 'subkit-subscriptions' ),
				'id'       => 'subkit_catch_up_policy',
				'type'     => 'select',
				'default'  => 'rebase',
				'options'  => array(
					'rebase'     => __( 'Charge once and move the schedule forward (recommended)', 'subkit-subscriptions' ),
					'charge_all' => __( 'Charge for every missed period', 'subkit-subscriptions' ),
				),
				'desc_tip' => __( 'Charging for every missed period bills a customer several times at once for an outage they did not cause. Only use it if you ship goods for every period regardless.', 'subkit-subscriptions' ),
			),
			array(
				'title'    => __( 'Grace period (days)', 'subkit-subscriptions' ),
				'desc'     => __( 'How long to keep trying after a payment fails before giving up. Access continues during this window.', 'subkit-subscriptions' ),
				'id'       => 'subkit_grace_period_days',
				'type'     => 'number',
				'default'  => 7,
				'desc_tip' => true,
			),
			array(
				'title'             => __( 'Remind before charging', 'subkit-subscriptions' ),
				'desc'              => __( 'days ahead', 'subkit-subscriptions' ),
				'id'                => 'subkit_renewal_reminder_days',
				'type'              => 'number',
				'default'           => 3,
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'desc_tip'          => __( 'Emails the customer before their card is charged, which is what stops an unexpected charge becoming a chargeback. 0 turns the reminder off. A renewal falling due sooner than this is not warned about, since the email would arrive after the charge.', 'subkit-subscriptions' ),
			),
			array(
				'title'    => __( 'Free first payments', 'subkit-subscriptions' ),
				'desc'     => __( 'Ask for a payment method even when the first payment is nothing', 'subkit-subscriptions' ),
				'id'       => \SubKit\Checkout\Trial_Payment::OPTION,
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc_tip' => __( 'A trial with no sign-up fee, or a coupon worth more than the first payment, makes the first order zero, and WooCommerce skips the payment step for a zero-total order — so nothing is stored to charge the next payment. A plan that renews for nothing is not asked. Offline methods (bank transfer, cheque, cash on delivery) store no card, so choosing one still leaves nothing to charge later. Switch this off only if you would rather chase customers for a card later.', 'subkit-subscriptions' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subkit_renewals_title',
			),

			array(
				'title' => __( 'Access', 'subkit-subscriptions' ),
				'type'  => 'title',
				'desc'  => __( 'What a live subscription grants, and what lapsing takes away.', 'subkit-subscriptions' ),
				'id'    => 'subkit_access_title',
			),
			array(
				'title'    => __( 'Buying without an account', 'subkit-subscriptions' ),
				'desc_tip' => __( 'A subscription has to belong to someone, so a guest who buys one gets an account created at checkout.', 'subkit-subscriptions' ),
				'type'     => 'select',
				'id'       => 'subkit_guest_checkout',
				'default'  => 'create_account',
				'options'  => array(
					'create_account' => __( 'Create an account for them automatically', 'subkit-subscriptions' ),
					'require_login'  => __( 'Require them to log in first', 'subkit-subscriptions' ),
				),
			),
			array(
				'title'    => __( 'Let customers pay early', 'subkit-subscriptions' ),
				'desc'     => __( 'Show a button in My Account that charges the next period now', 'subkit-subscriptions' ),
				'type'     => 'checkbox',
				'id'       => \SubKit\Lifecycle\Early_Renewal::OPTION,
				'default'  => 'no',
				'desc_tip' => __( 'The renewal date does not move: paying early settles the payment that was already coming. Only offered for cards SubKit charges itself, never for PayPal, which bills from a plan of its own.', 'subkit-subscriptions' ),
			),
			array(
				'title'   => __( 'Let customers turn off renewal', 'subkit-subscriptions' ),
				'desc'    => __( 'Show a switch in My Account that ends the subscription at the end of the paid period instead of cancelling it outright.', 'subkit-subscriptions' ),
				'type'    => 'checkbox',
				'id'      => 'subkit_allow_auto_renew_toggle',
				'default' => 'no',
			),
			array(
				'title'    => __( 'Role while subscribed', 'subkit-subscriptions' ),
				'desc_tip' => __( 'Assigned when a subscription becomes active. Administrators are never changed.', 'subkit-subscriptions' ),
				'type'     => 'select',
				'id'       => 'subkit_active_role',
				'default'  => '',
				'options'  => self::role_options(),
			),
			array(
				'title'    => __( 'Role once it ends', 'subkit-subscriptions' ),
				'desc_tip' => __( 'Assigned only when the customer has no other live subscription.', 'subkit-subscriptions' ),
				'type'     => 'select',
				'id'       => 'subkit_inactive_role',
				'default'  => '',
				'options'  => self::role_options(),
			),
			array(
				'title'    => __( 'Delete data when the plugin is deleted', 'subkit-subscriptions' ),
				'desc'     => __( 'Remove SubKit\'s settings and its own database tables on uninstall', 'subkit-subscriptions' ),
				'type'     => 'checkbox',
				'id'       => 'subkit_delete_data_on_uninstall',
				'default'  => 'no',
				'desc_tip' => __( 'Off by default, so deactivating or deleting the plugin to try something else loses nothing. Subscriptions and their orders are never deleted either way — they are WooCommerce orders and part of your financial record.', 'subkit-subscriptions' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subkit_access_title',
			),
		);
	}

	public function get_settings_for_paypal_section(): array {
		return array(
			array(
				'title' => __( 'PayPal Subscriptions', 'subkit-subscriptions' ),
				'type'  => 'title',
				'desc'  => __( 'PayPal manages the billing schedule itself and tells SubKit about each payment by webhook. Add the webhook URL below to your PayPal app before going live, or renewals will not be recorded.', 'subkit-subscriptions' ),
				'id'    => 'subkit_paypal_title',
			),
			array(
				'title'   => __( 'Enable PayPal', 'subkit-subscriptions' ),
				'desc'    => __( 'Offer PayPal for subscription purchases', 'subkit-subscriptions' ),
				'id'      => 'subkit_paypal_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Environment', 'subkit-subscriptions' ),
				'id'      => 'subkit_paypal_live',
				'type'    => 'select',
				'default' => 'no',
				'options' => array(
					'no'  => __( 'Sandbox (testing)', 'subkit-subscriptions' ),
					'yes' => __( 'Live', 'subkit-subscriptions' ),
				),
			),
			array(
				'title' => __( 'Client ID', 'subkit-subscriptions' ),
				'id'    => 'subkit_paypal_client_id',
				'type'  => 'text',
				'css'   => 'width:26rem',
			),
			array(
				'title' => __( 'Secret', 'subkit-subscriptions' ),
				'id'    => 'subkit_paypal_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'title'    => __( 'Webhook ID', 'subkit-subscriptions' ),
				'desc'     => __( 'From your PayPal app, after you add the webhook URL below. Without it SubKit cannot verify that a webhook really came from PayPal, and will reject every one.', 'subkit-subscriptions' ),
				'id'       => 'subkit_paypal_webhook_id',
				'type'     => 'text',
				'css'      => 'width:26rem',
				'desc_tip' => false,
			),
			array(
				'title'             => __( 'Webhook URL', 'subkit-subscriptions' ),
				'desc'              => __( 'Add this URL to your PayPal app and subscribe it to the billing subscription and payment sale events.', 'subkit-subscriptions' ),
				'id'                => 'subkit_paypal_webhook_url',
				'type'              => 'text',
				'value'             => rest_url( 'subkit/v1/webhook/paypal' ),
				'custom_attributes' => array(
					'readonly' => 'readonly',
					'onclick'  => 'this.select()',
				),
				'css'               => 'width:26rem',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subkit_paypal_title',
			),
		);
	}

	public function get_settings_for_stripe_section(): array {
		return array(
			array(
				'title' => __( 'Stripe', 'subkit-subscriptions' ),
				'type'  => 'title',
				'desc'  => __( 'Cards are collected by Stripe and charged again automatically when a renewal falls due. SubKit keeps the schedule; Stripe only stores the card.', 'subkit-subscriptions' ),
				'id'    => 'subkit_stripe_title',
			),
			array(
				'title'   => __( 'Enable Stripe', 'subkit-subscriptions' ),
				'desc'    => __( 'Offer card payments for subscription purchases', 'subkit-subscriptions' ),
				'id'      => 'subkit_stripe_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Environment', 'subkit-subscriptions' ),
				'id'      => 'subkit_stripe_live',
				'type'    => 'select',
				'default' => 'no',
				'options' => array(
					'no'  => __( 'Test mode', 'subkit-subscriptions' ),
					'yes' => __( 'Live', 'subkit-subscriptions' ),
				),
			),
			array(
				'title' => __( 'Test secret key', 'subkit-subscriptions' ),
				'id'    => 'subkit_stripe_test_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'title' => __( 'Live secret key', 'subkit-subscriptions' ),
				'id'    => 'subkit_stripe_secret',
				'type'  => 'password',
				'css'   => 'width:26rem',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'subkit_stripe_title',
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
		return array( '' => __( 'Leave the role alone', 'subkit-subscriptions' ) ) + \SubKit\Lifecycle\Role_Management::grantable_roles();
	}

	public function render_status(): void {
		echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html__( 'Status', 'subkit-subscriptions' ) . '</th><td class="forminp"><div class="subkit-checks">';

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
			'<div class="subkit-check subkit-check--%s"><span class="subkit-check__dot"></span><div>'
				. '<span class="subkit-check__label">%s</span><span class="subkit-check__detail">%s</span>',
			$check['ok'] ? 'ok' : 'bad',
			esc_html( $check['label'] ),
			esc_html( $check['ok'] ? $check['good'] : $check['bad'] )
		);

		if ( ! $check['ok'] && ! empty( $check['items'] ) ) {
			echo '<ul class="subkit-check__items">';

			foreach ( $check['items'] as $item ) {
				echo '<li>' . wp_kses( $item, array( 'a' => array( 'href' => true ) ) ) . '</li>';
			}

			echo '</ul>';
		}

		echo '</div></div>';
	}

	public function render_tax_repair(): void {
		$repair = \SubKit\Plugin::instance()->get( 'tax_repair' );
		$ids    = $repair instanceof Renewal_Tax_Repair ? $repair->affected_ids() : array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only; the repair itself was verified.
		$done   = absint( $_GET['subkit_tax_repaired'] ?? 0 );
		$failed = absint( $_GET['subkit_tax_repair_failed'] ?? 0 );
		$from   = Settings_Page::SLUG === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ? Renewal_Tax_Repair::FROM_SUBKIT : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $ids && ! $done && ! $failed ) {
			return;
		}

		printf(
			'<tr valign="top" id="%s"><th scope="row" class="titledesc">%s</th><td class="forminp">',
			esc_attr( Renewal_Tax_Repair::NOTICE ),
			esc_html__( 'Subscriptions renewing with tax added twice', 'subkit-subscriptions' )
		);

		if ( $done ) {
			/* translators: %d: subscription number */
			echo '<p>' . esc_html( sprintf( __( 'Subscription #%d repaired. Its renewals now charge the price without adding tax again.', 'subkit-subscriptions' ), $done ) ) . '</p>';
		}

		if ( $failed ) {
			/* translators: %d: subscription number */
			echo '<p><strong>' . esc_html( sprintf( __( 'Subscription #%d was not repaired: it has changed since this list was made. The list has been refreshed.', 'subkit-subscriptions' ), $failed ) ) . '</strong></p>';
		}

		if ( $ids ) {
			echo '<p class="description">' . esc_html__( 'These subscriptions were created before SubKit stored prices without tax. Because this store enters prices with tax, each renewal adds tax on top of a price that already includes it. Repair stores the price without tax, so renewals charge what the customer paid at checkout. Customers already charged extra may be owed a refund: check their past renewal orders.', 'subkit-subscriptions' ) . '</p>';

			printf(
				'<table class="widefat striped"><thead><tr><th>%s</th><th>%s</th><th>%s</th><th></th></tr></thead><tbody>',
				esc_html__( 'Subscription', 'subkit-subscriptions' ),
				esc_html__( 'Customer', 'subkit-subscriptions' ),
				esc_html__( 'Recurring price', 'subkit-subscriptions' )
			);

			foreach ( $ids as $id ) {
				$subscription = wc_get_order( $id );

				if ( ! $subscription instanceof \SubKit\Domain\Subscription ) {
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
					esc_attr( (string) wp_json_encode( __( 'Store this subscription\'s price without tax, so its renewals stop adding tax twice?', 'subkit-subscriptions' ) ) ),
					esc_html__( 'Repair', 'subkit-subscriptions' )
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
		$scheduler = \SubKit\Plugin::instance()->get( 'scheduler' );
		$migrator  = new Migrator();
		$slots     = \SubKit\Plugin::instance()->get( 'charge_slots' );

		// An hour, not the repository's 15 minutes: a charge still being retried is not
		// stuck, and a warning that clears itself teaches merchants to ignore it.
		$stuck = $slots instanceof Charge_Slot_Repository ? $slots->stuck_charging( 60 ) : array();

		// Direct Debit takes days, not this long.
		$stale = $slots instanceof Charge_Slot_Repository ? $slots->stale_pending( 10 ) : array();

		$repair = \SubKit\Plugin::instance()->get( 'tax_repair' );
		$twice  = $repair instanceof Renewal_Tax_Repair ? count( $repair->affected_ids() ) : 0;

		$checks = array(
			array(
				'label' => __( 'Renewal queue', 'subkit-subscriptions' ),
				'ok'    => $scheduler instanceof Renewal_Scheduler ? $scheduler->queue_is_healthy() : false,
				'good'  => __( 'Processing normally', 'subkit-subscriptions' ),
				'bad'   => __( 'Overdue tasks are piling up. Check that WordPress cron is running.', 'subkit-subscriptions' ),
			),
			array(
				'label' => __( 'Unresolved charges', 'subkit-subscriptions' ),
				'ok'    => empty( $stuck ),
				'good'  => __( 'None', 'subkit-subscriptions' ),
				'bad'   => sprintf(
					/* translators: %d: number of subscriptions */
					_n(
						'%d subscription has a charge whose outcome is still unknown and is not billing. Open it and check the gateway.',
						'%d subscriptions have a charge whose outcome is still unknown and are not billing. Open them and check the gateway.',
						count( $stuck ),
						'subkit-subscriptions'
					),
					count( $stuck )
				),
			),
			array(
				'label' => __( 'Payments awaiting confirmation', 'subkit-subscriptions' ),
				'ok'    => empty( $stale ),
				'good'  => __( 'None overdue', 'subkit-subscriptions' ),
				'bad'   => sprintf(
					/* translators: %d: number of renewals */
					_n(
						'%d renewal has waited more than 10 days for its payment provider to confirm it. Check that the provider\'s webhook reaches this site, and the payment in its dashboard.',
						'%d renewals have waited more than 10 days for their payment provider to confirm them. Check that the provider\'s webhook reaches this site, and the payments in its dashboard.',
						count( $stale ),
						'subkit-subscriptions'
					),
					count( $stale )
				),
				'items' => array_map( array( self::class, 'awaiting_item' ), $stale ),
			),
			array(
				'label' => __( 'Double-charge protection', 'subkit-subscriptions' ),
				'ok'    => $migrator->charge_slot_guard_intact(),
				'good'  => __( 'Active', 'subkit-subscriptions' ),
				'bad'   => __( 'The unique index on the charge ledger is missing. Deactivate and reactivate SubKit.', 'subkit-subscriptions' ),
			),
			array(
				'label' => __( 'Renewal tax', 'subkit-subscriptions' ),
				'ok'    => 0 === $twice,
				'good'  => __( 'Charged once', 'subkit-subscriptions' ),
				'bad'   => sprintf(
					/* translators: %d: number of subscriptions */
					_n(
						'%d subscription renews with tax added twice. Repair it under WooCommerce → Settings → Subscriptions.',
						'%d subscriptions renew with tax added twice. Repair them under WooCommerce → Settings → Subscriptions.',
						$twice,
						'subkit-subscriptions'
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
			return sprintf( esc_html( _n( 'Subscription %1$s, waiting %2$d day', 'Subscription %1$s, waiting %2$d days', $days, 'subkit-subscriptions' ) ), $subscription, $days );
		}

		return sprintf(
			/* translators: 1: subscription number, 2: amount, 3: number of days, 4: renewal order number */
			esc_html( _n( 'Subscription %1$s, %2$s, waiting %3$d day, renewal order %4$s', 'Subscription %1$s, %2$s, waiting %3$d days, renewal order %4$s', $days, 'subkit-subscriptions' ) ),
			$subscription,
			wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			$days,
			sprintf( '<a href="%s">#%s</a>', esc_url( $order->get_edit_order_url() ), esc_html( $order->get_order_number() ) )
		);
	}
}
