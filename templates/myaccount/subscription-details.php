<?php
/**
 * Single subscription. Ordered by what customers actually ask first:
 * when am I charged next, what is in it, how do I stop it.
 *
 * Override: yourtheme/easysubscription/myaccount/subscription-details.php
 *
 * @var \EasySubscription\Domain\Subscription $subscription
 * @var string                      $endpoint
 */

defined( 'ABSPATH' ) || exit;

use EasySubscription\Domain\Subscription_Status;
use EasySubscription\Frontend\MyAccount\Status_Presenter;
use EasySubscription\Lifecycle\Cancellation_Policy;

$easysubscription_state   = Status_Presenter::for( $subscription );
$easysubscription_pending = Status_Presenter::pending_charge( $subscription );
$easysubscription_by_link = Status_Presenter::pays_by_link( $subscription );
$easysubscription_live    = ! in_array(
	$subscription->get_status_enum(),
	array( Subscription_Status::Cancelled, Subscription_Status::Expired, Subscription_Status::Switched, Subscription_Status::PendingCancel ),
	true
);

// A term the customer agreed to, such as a minimum number of payments, can hold both buttons back.
$easysubscription_term_allows    = $easysubscription_live && apply_filters( 'easysubscription_can_cancel', true, $subscription, get_current_user_id() );
$easysubscription_can_cancel     = $easysubscription_term_allows && Cancellation_Policy::is_offered();
$easysubscription_cancel_refused = $easysubscription_live && ! $easysubscription_can_cancel ? \EasySubscription\Frontend\MyAccount\Account_Endpoint::cancel_refused_message( $subscription ) : '';
?>

<p class="easysubscription-back">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
		&larr; <?php esc_html_e( 'All subscriptions', 'easysubscription' ); ?>
	</a>
</p>

<div class="easysubscription-detail">
	<div class="easysubscription-card__head">
		<h2><?php echo esc_html( Status_Presenter::title( $subscription ) ); ?></h2>
		<span class="easysubscription-badge easysubscription-badge--<?php echo esc_attr( $easysubscription_state['tone'] ); ?>">
			<?php echo esc_html( $easysubscription_state['label'] ); ?>
		</span>
	</div>

	<?php if ( $easysubscription_state['detail'] ) : ?>
		<p class="easysubscription-detail__state"><?php echo esc_html( $easysubscription_state['detail'] ); ?></p>
	<?php endif; ?>

	<?php $easysubscription_action = \EasySubscription\Frontend\MyAccount\Status_Presenter::primary_action( $subscription ); ?>
	<?php if ( $easysubscription_action ) : ?>
		<p class="easysubscription-detail__action">
			<a class="easysubscription-btn easysubscription-btn--primary" href="<?php echo esc_url( $easysubscription_action['url'] ); ?>">
				<?php echo esc_html( $easysubscription_action['label'] ); ?>
			</a>
		</p>
	<?php endif; ?>

	<h3><?php esc_html_e( "What's included", 'easysubscription' ); ?></h3>
	<ul class="easysubscription-items">
		<?php foreach ( $subscription->get_items() as $easysubscription_item ) : ?>
			<li>
				<?php echo esc_html( $easysubscription_item->get_name() ); ?>
				<?php if ( $easysubscription_item->get_quantity() > 1 ) : ?>
					<span class="easysubscription-qty">&times; <?php echo esc_html( (string) $easysubscription_item->get_quantity() ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="easysubscription-detail__total">
		<strong><?php esc_html_e( 'Recurring total', 'easysubscription' ); ?>:</strong>
		<?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?>
	</p>

	<?php
	/**
	 * Renders after the subscription's totals, for anything an extension needs to show
	 * the customer here - licence keys, downloads, delivery dates.
	 *
	 * @param \EasySubscription\Domain\Subscription $subscription
	 */
	do_action( 'easysubscription_after_subscription_totals', $subscription );
	?>

	<?php
	/**
	 * Extra actions a customer can take on this subscription.
	 *
	 * @param array                        $actions      slug => button label
	 * @param \EasySubscription\Domain\Subscription $subscription
	 */
	$easysubscription_extra_actions = (array) apply_filters( 'easysubscription_myaccount_actions', array(), $subscription );
	?>
	<?php if ( $easysubscription_extra_actions ) : ?>
		<p class="easysubscription-actions">
			<?php foreach ( $easysubscription_extra_actions as $easysubscription_slug => $easysubscription_label ) : ?>
				<form method="post" style="display:inline-block;margin:0 .4em .4em 0">
					<?php wp_nonce_field( 'easysubscription_pro_' . $subscription->get_id() ); ?>
					<input type="hidden" name="easysubscription_pro_action" value="<?php echo esc_attr( (string) $easysubscription_slug ); ?>" />
					<input type="hidden" name="easysubscription_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
					<?php
					/**
					 * Fields an extension needs on one of its action forms, such as a reason for pausing.
					 *
					 * @param string                      $slug
					 * @param \EasySubscription\Domain\Subscription $subscription
					 */
					do_action( 'easysubscription_myaccount_action_fields', (string) $easysubscription_slug, $subscription );
					?>
					<button type="submit" class="button easysubscription-btn"><?php echo esc_html( (string) $easysubscription_label ); ?></button>
				</form>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( \EasySubscription\Lifecycle\Early_Renewal::is_available_for( $subscription ) ) : ?>
		<h3><?php esc_html_e( 'Pay early', 'easysubscription' ); ?></h3>

		<p>
			<?php
			printf(
				/* translators: 1: amount, 2: the date it would otherwise be taken */
				esc_html__( 'You can pay your next %1$s now instead of on %2$s. Your renewal date stays the same.', 'easysubscription' ),
				wp_kses_post( $subscription->get_formatted_order_total() ),
				esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
			);
			?>
		</p>

		<form method="post" class="easysubscription-renew-early">
			<?php wp_nonce_field( 'easysubscription_renew_early_' . $subscription->get_id() ); ?>
			<input type="hidden" name="easysubscription_action" value="renew_early" />
			<input type="hidden" name="easysubscription_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
			<button type="submit" class="button easysubscription-btn"><?php esc_html_e( 'Pay now', 'easysubscription' ); ?></button>
		</form>
	<?php endif; ?>

	<?php
	$easysubscription_auto_offered = \EasySubscription\Lifecycle\Auto_Renewal::is_offered() && $easysubscription_term_allows;
	$easysubscription_auto_on      = \EasySubscription\Lifecycle\Auto_Renewal::is_on( $subscription );
	?>
	<?php if ( $easysubscription_auto_offered ) : ?>
		<h3><?php echo $easysubscription_by_link ? esc_html__( 'Renewal', 'easysubscription' ) : esc_html__( 'Automatic renewal', 'easysubscription' ); ?></h3>

		<p class="easysubscription-auto-renew__state">
			<?php
			if ( $easysubscription_by_link ) {
				echo $easysubscription_auto_on
					? esc_html__( 'At the end of each period we send you a renewal to pay.', 'easysubscription' )
					: esc_html__( 'Renewal is off. You keep access until the end of the period you have paid for, and you will not be asked to pay again.', 'easysubscription' );
			} else {
				echo $easysubscription_auto_on
					? esc_html__( 'This subscription renews automatically.', 'easysubscription' )
					: esc_html__( 'Automatic renewal is off. You keep access until the end of the period you have paid for, and you will not be charged again.', 'easysubscription' );
			}
			?>
		</p>

		<form method="post" class="easysubscription-auto-renew">
			<?php wp_nonce_field( 'easysubscription_auto_renew_' . $subscription->get_id() ); ?>
			<input type="hidden" name="easysubscription_action" value="auto_renew" />
			<input type="hidden" name="easysubscription_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
			<input type="hidden" name="easysubscription_auto_renew" value="<?php echo $easysubscription_auto_on ? 'off' : 'on'; ?>" />

			<button type="submit" class="button easysubscription-btn">
				<?php
				if ( $easysubscription_by_link ) {
					echo $easysubscription_auto_on
						? esc_html__( 'Stop renewing', 'easysubscription' )
						: esc_html__( 'Turn renewal back on', 'easysubscription' );
				} else {
					echo $easysubscription_auto_on
						? esc_html__( 'Turn off automatic renewal', 'easysubscription' )
						: esc_html__( 'Turn automatic renewal back on', 'easysubscription' );
				}
				?>
			</button>
		</form>
	<?php endif; ?>

	<?php if ( $easysubscription_can_cancel ) : ?>
		<h3><?php esc_html_e( 'Cancel this subscription', 'easysubscription' ); ?></h3>

		<form method="post" class="easysubscription-cancel">
			<?php wp_nonce_field( 'easysubscription_cancel_' . $subscription->get_id() ); ?>
			<input type="hidden" name="easysubscription_action" value="cancel" />
			<input type="hidden" name="easysubscription_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />

			<p class="easysubscription-cancel__effect">
				<?php
				if ( Cancellation_Policy::is_immediate() ) {
					esc_html_e( 'Your subscription ends now, and access ends with it. There is no refund for the time left.', 'easysubscription' );
				} elseif ( $subscription->get_next_payment() && ! $easysubscription_pending ) {
					printf(
						/* translators: %s: date the current period ends */
						esc_html__( 'Your subscription stays active until %s. You will not be charged again.', 'easysubscription' ),
						esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
					);
				} else {
					esc_html_e( 'Your subscription stays active until the end of the current period. You will not be charged again.', 'easysubscription' );
				}
				?>
			</p>

			<div class="easysubscription-cancel__actions">
				<button type="submit" class="button easysubscription-btn"><?php esc_html_e( 'Cancel subscription', 'easysubscription' ); ?></button>
				<a class="button easysubscription-btn" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
					<?php esc_html_e( 'Keep subscription', 'easysubscription' ); ?>
				</a>
			</div>
		</form>
	<?php elseif ( $easysubscription_cancel_refused ) : ?>
		<p class="easysubscription-cancel__refused"><?php echo esc_html( $easysubscription_cancel_refused ); ?></p>
	<?php endif; ?>
</div>
