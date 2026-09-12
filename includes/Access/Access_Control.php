<?php

namespace SubKit\Access;

use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns subscription state into access: a WordPress role while active, and downloads that
 * stop working when billing does.
 *
 * Access is revoked on any non-active state rather than only on cancellation, so a failed
 * payment does not silently keep granting what the customer is no longer paying for.
 */
class Access_Control {

	private const META_GRANTED_ROLE = '_subkit_granted_role';
	private const META_PRIOR_ROLES  = '_subkit_prior_roles';

	public const PRODUCT_ROLE = '_subkit_grant_role';

	public function register(): void {
		add_action( 'subkit_subscription_activated', array( $this, 'grant' ), 10, 1 );
		add_action( 'subkit_subscription_status_changed', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'subkit_subscription_cancelled', array( $this, 'revoke' ), 10, 1 );

		add_filter( 'woocommerce_customer_has_capability', array( $this, 'filter_download_capability' ), 10, 3 );
		add_filter( 'woocommerce_customer_get_downloadable_products', array( $this, 'filter_downloads' ), 10, 1 );

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_role_field' ), 20 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_role_field' ) );
	}

	public function grant( Subscription $subscription ): void {
		$role = $this->role_for( $subscription );
		$user = $subscription->get_customer_id() ? get_userdata( $subscription->get_customer_id() ) : null;

		if ( '' === $role || ! $user || in_array( $role, (array) $user->roles, true ) ) {
			return;
		}

		// Remember what they had so revoking restores rather than guesses.
		$subscription->update_meta_data( self::META_PRIOR_ROLES, implode( ',', (array) $user->roles ) );
		$subscription->update_meta_data( self::META_GRANTED_ROLE, $role );
		$subscription->save();

		$user->add_role( $role );

		do_action( 'subkit_role_granted', $subscription, $role );
	}

	public function revoke( Subscription $subscription ): void {
		$granted = (string) $subscription->get_meta( self::META_GRANTED_ROLE );
		$user    = $subscription->get_customer_id() ? get_userdata( $subscription->get_customer_id() ) : null;

		if ( '' === $granted || ! $user ) {
			return;
		}

		// Another live subscription may grant the same role; do not strip it from under it.
		if ( $this->another_active_grants( $subscription, $granted ) ) {
			return;
		}

		$user->remove_role( $granted );

		if ( empty( $user->roles ) ) {
			foreach ( array_filter( explode( ',', (string) $subscription->get_meta( self::META_PRIOR_ROLES ) ) ) as $prior ) {
				$user->add_role( $prior );
			}
		}

		$subscription->delete_meta_data( self::META_GRANTED_ROLE );
		$subscription->save();

		do_action( 'subkit_role_revoked', $subscription, $granted );
	}

	public function on_status_change( Subscription $subscription, string $from, string $to ): void {
		$status = Subscription_Status::tryFrom( $to );

		if ( ! $status ) {
			return;
		}

		if ( Subscription_Status::Active === $status || Subscription_Status::Trialling === $status ) {
			$this->grant( $subscription );
			return;
		}

		// Anything else means they are not currently paying.
		$this->revoke( $subscription );
	}

	/**
	 * Hide downloads whose subscription is no longer active.
	 *
	 * @param array $downloads
	 */
	public function filter_downloads( $downloads ): array {
		if ( ! is_array( $downloads ) ) {
			return array();
		}

		return array_values( array_filter( $downloads, fn( $download ): bool => $this->product_access_allowed( (int) ( $download['product_id'] ?? 0 ) ) ) );
	}

	/**
	 * @param bool  $has
	 * @param string $capability
	 * @param array  $args
	 */
	public function filter_download_capability( $has, $capability, $args ) {
		if ( 'download_file' !== $capability || ! $has ) {
			return $has;
		}

		$product_id = (int) ( $args['download']->get_product_id() ?? 0 );

		return $product_id ? $this->product_access_allowed( $product_id ) : $has;
	}

	/**
	 * A subscription product's files are only available while a live subscription covers it.
	 */
	public function product_access_allowed( int $product_id ): bool {
		if ( ! $product_id || ! Subscription_Product::is_subscription( $product_id ) ) {
			return true;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		foreach ( Subscription_Query::get( array( 'limit' => -1, 'status' => null, 'customer_id' => $user_id ) ) as $subscription ) {
			$status = $subscription->get_status_enum();

			if ( ! $status || ! in_array( $status, array( Subscription_Status::Active, Subscription_Status::Trialling, Subscription_Status::PendingCancel ), true ) ) {
				continue;
			}

			foreach ( $subscription->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					return true;
				}
			}
		}

		return false;
	}

	public function render_role_field(): void {
		global $product_object;

		$roles = array( '' => __( 'No role change', 'subkit-subscriptions' ) );

		foreach ( wp_roles()->get_names() as $slug => $label ) {
			$roles[ $slug ] = translate_user_role( $label );
		}

		echo '<div class="subkit-schedule-fields-role">';
		woocommerce_wp_select( array(
			'id'          => self::PRODUCT_ROLE,
			'label'       => __( 'Grant role while active', 'subkit-subscriptions' ),
			'options'     => $roles,
			'value'       => $product_object ? (string) $product_object->get_meta( self::PRODUCT_ROLE ) : '',
			'desc_tip'    => true,
			'description' => __( 'The customer gets this role while the subscription is active, and loses it when billing stops.', 'subkit-subscriptions' ),
		) );
		echo '</div>';
	}

	public function save_role_field( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies before this hook.
		$role = isset( $_POST[ self::PRODUCT_ROLE ] ) ? sanitize_key( wp_unslash( $_POST[ self::PRODUCT_ROLE ] ) ) : '';

		$product->update_meta_data( self::PRODUCT_ROLE, array_key_exists( $role, wp_roles()->get_names() ) ? $role : '' );
	}

	private function role_for( Subscription $subscription ): string {
		foreach ( $subscription->get_items() as $item ) {
			$product = $item->get_product();

			if ( $product ) {
				$role = (string) $product->get_meta( self::PRODUCT_ROLE );

				if ( '' !== $role ) {
					return $role;
				}
			}
		}

		return '';
	}

	private function another_active_grants( Subscription $subscription, string $role ): bool {
		foreach ( Subscription_Query::get( array( 'limit' => -1, 'status' => null, 'customer_id' => $subscription->get_customer_id() ) ) as $other ) {
			if ( $other->get_id() === $subscription->get_id() ) {
				continue;
			}

			$status = $other->get_status_enum();

			if ( $status && ! $status->is_terminal() && $role === (string) $other->get_meta( self::META_GRANTED_ROLE ) ) {
				return true;
			}
		}

		return false;
	}
}
