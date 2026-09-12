<?php

namespace SubKit;

use SubKit\Billing\Lock;
use SubKit\Billing\Renewal_Order_Factory;
use SubKit\Billing\Renewal_Processor;
use SubKit\Billing\Renewal_Scheduler;
use SubKit\Data\Activity_Repository;
use SubKit\Data\Charge_Slot_Repository;
use SubKit\Data\Cleanup;
use SubKit\Data\Migrator;
use SubKit\Data\Order_Type;
use SubKit\Data\Stats;
use SubKit\Rest\Overview_Controller;
use SubKit\Checkout\Cart_Validation;
use SubKit\Checkout\Guest_Checkout;
use SubKit\Checkout\Store_Api;
use SubKit\Checkout\Subscription_Factory;
use SubKit\Frontend\Disclosure;
use SubKit\Frontend\Product_Display;
use SubKit\Gateways\Gateway_Registry;
use SubKit\Gateways\PayPal\PayPal_Checkout_Gateway;
use SubKit\Gateways\PayPal\PayPal_Client;
use SubKit\Gateways\PayPal\PayPal_Plans;
use SubKit\Gateways\PayPal\PayPal_Gateway;
use SubKit\Gateways\PayPal\Webhook_Controller as PayPal_Webhooks;
use SubKit\Gateways\Stripe\Stripe_Checkout_Gateway;
use SubKit\Gateways\Stripe\Stripe_Client;
use SubKit\Gateways\Stripe\Stripe_Gateway;
use SubKit\Product\Product_Meta_Fields;
use SubKit\Product\Product_Types;
use SubKit\Admin\Assets as Admin_Assets;
use SubKit\Admin\Help_Page;
use SubKit\Admin\Integration_Installer;
use SubKit\Admin\Integrations_Page;
use SubKit\Admin\Menu;
use SubKit\Admin\Settings;
use SubKit\Admin\Setup_Guide;
use SubKit\Emails\Mailer;
use SubKit\Privacy\Personal_Data;
use SubKit\Lifecycle\Auto_Renewal;
use SubKit\Lifecycle\Cancellation_Survey;
use SubKit\Lifecycle\Role_Management;
use SubKit\Frontend\Downloadable_Access;
use SubKit\Frontend\MyAccount\Account_Endpoint;
use SubKit\Frontend\MyAccount\Assets as Account_Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		$this->services['product_types'] = new Product_Types();
		$this->services['product_types']->register();

		$this->services['product_fields'] = new Product_Meta_Fields();
		$this->services['product_fields']->register();

		$this->services['disclosure'] = new Disclosure();

		$this->services['product_display'] = new Product_Display( $this->services['disclosure'] );
		$this->services['product_display']->register();

		$this->services['cart_rules'] = new Cart_Validation();
		$this->services['cart_rules']->register();

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

		$this->services['stripe_client'] = Stripe_Client::from_settings();

		add_filter(
			'woocommerce_payment_gateways',
			function ( array $gateways ): array {
				$gateways[] = new Stripe_Checkout_Gateway( $this->services['stripe_client'] );

				return $gateways;
			}
		);

		add_action(
			'subkit_register_gateways',
			function ( Gateway_Registry $registry ): void {
				if ( $this->services['paypal_client']->is_enabled() ) {
					$registry->add( new PayPal_Gateway( $this->services['paypal_client'] ) );
				}

				if ( $this->services['stripe_client']->is_enabled() ) {
					$registry->add( new Stripe_Gateway( $this->services['stripe_client'] ) );
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

		$this->services['admin_assets'] = new Admin_Assets();
		$this->services['admin_assets']->register();

		$this->services['integration_installer'] = new Integration_Installer();
		$this->services['integration_installer']->register();

		/**
		 * Fires once SubKit Free is loaded and its public API is available.
		 *
		 * SubKit Pro boots from this hook; see Developer Guide 4.
		 *
		 * @param string $version Free plugin version.
		 */
		do_action( 'subkit_loaded', SUBKIT_VERSION );
	}

	public function get( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}
}
