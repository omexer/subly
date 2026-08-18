<?php

namespace SubKit\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A short-lived, non-reentrant lock around one subscription's renewal.
 *
 * Built on add_option() because wp_options.option_name carries a unique index, which
 * makes the insert atomic. Transients are not — two workers can both "win" one.
 *
 * The lock is an optimisation, not the safety guarantee. The charge-slot unique key is
 * what actually prevents a double charge; this just stops wasted work.
 */
class Lock {

	private const TTL = 300;

	public function acquire( int $subscription_id, int $ttl = self::TTL ): bool {
		$name = $this->name( $subscription_id );

		if ( add_option( $name, time() + $ttl, '', false ) ) {
			return true;
		}

		// Someone holds it. Steal it only if it has expired.
		$expires = (int) get_option( $name );
		if ( $expires > 0 && $expires < time() ) {
			delete_option( $name );

			return add_option( $name, time() + $ttl, '', false );
		}

		return false;
	}

	public function release( int $subscription_id ): void {
		delete_option( $this->name( $subscription_id ) );
	}

	public function is_locked( int $subscription_id ): bool {
		$expires = (int) get_option( $this->name( $subscription_id ) );

		return $expires > time();
	}

	private function name( int $subscription_id ): string {
		return 'subkit_lock_' . $subscription_id;
	}
}
