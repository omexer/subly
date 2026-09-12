<?php

namespace SubKit\Admin;

use SubKit\Lifecycle\Cancellation_Survey;
use SubKit\Reports\Metrics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce → Subscriptions → Reports.
 *
 * Server-rendered on purpose. The figures are small and infrequent, and a React bundle
 * here would be cost without benefit.
 */
class Reports_Page {

	public function __construct(
		private readonly Metrics $metrics,
		private readonly Cancellation_Survey $survey
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 21 );
	}

	public function add_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Subscription reports', 'subkit-subscriptions' ),
			__( 'Subscription reports', 'subkit-subscriptions' ),
			Menu::CAPABILITY,
			Menu::SLUG . '-reports',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view subscription reports.', 'subkit-subscriptions' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Subscription reports', 'subkit-subscriptions' ) . '</h1>';

		$this->render_tiles();
		$this->render_statuses();
		$this->render_signups();
		$this->render_reasons();

		echo '</div>';
	}

	private function render_tiles(): void {
		$tiles = array(
			array( __( 'Monthly recurring revenue', 'subkit-subscriptions' ), wp_strip_all_tags( $this->metrics->mrr()->format() ) ),
			array( __( 'Annual run rate', 'subkit-subscriptions' ), wp_strip_all_tags( $this->metrics->arr()->format() ) ),
			array( __( 'Active subscriptions', 'subkit-subscriptions' ), (string) $this->metrics->active_count() ),
			array( __( 'Churn (30 days)', 'subkit-subscriptions' ), $this->metrics->churn_rate() . '%' ),
			array( __( 'Average lifetime value', 'subkit-subscriptions' ), wp_strip_all_tags( $this->metrics->lifetime_value()->format() ) ),
			array( __( 'Revenue collected', 'subkit-subscriptions' ), wp_strip_all_tags( $this->metrics->revenue_collected()->format() ) ),
		);

		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:1rem 0">';

		foreach ( $tiles as $tile ) {
			printf(
				'<div class="card" style="flex:1 1 14rem;margin:0;padding:1rem"><p style="margin:0;color:#50575e">%s</p><p style="margin:.25rem 0 0;font-size:1.6rem;font-weight:600">%s</p></div>',
				esc_html( $tile[0] ),
				esc_html( $tile[1] )
			);
		}

		echo '</div>';
	}

	private function render_statuses(): void {
		echo '<h2>' . esc_html__( 'By status', 'subkit-subscriptions' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:32rem"><tbody>';

		foreach ( $this->metrics->counts_by_status() as $status => $count ) {
			$label = \SubKit\Domain\Subscription_Status::tryFrom( $status );

			printf(
				'<tr><td>%s</td><td style="text-align:right;width:6rem">%d</td></tr>',
				esc_html( $label ? $label->label() : $status ),
				(int) $count
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * A bar per day, drawn with divs. No chart library for six weeks of integers.
	 */
	private function render_signups(): void {
		$series = $this->metrics->signups_by_day( 30 );
		$peak   = max( 1, max( $series ) );

		echo '<h2>' . esc_html__( 'New subscriptions, last 30 days', 'subkit-subscriptions' ) . '</h2>';
		echo '<div style="display:flex;align-items:flex-end;gap:3px;height:120px;max-width:42rem;border-bottom:1px solid #dcdcde;padding-bottom:2px">';

		foreach ( $series as $day => $count ) {
			printf(
				'<div title="%s: %d" style="flex:1;min-width:6px;height:%d%%;background:%s"></div>',
				esc_attr( $day ),
				(int) $count,
				(int) max( 2, round( ( $count / $peak ) * 100 ) ),
				$count > 0 ? '#7f54b3' : '#e0e0e0'
			);
		}

		echo '</div>';
	}

	private function render_reasons(): void {
		$tally = array_filter( $this->survey->tally() );

		echo '<h2>' . esc_html__( 'Why customers cancelled', 'subkit-subscriptions' ) . '</h2>';

		if ( empty( $tally ) ) {
			echo '<p>' . esc_html__( 'No cancellation reasons recorded yet. Reasons are collected after a customer cancels, and answering is optional.', 'subkit-subscriptions' ) . '</p>';
			return;
		}

		arsort( $tally );
		$labels = $this->survey->reasons();

		echo '<table class="widefat striped" style="max-width:32rem"><tbody>';

		foreach ( $tally as $reason => $count ) {
			printf(
				'<tr><td>%s</td><td style="text-align:right;width:6rem">%d</td></tr>',
				esc_html( $labels[ $reason ] ?? $reason ),
				(int) $count
			);
		}

		echo '</tbody></table>';
	}
}
