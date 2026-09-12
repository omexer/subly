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

		echo '<div class="wrap"><h1>' . esc_html__( 'Help', 'subkit-subscriptions' ) . '</h1>';

		echo '<h2>' . esc_html__( 'Before you ask', 'subkit-subscriptions' ) . '</h2><ul style="list-style:disc;margin-left:1.2rem">';
		echo '<li>' . esc_html__( 'The setup checklist on the Subscriptions screen runs a real renewal end to end without charging anyone. If that fails, it usually says why.', 'subkit-subscriptions' ) . '</li>';
		echo '<li>' . esc_html__( 'Every subscription has an Activity log recording each charge attempt and status change, with the reason.', 'subkit-subscriptions' ) . '</li>';
		echo '<li>' . esc_html__( 'The Status panel under Settings reports the renewal queue, unresolved charges and the double-charge index.', 'subkit-subscriptions' ) . '</li>';
		echo '</ul>';

		echo '<h2>' . esc_html__( 'System report', 'subkit-subscriptions' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Paste this into your support request. It contains no keys or customer data.', 'subkit-subscriptions' ) . '</p>';

		printf(
			'<textarea readonly rows="%d" style="width:100%%;max-width:46rem;font-family:monospace" onclick="this.select()">%s</textarea>',
			(int) min( 24, count( $report ) + 1 ),
			esc_textarea( $text )
		);

		echo '</div>';
	}

	private function hpos(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
