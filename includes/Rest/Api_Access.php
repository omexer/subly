<?php

namespace SubKit\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands EasySubscription's REST routes to WooCommerce's own API key check, when the store allows it.
 */
final class Api_Access {

	public const OPTION = 'subkit_allow_api_keys';

	public const SECTION = 'api';

	public const NAMESPACES = array( 'subkit/v1', 'subkit-pro/v1' );

	private const METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	public function register(): void {
		add_filter( 'woocommerce_rest_is_request_to_rest_api', array( $this, 'include_our_routes' ) );
		add_filter( 'woocommerce_get_sections_subkit', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_subkit', array( $this, 'settings' ), 10, 2 );
		add_action( 'woocommerce_admin_field_subkit_api_info', array( $this, 'render_info' ) );
	}

	public static function enabled(): bool {
		return 'yes' === get_option( self::OPTION, 'no' );
	}

	/**
	 * @param bool|mixed $is_api
	 * @return bool|mixed
	 */
	public function include_our_routes( $is_api ) {
		if ( ! self::enabled() ) {
			return $is_api;
		}

		$route = self::requested_route();

		return self::is_ours( $route ) && ! self::is_receiver( $route ) ? true : $is_api;
	}

	public static function is_ours( string $route ): bool {
		$route = strtolower( trim( $route, '/' ) );

		foreach ( self::NAMESPACES as $namespace ) {
			if ( $route === $namespace || str_starts_with( $route, $namespace . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Payment providers and WhatsApp call these, proving themselves with signatures of their own.
	 */
	public static function is_receiver( string $route ): bool {
		return in_array( 'webhook', explode( '/', strtolower( $route ) ), true );
	}

	/**
	 * The route asked for, from the start of the path only: admin-ajax.php/wp-json/... is no REST request.
	 */
	private static function requested_route(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		$home = trailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		$rest = trim( rest_get_url_prefix(), '/' ) . '/';

		foreach ( array( $home . $rest, $home . 'index.php/' . $rest ) as $base ) {
			if ( str_starts_with( $path, $base ) ) {
				return substr( $path, strlen( $base ) );
			}
		}

		// Plain permalinks: WordPress reads the route from the query on the front page only.
		if ( ! in_array( $path, array( $home, $home . 'index.php' ), true ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which route is asked for, before any user is known.
		return isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';
	}

	/**
	 * @param array $sections
	 * @return array<string, string>
	 */
	public function add_section( $sections ): array {
		$sections = (array) $sections;

		$sections[ self::SECTION ] = __( 'API Settings', 'subkit-subscriptions' );

		return $sections;
	}

	/**
	 * @param array  $settings
	 * @param string $section
	 * @return array<int, array<string, mixed>>
	 */
	public function settings( $settings, $section ): array {
		if ( self::SECTION !== $section ) {
			return (array) $settings;
		}

		return array(
			array(
				'title' => __( 'API access', 'subkit-subscriptions' ),
				'type'  => 'title',
				'id'    => 'subkit_api_title',
			),
			array(
				'title'    => __( 'Allow API keys', 'subkit-subscriptions' ),
				'desc'     => __( 'Apps can read and manage subscriptions with a WooCommerce REST API key.', 'subkit-subscriptions' ),
				'desc_tip' => __( 'A read key can only read, and a write key can only make changes. The key\'s user must be able to manage WooCommerce. When off, only a logged-in store manager can use these endpoints.', 'subkit-subscriptions' ),
				'type'     => 'checkbox',
				'id'       => self::OPTION,
				'default'  => 'no',
			),
			array( 'type' => 'subkit_api_info' ),
			array(
				'type' => 'sectionend',
				'id'   => 'subkit_api_title',
			),
		);
	}

	public function render_info(): void {
		printf( '<tr valign="top"><th scope="row" class="titledesc">%s</th><td class="forminp subkit-api-info">', esc_html__( 'Endpoints', 'subkit-subscriptions' ) );

		foreach ( self::endpoints() as $namespace => $routes ) {
			printf(
				'<p>%s <code>%s</code></p><table class="widefat striped"><tbody>',
				esc_html__( 'Base URL', 'subkit-subscriptions' ),
				esc_html( rest_url( $namespace ) )
			);

			foreach ( $routes as $route ) {
				printf( '<tr><td><code>%s</code></td><td><code>%s</code></td></tr>', esc_html( implode( ', ', $route['methods'] ) ), esc_html( $route['path'] ) );
			}

			echo '</tbody></table>';
		}

		printf(
			'<p><a href="%s">%s</a></p></td></tr>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys&create-key=1' ) ),
			esc_html__( 'Create API keys', 'subkit-subscriptions' )
		);
	}

	/**
	 * Every route an API key can reach, by namespace, with its path inside the namespace.
	 *
	 * @return array<string, array<int, array{path: string, methods: string[]}>>
	 */
	public static function endpoints(): array {
		$server = rest_get_server();
		$found  = array();

		foreach ( array_intersect( self::NAMESPACES, $server->get_namespaces() ) as $namespace ) {
			foreach ( $server->get_routes( $namespace ) as $route => $handlers ) {
				$path = substr( (string) $route, strlen( '/' . $namespace ) );

				if ( '' === $path || self::is_receiver( (string) $route ) ) {
					continue;
				}

				$methods = array();

				foreach ( $handlers as $handler ) {
					$methods = array_merge( $methods, array_keys( (array) ( $handler['methods'] ?? array() ) ) );
				}

				$found[ $namespace ][] = array(
					'path'    => (string) preg_replace( '#\(\?P<(\w+)>[^)]*\)#', '{$1}', $path ),
					'methods' => array_values( array_intersect( self::METHODS, $methods ) ),
				);
			}
		}

		return $found;
	}
}
