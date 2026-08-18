<?php
/**
 * SubKit email body (HTML).
 *
 * Override at yourtheme/subkit-subscriptions/emails/subscription-email.php
 *
 * @var string      $email_heading
 * @var string      $intro
 * @var array       $facts
 * @var array|null  $cta
 * @var string      $outro
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php echo wp_kses_post( $intro ); ?></p>

<?php if ( ! empty( $facts ) ) : ?>
	<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:1.5em" border="1">
		<tbody>
		<?php foreach ( $facts as $subkit_label => $subkit_value ) : ?>
			<?php if ( '' === $subkit_value ) { continue; } ?>
			<tr>
				<th scope="row" style="text-align:left;width:40%;border:1px solid #e5e5e5;padding:8px"><?php echo esc_html( $subkit_label ); ?></th>
				<td style="border:1px solid #e5e5e5;padding:8px"><?php echo esc_html( $subkit_value ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $cta['url'] ) ) : ?>
	<p style="margin:1.5em 0">
		<a href="<?php echo esc_url( $cta['url'] ); ?>" style="display:inline-block;padding:12px 22px;background:#7f54b3;color:#fff;text-decoration:none;border-radius:4px">
			<?php echo esc_html( $cta['label'] ); ?>
		</a>
	</p>
<?php endif; ?>

<?php if ( '' !== $outro ) : ?>
	<p><?php echo wp_kses_post( $outro ); ?></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_footer', $email );
