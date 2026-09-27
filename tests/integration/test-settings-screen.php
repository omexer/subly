<?php
/**
 * EasySubscription → Settings: every section is reachable in its group, a page saves what it draws,
 * dependent rows carry their parent, and the cancellation controls hold on the server.
 *
 * @package EasySubscription
 */

use EasySubscription\Admin\Settings_Page;
use EasySubscription\Domain\Subscription;
use EasySubscription\Domain\Subscription_Status;
use EasySubscription\Lifecycle\Cancellation_Policy;

require __DIR__ . '/bootstrap.php';

if ( ! class_exists( 'EasySubscription_Test_Redirected' ) ) {
	// Handlers redirect and exit once they act; stop at the redirect and read the state.
	final class EasySubscription_Test_Redirected extends Exception {}
}
add_filter(
	'wp_redirect',
	static function ( $to ) {
		throw new EasySubscription_Test_Redirected( (string) $to );
	},
	1
);

global $wp_actions;

$woo = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'easysubscription' === $candidate->get_id() ) {
		$woo = $candidate;
	}
}
$screen = \EasySubscription\Plugin::instance()->get( 'settings_page' );
if ( ! $woo || ! $screen instanceof Settings_Page ) {
	easysubscription_test_abort( 'the settings tab or the EasySubscription settings screen is not registered' );
}

// Two sections of our own for the duration: one no group names, and one stacked beside Renewal.
$extra = static function ( $sections ) {
	$sections = (array) $sections;
	$sections['easysubscription_test_extra'] = 'Test extra';
	if ( ! isset( $sections['recovery'] ) ) {
		$sections['recovery'] = 'Test retries';
	}
	return $sections;
};
$extra_fields = static function ( $settings, $section ) {
	if ( 'easysubscription_test_extra' === $section ) {
		return array( array( 'title' => 'Extra', 'type' => 'text', 'id' => 'easysubscription_test_extra_option', 'default' => '' ) );
	}
	if ( 'recovery' === $section ) {
		return array_merge( (array) $settings, array( array( 'title' => 'Stacked', 'type' => 'text', 'id' => 'easysubscription_test_stacked_option', 'default' => '' ) ) );
	}
	return $settings;
};
add_filter( 'woocommerce_get_sections_easysubscription', $extra, 99 );
add_filter( 'woocommerce_get_settings_easysubscription', $extra_fields, 99, 2 );

// Every option any page here can save, as it was, so the saves below can all be undone.
$absent   = new stdClass();
$snapshot = array();
foreach ( array_keys( $woo->get_sections() ) as $section ) {
	foreach ( $woo->get_settings_for_section( (string) $section ) as $field ) {
		if ( ! empty( $field['id'] ) && ! in_array( $field['type'] ?? '', array( 'title', 'sectionend' ), true ) ) {
			$snapshot[ $field['id'] ] = get_option( $field['id'], $absent );
		}
	}
}

$render = static function ( string $section ) use ( $screen ): string {
	$_GET = '' === $section ? array() : array( 'section' => $section );
	ob_start();
	$screen->render();
	$_GET = array();
	return (string) ob_get_clean();
};

$xpath = static function ( string $html ): DOMXPath {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	return new DOMXPath( $doc );
};

// What a browser would send from the page as drawn.
$form_of = static function ( string $html ) use ( $xpath ): array {
	$x    = $xpath( $html );
	$post = array();
	foreach ( $x->query( '//form[contains(@class,"easysubscription-settings__main")]//*[self::input or self::select or self::textarea][@name]' ) as $el ) {
		$name = $el->getAttribute( 'name' );
		if ( 'select' === $el->nodeName ) {
			$chosen        = $x->query( './/option[@selected]', $el )->item( 0 ) ?? $x->query( './/option', $el )->item( 0 );
			$post[ $name ] = $chosen ? $chosen->getAttribute( 'value' ) : '';
		} elseif ( 'textarea' === $el->nodeName ) {
			$post[ $name ] = $el->textContent;
		} elseif ( in_array( $el->getAttribute( 'type' ), array( 'checkbox', 'radio' ), true ) ) {
			if ( $el->hasAttribute( 'checked' ) ) {
				$post[ $name ] = $el->getAttribute( 'value' );
			}
		} elseif ( ! in_array( $el->getAttribute( 'type' ), array( 'submit', 'button' ), true ) ) {
			$post[ $name ] = $el->getAttribute( 'value' );
		}
	}
	return $post;
};

$save = static function ( array $post ) use ( $screen ): string {
	$_POST    = $post;
	$_REQUEST = $post;
	$to       = '';
	try {
		$screen->maybe_save();
	} catch ( EasySubscription_Test_Redirected $e ) {
		$to = $e->getMessage();
	}
	$_POST    = array();
	$_REQUEST = array();
	return $to;
};

$row_of = static function ( string $html, string $id ) use ( $xpath ): ?DOMElement {
	$found = $xpath( $html )->query( '//*[@id="' . $id . '"]/ancestor::div[contains(concat(" ",@class," ")," easysubscription-settings__row ")][1]' )->item( 0 );
	return $found instanceof DOMElement ? $found : null;
};

echo "\n1. Every section is reachable, in its group\n";
$sections = $woo->get_sections();
$groups   = Settings_Page::groups();
foreach ( $sections as $section => $label ) {
	$section = (string) $section;
	$group   = Settings_Page::group_of( $section );
	$html    = $render( $section );
	$x       = $xpath( $html );
	$current = $x->query( '//a[contains(concat(" ",@class," ")," easysubscription-settings__tab ")][contains(@class,"is-current")]' );
	$fields  = array_filter( $woo->get_settings_for_section( $section ), static fn( $f ) => ! empty( $f['id'] ) && in_array( $f['type'] ?? '', array( 'checkbox', 'select', 'text', 'number', 'password', 'textarea', 'email', 'url' ), true ) );
	$missing = array_values( array_filter( array_column( $fields, 'id' ), static fn( $id ) => 0 === $x->query( '//form//*[@id="' . $id . '"]' )->length ) );
	$url     = wp_parse_url( Settings_Page::section_url( $section ) );
	parse_str( $url['query'] ?? '', $query );

	$check( "'{$section}' ({$label}) belongs to the {$group} group", isset( $groups[ $group ] ) );
	$check( "  its link opens that section", ( '' === $section ? ! isset( $query['section'] ) : $section === ( $query['section'] ?? null ) ) && Settings_Page::SLUG === ( $query['page'] ?? '' ), $query );
	$check( "  where the menu marks {$groups[$group]['label']} as current", 1 === $current->length && str_contains( $current->item( 0 )->textContent, $groups[ $group ]['label'] ), $current->length ? $current->item( 0 )->textContent : null );
	$check( '  and every one of its fields is on the page', array() === $missing, $missing );
}
$check( 'a section no group names lands in Integrations', 'integrations' === Settings_Page::group_of( 'easysubscription_test_extra' ) );

$stripe = $xpath( $render( 'stripe' ) );
$subnav = array_map( static fn( $a ) => trim( $a->textContent ), iterator_to_array( $stripe->query( '//ul[contains(@class,"easysubscription-settings__subnav")]//a' ) ) );
$check( 'Payments lists its gateways under the menu entry', in_array( 'Stripe', $subnav, true ) && in_array( 'PayPal', $subnav, true ), $subnav );
$check( 'with the one on screen marked', 'Stripe' === trim( (string) $stripe->query( '//ul[contains(@class,"easysubscription-settings__subnav")]//a[@aria-current="page"]' )->item( 0 )?->textContent ) );
$check( 'and only its own fields in the form', 0 === $stripe->query( '//form//*[@id="easysubscription_paypal_client_id"]' )->length );

$renewal = $render( 'recovery' );
$check( 'a stacked group draws its sections together', str_contains( $renewal, 'id="easysubscription_catch_up_policy"' ) && str_contains( $renewal, 'id="easysubscription_test_stacked_option"' ) );
$check( 'with an anchor for each', str_contains( $renewal, 'id="easysubscription-section-recovery"' ) && str_contains( $renewal, 'id="easysubscription-section-renewal"' ) );

$general = $render( '' );
$check( 'General carries the health checks', str_contains( $general, 'Renewal queue' ) && str_contains( $general, 'Double-charge protection' ) );

$was_pro = $wp_actions['easysubscription_pro_loaded'] ?? null;
unset( $wp_actions['easysubscription_pro_loaded'] );
$check( 'without Pro the header offers the upgrade', str_contains( $render( 'customer_controls' ), 'easysubscription-shell__upgrade' ) );
$wp_actions['easysubscription_pro_loaded'] = 1;
$check( 'with Pro it does not', ! str_contains( $render( 'customer_controls' ), 'easysubscription-shell__upgrade' ) );
if ( null === $was_pro ) {
	unset( $wp_actions['easysubscription_pro_loaded'] );
} else {
	$wp_actions['easysubscription_pro_loaded'] = $was_pro;
}

echo "\n2. A page saves what it draws\n";
update_option( Cancellation_Policy::OPTION, 'yes' );
update_option( Cancellation_Policy::OPTION_WHEN, Cancellation_Policy::AT_PERIOD_END );
$html = $render( 'customer_controls' );
$post = $form_of( $html );
$check( 'the form carries the switches as checkboxes', isset( $post[ Cancellation_Policy::OPTION ] ) && ! isset( $post['easysubscription_allow_auto_renew_toggle'] ), array_keys( $post ) );
$switch = $xpath( $html )->query( '//input[@id="' . Cancellation_Policy::OPTION . '"]' )->item( 0 );
$check( 'a switch is a real checkbox announced as a switch, and labelled', $switch && 'checkbox' === $switch->getAttribute( 'type' ) && 'switch' === $switch->getAttribute( 'role' ) && str_contains( $html, 'for="' . Cancellation_Policy::OPTION . '"' ) );

unset( $post[ Cancellation_Policy::OPTION ] );
$post[ Cancellation_Policy::OPTION_WHEN ] = Cancellation_Policy::IMMEDIATELY;
$post['easysubscription_allow_auto_renew_toggle']   = '1';

wp_set_current_user( 0 );
$save( $post );
$check( 'someone who cannot manage the store saves nothing', 'yes' === get_option( Cancellation_Policy::OPTION ) );
wp_set_current_user( 1 );

$to = $save( $post );
$check( 'the store saves, and comes back to the same section', str_contains( $to, 'section=customer_controls' ) && str_contains( $to, 'easysubscription_saved=1' ), $to );
$check( 'a switch turned off saves as off', 'no' === get_option( Cancellation_Policy::OPTION ) );
$check( 'a switch turned on saves as on', 'yes' === get_option( 'easysubscription_allow_auto_renew_toggle' ) );
$check( 'a row hidden behind a switch keeps the value it was given', Cancellation_Policy::IMMEDIATELY === get_option( Cancellation_Policy::OPTION_WHEN ) );

$html = $render( 'customer_controls' );
$row  = $row_of( $html, Cancellation_Policy::OPTION_WHEN );
$check( 'a dependent row names its parent', $row && Cancellation_Policy::OPTION === $row->getAttribute( 'data-easysubscription-show-if' ) );
$check( 'and starts hidden while the parent is off', $row && str_contains( $row->getAttribute( 'class' ), 'is-hidden' ) );
$check( 'its saved choice still drawn', str_contains( $html, 'value="' . Cancellation_Policy::IMMEDIATELY . '" selected' ) );
$check( 'while off, the server reads the dependent setting as off too', ! Cancellation_Policy::is_immediate() );

$post                                = $form_of( $html );
$post[ Cancellation_Policy::OPTION ] = '1';
$save( $post );
$row = $row_of( $render( 'customer_controls' ), Cancellation_Policy::OPTION_WHEN );
$check( 'switched back on, it saves and the row shows', 'yes' === get_option( Cancellation_Policy::OPTION ) && $row && ! str_contains( $row->getAttribute( 'class' ), 'is-hidden' ) );
$check( 'and the dependent setting applies again', Cancellation_Policy::is_immediate() );

$post                                = $form_of( $render( 'renewal' ) );
$post['easysubscription_catch_up_policy']      = 'charge_all';
$post['easysubscription_test_stacked_option']  = 'saved together';
$save( $post );
$check( 'a stacked page saves every section on it', 'charge_all' === (string) get_option( 'easysubscription_catch_up_policy' ) && 'saved together' === get_option( 'easysubscription_test_stacked_option' ) );

$post                             = $form_of( $render( 'easysubscription_test_extra' ) );
$post['easysubscription_test_extra_option'] = 'listed';
$save( $post );
$check( 'a listed section saves on its own', 'listed' === get_option( 'easysubscription_test_extra_option' ) );

echo "\n3. The cancellation controls hold on the server\n";
$account = \EasySubscription\Plugin::instance()->get( 'account' );
$alice   = wp_insert_user(
	array(
		'user_login' => 'sk_ctrl_' . wp_generate_password( 6, false, false ),
		'user_pass'  => wp_generate_password( 32 ),
		'user_email' => 'es-ctrl-' . wp_generate_password( 6, false, false ) . '@example.test',
		'role'       => 'customer',
	)
);
$made = array();
$make = static function () use ( $alice, &$made ): Subscription {
	$s = new Subscription();
	$s->set_customer_id( $alice );
	$s->set_billing_period( 'month' );
	$s->set_billing_interval( 1 );
	$s->set_next_payment( gmdate( 'Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS ) );
	$s->transition_to( Subscription_Status::Pending );
	$s->save();
	$s->transition_to( Subscription_Status::Active );
	$s->save();
	$made[] = $s->get_id();
	return $s;
};
$cancel = static function ( Subscription $s, string $when ) use ( $account ): string {
	$post     = array( 'easysubscription_action' => 'cancel', 'easysubscription_when' => $when, 'easysubscription_subscription' => $s->get_id(), '_wpnonce' => wp_create_nonce( 'easysubscription_cancel_' . $s->get_id() ) );
	$_POST    = $post;
	$_REQUEST = $post;
	wc_clear_notices();
	try {
		$account->handle_actions();
	} catch ( EasySubscription_Test_Redirected $e ) {
		unset( $e );
	}
	$_POST    = array();
	$_REQUEST = array();
	return wc_get_order( $s->get_id() )->get_status();
};
$detail = static function ( Subscription $s ) use ( $account ): string {
	ob_start();
	$account->render( (string) $s->get_id() );
	return (string) ob_get_clean();
};

wp_set_current_user( $alice );

update_option( Cancellation_Policy::OPTION, 'no' );
$s      = $make();
$status = $cancel( $s, 'immediate' );
$errors = array_column( wc_get_notices( 'error' ), 'notice' );
$check( 'with cancelling off, a customer posting the form directly cannot cancel', 'es-active' === $status, $status );
$check( 'and is told to contact the store', in_array( \EasySubscription\Frontend\MyAccount\Account_Endpoint::cancel_refused_message( $s ), $errors, true ), $errors );
$page = $detail( $s );
$check( 'the page offers no cancel form, and says why', ! str_contains( $page, 'value="cancel"' ) && str_contains( $page, 'cannot be cancelled online' ) );

wp_set_current_user( 1 );
$request = new WP_REST_Request( 'POST', '/easysubscription/v1/subscriptions/' . $s->get_id() . '/actions' );
$request->set_body_params( array( 'action' => 'cancel' ) );
$response = rest_do_request( $request );
$check( 'the store still can', 'es-cancelled' === wc_get_order( $s->get_id() )->get_status(), array( $response->get_status(), wc_get_order( $s->get_id() )->get_status() ) );
wp_set_current_user( $alice );

update_option( Cancellation_Policy::OPTION, 'yes' );
update_option( Cancellation_Policy::OPTION_WHEN, Cancellation_Policy::AT_PERIOD_END );
$s    = $make();
$page = $detail( $s );
$date = date_i18n( (string) get_option( 'date_format' ), strtotime( $s->get_next_payment() . ' UTC' ) );
$check( 'at the end of the cycle, the form says until when and offers no choice', str_contains( $page, 'Your subscription stays active until ' . $date ) && ! str_contains( $page, 'easysubscription_when' ), $page );
$status = $cancel( $s, 'immediate' );
$check( 'posting "immediate" cannot override the store\'s choice', 'es-pending-cancel' === $status, $status );

update_option( Cancellation_Policy::OPTION_WHEN, Cancellation_Policy::IMMEDIATELY );
$s    = $make();
$page = $detail( $s );
$check( 'immediately, the form says it ends now with no refund', str_contains( $page, 'ends now' ) && str_contains( $page, 'no refund for the time left' ) && ! str_contains( $page, 'easysubscription_when' ) );
$status = $cancel( $s, 'period_end' );
$check( 'posting "period_end" cannot override it either', 'es-cancelled' === $status, $status );
wp_set_current_user( 1 );

// --- clean up ------------------------------------------------------------------------
remove_filter( 'woocommerce_get_sections_easysubscription', $extra, 99 );
remove_filter( 'woocommerce_get_settings_easysubscription', $extra_fields, 99 );
foreach ( $made as $id ) {
	$order = wc_get_order( $id );
	if ( $order ) {
		$order->delete( true );
	}
}
wp_delete_user( $alice );
foreach ( array_merge( $snapshot, array( 'easysubscription_test_extra_option' => $absent, 'easysubscription_test_stacked_option' => $absent ) ) as $name => $value ) {
	if ( $absent === $value ) {
		delete_option( $name );
	} else {
		update_option( $name, $value );
	}
}
wc_clear_notices();

easysubscription_test_done( $fail );
