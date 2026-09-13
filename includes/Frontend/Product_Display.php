<?php

namespace SubKit\Frontend;

use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puts the disclosure on every surface it is legally required to appear on.
 *
 * Product page, cart, checkout and the confirmation email. Never behind a tab or an
 * accordion — a disclosure the customer has to open is not a disclosure.
 */
class Product_Display {

	public function __construct( private readonly Disclosure $disclosure ) {}

	public function register(): void {
		add_action( 'woocommerce_single_product_summary', array( $this, 'on_product_page' ), 11 );
		// Late, so it sees WooCommerce's finished markup - sale strikethrough and tax suffix
		// included - and adds to it rather than being overwritten by it.
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'on_cart_line' ), 10, 3 );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'on_checkout_totals' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'before_place_order' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
	}

	public function on_product_page(): void {
		global $product;

		if ( $product instanceof \WC_Product && Subscription_Product::is_subscription( $product ) ) {
			echo wp_kses_post( $this->disclosure->render( $product ) );
		}
	}

	/**
	 * Everywhere WooCommerce prints a price: product page, shop, category, related products.
	 *
	 * @param string $html
	 * @param mixed  $product
	 */
	public function price_html( $html, $product ): string {
		return $product instanceof \WC_Product
			? $this->disclosure->price_html( (string) $html, $product )
			: (string) $html;
	}

	/**
	 * Per line item, because a cart can hold recurring and one-off products together.
	 */
	public function on_cart_line( $price_html, $cart_item, $cart_item_key ) {
		$product = $cart_item['data'] ?? null;

		if ( ! Subscription_Product::is_subscription( $product ) ) {
			return $price_html;
		}

		return $price_html . '<span class="subkit-cart-terms">' . esc_html( wp_strip_all_tags( $this->disclosure->price_line( $product ) ) ) . '</span>';
	}

	public function on_checkout_totals(): void {
		foreach ( $this->subscription_products_in_cart() as $product ) {
			echo '<tr class="subkit-recurring-total"><th>' . esc_html__( 'Recurring', 'subkit-subscriptions' ) . '</th><td>'
				. wp_kses_post( $this->disclosure->price_line( $product ) ) . '</td></tr>';
		}
	}

	/**
	 * The sentence next to the Place Order button — the one that legally matters.
	 */
	public function before_place_order(): void {
		$products = $this->subscription_products_in_cart();

		if ( empty( $products ) ) {
			return;
		}

		echo '<p class="subkit-checkout-consent">' . esc_html( $this->disclosure->sentence( reset( $products ) ) ) . '</p>';
	}

	public function styles(): void {
		if ( ! is_product() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_register_style( 'subkit-frontend', false, array(), SUBKIT_VERSION );
		wp_enqueue_style( 'subkit-frontend' );
		wp_add_inline_style(
			'subkit-frontend',
			'.subkit-disclosure{margin:1em 0}
			 .subkit-disclosure__facts{list-style:none;margin:0;padding:0;font-size:.9em;line-height:1.6;opacity:.85}
			 .subkit-cart-terms{display:block;font-size:.85em;opacity:.8}
			 .subkit-checkout-consent{margin:0 0 1em;font-size:.9em}
			 .subkit-blocks-disclosure{padding:1em 0;border-top:1px solid rgba(0,0,0,.1)}
			 .subkit-blocks-disclosure__price{font-weight:600;margin:0 0 .35em}
			 .subkit-blocks-disclosure__facts{list-style:none;margin:0;padding:0;font-size:.9em;line-height:1.6;opacity:.85}
			 .subkit-blocks-disclosure__consent{margin:.75em 0 0;font-size:.85em}'
		);
	}

	/**
	 * @return \WC_Product[]
	 */
	private function subscription_products_in_cart(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}

		$products = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( Subscription_Product::is_subscription( $item['data'] ?? null ) ) {
				$products[] = $item['data'];
			}
		}

		return $products;
	}
}
