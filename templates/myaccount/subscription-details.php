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
use SubKit\Lifecycle\Cancellation_Policy;

$subkit_state   = Status_Presenter::for( $subscription );
$subkit_pending = Status_Presenter::pending_charge( $subscription );
$subkit_by_link = Status_Presenter::pays_by_link( $subscription );
$subkit_live    = ! in_array(
	$subscription->get_status_enum(),
	array( Subscription_Status::Cancelled, Subscription_Status::Expired, Subscription_Status::Switched, Subscription_Status::PendingCancel ),
	true
);

// A term the customer agreed to, such as a minimum number of payments, can hold both buttons back.
$subkit_term_allows    = $subkit_live && apply_filters( 'subkit_can_cancel', true, $subscription, get_current_user_id() );
$subkit_can_cancel     = $subkit_term_allows && Cancellation_Policy::is_offered();
$subkit_cancel_refused = $subkit_live && ! $subkit_can_cancel ? \SubKit\Frontend\MyAccount\Account_Endpoint::cancel_refused_message( $subscription ) : '';
?>

<p class="subkit-back">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
		&larr; <?php esc_html_e( 'All subscriptions', 'subkit-subscriptions' ); ?>
	</a>
</p>

<div class="subkit-detail">
	<div class="subkit-card__head">
		<h2><?php echo esc_html( Status_Presenter::title( $subscription ) ); ?></h2>
		<span class="subkit-badge subkit-badge--<?php echo esc_attr( $subkit_state['tone'] ); ?>">
			<?php echo esc_html( $subkit_state['label'] ); ?>
		</span>
	</div>

	<?php if ( $subkit_state['detail'] ) : ?>
		<p class="subkit-detail__state"><?php echo esc_html( $subkit_state['detail'] ); ?></p>
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
		<?php foreach ( $subscription->get_items() as $subkit_item ) : ?>
			<li>
				<?php echo esc_html( $subkit_item->get_name() ); ?>
				<?php if ( $subkit_item->get_quantity() > 1 ) : ?>
					<span class="subkit-qty">&times; <?php echo esc_html( (string) $subkit_item->get_quantity() ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="subkit-detail__total">
		<strong><?php esc_html_e( 'Recurring total', 'subkit-subscriptions' ); ?>:</strong>
		<?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?>
	</p>

	<?php
	/**
	 * Renders after the subscription's totals, for anything an extension needs to show
	 * the customer here - licence keys, downloads, delivery dates.
	 *
	 * @param \SubKit\Domain\Subscription $subscription
	 */
	do_action( 'subkit_after_subscription_totals', $subscription );
	?>

	<?php
	/**
	 * Extra actions a customer can take on this subscription.
	 *
	 * @param array                        $actions      slug => button label
	 * @param \SubKit\Domain\Subscription $subscription
	 */
	$subkit_extra_actions = (array) apply_filters( 'subkit_myaccount_actions', array(), $subscription );
	?>
	<?php if ( $subkit_extra_actions ) : ?>
		<p class="subkit-actions">
			<?php foreach ( $subkit_extra_actions as $subkit_slug => $subkit_label ) : ?>
				<form method="post" style="display:inline-block;margin:0 .4em .4em 0">
					<?php wp_nonce_field( 'subkit_pro_' . $subscription->get_id() ); ?>
					<input type="hidden" name="subkit_pro_action" value="<?php echo esc_attr( (string) $subkit_slug ); ?>" />
					<input type="hidden" name="subkit_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
					<?php
					/**
					 * Fields an extension needs on one of its action forms, such as a reason for pausing.
					 *
					 * @param string                      $slug
					 * @param \SubKit\Domain\Subscription $subscription
					 */
					do_action( 'subkit_myaccount_action_fields', (string) $subkit_slug, $subscription );
					?>
					<button type="submit" class="button subkit-btn"><?php echo esc_html( (string) $subkit_label ); ?></button>
				</form>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( \SubKit\Lifecycle\Early_Renewal::is_available_for( $subscription ) ) : ?>
		<h3><?php esc_html_e( 'Pay early', 'subkit-subscriptions' ); ?></h3>

		<p>
			<?php
			printf(
				/* translators: 1: amount, 2: the date it would otherwise be taken */
				esc_html__( 'You can pay your next %1$s now instead of on %2$s. Your renewal date stays the same.', 'subkit-subscriptions' ),
				wp_kses_post( $subscription->get_formatted_order_total() ),
				esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
			);
			?>
		</p>

		<form method="post" class="subkit-renew-early">
			<?php wp_nonce_field( 'subkit_renew_early_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subkit_action" value="renew_early" />
			<input type="hidden" name="subkit_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
			<button type="submit" class="button subkit-btn"><?php esc_html_e( 'Pay now', 'subkit-subscriptions' ); ?></button>
		</form>
	<?php endif; ?>

	<?php
	$subkit_auto_offered = \SubKit\Lifecycle\Auto_Renewal::is_offered() && $subkit_term_allows;
	$subkit_auto_on      = \SubKit\Lifecycle\Auto_Renewal::is_on( $subscription );
	?>
	<?php if ( $subkit_auto_offered ) : ?>
		<h3><?php echo $subkit_by_link ? esc_html__( 'Renewal', 'subkit-subscriptions' ) : esc_html__( 'Automatic renewal', 'subkit-subscriptions' ); ?></h3>

		<p class="subkit-auto-renew__state">
			<?php
			if ( $subkit_by_link ) {
				echo $subkit_auto_on
					? esc_html__( 'At the end of each period we send you a renewal to pay.', 'subkit-subscriptions' )
					: esc_html__( 'Renewal is off. You keep access until the end of the period you have paid for, and you will not be asked to pay again.', 'subkit-subscriptions' );
			} else {
				echo $subkit_auto_on
					? esc_html__( 'This subscription renews automatically.', 'subkit-subscriptions' )
					: esc_html__( 'Automatic renewal is off. You keep access until the end of the period you have paid for, and you will not be charged again.', 'subkit-subscriptions' );
			}
			?>
		</p>

		<form method="post" class="subkit-auto-renew">
			<?php wp_nonce_field( 'subkit_auto_renew_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subkit_action" value="auto_renew" />
			<input type="hidden" name="subkit_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
			<input type="hidden" name="subkit_auto_renew" value="<?php echo $subkit_auto_on ? 'off' : 'on'; ?>" />

			<button type="submit" class="button subkit-btn">
				<?php
				if ( $subkit_by_link ) {
					echo $subkit_auto_on
						? esc_html__( 'Stop renewing', 'subkit-subscriptions' )
						: esc_html__( 'Turn renewal back on', 'subkit-subscriptions' );
				} else {
					echo $subkit_auto_on
						? esc_html__( 'Turn off automatic renewal', 'subkit-subscriptions' )
						: esc_html__( 'Turn automatic renewal back on', 'subkit-subscriptions' );
				}
				?>
			</button>
		</form>
	<?php endif; ?>

	<?php if ( $subkit_can_cancel ) : ?>
		<h3><?php esc_html_e( 'Cancel this subscription', 'subkit-subscriptions' ); ?></h3>

		<form method="post" class="subkit-cancel">
			<?php wp_nonce_field( 'subkit_cancel_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subkit_action" value="cancel" />
			<input type="hidden" name="subkit_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />

			<p class="subkit-cancel__effect">
				<?php
				if ( Cancellation_Policy::is_immediate() ) {
					esc_html_e( 'Your subscription ends now, and access ends with it. There is no refund for the time left.', 'subkit-subscriptions' );
				} elseif ( $subscription->get_next_payment() && ! $subkit_pending ) {
					printf(
						/* translators: %s: date the current period ends */
						esc_html__( 'Your subscription stays active until %s. You will not be charged again.', 'subkit-subscriptions' ),
						esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
					);
				} else {
					esc_html_e( 'Your subscription stays active until the end of the current period. You will not be charged again.', 'subkit-subscriptions' );
				}
				?>
			</p>

			<div class="subkit-cancel__actions">
				<button type="submit" class="button subkit-btn"><?php esc_html_e( 'Cancel subscription', 'subkit-subscriptions' ); ?></button>
				<a class="button subkit-btn" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
					<?php esc_html_e( 'Keep subscription', 'subkit-subscriptions' ); ?>
				</a>
			</div>
		</form>
	<?php elseif ( $subkit_cancel_refused ) : ?>
		<p class="subkit-cancel__refused"><?php echo esc_html( $subkit_cancel_refused ); ?></p>
	<?php endif; ?>
</div>
