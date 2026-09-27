<?php

namespace EasySubscription\Rest;

use EasySubscription\Admin\Menu;
use EasySubscription\Admin\Settings_Page;
use EasySubscription\Emails\Notification_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One EasySubscription email's own WooCommerce settings, read and saved through the email itself.
 */
final class Email_Settings_Controller {

	/**
	 * Field types the screen draws; anything else an email defines is kept as it is.
	 */
	private const EDITABLE = array( 'checkbox', 'text', 'email', 'textarea', 'select', 'number' );

	public function __construct( private readonly Settings_Page $page ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			Settings_Controller::NAMESPACE,
			'/settings/emails/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'may_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'may_manage' ),
					'args'                => array(
						'values' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);
	}

	public function may_manage(): bool {
		return current_user_can( Menu::CAPABILITY );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$email = $this->find( (string) $request['id'] );

		return $email instanceof \WC_Email ? rest_ensure_response( $this->describe( $email ) ) : $email;
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $request ) {
		$email = $this->find( (string) $request['id'] );

		if ( ! $email instanceof \WC_Email ) {
			return $email;
		}

		$values   = (array) $request['values'];
		$editable = $this->editable( $email );
		$unknown  = array_keys( array_diff_key( $values, $editable ) );

		if ( $unknown ) {
			return new \WP_Error(
				'easysubscription_unknown_email_setting',
				/* translators: %s: comma-separated setting names */
				sprintf( __( 'Not saved: %s is not a setting of this email.', 'easysubscription' ), implode( ', ', array_map( 'strval', $unknown ) ) ),
				array( 'status' => 400 )
			);
		}

		foreach ( $values as $key => $value ) {
			$options = (array) ( $editable[ $key ]['options'] ?? array() );

			if ( ! is_scalar( $value ) || ( 'select' === $editable[ $key ]['type'] && ! array_key_exists( (string) $value, $options ) ) ) {
				return new \WP_Error(
					'easysubscription_invalid_email_setting',
					/* translators: %s: setting name */
					sprintf( __( 'Not saved: %s has a value it cannot take.', 'easysubscription' ), (string) $key ),
					array( 'status' => 400 )
				);
			}
		}

		$email->set_post_data( wp_slash( $this->as_posted( $email, $values ) ) );
		$email->process_admin_options();

		return rest_ensure_response( $this->describe( $email ) + array( 'errors' => array_values( array_map( 'strval', $email->get_errors() ) ) ) );
	}

	/**
	 * What WooCommerce's own form would post: every field, the unchanged ones as they are, since a field left out is blanked.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function as_posted( \WC_Email $email, array $values ): array {
		$posted = array();

		foreach ( $email->get_form_fields() as $key => $field ) {
			$type  = $email->get_field_type( $field );
			$value = array_key_exists( $key, $values ) ? (string) $values[ $key ] : $email->get_option( $key );

			if ( 'title' === $type ) {
				continue;
			}

			if ( 'checkbox' === $type ) {
				if ( 'yes' === $value ) {
					$posted[ $email->get_field_key( $key ) ] = '1';
				}

				continue;
			}

			$posted[ $email->get_field_key( $key ) ] = is_array( $value ) ? array_map( 'strval', $value ) : (string) $value;
		}

		return $posted;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describe( \WC_Email $email ): array {
		$fields = array();

		foreach ( $this->editable( $email ) as $key => $field ) {
			$options = array();

			foreach ( (array) ( $field['options'] ?? array() ) as $value => $label ) {
				$options[] = array(
					'value' => (string) $value,
					'label' => (string) $label,
				);
			}

			$fields[] = array(
				'key'         => (string) $key,
				'type'        => $field['type'],
				'title'       => wp_strip_all_tags( (string) ( $field['title'] ?? '' ) ),
				'label'       => wp_strip_all_tags( (string) ( $field['label'] ?? '' ) ),
				// WooCommerce escapes the tags inside its own placeholder list.
				'help'        => wp_kses_post( html_entity_decode( (string) ( $field['description'] ?? '' ), ENT_QUOTES, 'UTF-8' ) ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'options'     => $options,
				'value'       => (string) $email->get_option( $key ),
			);
		}

		return array(
			'id'          => $email->id,
			'title'       => $email->get_title(),
			'description' => $email->get_description(),
			'recipient'   => $email->is_customer_email() ? 'customer' : 'store',
			'fields'      => $fields,
			'woo_url'     => Notification_Settings::woo_url( $email->id ),
			'preview_url' => $this->preview_url( $email ),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function editable( \WC_Email $email ): array {
		$editable = array();

		foreach ( $email->get_form_fields() as $key => $field ) {
			$type = $email->get_field_type( $field );

			if ( in_array( $type, self::EDITABLE, true ) ) {
				$editable[ (string) $key ] = array( 'type' => $type ) + (array) $field;
			}
		}

		return $editable;
	}

	/**
	 * WooCommerce's own preview of the saved email, where it has one (9.6 and later).
	 */
	private function preview_url( \WC_Email $email ): string {
		if ( ! class_exists( '\Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview' ) ) {
			return '';
		}

		return add_query_arg(
			array(
				'preview_woocommerce_mail' => 'true',
				'type'                     => rawurlencode( get_class( $email ) ),
				'_wpnonce'                 => wp_create_nonce( 'preview-mail' ),
			),
			admin_url()
		);
	}

	/**
	 * Only the emails listed on the Notifications page, so no other plugin's email can be written here.
	 *
	 * @return \WC_Email|\WP_Error
	 */
	private function find( string $email_id ) {
		$listed = array_filter( array_column( $this->page->settings_for( Notification_Settings::SECTION ), 'easysubscription_email' ) );
		$email  = in_array( $email_id, $listed, true ) ? Notification_Settings::email( $email_id ) : null;

		if ( $email ) {
			// The mailer's copy was loaded earlier in the request, and may predate a save made since.
			$email->init_settings();

			return $email;
		}

		return new \WP_Error(
			'easysubscription_unknown_email',
			__( 'There is no such EasySubscription email.', 'easysubscription' ),
			array( 'status' => 404 )
		);
	}
}
