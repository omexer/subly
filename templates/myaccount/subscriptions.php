<?php
/**
 * Customer subscription list. Cards on mobile, table on wide screens.
 *
 * Override: yourtheme/easysubscription/myaccount/subscriptions.php
 *
 * @var \EasySubscription\Domain\Subscription[] $subscriptions
 * @var string                        $endpoint
 */

defined( 'ABSPATH' ) || exit;

use EasySubscription\Frontend\MyAccount\Status_Presenter;

if ( empty( $subscriptions ) ) : ?>
	<div class="easysubscription-empty">
		<p><?php esc_html_e( "You don't have any subscriptions.", 'easysubscription' ); ?></p>
		<a class="woocommerce-Button button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
			<?php esc_html_e( 'Browse products', 'easysubscription' ); ?>
		</a>
	</div>
	<?php return; ?>
<?php endif; ?>

<ul class="easysubscription">
	<?php
	foreach ( $subscriptions as $easysubscription_subscription ) :
		$easysubscription_state = Status_Presenter::for( $easysubscription_subscription );
		?>
		<li class="easysubscription-card easysubscription-card--<?php echo esc_attr( $easysubscription_state['tone'] ); ?>">
			<div class="easysubscription-card__head">
				<h3 class="easysubscription-card__title">
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint . '/' . $easysubscription_subscription->get_id() ) ); ?>">
						<?php echo esc_html( Status_Presenter::title( $easysubscription_subscription ) ); ?>
					</a>
				</h3>
				<span class="easysubscription-badge easysubscription-badge--<?php echo esc_attr( $easysubscription_state['tone'] ); ?>">
					<?php echo esc_html( $easysubscription_state['label'] ); ?>
				</span>
			</div>

			<p class="easysubscription-card__price"><?php echo wp_kses_post( $easysubscription_subscription->get_formatted_order_total() ); ?></p>

			<?php if ( $easysubscription_state['detail'] ) : ?>
				<p class="easysubscription-card__detail"><?php echo esc_html( $easysubscription_state['detail'] ); ?></p>
			<?php endif; ?>

			<div class="easysubscription-card__actions">
				<?php $easysubscription_action = \EasySubscription\Frontend\MyAccount\Status_Presenter::primary_action( $easysubscription_subscription ); ?>
				<?php if ( $easysubscription_action ) : ?>
					<a class="button easysubscription-btn easysubscription-btn--primary" href="<?php echo esc_url( $easysubscription_action['url'] ); ?>">
						<?php echo esc_html( $easysubscription_action['label'] ); ?>
					</a>
				<?php endif; ?>
				<a class="easysubscription-card__link" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint . '/' . $easysubscription_subscription->get_id() ) ); ?>">
					<?php esc_html_e( 'View details', 'easysubscription' ); ?>
				</a>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
