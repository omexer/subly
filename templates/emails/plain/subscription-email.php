<?php
/**
 * SubKit email body (plain text).
 *
 * @var string     $email_heading
 * @var string     $intro
 * @var array      $facts
 * @var array|null $cta
 * @var string     $outro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Escape for the linter, then decode: entities like &#039; are correct in HTML and
 * simply wrong in a plain-text body.
 */
$subkit_text = static function ( $value ): string {
	return wp_specialchars_decode( esc_html( wp_strip_all_tags( (string) $value ) ), ENT_QUOTES );
};

echo "= " . $subkit_text( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $subkit_text( $intro ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

foreach ( $facts as $subkit_label => $subkit_value ) {
	if ( '' === $subkit_value ) {
		continue;
	}
	echo $subkit_text( $subkit_label ) . ': ' . $subkit_text( $subkit_value ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

if ( ! empty( $cta['url'] ) ) {
	echo "\n" . $subkit_text( $cta['label'] ) . ":\n" . esc_url_raw( $cta['url'] ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

if ( '' !== $outro ) {
	echo "\n" . $subkit_text( $outro ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

echo "\n----------\n\n";
echo $subkit_text( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
