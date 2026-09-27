<?php
/**
 * The Notifications page: each row's switch is its email's own WooCommerce setting, whichever
 * screen saves it; no email has a second switch; the reminder moved here in hours; and an
 * email's own settings are edited in the app through the email itself.
 *
 * @package EasySubscription
 */

use SubKit\Billing\Renewal_Scheduler;
use SubKit\Emails\Notification_Settings;

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$woo = null;
foreach ( WC_Admin_Settings::get_settings_pages() as $candidate ) {
	if ( $candidate instanceof WC_Settings_Page && 'subkit' === $candidate->get_id() ) {
		$woo = $candidate;
	}
}
if ( ! $woo ) {
	subkit_test_abort( 'the settings tab is not registered' );
}

$emails = WC()->mailer()->get_emails();
$by_id  = array();
foreach ( $emails as $key => $email ) {
	$by_id[ $email->id ] = $email;
}

// Every option this test can write, as it was.
$absent  = new stdClass();
$touched = array( 'subkit_renewal_reminder_days', Renewal_Scheduler::OPTION_REMINDER_HOURS, Renewal_Scheduler::OPTION_EXPIRY_HOURS );
foreach ( $by_id as $id => $email ) {
	if ( 0 === strpos( $id, 'subkit_' ) ) {
		$touched[] = $email->get_option_key();
	}
}
$was = array();
foreach ( $touched as $name ) {
	$was[ $name ] = get_option( $name, $absent );
}

$section = static fn( string $id ): array => (array) $woo->get_settings_for_section( $id );
$rows    = static fn( array $fields ): array => array_values( array_filter( $fields, static fn( $f ) => ! empty( $f['id'] ) && ! in_array( $f['type'] ?? '', array( 'title', 'sectionend' ), true ) ) );

$post = static function ( string $path, array $values ): WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/subkit/v1/settings/' . $path );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'values' => $values ) ) );
	return rest_do_request( $request );
};
$get = static fn( string $path ): WP_REST_Response => rest_do_request( new WP_REST_Request( 'GET', '/subkit/v1/settings/' . $path ) );

$enabled = static function ( string $email_id ) use ( $by_id ): string {
	$settings = get_option( $by_id[ $email_id ]->get_option_key(), array() );
	return (string) ( is_array( $settings ) ? ( $settings['enabled'] ?? '' ) : '' );
};

echo "\n1. The section\n";
$check( 'Notifications is a section of the Subscriptions tab', isset( $woo->get_sections()['notifications'] ), array_keys( $woo->get_sections() ) );
$fields = $section( 'notifications' );
$ids    = array_column( $rows( $fields ), 'id' );

$sw   = static fn( string $email_id ): string => Notification_Settings::switch_id( $email_id );
$main = array(
	$sw( 'subkit_renewal_reminder' ),
	Renewal_Scheduler::OPTION_REMINDER_HOURS,
	$sw( 'subkit_expiring_soon' ),
	Renewal_Scheduler::OPTION_EXPIRY_HOURS,
	$sw( 'subkit_payment_failed' ),
	$sw( 'subkit_trial_ending' ),
	$sw( 'subkit_renewal_receipt' ),
	$sw( 'subkit_subscription_cancelled' ),
	$sw( 'subkit_subscription_reactivated' ),
);
$other = array( $sw( 'subkit_subscription_started' ), $sw( 'subkit_confirm_payment' ), $sw( 'subkit_merchant_new_subscription' ), $sw( 'subkit_merchant_subscription_cancelled' ), $sw( 'subkit_merchant_subscription_ended' ) );
$in    = static fn( array $want ): array => array_values( array_intersect( $ids, $want ) );
$check( "free's rows come in the design's order", $main === $in( $main ), $in( $main ) );
$check( 'the other emails follow in their own card', $other === $in( $other ) && array_search( $other[0], $ids, true ) > array_search( end( $main ), $ids, true ), $ids );

$cards = array();
$card  = '';
foreach ( $fields as $f ) {
	if ( 'title' === ( $f['type'] ?? '' ) ) {
		$card = (string) $f['title'];
	} elseif ( ! empty( $f['subkit_email'] ) ) {
		$cards[ $card ][] = $f['subkit_email'];
	}
}
$check( 'two cards: Notifications, then Other emails', array( 'Notifications', 'Other emails' ) === array_keys( $cards ), array_keys( $cards ) );

$field = static function ( string $id ) use ( $fields ): array {
	foreach ( $fields as $f ) {
		if ( ( $f['id'] ?? '' ) === $id ) {
			return $f;
		}
	}
	return array();
};
$check( 'each switch is the email\'s own WooCommerce setting', 'woocommerce_subkit_trial_ending_settings[enabled]' === $sw( 'subkit_trial_ending' ) && 'checkbox' === ( $field( $sw( 'subkit_trial_ending' ) )['type'] ?? '' ) );
$check( 'the renewal hours show only while the renewal reminder is on', $sw( 'subkit_renewal_reminder' ) === ( $field( Renewal_Scheduler::OPTION_REMINDER_HOURS )['subkit_show_if'] ?? '' ) );
$check( 'the expiry hours show only while the expiry reminder is on', $sw( 'subkit_expiring_soon' ) === ( $field( Renewal_Scheduler::OPTION_EXPIRY_HOURS )['subkit_show_if'] ?? '' ) );
$check( 'the unit is in the title, the help in desc_tip', 'Send renewal reminder before (Hours)' === ( $field( Renewal_Scheduler::OPTION_REMINDER_HOURS )['title'] ?? '' ) && is_string( $field( Renewal_Scheduler::OPTION_REMINDER_HOURS )['desc_tip'] ?? null ) );

echo "\n2. No second switch for any email\n";
$listed = array();
foreach ( array_keys( $woo->get_sections() ) as $id ) {
	foreach ( $section( (string) $id ) as $f ) {
		foreach ( $by_id as $email_id => $email ) {
			if ( 0 === strpos( (string) ( $f['id'] ?? '' ), $email->get_option_key() ) ) {
				$listed[ $email_id ][] = $id . ':' . $f['id'];
			}
		}
	}
}
$ours    = array_filter( array_keys( $by_id ), static fn( $id ) => 0 === strpos( $id, 'subkit_' ) );
$missing = array_values( array_diff( $ours, array_keys( $listed ) ) );
$twice   = array_filter( $listed, static fn( $where ) => count( $where ) > 1 );
$check( 'every EasySubscription email has a row', array() === $missing, $missing );
$check( 'and only one, on the Notifications page', array() === $twice && array() === array_filter( $listed, static fn( $where ) => 0 !== strpos( $where[0], 'notifications:' ) ), $twice );

echo "\n3. The switch saves WooCommerce's own setting, from either screen\n";
$reminder = $by_id['subkit_trial_ending'];
update_option( $reminder->get_option_key(), array( 'enabled' => 'yes', 'subject' => 'Kept subject' ) );

$saved = $post( 'notifications', array( $sw( 'subkit_trial_ending' ) => 'no' ) );
$check( 'the app\'s save turns it off in WooCommerce', 200 === $saved->get_status() && 'no' === $enabled( 'subkit_trial_ending' ), array( $saved->get_status(), get_option( $reminder->get_option_key() ) ) );
$check( 'and leaves the email\'s other settings alone', 'Kept subject' === ( get_option( $reminder->get_option_key() )['subject'] ?? '' ) );
$check( 'the app reads it back as off', 'no' === ( $saved->get_data()['values'][ $sw( 'subkit_trial_ending' ) ] ?? '' ), $saved->get_data()['values'] ?? null );
$check( 'and the email itself is off', ! ( new \SubKit\Emails\Trial_Ending() )->is_enabled() );

$post( 'notifications', array( $sw( 'subkit_trial_ending' ) => 'yes' ) );
$check( 'and on again', 'yes' === $enabled( 'subkit_trial_ending' ) );

// WooCommerce's tab posts the form as the browser nests it.
WC_Admin_Settings::save_fields( array( $field( $sw( 'subkit_trial_ending' ) ) ), array( $reminder->get_option_key() => array() ) );
$check( 'WooCommerce\'s own tab turns it off', 'no' === $enabled( 'subkit_trial_ending' ), get_option( $reminder->get_option_key() ) );
WC_Admin_Settings::save_fields( array( $field( $sw( 'subkit_trial_ending' ) ) ), array( $reminder->get_option_key() => array( 'enabled' => '1' ) ) );
$check( 'and on', 'yes' === $enabled( 'subkit_trial_ending' ) && 'Kept subject' === ( get_option( $reminder->get_option_key() )['subject'] ?? '' ) );

update_option( $reminder->get_option_key(), array( 'enabled' => 'no' ) );
$read = $get( 'notifications' )->get_data();
$row  = array();
foreach ( $read['cards'] as $c ) {
	foreach ( $c['rows'] as $r ) {
		if ( ( $r['id'] ?? '' ) === $sw( 'subkit_trial_ending' ) ) {
			$row = $r;
		}
	}
}
$check( 'a change made under WooCommerce → Emails shows in the app', 'no' === ( $row['value'] ?? '' ) && 'subkit_trial_ending' === ( $row['subkit_email'] ?? '' ), $row );

echo "\n4. The reminder moved here, in hours\n";
$check( 'Renewal & billing no longer has it', array() === array_intersect( array( 'subkit_renewal_reminder_days', Renewal_Scheduler::OPTION_REMINDER_HOURS ), array_column( $section( 'renewal' ), 'id' ) ), array_column( $section( 'renewal' ), 'id' ) );
$check( 'Notifications does', in_array( Renewal_Scheduler::OPTION_REMINDER_HOURS, $ids, true ) );

$migrate = static function (): void {
	\SubKit\Plugin::instance()->get( 'notification_settings' )->migrate();
};
$restore_reminder = get_option( $by_id['subkit_renewal_reminder']->get_option_key(), $absent );

delete_option( Renewal_Scheduler::OPTION_REMINDER_HOURS );
update_option( 'subkit_renewal_reminder_days', 5 );
$migrate();
$check( 'a store\'s days become hours', 120 === (int) get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ), get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ) );
$check( 'and the days are gone', false === get_option( 'subkit_renewal_reminder_days', false ) );
$check( 'the same value the page shows', '120' === (string) ( $field( Renewal_Scheduler::OPTION_REMINDER_HOURS ) ? WC_Admin_Settings::get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ) : '' ) );

update_option( Renewal_Scheduler::OPTION_REMINDER_HOURS, 30 );
update_option( 'subkit_renewal_reminder_days', 2 );
$migrate();
$migrate();
$check( 'it runs once: hours already set are never overwritten', 30 === (int) get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ), get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ) );
delete_option( 'subkit_renewal_reminder_days' );

delete_option( Renewal_Scheduler::OPTION_REMINDER_HOURS );
update_option( $by_id['subkit_renewal_reminder']->get_option_key(), array( 'enabled' => 'yes' ) );
update_option( 'subkit_renewal_reminder_days', 0 );
$migrate();
$check( '0 days, which turned the reminder off, turns its email off', 'no' === $enabled( 'subkit_renewal_reminder' ), get_option( $by_id['subkit_renewal_reminder']->get_option_key() ) );
$check( 'and the hours start at the default', Renewal_Scheduler::DEFAULT_HOURS === (int) get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ) );

delete_option( Renewal_Scheduler::OPTION_REMINDER_HOURS );
$migrate();
$check( 'a store that never set it gets 24 hours', 24 === (int) get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ) );

foreach ( array( '0' => 1, '-3' => 1, 'abc' => 24, '48' => 48 ) as $posted => $kept ) {
	WC_Admin_Settings::save_fields( array( $field( Renewal_Scheduler::OPTION_REMINDER_HOURS ) ), array( Renewal_Scheduler::OPTION_REMINDER_HOURS => (string) $posted ) );
	$check( "hours saved as '$posted' are kept as $kept", $kept === (int) get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ), get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS ) );
}
update_option( Renewal_Scheduler::OPTION_EXPIRY_HOURS, 'lots' );
$check( 'hours written around the screen are read as the default', 24 === Renewal_Scheduler::hours( Renewal_Scheduler::OPTION_EXPIRY_HOURS ) );

if ( $absent === $restore_reminder ) {
	delete_option( $by_id['subkit_renewal_reminder']->get_option_key() );
} else {
	update_option( $by_id['subkit_renewal_reminder']->get_option_key(), $restore_reminder );
}

echo "\n5. Editing an email in the app\n";
update_option( $reminder->get_option_key(), array( 'enabled' => 'yes' ) );
$reminder->init_settings();

$shown = $get( 'emails/subkit_trial_ending' );
$keys  = array_column( (array) ( $shown->get_data()['fields'] ?? array() ), 'key' );
$check( 'the email\'s own fields are offered', 200 === $shown->get_status() && array() === array_diff( array( 'enabled', 'subject', 'heading', 'additional_content', 'email_type' ), $keys ), $keys );
$check( 'with its placeholders as the hint', str_contains( (string) ( array_column( $shown->get_data()['fields'], 'help', 'key' )['subject'] ?? '' ), '{site_title}' ) );
$check( 'and a way to WooCommerce\'s own screen', str_contains( (string) $shown->get_data()['woo_url'], 'tab=email&section=' ) );

$edit = $post( 'emails/subkit_trial_ending', array( 'subject' => 'Trial ends {site_title}', 'heading' => 'Heads up', 'additional_content' => 'Questions? Reply to this email.' ) );
$kept = get_option( $reminder->get_option_key() );
$check( 'saving writes WooCommerce\'s own option', 200 === $edit->get_status() && 'Trial ends {site_title}' === ( $kept['subject'] ?? '' ) && 'Heads up' === ( $kept['heading'] ?? '' ) && 'Questions? Reply to this email.' === ( $kept['additional_content'] ?? '' ), array( $edit->get_status(), $kept ) );
$check( 'without touching its switch', 'yes' === ( $kept['enabled'] ?? '' ) );

$mail = array();
add_filter(
	'wp_mail',
	static function ( $args ) use ( &$mail ) {
		$mail[] = $args;
		return $args;
	},
	999
);
$s = new \SubKit\Domain\Subscription();
$s->set_customer_id( 1 );
$s->set_currency( get_woocommerce_currency() );
$s->set_address( array( 'email' => 'notify-edit@example.test' ), 'billing' );
$s->set_trial_end( gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ) );
$s->transition_to( \SubKit\Domain\Subscription_Status::Pending );
$s->save();
WC()->mailer()->get_emails()['SubKit_Trial_Ending']->trigger( $s );
$sent = $mail[0] ?? array();
$check( 'the email sent uses the new subject', str_starts_with( (string) ( $sent['subject'] ?? '' ), 'Trial ends ' ) && ! str_contains( (string) ( $sent['subject'] ?? '' ), '{site_title}' ), $sent['subject'] ?? '' );
$check( 'and heading and additional content', str_contains( (string) ( $sent['message'] ?? '' ), 'Heads up' ) && str_contains( (string) ( $sent['message'] ?? '' ), 'Reply to this email' ) );
$s->delete( true );

update_option( $reminder->get_option_key(), array_merge( (array) get_option( $reminder->get_option_key() ), array( 'subject' => 'Changed in WooCommerce' ) ) );
$check( 'a change made in WooCommerce shows in the app', 'Changed in WooCommerce' === ( array_column( $get( 'emails/subkit_trial_ending' )->get_data()['fields'], 'value', 'key' )['subject'] ?? '' ) );

echo "\n6. What cannot be written\n";
$check( 'another plugin\'s email is refused', 404 === $get( 'emails/customer_processing_order' )->get_status() && 404 === $post( 'emails/customer_processing_order', array( 'subject' => 'x' ) )->get_status() );
$check( 'an id that is no email is refused', 404 === $get( 'emails/subkit_nope' )->get_status() );
$check( 'an unknown field is refused', 400 === $post( 'emails/subkit_trial_ending', array( 'template_html_code' => '<?php evil(); ?>' ) )->get_status() );
$check( 'a choice the email does not offer is refused', 400 === $post( 'emails/subkit_trial_ending', array( 'email_type' => 'pdf' ) )->get_status() && 'html' === $reminder->get_option( 'email_type' ) );
$check( 'nothing was saved by the refusals', 'Changed in WooCommerce' === ( get_option( $reminder->get_option_key() )['subject'] ?? '' ) );

$customer = wp_insert_user(
	array(
		'user_login' => 'sk_notify_' . strtolower( wp_generate_password( 8, false, false ) ),
		'user_pass'  => wp_generate_password( 32 ),
		'user_email' => 'sk-notify-' . strtolower( wp_generate_password( 8, false, false ) ) . '@example.test',
		'role'       => 'customer',
	)
);
foreach ( array( 'a visitor' => array( 0, 401 ), 'a customer' => array( $customer, 403 ) ) as $who => list( $user, $status ) ) {
	wp_set_current_user( $user );
	$check( "$who cannot read an email's settings", $status === $get( 'emails/subkit_trial_ending' )->get_status() );
	$check( "$who cannot save one", $status === $post( 'emails/subkit_trial_ending', array( 'subject' => 'Hijacked' ) )->get_status() );
	$check( "$who cannot flip a switch", $status === $post( 'notifications', array( $sw( 'subkit_trial_ending' ) => 'no' ) )->get_status() );
}
wp_set_current_user( 1 );
$check( 'nor did they change anything', 'Changed in WooCommerce' === ( get_option( $reminder->get_option_key() )['subject'] ?? '' ) && 'yes' === $enabled( 'subkit_trial_ending' ) );
wp_delete_user( $customer );

foreach ( $was as $name => $value ) {
	if ( $absent === $value ) {
		delete_option( $name );
	} else {
		update_option( $name, $value );
	}
}
foreach ( $by_id as $email ) {
	$email->init_settings();
}

subkit_test_done( $fail );
