<?php
/**
 * Single subscription. Ordered by what customers actually ask first:
 * when am I charged next, what is in it, how do I stop it.
 *
 * Override: yourtheme/subkit-subscriptions/myaccount/subscription-details.php
 *
 * @var \SubKit\Domain\Subscription $subscription
 * @var string                      $endpoint
 */

defined( 'ABSPATH' ) || exit;

use SubKit\Domain\Subscription_Status;
use SubKit\Frontend\MyAccount\Status_Presenter;

$state      = Status_Presenter::for( $subscription );
$can_cancel = ! in_array(
	$subscription->get_status_enum(),
	array( Subscription_Status::Cancelled, Subscription_Status::Expired, Subscription_Status::Switched, Subscription_Status::PendingCancel ),
	true
);
?>

<p class="subkit-back">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
		&larr; <?php esc_html_e( 'All subscriptions', 'subkit-subscriptions' ); ?>
	</a>
</p>

<div class="subkit-detail">
	<div class="subkit-card__head">
		<h2><?php echo esc_html( Status_Presenter::title( $subscription ) ); ?></h2>
		<span class="subkit-badge subkit-badge--<?php echo esc_attr( $state['tone'] ); ?>">
			<?php echo esc_html( $state['label'] ); ?>
		</span>
	</div>

	<?php if ( $state['detail'] ) : ?>
		<p class="subkit-detail__state"><?php echo esc_html( $state['detail'] ); ?></p>
	<?php endif; ?>

	<?php $subkit_action = \SubKit\Frontend\MyAccount\Status_Presenter::primary_action( $subscription ); ?>
	<?php if ( $subkit_action ) : ?>
		<p class="subkit-detail__action">
			<a class="subkit-btn subkit-btn--primary" href="<?php echo esc_url( $subkit_action['url'] ); ?>">
				<?php echo esc_html( $subkit_action['label'] ); ?>
			</a>
		</p>
	<?php endif; ?>

	<h3><?php esc_html_e( "What's included", 'subkit-subscriptions' ); ?></h3>
	<ul class="subkit-items">
		<?php foreach ( $subscription->get_items() as $item ) : ?>
			<li>
				<?php echo esc_html( $item->get_name() ); ?>
				<?php if ( $item->get_quantity() > 1 ) : ?>
					<span class="subkit-qty">&times; <?php echo esc_html( $item->get_quantity() ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="subkit-detail__total">
		<strong><?php esc_html_e( 'Recurring total', 'subkit-subscriptions' ); ?>:</strong>
		<?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?>
	</p>

	<?php if ( $can_cancel ) : ?>
		<h3><?php esc_html_e( 'Cancel this subscription', 'subkit-subscriptions' ); ?></h3>

		<form method="post" class="subkit-cancel">
			<?php wp_nonce_field( 'subkit_cancel_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subkit_action" value="cancel" />
			<input type="hidden" name="subkit_subscription" value="<?php echo esc_attr( $subscription->get_id() ); ?>" />

			<?php if ( $subscription->get_next_payment() ) : ?>
				<p class="subkit-cancel__paid-through">
					<?php
					printf(
						/* translators: %s: date the current period ends */
						esc_html__( "You've paid through %s.", 'subkit-subscriptions' ),
						esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
					);
					?>
				</p>
			<?php endif; ?>

			<label class="subkit-choice">
				<input type="radio" name="subkit_when" value="period_end" checked="checked" />
				<span>
					<strong><?php esc_html_e( 'Cancel at the end of the period', 'subkit-subscriptions' ); ?></strong>
					<em><?php esc_html_e( 'Keep access until the period ends. No more charges.', 'subkit-subscriptions' ); ?></em>
				</span>
			</label>

			<label class="subkit-choice">
				<input type="radio" name="subkit_when" value="immediate" />
				<span>
					<strong><?php esc_html_e( 'Cancel immediately', 'subkit-subscriptions' ); ?></strong>
					<em><?php esc_html_e( 'Ends access today. No refund for the remaining days.', 'subkit-subscriptions' ); ?></em>
				</span>
			</label>

			<div class="subkit-cancel__actions">
				<button type="submit" class="button subkit-btn"><?php esc_html_e( 'Cancel subscription', 'subkit-subscriptions' ); ?></button>
				<a class="button subkit-btn" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
					<?php esc_html_e( 'Keep subscription', 'subkit-subscriptions' ); ?>
				</a>
			</div>
		</form>
	<?php endif; ?>
</div>
