<?php

namespace SubKit\Admin;

use SubKit\Billing\Renewal_Scheduler;
use SubKit\Data\Migrator;
use SubKit\Data\Subscription_Query;
use SubKit\Gateways\Gateway_Registry;

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

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 90 );
	}

	public function add_page(): void {
		add_submenu_page(
			Menu::PARENT,
			__( 'Help', 'subkit-subscriptions' ),
			__( 'Help', 'subkit-subscriptions' ),
			Menu::CAPABILITY,
			Menu::SLUG . '-help',
			array( $this, 'render' )
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function report(): array {
		global $wpdb;

		$scheduler = \SubKit\Plugin::instance()->get( 'scheduler' );
		$gateways  = \SubKit\Plugin::instance()->get( 'gateways' );
		$migrator  = new Migrator();

		$rows = array(
			'SubKit'      => defined( 'SUBKIT_VERSION' ) ? SUBKIT_VERSION : '?',
			'SubKit Pro'  => defined( 'SUBKIT_PRO_VERSION' ) ? SUBKIT_PRO_VERSION : __( 'not installed', 'subkit-subscriptions' ),
			'WordPress'   => get_bloginfo( 'version' ),
			'WooCommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '?',
			'PHP'         => PHP_VERSION,
			'MySQL'       => $wpdb->db_version(),
			'Theme'       => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'Locale'      => get_locale(),
			'Currency'    => get_woocommerce_currency(),
			'Timezone'    => wp_timezone_string(),
			'HPOS'        => $this->hpos() ? 'yes' : 'no',
			'Site URL'    => (string) get_option( 'siteurl' ),
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
		return (array) apply_filters( 'subkit_support_report', $rows );
	}

	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'subkit-subscriptions' ) );
		}

		$report = $this->report();
		$text   = '';

		foreach ( $report as $label => $value ) {
			$text .= $label . ': ' . $value . "\n";
		}

		Page_Shell::open(
			__( 'Help', 'subkit-subscriptions' ),
			__( 'Where to look first, and the report to send if you still need a hand.', 'subkit-subscriptions' )
		);

		echo '<h2 class="subkit-section-title">' . esc_html__( 'Check these first', 'subkit-subscriptions' ) . '</h2>';
		echo '<div class="subkit-grid">';

		// Every tile lands somewhere inside this site. Documentation links would be the
		// obvious addition, and would 404 for every merchant until the docs are public.
		$tiles = array(
			array(
				'icon'  => '1',
				'title' => __( 'Run the setup checks', 'subkit-subscriptions' ),
				'body'  => __( 'Home runs a real renewal end to end without charging anyone. If renewals are the problem, that test usually says why.', 'subkit-subscriptions' ),
				'label' => __( 'Open Home', 'subkit-subscriptions' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG ),
			),
			array(
				'icon'  => '2',
				'title' => __( 'Read the activity log', 'subkit-subscriptions' ),
				'body'  => __( 'Every subscription records each charge attempt and status change, with the reason. Open one and scroll to Activity.', 'subkit-subscriptions' ),
				'label' => __( 'All subscriptions', 'subkit-subscriptions' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::LIST_SLUG ),
			),
			array(
				'icon'  => '3',
				'title' => __( 'Check renewal health', 'subkit-subscriptions' ),
				'body'  => __( 'Settings shows whether the renewal queue is running, any charge whose outcome is unknown, and the double-charge safeguard.', 'subkit-subscriptions' ),
				'label' => __( 'Open settings', 'subkit-subscriptions' ),
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=subkit' ),
			),
		);

		foreach ( $tiles as $tile ) {
			printf(
				'<div class="subkit-tile"><div class="subkit-tile__top"><span class="subkit-tile__icon" aria-hidden="true">%s</span><h3 class="subkit-tile__title">%s</h3></div><p class="subkit-tile__body">%s</p><div class="subkit-tile__foot"><a class="subkit-btn subkit-btn--sm" href="%s">%s</a></div></div>',
				esc_html( $tile['icon'] ),
				esc_html( $tile['title'] ),
				esc_html( $tile['body'] ),
				esc_url( $tile['url'] ),
				esc_html( $tile['label'] )
			);
		}

		echo '</div>';

		echo '<h2 class="subkit-section-title">' . esc_html__( 'System report', 'subkit-subscriptions' ) . '</h2>';
		echo '<div class="subkit-card subkit-report-card">';
		echo '<div class="subkit-report-card__head"><p class="subkit-lede">' . esc_html__( 'Paste this into your support request. It contains no keys and no customer data.', 'subkit-subscriptions' ) . '</p>';
		printf(
			'<button type="button" class="subkit-btn subkit-btn--primary subkit-btn--sm" data-subkit-copy="subkit-report" data-subkit-copied="%s">%s</button></div>',
			esc_attr__( 'Copied', 'subkit-subscriptions' ),
			esc_html__( 'Copy report', 'subkit-subscriptions' )
		);

		printf(
			'<textarea id="subkit-report" readonly rows="%d" class="subkit-report">%s</textarea>',
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
