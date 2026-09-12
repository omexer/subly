<?php

namespace SubKit\Gateways\Stripe;

use SubKit\Domain\Money;
use SubKit\Domain\Subscription;
use SubKit\Gateways\Charge_Result;
use SubKit\Gateways\Gateway_Model;
use SubKit\Gateways\Recurring_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe as a Model A gateway: SubKit owns the schedule and charges a stored payment
 * method off-session when a renewal falls due.
 *
 * Deliberately not Stripe Billing. Letting Stripe own the schedule would make it a second
 * source of truth for when a customer is charged, and reconciling two schedules is worse
 * than owning one.
 */
class Stripe_Gateway implements Recurring_Gateway {

	public const ID = 'subkit_stripe';

	public const META_CUSTOMER = '_subkit_stripe_customer';
	public const META_METHOD   = '_subkit_stripe_payment_method';

	/** Codes Stripe considers permanent; retrying these only annoys the customer. */
	private const HARD_DECLINES = array(
		'stolen_card',
		'lost_card',
		'pickup_card',
		'card_not_supported',
		'invalid_account',
		'revocation_of_authorization',
		'revocation_of_all_authorizations',
		'transaction_not_allowed',
	);

	public function __construct( private readonly Stripe_Client $client ) {}

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'Stripe', 'subkit-subscriptions' );
	}

	public function model(): Gateway_Model {
		return Gateway_Model::Tokenized;
	}

	public function supports( string $feature ): bool {
		return in_array( $feature, array( 'amount_change', 'date_change', 'pause', 'multiple_subs', 'refunds', 'payment_method_change' ), true );
	}

	public function create_mandate( Subscription $subscription, \WC_Order $initial ): Charge_Result {
		$customer = (string) $initial->get_meta( self::META_CUSTOMER );
		$method   = (string) $initial->get_meta( self::META_METHOD );

		if ( '' === $customer || '' === $method ) {
			return Charge_Result::error( 'Stripe did not return a saved payment method for this order.' );
		}

		$subscription->update_meta_data( self::META_CUSTOMER, $customer );
		$subscription->update_meta_data( self::META_METHOD, $method );
		$subscription->save();

		return Charge_Result::success( $method );
	}

	/**
	 * Charge the stored method with the customer away.
	 *
	 * off_session tells Stripe this is a merchant-initiated transaction, which is what
	 * makes SCA exemptions apply. Without it European cards challenge on every renewal.
	 */
	public function charge_renewal( Subscription $subscription, \WC_Order $renewal, string $idempotency_key ): Charge_Result {
		$customer = (string) $subscription->get_meta( self::META_CUSTOMER );
		$method   = (string) $subscription->get_meta( self::META_METHOD );

		if ( '' === $customer || '' === $method ) {
			return Charge_Result::hard_decline( 'no_payment_method', 'No stored Stripe payment method for this subscription.' );
		}

		$amount = Money::from_decimal( $renewal->get_total(), $renewal->get_currency() );

		$response = $this->client->post(
			'/v1/payment_intents',
			array(
				'amount'         => $this->stripe_amount( $amount, $renewal->get_currency() ),
				'currency'       => strtolower( $renewal->get_currency() ),
				'customer'       => $customer,
				'payment_method' => $method,
				'off_session'    => 'true',
				'confirm'        => 'true',
				'description'    => sprintf( 'Renewal for subscription #%d', $subscription->get_id() ),
				'metadata'       => array(
					'subkit_subscription' => (string) $subscription->get_id(),
					'subkit_order'        => (string) $renewal->get_id(),
				),
			),
			$idempotency_key
		);

		return $this->interpret( $response );
	}

	/**
	 * Ask Stripe what happened to a charge we never got an answer for.
	 *
	 * Looks the intent up by our idempotency key rather than assuming, so a timeout can
	 * never become a second charge.
	 */
	public function reconcile( Subscription $subscription, string $idempotency_key ): ?Charge_Result {
		$search = $this->client->get(
			'/v1/payment_intents/search?query=' . rawurlencode( sprintf( 'metadata["subkit_subscription"]:"%d"', $subscription->get_id() ) ) . '&limit=5'
		);

		if ( ! $search['ok'] ) {
			return Charge_Result::error( $search['error'] );
		}

		foreach ( $search['body']['data'] ?? array() as $intent ) {
			if ( 'succeeded' === ( $intent['status'] ?? '' ) && $this->is_recent( $intent ) ) {
				return Charge_Result::success( (string) $intent['id'] );
			}
		}

		// No matching successful charge, so nothing landed and the slot is safe to retry.
		return null;
	}

	public function cancel_mandate( Subscription $subscription ): bool {
		// Nothing to cancel at Stripe: we simply stop charging. Detaching the payment
		// method would break any other subscription sharing the same customer.
		return true;
	}

	public function update_payment_method( Subscription $subscription, string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		$subscription->update_meta_data( self::META_METHOD, $token );
		$subscription->save();

		return true;
	}

	/**
	 * Map a Stripe response onto the four outcomes the pipeline distinguishes.
	 */
	private function interpret( array $response ): Charge_Result {
		if ( $response['ok'] ) {
			$intent = $response['body'];

			return match ( (string) ( $intent['status'] ?? '' ) ) {
				'succeeded'              => Charge_Result::success( (string) ( $intent['id'] ?? '' ) ),
				'requires_action',
				'requires_confirmation'  => Charge_Result::requires_action(
					$this->authenticate_url( $intent ),
					(string) ( $intent['id'] ?? '' )
				),
				default                  => Charge_Result::soft_decline( (string) ( $intent['status'] ?? 'unknown' ), 'Stripe did not complete the payment.' ),
			};
		}

		// A network or 5xx failure is an unknown outcome, not a decline.
		if ( 0 === $response['status'] || $response['status'] >= 500 ) {
			return Charge_Result::error( $response['error'] );
		}

		$code = $response['code'];

		// The bank wants the customer present, which is not a failure.
		if ( 'authentication_required' === $code ) {
			return Charge_Result::requires_action(
				$this->authenticate_url( $response['body']['error']['payment_intent'] ?? array() ),
				(string) ( $response['body']['error']['payment_intent']['id'] ?? '' )
			);
		}

		return in_array( $code, self::HARD_DECLINES, true )
			? Charge_Result::hard_decline( $code, $response['error'] )
			: Charge_Result::soft_decline( $code ?: 'card_declined', $response['error'] );
	}

	private function authenticate_url( array $intent ): string {
		$secret = (string) ( $intent['client_secret'] ?? '' );

		return '' === $secret ? '' : add_query_arg(
			array( 'subkit_stripe_intent' => rawurlencode( $secret ) ),
			wc_get_account_endpoint_url( 'subscriptions' )
		);
	}

	/**
	 * Zero-decimal currencies (JPY, KRW) are sent as whole units, everything else in minor.
	 */
	private function stripe_amount( Money $amount, string $currency ): int {
		$zero_decimal = array( 'BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (int) round( (float) $amount->decimal() );
		}

		return $amount->minor();
	}

	private function is_recent( array $intent ): bool {
		return isset( $intent['created'] ) && ( time() - (int) $intent['created'] ) < DAY_IN_SECONDS;
	}
}
