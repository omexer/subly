<?php
/**
 * A notice callback of our own for the notices test: it has to live in a file inside the plugin, as ours do.
 *
 * @package Subly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function subly_harness_tip(): void {
	echo '<div class="notice notice-info"><p>Subly Pro is in test licence mode.</p></div>';
}
