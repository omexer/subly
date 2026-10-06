<?php

namespace Subly\Frontend\MyAccount;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Styles for the account screens. Cards below 768px, table-ish above.
 *
 * Most customers manage a subscription from a phone, arriving from an email, so the
 * card layout is the default and the wide layout is the adaptation.
 */
class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		wp_register_style( 'subly-account', false, array(), SUBLY_VERSION );
		wp_enqueue_style( 'subly-account' );
		wp_add_inline_style( 'subly-account', $this->css() );
	}

	private function css(): string {
		return '
		.subly{list-style:none;margin:0;padding:0;display:grid;gap:1rem}
		.subly-card{border:1px solid rgba(0,0,0,.12);border-radius:8px;padding:1rem}
		.subly-card--warning{border-left:4px solid #b8860b}
		.subly-card--positive{border-left:4px solid #2e7d32}
		.subly-card--muted{opacity:.75}
		.subly-card__head{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between}
		.subly-card__title{margin:0;font-size:1.05rem}
		.subly-card__price{margin:.35rem 0;font-weight:600}
		.subly-card__detail{margin:.25rem 0;font-size:.9em;opacity:.85}
		.subly-badge{display:inline-block;padding:.2em .6em;border-radius:999px;font-size:.8em;border:1px solid currentColor}
		.subly-badge--positive{color:#2e7d32}
		.subly-badge--warning{color:#8a6100}
		.subly-badge--muted{color:#666}
		.subly-card__actions{margin-top:.75rem}
		/* 44px minimum touch targets: cancel must be reachable on a phone. */
		.subly-card__actions .button,.subly-cancel__actions .button{min-height:44px;line-height:44px;padding:0 1rem}
		.subly-items{margin:.5rem 0 1rem;padding-left:1.1rem}
		/* Equal visual weight: cancel is never a grey link next to a primary button. */
		.subly-cancel__actions{display:flex;flex-wrap:wrap;gap:.6rem}
		.subly-cancel__actions .subly-btn--primary{background:#7f54b3;border-color:#7f54b3;color:#fff;font-weight:600}
			.subly-btn--primary:hover{background:#6b4699;border-color:#6b4699;color:#fff}
			.subly-card__link{align-self:center;font-size:.9em}
			.subly-detail__action{margin:0 0 1.25rem}
			.subly-btn{flex:1 1 12rem;text-align:center}
		.subly-empty{padding:1rem 0}
		@media(min-width:768px){.subly{gap:.75rem}}
		';
	}
}
