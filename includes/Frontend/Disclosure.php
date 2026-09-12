<?php

namespace SubKit\Frontend;

use SubKit\Domain\Billing_Schedule;
use SubKit\Domain\Money;
use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The recurring-payment disclosure.
 *
 * A price line plus independent fact lines, never one composed sentence. Prose collapses
 * the moment two modifiers combine — "charged $29.00 every 1 month starting 24 August
 * after a 7 day free trial plus a $20.00 sign-up fee" is unreadable and untranslatable.
 * Each line here stands alone, so any combination composes and RTL survives.
 *
 * UX Spec 6.
 */
class Disclosure {

	/**
	 * Structured lines for a product. Callers render; this decides what is true.
	 *
	 * @return array<int, array{key: string, text: string}>
	 */
	public function lines( \WC_Product $product, ?\DateTimeImmutable $now = null ): array {
		$now      = $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$schedule = Subscription_Product::schedule( $product );
		$price    = Subscription_Product::recurring_price( $product );
		$fee      = Subscription_Product::signup_fee( $product );

		$lines = array();

		if ( $schedule->has_trial() ) {
			$lines[] = array(
				'key'  => 'trial',
				/* translators: %d: number of free trial days */
				'text' => sprintf( _n( '%d day free', '%d days free', $schedule->trial_days(), 'subkit-subscriptions' ), $schedule->trial_days() ),
			);
		}

		if ( ! $fee->is_zero() ) {
			$lines[] = array(
				'key'  => 'signup_fee',
				/* translators: %s: sign-up fee amount */
				'text' => sprintf( __( '%s sign-up fee today', 'subkit-subscriptions' ), $this->amount( $fee ) ),
			);
		}

		// Always state what is charged first and when. This is the single most common
		// point of confusion: am I paying now, or after the trial?
		$first_date = $schedule->trial_end_from( $now );
		$first_due  = $schedule->has_trial() ? $price : $price->add( $fee );

		$lines[] = array(
			'key'  => 'first_payment',
			'text' => $first_date
				/* translators: 1: amount, 2: date */
				? sprintf( __( 'First payment %1$s on %2$s', 'subkit-subscriptions' ), $this->amount( $first_due ), $this->date( $first_date ) )
				/* translators: %s: amount */
				: sprintf( __( 'First payment %s today', 'subkit-subscriptions' ), $this->amount( $first_due ) ),
		);

		$lines[] = array(
			'key'  => 'recurring',
			/* translators: 1: amount, 2: billing interval such as "every month" */
			'text' => sprintf( __( 'Then %1$s %2$s', 'subkit-subscriptions' ), $this->amount( $price ), $schedule->describe() ),
		);

		$lines[] = array(
			'key'  => 'cancel',
			'text' => __( 'Cancel anytime', 'subkit-subscriptions' ),
		);

		/**
		 * Filter the disclosure lines.
		 *
		 * @param array        $lines
		 * @param \WC_Product  $product
		 */
		return apply_filters( 'subkit_disclosure_lines', $lines, $product );
	}

	/**
	 * The headline price, e.g. "$29.00 / month".
	 */
	public function price_line( \WC_Product $product ): string {
		$price    = Subscription_Product::recurring_price( $product );
		$schedule = Subscription_Product::schedule( $product );

		/* translators: 1: price, 2: billing interval such as "every month" */
		$line = sprintf( __( '%1$s %2$s', 'subkit-subscriptions' ), $this->amount( $price ), $schedule->describe() );

		/**
		 * Filter the headline price line, e.g. to state an instalment plan instead.
		 *
		 * @param string      $line
		 * @param \WC_Product $product
		 */
		return (string) apply_filters( 'subkit_disclosure_price_line', $line, $product );
	}

	public function render( \WC_Product $product ): string {
		if ( ! Subscription_Product::is_subscription( $product ) ) {
			return '';
		}

		$html  = '<div class="subkit-disclosure" role="group" aria-label="' . esc_attr__( 'Subscription terms', 'subkit-subscriptions' ) . '">';
		$html .= '<p class="subkit-disclosure__price">' . wp_kses_post( $this->price_line( $product ) ) . '</p>';
		$html .= '<ul class="subkit-disclosure__facts">';

		foreach ( $this->lines( $product ) as $line ) {
			$html .= '<li class="subkit-disclosure__fact subkit-disclosure__fact--' . esc_attr( $line['key'] ) . '">' . wp_kses_post( $line['text'] ) . '</li>';
		}

		return $html . '</ul></div>';
	}

	/**
	 * The one place prose is used: the line beside the Place Order button, which has to
	 * be readable in a single glance. This is the sentence that legally matters.
	 */
	public function sentence( \WC_Product $product ): string {
		$schedule = Subscription_Product::schedule( $product );
		$price    = Subscription_Product::recurring_price( $product );

		if ( $schedule->has_trial() ) {
			return sprintf(
				/* translators: 1: trial length in days, 2: amount, 3: billing interval */
				__( "You're starting a subscription. After a %1\$d-day free trial you'll be charged %2\$s %3\$s until you cancel.", 'subkit-subscriptions' ),
				$schedule->trial_days(),
				$this->amount( $price ),
				$schedule->describe()
			);
		}

		return sprintf(
			/* translators: 1: amount, 2: billing interval */
			__( "You're starting a subscription. You'll be charged %1\$s %2\$s until you cancel.", 'subkit-subscriptions' ),
			$this->amount( $price ),
			$schedule->describe()
		);
	}

	/**
	 * Always through wc_price(), which is what makes zero-decimal (JPY) and
	 * three-decimal (KWD) currencies correct for free. bdi keeps RTL from reversing it.
	 */
	private function amount( Money $money ): string {
		return '<bdi>' . wp_strip_all_tags( $money->format() ) . '</bdi>';
	}

	private function date( \DateTimeImmutable $date ): string {
		return date_i18n( (string) get_option( 'date_format' ), $date->getTimestamp() );
	}
}
