<?php

namespace SubKit\Admin;

use SubKit\Billing\Renewal_Scheduler;
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
	}

	public function get_sections(): array {
		return array(
			''       => __( 'General', 'subkit-subscriptions' ),
			'paypal' => __( 'PayPal', 'subkit-subscriptions' ),
		);
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
			array( 'type' => 'sectionend', 'id' => 'subkit_health_title' ),

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
			array( 'type' => 'sectionend', 'id' => 'subkit_renewals_title' ),
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
				'custom_attributes' => array( 'readonly' => 'readonly', 'onclick' => 'this.select()' ),
				'css'               => 'width:26rem',
			),
			array( 'type' => 'sectionend', 'id' => 'subkit_paypal_title' ),
		);
	}

	/**
	 * A plain readout of whether renewals can actually run.
	 */
	public function render_status(): void {
		$scheduler = \SubKit\Plugin::instance()->get( 'scheduler' );
		$migrator  = new Migrator();

		$checks = array(
			array(
				'label' => __( 'Renewal queue', 'subkit-subscriptions' ),
				'ok'    => $scheduler instanceof Renewal_Scheduler ? $scheduler->queue_is_healthy() : false,
				'good'  => __( 'Processing normally', 'subkit-subscriptions' ),
				'bad'   => __( 'Overdue tasks are piling up. Check that WordPress cron is running.', 'subkit-subscriptions' ),
			),
			array(
				'label' => __( 'Double-charge protection', 'subkit-subscriptions' ),
				'ok'    => $migrator->charge_slot_guard_intact(),
				'good'  => __( 'Active', 'subkit-subscriptions' ),
				'bad'   => __( 'The unique index on the charge ledger is missing. Deactivate and reactivate SubKit.', 'subkit-subscriptions' ),
			),
		);

		echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html__( 'Status', 'subkit-subscriptions' ) . '</th><td class="forminp"><ul style="margin:0">';

		foreach ( $checks as $check ) {
			printf(
				'<li style="margin:0 0 .4em"><strong>%s:</strong> <span style="color:%s">%s</span></li>',
				esc_html( $check['label'] ),
				$check['ok'] ? '#1a7f37' : '#b32d2e',
				esc_html( $check['ok'] ? $check['good'] : $check['bad'] )
			);
		}

		echo '</ul></td></tr>';
	}
}
