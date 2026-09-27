<?php

namespace EasySubscription\Frontend\MyAccount;

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

		wp_register_style( 'easysubscription-account', false, array(), EASYSUBSCRIPTION_VERSION );
		wp_enqueue_style( 'easysubscription-account' );
		wp_add_inline_style( 'easysubscription-account', $this->css() );
	}

	private function css(): string {
		return '
		.easysubscription{list-style:none;margin:0;padding:0;display:grid;gap:1rem}
		.easysubscription-card{border:1px solid rgba(0,0,0,.12);border-radius:8px;padding:1rem}
		.easysubscription-card--warning{border-left:4px solid #b8860b}
		.easysubscription-card--positive{border-left:4px solid #2e7d32}
		.easysubscription-card--muted{opacity:.75}
		.easysubscription-card__head{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between}
		.easysubscription-card__title{margin:0;font-size:1.05rem}
		.easysubscription-card__price{margin:.35rem 0;font-weight:600}
		.easysubscription-card__detail{margin:.25rem 0;font-size:.9em;opacity:.85}
		.easysubscription-badge{display:inline-block;padding:.2em .6em;border-radius:999px;font-size:.8em;border:1px solid currentColor}
		.easysubscription-badge--positive{color:#2e7d32}
		.easysubscription-badge--warning{color:#8a6100}
		.easysubscription-badge--muted{color:#666}
		.easysubscription-card__actions{margin-top:.75rem}
		/* 44px minimum touch targets: cancel must be reachable on a phone. */
		.easysubscription-card__actions .button,.easysubscription-cancel__actions .button{min-height:44px;line-height:44px;padding:0 1rem}
		.easysubscription-items{margin:.5rem 0 1rem;padding-left:1.1rem}
		/* Equal visual weight: cancel is never a grey link next to a primary button. */
		.easysubscription-cancel__actions{display:flex;flex-wrap:wrap;gap:.6rem}
		.easysubscription-cancel__actions .easysubscription-btn--primary{background:#7f54b3;border-color:#7f54b3;color:#fff;font-weight:600}
			.easysubscription-btn--primary:hover{background:#6b4699;border-color:#6b4699;color:#fff}
			.easysubscription-card__link{align-self:center;font-size:.9em}
			.easysubscription-detail__action{margin:0 0 1.25rem}
			.easysubscription-btn{flex:1 1 12rem;text-align:center}
		.easysubscription-empty{padding:1rem 0}
		@media(min-width:768px){.easysubscription{gap:.75rem}}
		';
	}
}
