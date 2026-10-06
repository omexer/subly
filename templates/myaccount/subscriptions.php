<?php
/**
 * Customer subscription list. Cards on mobile, table on wide screens.
 *
 * Override: yourtheme/subly/myaccount/subscriptions.php
 *
 * @var \Subly\Domain\Subscription[] $subscriptions
 * @var string                        $endpoint
 */

defined( 'ABSPATH' ) || exit;

use Subly\Frontend\MyAccount\Status_Presenter;

if ( empty( $subscriptions ) ) : ?>
	<div class="subly-empty">
		<p><?php esc_html_e( "You don't have any subscriptions.", 'subly' ); ?></p>
		<a class="woocommerce-Button button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
			<?php esc_html_e( 'Browse products', 'subly' ); ?>
		</a>
	</div>
	<?php return; ?>
<?php endif; ?>

<ul class="subly">
	<?php
	foreach ( $subscriptions as $subly_subscription ) :
		$subly_state = Status_Presenter::for( $subly_subscription );
		?>
		<li class="subly-card subly-card--<?php echo esc_attr( $subly_state['tone'] ); ?>">
			<div class="subly-card__head">
				<h3 class="subly-card__title">
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint . '/' . $subly_subscription->get_id() ) ); ?>">
						<?php echo esc_html( Status_Presenter::title( $subly_subscription ) ); ?>
					</a>
				</h3>
				<span class="subly-badge subly-badge--<?php echo esc_attr( $subly_state['tone'] ); ?>">
					<?php echo esc_html( $subly_state['label'] ); ?>
				</span>
			</div>

			<p class="subly-card__price"><?php echo wp_kses_post( $subly_subscription->get_formatted_order_total() ); ?></p>

			<?php if ( $subly_state['detail'] ) : ?>
				<p class="subly-card__detail"><?php echo esc_html( $subly_state['detail'] ); ?></p>
			<?php endif; ?>

			<div class="subly-card__actions">
				<?php $subly_action = \Subly\Frontend\MyAccount\Status_Presenter::primary_action( $subly_subscription ); ?>
				<?php if ( $subly_action ) : ?>
					<a class="button subly-btn subly-btn--primary" href="<?php echo esc_url( $subly_action['url'] ); ?>">
						<?php echo esc_html( $subly_action['label'] ); ?>
					</a>
				<?php endif; ?>
				<a class="subly-card__link" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint . '/' . $subly_subscription->get_id() ) ); ?>">
					<?php esc_html_e( 'View details', 'subly' ); ?>
				</a>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
