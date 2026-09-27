<?php

namespace EasySubscription\Frontend;

use EasySubscription\Product\Subscription_Product;

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

	public const OPTION_BUTTON_TEXT = 'easysubscription_subscribe_button_text';

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
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'single_button_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'list_button_text' ), 10, 2 );
	}

	public static function button_text(): string {
		$text = trim( (string) get_option( self::OPTION_BUTTON_TEXT, '' ) );

		return '' === $text ? __( 'Subscribe', 'easysubscription' ) : $text;
	}

	/**
	 * @param string $text
	 * @param mixed  $product
	 */
	public function single_button_text( $text, $product ): string {
		return Subscription_Product::is_subscription( $product ) ? self::button_text() : (string) $text;
	}

	/**
	 * Only where the button adds to the cart: "Select options" and "Read more" say what they do.
	 *
	 * @param string $text
	 * @param mixed  $product
	 */
	public function list_button_text( $text, $product ): string {
		if (
			! $product instanceof \WC_Product
			|| $product instanceof \WC_Product_Variable
			|| ! $product->is_purchasable()
			|| ! $product->is_in_stock()
			|| ! Subscription_Product::is_subscription( $product )
		) {
			return (string) $text;
		}

		return self::button_text();
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

		return $price_html . '<span class="easysubscription-cart-terms">' . esc_html( wp_strip_all_tags( $this->disclosure->price_line( $product ) ) ) . '</span>';
	}

	public function on_checkout_totals(): void {
		foreach ( $this->subscription_products_in_cart() as $product ) {
			echo '<tr class="easysubscription-recurring-total"><th>' . esc_html__( 'Recurring', 'easysubscription' ) . '</th><td>'
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

		// Amounts arrive wrapped in <bdi> so RTL cannot reorder them.
		echo '<p class="easysubscription-checkout-consent">' . wp_kses( $this->disclosure->sentence( reset( $products ) ), array( 'bdi' => array() ) ) . '</p>';
	}

	public function styles(): void {
		if ( ! is_product() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_register_style( 'easysubscription-frontend', false, array(), EASYSUBSCRIPTION_VERSION );
		wp_enqueue_style( 'easysubscription-frontend' );
		wp_add_inline_style(
			'easysubscription-frontend',
			'.easysubscription-disclosure{margin:1em 0}
			 .easysubscription-disclosure__facts{list-style:none;margin:0;padding:0;font-size:.9em;line-height:1.6;opacity:.85}
			 .easysubscription-cart-terms{display:block;font-size:.85em;opacity:.8}
			 .easysubscription-checkout-consent{margin:0 0 1em;font-size:.9em}
			 .easysubscription-blocks-disclosure{padding:1em 0;border-top:1px solid rgba(0,0,0,.1)}
			 .easysubscription-blocks-disclosure__price{font-weight:600;margin:0 0 .35em}
			 .easysubscription-blocks-disclosure__facts{list-style:none;margin:0;padding:0;font-size:.9em;line-height:1.6;opacity:.85}
			 .easysubscription-blocks-disclosure__consent{margin:.75em 0 0;font-size:.85em}'
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
