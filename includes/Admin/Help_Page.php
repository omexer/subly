<?php

namespace Subly\Admin;

use Subly\Billing\Renewal_Scheduler;
use Subly\Data\Migrator;
use Subly\Data\Subscription_Query;
use Subly\Gateways\Gateway_Registry;

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
			__( 'Help', 'subly' ),
			__( 'Help', 'subly' ),
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

		$scheduler = \Subly\Plugin::instance()->get( 'scheduler' );
		$gateways  = \Subly\Plugin::instance()->get( 'gateways' );
		$migrator  = new Migrator();

		$rows = array(
			'Subly'     => defined( 'SUBLY_VERSION' ) ? SUBLY_VERSION : '?',
			'Subly Pro' => defined( 'SUBLY_PRO_VERSION' ) ? SUBLY_PRO_VERSION : __( 'not installed', 'subly' ),
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
		return (array) apply_filters( 'subly_support_report', $rows );
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
				'title' => __( 'Run the setup checks', 'subly' ),
				'body'  => __( 'Home runs a real renewal end to end without charging anyone. If renewals are the problem, that test usually says why.', 'subly' ),
				'label' => __( 'Open Home', 'subly' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG ),
			),
			array(
				'icon'  => '2',
				'title' => __( 'Read the activity log', 'subly' ),
				'body'  => __( 'Every subscription records each charge attempt and status change, with the reason. Open one and scroll to Activity.', 'subly' ),
				'label' => __( 'All subscriptions', 'subly' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::LIST_SLUG ),
			),
			array(
				'icon'  => '3',
				'title' => __( 'Check renewal health', 'subly' ),
				'body'  => __( 'Settings shows whether the renewal queue is running, any charge whose outcome is unknown, and the double-charge safeguard.', 'subly' ),
				'label' => __( 'Open settings', 'subly' ),
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=subly' ),
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
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'subly' ) );
		}

		App_Host::start( self::SLUG );
		$this->render_screen();
		App_Host::end();
	}

	private function render_screen(): void {
		$report = $this->report();
		$text   = self::report_text( $report );

		Page_Shell::open(
			__( 'Help', 'subly' ),
			__( 'Where to look first, and the report to send if you still need a hand.', 'subly' )
		);

		echo '<h2 class="subly-section-title">' . esc_html__( 'Check these first', 'subly' ) . '</h2>';
		echo '<div class="subly-grid">';

		foreach ( $this->tiles() as $tile ) {
			printf(
				'<div class="subly-tile"><div class="subly-tile__top"><span class="subly-tile__icon" aria-hidden="true">%s</span><h3 class="subly-tile__title">%s</h3></div><p class="subly-tile__body">%s</p><div class="subly-tile__foot"><a class="subly-btn subly-btn--sm" href="%s">%s</a></div></div>',
				esc_html( $tile['icon'] ),
				esc_html( $tile['title'] ),
				esc_html( $tile['body'] ),
				esc_url( $tile['url'] ),
				esc_html( $tile['label'] )
			);
		}

		echo '</div>';

		echo '<h2 class="subly-section-title">' . esc_html__( 'System report', 'subly' ) . '</h2>';
		echo '<div class="subly-card subly-report-card">';
		echo '<div class="subly-report-card__head"><p class="subly-lede">' . esc_html__( 'Paste this into your support request. It contains no keys and no customer data.', 'subly' ) . '</p>';
		printf(
			'<button type="button" class="subly-btn subly-btn--primary subly-btn--sm" data-subly-copy="subly-report" data-subly-copied="%s">%s</button></div>',
			esc_attr__( 'Copied', 'subly' ),
			esc_html__( 'Copy report', 'subly' )
		);

		printf(
			'<textarea id="subly-report" readonly rows="%d" class="subly-report">%s</textarea>',
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
