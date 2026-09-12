<?php
/**
 * Customer subscription list. Cards on mobile, table on wide screens.
 *
 * Override: yourtheme/subkit-subscriptions/myaccount/subscriptions.php
 *
 * @var \SubKit\Domain\Subscription[] $subscriptions
 * @var string                        $endpoint
 */

defined( 'ABSPATH' ) || exit;

use SubKit\Frontend\MyAccount\Status_Presenter;

if ( empty( $subscriptions ) ) : ?>
	<div class="subkit-empty">
		<p><?php esc_html_e( "You don't have any subscriptions.", 'subkit-subscriptions' ); ?></p>
		<a class="woocommerce-Button button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
			<?php esc_html_e( 'Browse products', 'subkit-subscriptions' ); ?>
		</a>
	</div>
	<?php return; ?>
<?php endif; ?>

<ul class="subkit-subscriptions">
	<?php
	foreach ( $subscriptions as $subscription ) :
		$state = Status_Presenter::for( $subscription );
		?>
		<li class="subkit-card subkit-card--<?php echo esc_attr( $state['tone'] ); ?>">
			<div class="subkit-card__head">
				<h3 class="subkit-card__title">
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint . '/' . $subscription->get_id() ) ); ?>">
						<?php echo esc_html( Status_Presenter::title( $subscription ) ); ?>
					</a>
				</h3>
				<span class="subkit-badge subkit-badge--<?php echo esc_attr( $state['tone'] ); ?>">
					<?php echo esc_html( $state['label'] ); ?>
				</span>
			</div>

			<p class="subkit-card__price"><?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?></p>

			<?php if ( $state['detail'] ) : ?>
				<p class="subkit-card__detail"><?php echo esc_html( $state['detail'] ); ?></p>
			<?php endif; ?>

			<div class="subkit-card__actions">
				<?php $subkit_action = \SubKit\Frontend\MyAccount\Status_Presenter::primary_action( $subscription ); ?>
				<?php if ( $subkit_action ) : ?>
					<a class="button subkit-btn subkit-btn--primary" href="<?php echo esc_url( $subkit_action['url'] ); ?>">
						<?php echo esc_html( $subkit_action['label'] ); ?>
					</a>
				<?php endif; ?>
				<a class="subkit-card__link" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint . '/' . $subscription->get_id() ) ); ?>">
					<?php esc_html_e( 'View details', 'subkit-subscriptions' ); ?>
				</a>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
