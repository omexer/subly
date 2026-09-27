<?php

namespace EasySubscription\Emails;

use EasySubscription\Billing\Renewal_Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Notifications section: one row per email, whose switch is that email's own WooCommerce setting.
 */
final class Notification_Settings {

	public const SECTION = 'notifications';

	public const CARD_MAIN  = 'easysubscription_notifications_title';
	public const CARD_OTHER = 'easysubscription_other_emails_title';

	/** Stored in days before 0.27; read once to set the hours, then removed. */
	private const LEGACY_DAYS = 'easysubscription_renewal_reminder_days';

	public function register(): void {
		add_action( 'init', array( $this, 'migrate' ), 6 );

		foreach ( array( Renewal_Scheduler::OPTION_REMINDER_HOURS, Renewal_Scheduler::OPTION_EXPIRY_HOURS ) as $option ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_' . $option, array( self::class, 'clamp_hours' ) );
		}
	}

	/**
	 * @param mixed $value
	 */
	public static function clamp_hours( $value ): int {
		return is_numeric( $value ) ? max( 1, (int) $value ) : Renewal_Scheduler::DEFAULT_HOURS;
	}

	/**
	 * Days become hours once. A store that had set 0 days had turned the reminder off, which is now its email's switch.
	 */
	public function migrate(): void {
		if ( false !== get_option( Renewal_Scheduler::OPTION_REMINDER_HOURS, false ) ) {
			return;
		}

		$days = get_option( self::LEGACY_DAYS, false );

		if ( false !== $days && (int) $days < 1 ) {
			$email = self::email( 'easysubscription_renewal_reminder' );
			$key   = $email ? $email->get_option_key() : 'woocommerce_easysubscription_renewal_reminder_settings';
			$saved = (array) get_option( $key, array() );

			$saved['enabled'] = 'no';
			update_option( $key, $saved );
		}

		update_option( Renewal_Scheduler::OPTION_REMINDER_HOURS, false !== $days && (int) $days >= 1 ? (int) $days * 24 : Renewal_Scheduler::DEFAULT_HOURS, true );
		delete_option( self::LEGACY_DAYS );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(): array {
		$renewal = self::email_row( 'easysubscription_renewal_reminder', __( 'Renewal reminder', 'easysubscription' ), __( 'Send customers a reminder email before their next renewal payment.', 'easysubscription' ) );
		$expiry  = self::email_row( 'easysubscription_expiring_soon', __( 'Expiring soon reminder', 'easysubscription' ), __( 'Send customers a reminder email before their subscription expires.', 'easysubscription' ) );

		$main = array(
			$renewal,
			$renewal ? self::hours_row( Renewal_Scheduler::OPTION_REMINDER_HOURS, __( 'Send renewal reminder before (Hours)', 'easysubscription' ), __( 'Choose how many hours before the renewal date the reminder email is sent.', 'easysubscription' ), $renewal['id'] ) : null,
			$expiry,
			$expiry ? self::hours_row( Renewal_Scheduler::OPTION_EXPIRY_HOURS, __( 'Send expiry reminder before (Hours)', 'easysubscription' ), __( 'Choose how many hours before the expiry date the reminder email is sent.', 'easysubscription' ), $expiry['id'] ) : null,
			self::email_row( 'easysubscription_payment_failed', __( 'Payment failure emails', 'easysubscription' ), __( 'Notify customers when a renewal payment fails.', 'easysubscription' ) ),
			self::email_row( 'easysubscription_trial_ending', __( 'Trial ending reminder', 'easysubscription' ), __( 'Send customers a reminder before their free trial ends.', 'easysubscription' ) ),
			self::email_row( 'easysubscription_renewal_receipt', __( 'Renewal success email', 'easysubscription' ), __( 'Send customers a confirmation email after a subscription renewal payment is completed successfully.', 'easysubscription' ) ),
			self::email_row( 'easysubscription_subscription_cancelled', __( 'Subscription cancelled email', 'easysubscription' ), __( 'Send customers a confirmation email when their subscription is cancelled.', 'easysubscription' ) ),
			self::email_row( 'easysubscription_subscription_reactivated', __( 'Subscription reactivated email', 'easysubscription' ), __( 'Send customers a confirmation email when their paused or cancelled subscription is reactivated.', 'easysubscription' ) ),
		);

		$other = array(
			self::email_row( 'easysubscription_subscription_started' ),
			self::email_row( 'easysubscription_confirm_payment' ),
			self::email_row( 'easysubscription_merchant_new_subscription' ),
			self::email_row( 'easysubscription_merchant_subscription_cancelled' ),
			self::email_row( 'easysubscription_merchant_subscription_ended' ),
		);

		return array_merge(
			array(
				array(
					'title' => __( 'Notifications', 'easysubscription' ),
					'type'  => 'title',
					'id'    => self::CARD_MAIN,
				),
			),
			array_values( array_filter( $main ) ),
			array(
				array(
					'type' => 'sectionend',
					'id'   => self::CARD_MAIN,
				),
				array(
					'title' => __( 'Other emails', 'easysubscription' ),
					'type'  => 'title',
					'id'    => self::CARD_OTHER,
				),
			),
			array_values( array_filter( $other ) ),
			array(
				array(
					'type' => 'sectionend',
					'id'   => self::CARD_OTHER,
				),
			)
		);
	}

	/**
	 * A row whose switch is the email's own "enabled" setting, or null when the email is not registered.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function email_row( string $email_id, string $title = '', string $desc = '' ): ?array {
		$email = self::email( $email_id );

		if ( ! $email ) {
			return null;
		}

		return array(
			'title'        => '' !== $title ? $title : $email->get_title(),
			'desc'         => '' !== $desc ? $desc : $email->get_description(),
			'id'           => $email->get_option_key() . '[enabled]',
			'type'         => 'checkbox',
			'default'      => (string) ( $email->get_form_fields()['enabled']['default'] ?? 'yes' ),
			'easysubscription_email' => $email_id,
		);
	}

	/**
	 * Splice rows in after the row with $after_id, or at the end of the card $card when it is missing.
	 *
	 * @param array<int, array<string, mixed>>      $settings
	 * @param array<int, array<string, mixed>|null> $rows
	 * @return array<int, array<string, mixed>>
	 */
	public static function insert( array $settings, string $after_id, array $rows, string $card = self::CARD_MAIN ): array {
		$rows = array_values( array_filter( $rows ) );
		$at   = null;

		foreach ( $settings as $index => $field ) {
			$id = (string) ( $field['id'] ?? '' );

			if ( $id === $after_id ) {
				$at = (int) $index + 1;
				break;
			}

			if ( null === $at && 'sectionend' === ( $field['type'] ?? '' ) && $card === $id ) {
				$at = (int) $index;
			}
		}

		if ( null === $at || ! $rows ) {
			return $settings;
		}

		array_splice( $settings, $at, 0, $rows );

		return $settings;
	}

	/**
	 * The row id an email's switch has on this page.
	 */
	public static function switch_id( string $email_id ): string {
		$email = self::email( $email_id );

		return ( $email ? $email->get_option_key() : 'woocommerce_' . $email_id . '_settings' ) . '[enabled]';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function hours_row( string $option, string $title, string $tip, string $parent ): array {
		return array(
			'title'             => $title,
			'desc_tip'          => $tip,
			'id'                => $option,
			'type'              => 'number',
			'default'           => Renewal_Scheduler::DEFAULT_HOURS,
			'custom_attributes' => array(
				'min'  => '1',
				'step' => '1',
			),
			'easysubscription_show_if'    => $parent,
		);
	}

	public static function email( string $email_id ): ?\WC_Email {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return null;
		}

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof \WC_Email && $email->id === $email_id ) {
				return $email;
			}
		}

		return null;
	}

	/**
	 * WooCommerce's own screen for the email.
	 */
	public static function woo_url( string $email_id ): string {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return '';
		}

		foreach ( WC()->mailer()->get_emails() as $key => $email ) {
			if ( $email instanceof \WC_Email && $email->id === $email_id ) {
				return admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( (string) $key ) );
			}
		}

		return '';
	}
}
