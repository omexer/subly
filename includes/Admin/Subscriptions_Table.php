<?php

namespace SubKit\Admin;

use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Frontend\MyAccount\Status_Presenter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The subscriptions list. Uses WP_List_Table so it inherits sorting, pagination and
 * screen options, and looks like the rest of wp-admin rather than like a plugin.
 */
class Subscriptions_Table extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'subscription',
			'plural'   => 'subscriptions',
			'ajax'     => false,
		) );
	}

	public function get_columns(): array {
		return array(
			'subscription' => __( 'Subscription', 'subkit-subscriptions' ),
			'customer'     => __( 'Customer', 'subkit-subscriptions' ),
			'status'       => __( 'Status', 'subkit-subscriptions' ),
			'next_payment' => __( 'Next payment', 'subkit-subscriptions' ),
			'total'        => __( 'Recurring total', 'subkit-subscriptions' ),
			'gateway'      => __( 'Payment method', 'subkit-subscriptions' ),
		);
	}

	public function prepare_items(): void {
		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$paged    = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
		$status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array( 'limit' => $per_page, 'paged' => $paged, 'orderby' => 'date', 'order' => 'DESC' );

		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		$this->items           = array_filter( Subscription_Query::get( $args ), static fn( $s ) => $s instanceof Subscription );
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$total = count( Subscription_Query::ids( array_merge( $args, array( 'limit' => -1, 'paged' => 1 ) ) ) );

		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page ) );
	}

	/**
	 * @param Subscription $item
	 */
	public function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'subscription' => $this->subscription_cell( $item ),
			'customer'     => esc_html( trim( $item->get_billing_first_name() . ' ' . $item->get_billing_last_name() ) ?: $item->get_billing_email() ?: '—' ),
			// Text, not just a colour: a merchant scanning for problems must be able to read it.
			'status'       => esc_html( Status_Presenter::for( $item )['label'] ),
			'next_payment' => $item->get_next_payment()
				? esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $item->get_next_payment() . ' UTC' ) ) )
				: '—',
			'total'        => wp_kses_post( $item->get_formatted_order_total() ),
			'gateway'      => esc_html( $item->get_payment_method_title() ?: $item->get_payment_method() ?: '—' ),
			default        => '',
		};
	}

	private function subscription_cell( Subscription $item ): string {
		$url = add_query_arg(
			array( 'page' => Menu::SLUG, 'subscription' => $item->get_id() ),
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<strong><a href="%s">#%d</a></strong><br><span class="description">%s</span>',
			esc_url( $url ),
			$item->get_id(),
			esc_html( Status_Presenter::title( $item ) )
		);
	}

	public function no_items(): void {
		esc_html_e( 'No subscriptions yet. Tick "Subscription" on a product to start selling one.', 'subkit-subscriptions' );
	}

	/**
	 * Status filter links across the top, with counts.
	 */
	protected function get_views(): array {
		$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base    = add_query_arg( array( 'page' => Menu::SLUG ), admin_url( 'admin.php' ) );

		$views = array(
			'all' => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $base ),
				'' === $current ? ' class="current"' : '',
				esc_html__( 'All', 'subkit-subscriptions' )
			),
		);

		foreach ( Subscription_Status::cases() as $status ) {
			$count = count( Subscription_Query::ids( array( 'status' => $status->value, 'limit' => -1 ) ) );

			if ( 0 === $count ) {
				continue;
			}

			$views[ $status->value ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', $status->value, $base ) ),
				$current === $status->value ? ' class="current"' : '',
				esc_html( $status->label() ),
				$count
			);
		}

		return $views;
	}
}
