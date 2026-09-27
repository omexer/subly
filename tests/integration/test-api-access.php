<?php
/**
 * API Settings: WooCommerce API keys reach EasySubscription's routes only when allowed, with the
 * key's read or write permission and its user's capability still enforced; webhook receivers
 * are untouched either way; and the endpoint list leaves the receivers out.
 *
 * Each request goes through the REST server as a real one does, with WooCommerce's own
 * authentication, fresh for every request as a new PHP process would have it.
 *
 * @package EasySubscription
 */

use EasySubscription\Rest\Api_Access;

require __DIR__ . '/bootstrap.php';

// Held until the end, so the REST server can still send its headers.
ob_start();

global $wpdb;

if ( ! class_exists( 'WC_REST_Authentication' ) ) {
	easysubscription_test_abort( "WooCommerce's REST authentication is not loaded" );
}

$absent   = new stdClass();
$snapshot = array(
	Api_Access::OPTION                                         => get_option( Api_Access::OPTION, $absent ),
	\EasySubscription\Frontend\Product_Display::OPTION_BUTTON_TEXT       => get_option( \EasySubscription\Frontend\Product_Display::OPTION_BUTTON_TEXT, $absent ),
	'easysubscription_paypal_webhook_id'                                 => get_option( 'easysubscription_paypal_webhook_id', $absent ),
);
$server_before = array_intersect_key( $_SERVER, array_flip( array( 'REQUEST_METHOD', 'REQUEST_URI', 'HTTPS', 'PHP_AUTH_USER', 'PHP_AUTH_PW', 'CONTENT_TYPE' ) ) );
$get_before    = $_GET;
$post_before   = $_POST;

// WooCommerce's authentication keeps the key it found for the rest of the process.
$auth_hooks = array(
	array( 'determine_current_user', 'authenticate', 15, 1 ),
	array( 'rest_authentication_errors', 'authentication_fallback', 10, 1 ),
	array( 'rest_authentication_errors', 'check_authentication_error', 15, 1 ),
	array( 'rest_post_dispatch', 'send_unauthorized_headers', 50, 1 ),
	array( 'rest_pre_dispatch', 'check_user_permissions', 10, 3 ),
);
$unhook = static function ( $auth ) use ( $auth_hooks ): void {
	foreach ( $auth_hooks as list( $hook, $method, $priority ) ) {
		remove_filter( $hook, array( $auth, $method ), $priority );
	}
};
$shared = null;
foreach ( $GLOBALS['wp_filter']['determine_current_user']->callbacks[15] ?? array() as $callback ) {
	if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof WC_REST_Authentication ) {
		$shared = $callback['function'][0];
	}
}
if ( ! $shared ) {
	easysubscription_test_abort( "WooCommerce's REST authentication is not hooked" );
}
$unhook( $shared );
$auth = null;

$make_user = static function ( string $role ): int {
	return (int) wp_insert_user(
		array(
			'user_login' => 'sk_api_' . $role . '_' . wp_generate_password( 6, false, false ),
			'user_pass'  => wp_generate_password( 32 ),
			'user_email' => 'es-api-' . wp_generate_password( 6, false, false ) . '@example.test',
			'role'       => $role,
		)
	);
};
$manager  = $make_user( 'shop_manager' );
$customer = $make_user( 'customer' );

// Made as WooCommerce makes them: the key stored hashed, the secret as issued.
$key_ids  = array();
$make_key = static function ( int $user_id, string $permissions ) use ( $wpdb, &$key_ids ): array {
	$consumer_key    = 'ck_' . wc_rand_hash();
	$consumer_secret = 'cs_' . wc_rand_hash();
	$wpdb->insert(
		$wpdb->prefix . 'woocommerce_api_keys',
		array(
			'user_id'         => $user_id,
			'description'     => 'SK API access test',
			'permissions'     => $permissions,
			'consumer_key'    => wc_api_hash( $consumer_key ),
			'consumer_secret' => $consumer_secret,
			'truncated_key'   => substr( $consumer_key, -7 ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	$key_ids[] = (int) $wpdb->insert_id;
	return array( $consumer_key, $consumer_secret );
};
$read       = $make_key( $manager, 'read' );
$write      = $make_key( $manager, 'write' );
$read_write = $make_key( $manager, 'read_write' );
$customers  = $make_key( $customer, 'read_write' );
$wrong      = array( $read_write[0], 'cs_' . wc_rand_hash() );

$status = 0;
$code   = '';
$record = static function ( $response ) use ( &$status, &$code ) {
	$status = $response instanceof WP_REST_Response ? $response->get_status() : 0;
	$data   = $response instanceof WP_REST_Response ? $response->get_data() : array();
	$code   = is_array( $data ) ? (string) ( $data['code'] ?? $data['error'] ?? '' ) : '';
	return $response;
};
add_filter( 'rest_post_dispatch', $record, 1000 );

/**
 * One HTTPS request with a JSON body, as WordPress serves it: WooCommerce reads Basic auth from the server vars.
 *
 * @param array{0: string, 1: string}|null $key
 * @return array{0: int, 1: string}
 */
$call = static function ( string $method, string $route, ?array $key, array $body = array(), string $uri = '' ) use ( &$auth, &$status, &$code, $unhook ): array {
	if ( $auth ) {
		$unhook( $auth );
	}
	$auth = new WC_REST_Authentication();

	$_SERVER['REQUEST_METHOD'] = $method;
	$_SERVER['REQUEST_URI']    = '' === $uri ? '/' . rest_get_url_prefix() . $route : $uri;
	$_SERVER['HTTPS']          = 'on';
	unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
	if ( $key ) {
		$_SERVER['PHP_AUTH_USER'] = $key[0];
		$_SERVER['PHP_AUTH_PW']   = $key[1];
	}
	$_SERVER['CONTENT_TYPE'] = 'application/json';
	$_GET                    = array();
	$_POST                   = array();
	// What the REST server reads as the raw body, in place of php://input.
	$GLOBALS['HTTP_RAW_POST_DATA'] = $body ? (string) wp_json_encode( $body ) : '';

	$GLOBALS['current_user'] = null;
	wp_get_current_user();

	$status = 0;
	$code   = '';
	ob_start();
	rest_get_server()->serve_request( $route );
	ob_end_clean();

	return array( $status, $code );
};

$is_ours = static function ( string $uri ): bool {
	$_SERVER['REQUEST_URI'] = $uri;
	$_GET                   = array();
	$query                  = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	if ( '' !== $query ) {
		parse_str( $query, $_GET );
	}
	return (bool) apply_filters( 'woocommerce_rest_is_request_to_rest_api', false );
};

$save_toggle = static function ( string $value ): int {
	$GLOBALS['current_user'] = null;
	wp_set_current_user( 1 );
	$request = new WP_REST_Request( 'POST', '/easysubscription/v1/settings/api' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'values' => array( Api_Access::OPTION => $value ) ) ) );
	return rest_do_request( $request )->get_status();
};

$label  = \EasySubscription\Frontend\Product_Display::OPTION_BUTTON_TEXT;
$change = static fn( string $text ): array => array( 'values' => array( $label => $text ) );
update_option( $label, 'Before' );

echo "\n1. Off, the default: a valid key is ignored on EasySubscription's routes\n";
delete_option( Api_Access::OPTION );
$check( 'the key is valid: WooCommerce\'s own route accepts it', 200 === $call( 'GET', '/wc/v3/products', $read_write )[0], $status );
$check( 'GET on easysubscription/v1 is refused as a visitor', array( 401, 'rest_forbidden' ) === $call( 'GET', '/easysubscription/v1/overview', $read_write ), array( $status, $code ) );
$check( 'POST on easysubscription/v1 is refused, nothing written', 401 === $call( 'POST', '/easysubscription/v1/settings/checkout', $read_write, $change( 'Off write' ) )[0] && 'Before' === get_option( $label ) );
$check( 'our filter leaves WooCommerce\'s answer alone', false === $is_ours( '/wp-json/easysubscription/v1/overview' ) && true === (bool) apply_filters( 'woocommerce_rest_is_request_to_rest_api', true ) );

echo "\n2. On: WooCommerce authenticates the key, with its permission and its user's capability\n";
$check( 'the switch saves on through the settings API', 200 === $save_toggle( 'yes' ) && Api_Access::enabled() );
$check( 'a read key can GET', 200 === $call( 'GET', '/easysubscription/v1/overview', $read )[0], array( $status, $code ) );
$check( 'a read key can GET a route with a parameter', 200 === $call( 'GET', '/easysubscription/v1/settings/api', $read )[0], array( $status, $code ) );
$check( 'a read key cannot POST, nothing written', array( 401, 'woocommerce_rest_authentication_error' ) === $call( 'POST', '/easysubscription/v1/settings/checkout', $read, $change( 'Read write' ) ) && 'Before' === get_option( $label ), array( $status, $code, get_option( $label ) ) );
$check( '  nor on a second try', 401 === $call( 'POST', '/easysubscription/v1/settings/checkout', $read, $change( 'Read write' ) )[0] && 'Before' === get_option( $label ) );
$check( 'a write key cannot GET', array( 401, 'woocommerce_rest_authentication_error' ) === $call( 'GET', '/easysubscription/v1/overview', $write ), array( $status, $code ) );
$check( 'a write key can POST', 200 === $call( 'POST', '/easysubscription/v1/settings/checkout', $write, $change( 'Write key' ) )[0] && 'Write key' === get_option( $label ), array( $status, $code ) );
$check( 'a read/write key can GET', 200 === $call( 'GET', '/easysubscription/v1/overview', $read_write )[0] );
$check( 'a read/write key can POST', 200 === $call( 'POST', '/easysubscription/v1/settings/checkout', $read_write, $change( 'Read-write key' ) )[0] && 'Read-write key' === get_option( $label ), array( $status, $code ) );
$check( '  and the same POST again changes nothing further', 200 === $call( 'POST', '/easysubscription/v1/settings/checkout', $read_write, $change( 'Read-write key' ) )[0] && 'Read-write key' === get_option( $label ) );
$check( 'a customer\'s read/write key is forbidden to GET', array( 403, 'rest_forbidden' ) === $call( 'GET', '/easysubscription/v1/overview', $customers ), array( $status, $code ) );
$check( 'a customer\'s read/write key is forbidden to POST, nothing written', 403 === $call( 'POST', '/easysubscription/v1/settings/checkout', $customers, $change( 'Customer' ) )[0] && 'Read-write key' === get_option( $label ), array( $status, $code ) );
$check( 'a wrong secret is refused', array( 401, 'woocommerce_rest_authentication_error' ) === $call( 'GET', '/easysubscription/v1/overview', $wrong ), array( $status, $code ) );
$check( 'no key is still a visitor', array( 401, 'rest_forbidden' ) === $call( 'GET', '/easysubscription/v1/overview', null ), array( $status, $code ) );
$check( 'plain permalinks are recognised too', true === $is_ours( '/index.php?rest_route=/easysubscription/v1/overview' ) && true === $is_ours( '/wp-json/EasySubscription/V1/overview' ) && true === $is_ours( '/wp-json/easysubscription-pro/v1/deliveries' ) );
$check( 'the pretty and index.php forms of the address are recognised', true === $is_ours( '/index.php/wp-json/easysubscription/v1/overview' ) );
$check( 'a REST-looking address that is not a REST request is not claimed', false === $is_ours( '/wp-admin/admin-ajax.php/wp-json/easysubscription/v1/overview' ) && false === $is_ours( '/wp-admin/admin.php?rest_route=/easysubscription/v1/overview' ) && false === $is_ours( '/shop/wp-json/easysubscription/v1/overview' ) && false === $is_ours( '/?p=1&x=/wp-json/easysubscription/v1/overview' ) );
$check( 'other plugins\' routes are not claimed', false === $is_ours( '/wp-json/easysubscriptionx/v1/overview' ) && false === $is_ours( '/wp-json/easysubscription/v10/overview' ) && false === $is_ours( '/wp-json/wp/v2/users' ) && false === $is_ours( '/easysubscription/v1/overview' ) );

echo "\n3. Webhook receivers: the same with the switch on or off\n";
$receivers = array();
foreach ( array_keys( rest_get_server()->get_routes() ) as $route ) {
	if ( Api_Access::is_ours( (string) $route ) && Api_Access::is_receiver( (string) $route ) ) {
		$receivers[] = (string) $route;
	}
}
$check( 'the PayPal receiver is one of them', in_array( '/easysubscription/v1/webhook/paypal', $receivers, true ), $receivers );
$claimed = array_values( array_filter( $receivers, static fn( string $route ): bool => $is_ours( '/wp-json' . $route ) || $is_ours( '/index.php?rest_route=' . $route ) ) );
$check( 'no receiver is handed to WooCommerce\'s key check', array() === $claimed, $claimed );
$check( '  nor one written differently', false === $is_ours( '/wp-json/EasySubscription/v1/Webhook/PayPal/' ) && false === $is_ours( '/wp-json/easysubscription/v1/web%68ook/paypal' ) );

// With no webhook ID the receiver refuses every event itself, without asking PayPal.
delete_option( 'easysubscription_paypal_webhook_id' );
$forged = static function ( ?array $key ) use ( $call ): array {
	return $call(
		'POST',
		'/easysubscription/v1/webhook/paypal',
		$key,
		array(
			'id'         => 'WH-FORGED-' . wp_generate_password( 8, false, false ),
			'event_type' => 'PAYMENT.SALE.COMPLETED',
		)
	);
};
$outcomes = array();
foreach ( array( 'yes', 'no' ) as $toggle ) {
	$save_toggle( $toggle );
	// A read key would be refused a POST, and a wrong secret refused outright, if WooCommerce checked them here.
	foreach ( array( 'read key' => $read, 'wrong secret' => $wrong, 'no key' => null ) as $who => $key ) {
		$outcomes[ $who ][ $toggle ] = $forged( $key );
	}
}
foreach ( $outcomes as $who => $by_toggle ) {
	$check( "the PayPal receiver answers a {$who} the same way on and off, with its own signature check", array( 401, 'signature' ) === $by_toggle['yes'] && $by_toggle['yes'] === $by_toggle['no'], $by_toggle );
}

echo "\n4. The endpoint list\n";
$save_toggle( 'yes' );
$endpoints = Api_Access::endpoints();
$listed    = array();
foreach ( $endpoints as $namespace => $routes ) {
	foreach ( $routes as $route ) {
		$listed[] = '/' . $namespace . $route['path'];
	}
}
$expected = array();
foreach ( rest_get_server()->get_routes( 'easysubscription/v1' ) as $route => $handlers ) {
	if ( '/easysubscription/v1' !== $route && ! Api_Access::is_receiver( (string) $route ) ) {
		$expected[] = (string) preg_replace( '#\(\?P<(\w+)>[^)]*\)#', '{$1}', (string) $route );
	}
}
$check( 'every easysubscription/v1 route is listed', array() === array_diff( $expected, $listed ), array_diff( $expected, $listed ) );
$check( 'no webhook receiver is listed', array() === array_filter( $listed, static fn( string $route ): bool => str_contains( $route, 'webhook' ) ), $listed );
$by_path = array_column( $endpoints['easysubscription/v1'] ?? array(), 'methods', 'path' );
$check( 'each route carries its methods', array( 'GET' ) === ( $by_path['/overview'] ?? null ) && array( 'GET', 'POST' ) === ( $by_path['/settings/{section}'] ?? null ), $by_path );

$GLOBALS['current_user'] = null;
wp_set_current_user( 1 );
$page = rest_do_request( new WP_REST_Request( 'GET', '/easysubscription/v1/settings/api' ) )->get_data();
$html = implode( '', array_filter( array_column( array_merge( ...array_column( $page['cards'] ?? array(), 'rows' ) ), 'html' ) ) );
$ids  = array_filter( array_column( array_merge( ...array_column( $page['cards'] ?? array(), 'rows' ) ), 'id' ) );
$check( 'the API Settings page has the switch and the endpoint list', in_array( Api_Access::OPTION, $ids, true ) && str_contains( $html, esc_html( rest_url( 'easysubscription/v1' ) ) ) && str_contains( $html, '/settings/{section}' ), $ids );
$check( '  with no receiver in it', ! str_contains( $html, 'webhook/paypal' ) );
$check( '  and a link to create API keys', str_contains( $html, 'section=keys' ) && str_contains( $html, 'Create API keys' ) );
$check( 'it sits in the API Settings group', 'api' === ( $page['group'] ?? '' ) );

remove_filter( 'rest_post_dispatch', $record, 1000 );
if ( $auth ) {
	$unhook( $auth );
}
foreach ( $auth_hooks as list( $hook, $method, $priority, $args ) ) {
	add_filter( $hook, array( $shared, $method ), $priority, $args );
}
foreach ( $key_ids as $key_id ) {
	$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $key_id ), array( '%d' ) );
}
wp_delete_user( $manager );
wp_delete_user( $customer );
foreach ( $snapshot as $option => $value ) {
	$absent === $value ? delete_option( $option ) : update_option( $option, $value );
}
foreach ( array( 'REQUEST_METHOD', 'REQUEST_URI', 'HTTPS', 'PHP_AUTH_USER', 'PHP_AUTH_PW', 'CONTENT_TYPE' ) as $var ) {
	unset( $_SERVER[ $var ] );
}
unset( $GLOBALS['HTTP_RAW_POST_DATA'] );
$_SERVER = array_merge( $_SERVER, $server_before );
$_GET    = $get_before;
$_POST   = $post_before;
$GLOBALS['current_user'] = null;
wp_set_current_user( 1 );

ob_end_flush();
easysubscription_test_done( $fail );
