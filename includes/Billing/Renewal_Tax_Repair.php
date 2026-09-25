<?php

namespace SubKit\Billing;

use SubKit\Admin\Menu;
use SubKit\Data\Activity_Repository;
use SubKit\Data\Subscription_Query;
use SubKit\Domain\Money;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;
use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscriptions created before lines were stored without tax renew with tax added twice; the store repairs each one.
 */
class Renewal_Tax_Repair {

	public const OPTION_SINCE = 'subkit_tax_exclusive_lines_since';

	public const ACTION = 'subkit_repair_renewal_tax';

	public const CACHE = 'subkit_tax_added_twice';

	public function __construct( private readonly Activity_Repository $activity ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'mark_cut_over' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Every subscription created from here on stores its line without tax; only older ones can be affected.
	 */
	public function mark_cut_over(): void {
		// Autoloaded, so the check costs nothing on later requests.
		add_option( self::OPTION_SINCE, gmdate( 'Y-m-d H:i:s' ), '', true );
	}

	/**
	 * @return int[]
	 */
	public function affected_ids(): array {
		$cached = get_transient( self::CACHE );

		if ( is_array( $cached ) ) {
			return array_map( 'intval', $cached );
		}

		$ids = array();

		if ( $this->store_can_be_affected() ) {
			$candidates = Subscription_Query::ids(
				array(
					'status'       => array( Subscription_Status::Active->value, Subscription_Status::Trialling->value, Subscription_Status::OnHold->value ),
					'date_created' => '<' . $this->since(),
					'limit'        => -1,
				)
			);

			foreach ( $candidates as $id ) {
				$subscription = wc_get_order( $id );

				if ( $subscription instanceof Subscription && $this->is_affected( $subscription ) ) {
					$ids[] = $id;
				}
			}
		}

		set_transient( self::CACHE, $ids, HOUR_IN_SECONDS );

		return $ids;
	}

	public function is_affected( Subscription $subscription ): bool {
		$status  = $subscription->get_status_enum();
		$created = $subscription->get_date_created();
		$lines   = $subscription->get_items();

		if ( ! $this->store_can_be_affected()
			|| ! in_array( $status, array( Subscription_Status::Active, Subscription_Status::Trialling, Subscription_Status::OnHold ), true )
			|| ! $created || $created->getTimestamp() >= $this->since()
			|| $subscription->get_items( 'tax' ) || 0.0 !== (float) $subscription->get_total_tax()
			|| '' !== (string) $subscription->get_meta( '_subkit_renewal_price_applied' )
			|| ! $lines ) {
			return false;
		}

		foreach ( $lines as $line ) {
			$product = $line instanceof \WC_Order_Item_Product ? $line->get_product() : null;

			if ( ! $product instanceof \WC_Product || ! $product->is_taxable() ) {
				return false;
			}

			$quantity = max( 1, (int) $line->get_quantity() );
			$gross    = Subscription_Product::recurring_price( $product )->multiply( $quantity );

			// Only a line still at the price as entered: anything else was set on purpose since.
			if ( $gross->minor() !== Money::from_decimal( $line->get_total(), $subscription->get_currency() )->minor() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Store the line without tax and let the subscription add tax back, as a new one would.
	 */
	public function repair( Subscription $subscription ): bool {
		if ( ! $this->is_affected( $subscription ) ) {
			return false;
		}

		$before = $subscription->get_total();

		foreach ( $subscription->get_items() as $line ) {
			$net = wc_format_decimal(
				wc_get_price_excluding_tax(
					$line->get_product(),
					array(
						'qty'   => 1,
						'price' => $line->get_total(),
						'order' => $subscription,
					)
				)
			);

			$line->set_subtotal( $net );
			$line->set_total( $net );
		}

		$subscription->calculate_totals( true );
		$subscription->save();

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_NOTE,
			sprintf( 'Renewal tax repaired: the line is now stored without tax, so renewals stop adding tax twice. Recurring total %1$s before, %2$s now.', $before, $subscription->get_total() ),
			array(),
			'admin'
		);

		delete_transient( self::CACHE );

		return true;
	}

	public function handle(): void {
		$id = absint( $_GET['subscription'] ?? 0 );

		if ( ! current_user_can( Menu::CAPABILITY ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), self::ACTION . '_' . $id ) ) {
			wp_die( esc_html__( 'That request could not be verified.', 'subkit-subscriptions' ) );
		}

		$subscription = wc_get_order( $id );
		$repaired     = $subscription instanceof Subscription && $this->repair( $subscription );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => 'wc-settings',
					'tab'                 => 'subkit',
					'subkit_tax_repaired' => $repaired ? $id : 0,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function repair_url( int $subscription_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'       => self::ACTION,
					'subscription' => $subscription_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $subscription_id
		);
	}

	private function store_can_be_affected(): bool {
		return wc_tax_enabled() && wc_prices_include_tax();
	}

	private function since(): int {
		$since = (string) get_option( self::OPTION_SINCE, '' );
		$time  = '' === $since ? false : strtotime( $since . ' UTC' );

		return false === $time ? 0 : $time;
	}
}
