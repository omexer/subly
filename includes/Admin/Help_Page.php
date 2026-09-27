<?php

namespace EasySubscription\Admin;

use EasySubscription\Billing\Renewal_Scheduler;
use EasySubscription\Data\Migrator;
use EasySubscription\Data\Subscription_Query;
use EasySubscription\Gateways\Gateway_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where to get help, and the facts to bring with you.
 *
 * The report is the point. Most support threads open with a question that cannot be
 * answered until somebody asks for versions, whether HPOS is on and whether the queue is
 * running, so this hands all of it over in one paste. It deliberately reports whether a
 * gateway is configured, never any part of its credentials.
 */
class Help_Page {

	public const SLUG = Menu::SLUG . '-help';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 90 );
	}

	public function add_page(): void {
		add_submenu_page(
			Menu::PARENT,
			__( 'Help', 'easysubscription' ),
			__( 'Help', 'easysubscription' ),
			Menu::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function report(): array {
		global $wpdb;

		$scheduler = \EasySubscription\Plugin::instance()->get( 'scheduler' );
		$gateways  = \EasySubscription\Plugin::instance()->get( 'gateways' );
		$migrator  = new Migrator();

		$rows = array(
			'EasySubscription'     => defined( 'EASYSUBSCRIPTION_VERSION' ) ? EASYSUBSCRIPTION_VERSION : '?',
			'EasySubscription Pro' => defined( 'EASYSUBSCRIPTION_PRO_VERSION' ) ? EASYSUBSCRIPTION_PRO_VERSION : __( 'not installed', 'easysubscription' ),
			'WordPress'            => get_bloginfo( 'version' ),
			'WooCommerce'          => defined( 'WC_VERSION' ) ? WC_VERSION : '?',
			'PHP'                  => PHP_VERSION,
			'MySQL'                => $wpdb->db_version(),
			'Theme'                => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'Locale'               => get_locale(),
			'Currency'             => get_woocommerce_currency(),
			'Timezone'             => wp_timezone_string(),
			'HPOS'                 => $this->hpos() ? 'yes' : 'no',
			'Site URL'             => (string) get_option( 'siteurl' ),
		);

		$rows['Renewal queue'] = $scheduler instanceof Renewal_Scheduler && $scheduler->queue_is_healthy() ? 'healthy' : 'overdue tasks';
		$rows['Charge ledger'] = $migrator->charge_slot_guard_intact() ? 'unique index present' : 'MISSING';
		$rows['Subscriptions'] = (string) count(
			Subscription_Query::ids(
				array(
					'limit'  => -1,
					'status' => null,
				)
			)
		);

		if ( $gateways instanceof Gateway_Registry ) {
			$ids = array();

			foreach ( $gateways->all() as $gateway ) {
				$ids[] = $gateway->id();
			}

			$rows['Recurring gateways'] = $ids ? implode( ', ', $ids ) : 'none registered';
		}

		/**
		 * Filter the rows of the support report.
		 *
		 * Never add anything secret: merchants paste this into public forums.
		 *
		 * @param array $rows
		 */
		return (array) apply_filters( 'easysubscription_support_report', $rows );
	}

	/**
	 * Every tile lands somewhere inside this site: documentation links would 404 until the docs are public.
	 *
	 * @return array<int, array{icon: string, title: string, body: string, label: string, url: string}>
	 */
	public function tiles(): array {
		return array(
			array(
				'icon'  => '1',
				'title' => __( 'Run the setup checks', 'easysubscription' ),
				'body'  => __( 'Home runs a real renewal end to end without charging anyone. If renewals are the problem, that test usually says why.', 'easysubscription' ),
				'label' => __( 'Open Home', 'easysubscription' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG ),
			),
			array(
				'icon'  => '2',
				'title' => __( 'Read the activity log', 'easysubscription' ),
				'body'  => __( 'Every subscription records each charge attempt and status change, with the reason. Open one and scroll to Activity.', 'easysubscription' ),
				'label' => __( 'All subscriptions', 'easysubscription' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::LIST_SLUG ),
			),
			array(
				'icon'  => '3',
				'title' => __( 'Check renewal health', 'easysubscription' ),
				'body'  => __( 'Settings shows whether the renewal queue is running, any charge whose outcome is unknown, and the double-charge safeguard.', 'easysubscription' ),
				'label' => __( 'Open settings', 'easysubscription' ),
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=easysubscription' ),
			),
		);
	}

	/**
	 * The report as the text a merchant pastes.
	 *
	 * @param array<string, string> $report
	 */
	public static function report_text( array $report ): string {
		$text = '';

		foreach ( $report as $label => $value ) {
			$text .= $label . ': ' . $value . "\n";
		}

		return $text;
	}

	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'easysubscription' ) );
		}

		App_Host::start( self::SLUG );
		$this->render_screen();
		App_Host::end();
	}

	private function render_screen(): void {
		$report = $this->report();
		$text   = self::report_text( $report );

		Page_Shell::open(
			__( 'Help', 'easysubscription' ),
			__( 'Where to look first, and the report to send if you still need a hand.', 'easysubscription' )
		);

		echo '<h2 class="easysubscription-section-title">' . esc_html__( 'Check these first', 'easysubscription' ) . '</h2>';
		echo '<div class="easysubscription-grid">';

		foreach ( $this->tiles() as $tile ) {
			printf(
				'<div class="easysubscription-tile"><div class="easysubscription-tile__top"><span class="easysubscription-tile__icon" aria-hidden="true">%s</span><h3 class="easysubscription-tile__title">%s</h3></div><p class="easysubscription-tile__body">%s</p><div class="easysubscription-tile__foot"><a class="easysubscription-btn easysubscription-btn--sm" href="%s">%s</a></div></div>',
				esc_html( $tile['icon'] ),
				esc_html( $tile['title'] ),
				esc_html( $tile['body'] ),
				esc_url( $tile['url'] ),
				esc_html( $tile['label'] )
			);
		}

		echo '</div>';

		echo '<h2 class="easysubscription-section-title">' . esc_html__( 'System report', 'easysubscription' ) . '</h2>';
		echo '<div class="easysubscription-card easysubscription-report-card">';
		echo '<div class="easysubscription-report-card__head"><p class="easysubscription-lede">' . esc_html__( 'Paste this into your support request. It contains no keys and no customer data.', 'easysubscription' ) . '</p>';
		printf(
			'<button type="button" class="easysubscription-btn easysubscription-btn--primary easysubscription-btn--sm" data-easysubscription-copy="easysubscription-report" data-easysubscription-copied="%s">%s</button></div>',
			esc_attr__( 'Copied', 'easysubscription' ),
			esc_html__( 'Copy report', 'easysubscription' )
		);

		printf(
			'<textarea id="easysubscription-report" readonly rows="%d" class="easysubscription-report">%s</textarea>',
			(int) min( 24, count( $report ) + 1 ),
			esc_textarea( $text )
		);

		echo '</div>';

		Page_Shell::close();
	}

	private function hpos(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
