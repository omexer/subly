<?php

namespace EasySubscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EasySubscription\Billing\Lock;
use EasySubscription\Billing\Renewal_Order_Factory;
use EasySubscription\Billing\Renewal_Processor;
use EasySubscription\Billing\Renewal_Scheduler;
use EasySubscription\Billing\Renewal_Tax_Repair;
use EasySubscription\Data\Activity_Repository;
use EasySubscription\Data\Charge_Slot_Repository;
use EasySubscription\Data\Cleanup;
use EasySubscription\Data\Migrator;
use EasySubscription\Data\Order_Type;
use EasySubscription\Data\Stats;
use EasySubscription\Rest\Dashboard_Controller;
use EasySubscription\Rest\Help_Controller;
use EasySubscription\Rest\Integrations_Controller;
use EasySubscription\Rest\Overview_Controller;
use EasySubscription\Rest\Settings_Controller;
use EasySubscription\Rest\Subscriptions_Controller;
use EasySubscription\Checkout\Cart_Validation;
use EasySubscription\Checkout\Guest_Checkout;
use EasySubscription\Checkout\Store_Api;
use EasySubscription\Checkout\Subscription_Factory;
use EasySubscription\Checkout\Initial_Payment;
use EasySubscription\Checkout\Renewal_Order_Pay;
use EasySubscription\Checkout\Trial_Payment;
use EasySubscription\Frontend\Disclosure;
use EasySubscription\Frontend\Product_Display;
use EasySubscription\Gateways\Gateway_Registry;
use EasySubscription\Gateways\PayPal\PayPal_Checkout_Gateway;
use EasySubscription\Gateways\PayPal\PayPal_Client;
use EasySubscription\Gateways\PayPal\PayPal_Plans;
use EasySubscription\Gateways\PayPal\PayPal_Gateway;
use EasySubscription\Gateways\PayPal\Webhook_Controller as PayPal_Webhooks;
use EasySubscription\Integrations\FluentCRM;
use EasySubscription\Integrations\Integrations;
use EasySubscription\Product\Product_Meta_Fields;
use EasySubscription\Product\Product_Types;
use EasySubscription\Admin\App_Host;
use EasySubscription\Admin\Assets as Admin_Assets;
use EasySubscription\Admin\Help_Page;
use EasySubscription\Admin\Integration_Installer;
use EasySubscription\Admin\Integrations_Page;
use EasySubscription\Admin\Gateway_Notice;
use EasySubscription\Blocks\Gateway_Support;
use EasySubscription\Admin\Menu;
use EasySubscription\Admin\Notice_Dismissals;
use EasySubscription\Admin\Notices;
use EasySubscription\Admin\Settings;
use EasySubscription\Admin\Settings_Page;
use EasySubscription\Admin\Setup_Guide;
use EasySubscription\Emails\Mailer;
use EasySubscription\Emails\Notification_Settings;
use EasySubscription\Privacy\Personal_Data;
use EasySubscription\Lifecycle\Auto_Renewal;
use EasySubscription\Lifecycle\Cancellation_Survey;
use EasySubscription\Lifecycle\Role_Management;
use EasySubscription\Frontend\Downloadable_Access;
use EasySubscription\Frontend\MyAccount\Account_Endpoint;
use EasySubscription\Frontend\MyAccount\Assets as Account_Assets;

/**
 * Service registration. Deliberately a plain registry rather than a DI container —
 * there is not enough here yet to justify one.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	/** @var array<string, object> */
	private array $services = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->services['order_type'] = new Order_Type();
		$this->services['order_type']->register();

		$this->services['migrator'] = new Migrator();
		$this->services['migrator']->register();

		$this->services['cleanup'] = new Cleanup();
		$this->services['cleanup']->register();

		$this->services['charge_slots']  = new Charge_Slot_Repository();
		$this->services['activity']      = new Activity_Repository();
		$this->services['lock']          = new Lock();
		$this->services['order_factory'] = new Renewal_Order_Factory();

		$this->services['gateways'] = new Gateway_Registry();
		$this->services['gateways']->register();

		$this->services['scheduler'] = new Renewal_Scheduler( $this->services['activity'] );
		$this->services['scheduler']->register();

		$this->services['tax_repair'] = new Renewal_Tax_Repair( $this->services['activity'] );
		$this->services['tax_repair']->register();

		$this->services['processor'] = new Renewal_Processor(
			$this->services['charge_slots'],
			$this->services['activity'],
			$this->services['gateways'],
			$this->services['order_factory'],
			$this->services['scheduler'],
			$this->services['lock']
		);
		$this->services['processor']->register();

		$this->services['stats'] = new Stats();
		$this->services['stats']->register();

		$this->services['overview_api'] = new Overview_Controller( $this->services['stats'] );
		$this->services['overview_api']->register();

		$this->services['subscriptions_api'] = new Subscriptions_Controller( $this->services['activity'], $this->services['processor'] );
		$this->services['subscriptions_api']->register();

		$this->services['product_types'] = new Product_Types();
		$this->services['product_types']->register();

		$this->services['product_fields'] = new Product_Meta_Fields();
		$this->services['product_fields']->register();

		$this->services['disclosure'] = new Disclosure();

		$this->services['product_display'] = new Product_Display( $this->services['disclosure'] );
		$this->services['product_display']->register();

		$this->services['cart_rules'] = new Cart_Validation();
		$this->services['cart_rules']->register();

		$this->services['initial_payment'] = new Initial_Payment();
		$this->services['initial_payment']->register();

		$this->services['trial_payment'] = new Trial_Payment();
		$this->services['trial_payment']->register();

		$this->services['store_api'] = new Store_Api( $this->services['disclosure'] );
		$this->services['store_api']->register();

		$this->services['guest_checkout'] = new Guest_Checkout();
		$this->services['guest_checkout']->register();

		$this->services['subscription_factory'] = new Subscription_Factory(
			$this->services['charge_slots'],
			$this->services['activity'],
			$this->services['scheduler']
		);
		$this->services['subscription_factory']->register();

		$this->services['renewal_order_pay'] = new Renewal_Order_Pay( $this->services['gateways'] );
		$this->services['renewal_order_pay']->register();

		$this->services['account'] = new Account_Endpoint( $this->services['activity'] );
		$this->services['account']->register();

		$this->services['account_assets'] = new Account_Assets();
		$this->services['account_assets']->register();

		$this->services['paypal_client'] = PayPal_Client::from_settings();
		$this->services['paypal_plans']  = new PayPal_Plans( $this->services['paypal_client'] );

		// WC_Payment_Gateway is only defined once WooCommerce has loaded its gateway classes.
		add_filter(
			'woocommerce_payment_gateways',
			function ( array $gateways ): array {
				$gateways[] = new PayPal_Checkout_Gateway(
					$this->services['paypal_client'],
					$this->services['paypal_plans']
				);

				return $gateways;
			}
		);

		add_action(
			'easysubscription_register_gateways',
			function ( Gateway_Registry $registry ): void {
				if ( $this->services['paypal_client']->is_enabled() ) {
					$registry->add( new PayPal_Gateway( $this->services['paypal_client'] ) );
				}
			}
		);

		$this->services['paypal_webhooks'] = new PayPal_Webhooks(
			$this->services['paypal_client'],
			$this->services['charge_slots'],
			$this->services['activity'],
			$this->services['order_factory']
		);
		$this->services['paypal_webhooks']->register();

		$this->services['auto_renewal'] = new Auto_Renewal( $this->services['activity'] );
		$this->services['auto_renewal']->register();

		$this->services['roles'] = new Role_Management();
		$this->services['roles']->register();

		$this->services['downloads'] = new Downloadable_Access();
		$this->services['downloads']->register();

		$this->services['survey'] = new Cancellation_Survey( $this->services['activity'] );
		$this->services['survey']->register();

		$this->services['privacy'] = new Personal_Data( $this->services['activity'] );
		$this->services['privacy']->register();

		$this->services['mailer'] = new Mailer();
		$this->services['mailer']->register();

		$this->services['notification_settings'] = new Notification_Settings();
		$this->services['notification_settings']->register();

		add_filter(
			'woocommerce_get_settings_pages',
			static function ( array $pages ): array {
				// WC_Settings_Page is only loaded on admin screens; instantiating without it fatals.
				if ( class_exists( '\WC_Settings_Page' ) ) {
					$pages[] = new Settings();
				}

				return $pages;
			}
		);

		$this->services['setup'] = new Setup_Guide( $this->services['scheduler'] );
		$this->services['setup']->register();

		// After the setup guide, which it reads the steps from.
		$this->services['dashboard_api'] = new Dashboard_Controller( $this->services['stats'], $this->services['setup'] );
		$this->services['dashboard_api']->register();

		$this->services['gateway_notice'] = new Gateway_Notice();
		$this->services['gateway_notice']->register();

		$this->services['notice_dismissals'] = new Notice_Dismissals();
		$this->services['notice_dismissals']->register();

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ): void {
				if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
					return;
				}

				$registry->register( new Gateway_Support( PayPal_Checkout_Gateway::ID ) );
			}
		);

		$this->services['admin_menu'] = new Menu(
			$this->services['activity'],
			$this->services['processor'],
			$this->services['setup']
		);
		$this->services['admin_menu']->register();

		$this->services['integrations_page'] = new Integrations_Page();
		$this->services['integrations_page']->register();

		$this->services['help_page'] = new Help_Page();
		$this->services['help_page']->register();

		$this->services['settings_page'] = new Settings_Page();
		$this->services['settings_page']->register();

		$this->services['settings_api'] = new Settings_Controller( $this->services['settings_page'] );
		$this->services['settings_api']->register();

		$this->services['admin_notices'] = new Notices();
		$this->services['admin_notices']->register();

		$this->services['admin_assets'] = new Admin_Assets();
		$this->services['admin_assets']->register();

		$this->services['app_host'] = new App_Host();
		$this->services['app_host']->register();

		$this->services['integrations_api'] = new Integrations_Controller( $this->services['integrations_page'] );
		$this->services['integrations_api']->register();

		$this->services['help_api'] = new Help_Controller( $this->services['help_page'] );
		$this->services['help_api']->register();

		$this->services['integration_installer'] = new Integration_Installer();
		$this->services['integration_installer']->register();

		// Registered once extensions booting on easysubscription_loaded have added theirs.
		$this->services['integrations'] = new Integrations();
		add_action( 'easysubscription_loaded', array( $this->services['integrations'], 'register' ), 100, 0 );

		add_filter( 'easysubscription_admin_integrations', array( FluentCRM::class, 'describe' ) );

		/**
		 * Fires once EasySubscription Free is loaded and its public API is available.
		 *
		 * EasySubscription Pro boots from this hook; see Developer Guide 4.
		 *
		 * @param string $version Free plugin version.
		 */
		do_action( 'easysubscription_loaded', EASYSUBSCRIPTION_VERSION );
	}

	public function get( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}
}
