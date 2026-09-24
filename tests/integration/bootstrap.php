<?php
/**
 * Shared by every integration test.
 *
 * Each test is run through `wp eval-file`, so WordPress, WooCommerce and SubKit are loaded
 * and this file shares the test's scope: it leaves `$check` and `$fail` behind for the test
 * to use, and the fixtures every test can rely on. A test ends with subkit_test_done( $fail ),
 * which exits non-zero on any failure so the runner — and CI — sees it.
 *
 * @package SubKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

// Deterministic regardless of what the site was set to: amounts are asserted as text.
update_option( 'woocommerce_currency', 'USD' );

$fail = 0;

$check = static function ( string $label, bool $ok, $detail = '' ) use ( &$fail ): void {
	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok || '' === $detail ? '' : '  -> ' . wp_json_encode( $detail ) ) . "\n";
};

if ( ! function_exists( 'subkit_test_done' ) ) {
	function subkit_test_done( int $fail ): void {
		echo "\n" . ( $fail ? "{$fail} CHECK(S) FAILED" : 'all checks passed' ) . "\n";

		if ( $fail ) {
			WP_CLI::halt( 1 );
		}
	}
}

if ( ! function_exists( 'subkit_test_product' ) ) {
	/**
	 * A plain monthly subscription product, found or made.
	 *
	 * Shared and read-only: a test that changes a product's terms makes its own, so the
	 * order tests run in can never change what they see.
	 */
	function subkit_test_product( string $name = 'SK Harness Recurring', string $price = '20' ): WC_Product {
		$found = get_posts(
			array(
				'post_type'   => 'product',
				'title'       => $name,
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);

		if ( $found ) {
			return wc_get_product( (int) $found[0] );
		}

		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->update_meta_data( \SubKit\Product\Subscription_Product::META_PERIOD, 'month' );
		$product->update_meta_data( \SubKit\Product\Subscription_Product::META_INTERVAL, 1 );
		$id = $product->save();

		wp_set_object_terms( $id, \SubKit\Product\Product_Types::SIMPLE, 'product_type' );

		return wc_get_product( $id );
	}
}

subkit_test_product();
