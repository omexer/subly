<?php

namespace SubKit\Data;

use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query subscriptions without tripping over WooCommerce's status handling.
 *
 * wc_get_orders() resolves an omitted status — and 'any' — to the shop_order status
 * list, which never contains our sk-* slugs, so a bare
 * wc_get_orders( array( 'type' => 'subkit_sub' ) ) silently returns nothing. Statuses
 * must also be passed wc- prefixed even though the object reports them unprefixed.
 * Everything in SubKit queries through here so that asymmetry lives in one place.
 */
class Subscription_Query {

	/**
	 * @param array<string, mixed> $args Standard wc_get_orders() args.
	 * @return Subscription[]|int[]
	 */
	public static function get( array $args = array() ): array {
		$args['type'] = Subscription::TYPE;

		$args['status'] = self::prefix_statuses( $args['status'] ?? null );

		return wc_get_orders( $args );
	}

	/**
	 * @return int[]
	 */
	public static function ids( array $args = array() ): array {
		$args['return'] = 'ids';

		return array_map( 'intval', self::get( $args ) );
	}

	/**
	 * Subscriptions eligible to be billed.
	 *
	 * @return int[]
	 */
	public static function billable_ids( array $args = array() ): array {
		return self::ids( array_merge( $args, array( 'status' => Subscription_Status::Active->value ) ) );
	}

	/**
	 * Find one subscription by a meta value, across every status.
	 *
	 * Exists so callers never hand-roll wc_get_orders() for this: passing status 'any'
	 * silently matches only shop_order statuses and returns nothing for sk-* records.
	 */
	public static function find_by_meta( string $meta_key, string $meta_value ): ?Subscription {
		if ( '' === $meta_value ) {
			return null;
		}

		$found = self::get( array(
			'limit'      => 1,
			'status'     => null,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		) );

		$subscription = $found[0] ?? null;

		return $subscription instanceof Subscription ? $subscription : null;
	}

	/**
	 * Normalise a status argument into the wc- prefixed slugs the data store matches on.
	 *
	 * @param string|string[]|Subscription_Status|null $status
	 * @return string[]
	 */
	private static function prefix_statuses( $status ): array {
		if ( null === $status || 'any' === $status || array() === $status ) {
			return array_map(
				static fn( Subscription_Status $case ): string => 'wc-' . $case->value,
				Subscription_Status::cases()
			);
		}

		$statuses = is_array( $status ) ? $status : array( $status );

		return array_values( array_map(
			static function ( $one ): string {
				$slug = $one instanceof Subscription_Status ? $one->value : (string) $one;

				return 0 === strpos( $slug, 'wc-' ) ? $slug : 'wc-' . $slug;
			},
			$statuses
		) );
	}
}
