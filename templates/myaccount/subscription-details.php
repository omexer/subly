<?php
/**
 * Single subscription. Ordered by what customers actually ask first:
 * when am I charged next, what is in it, how do I stop it.
 *
 * Override: yourtheme/subly/myaccount/subscription-details.php
 *
 * @var \Subly\Domain\Subscription $subscription
 * @var string                      $endpoint
 */

defined( 'ABSPATH' ) || exit;

use Subly\Domain\Subscription_Status;
use Subly\Frontend\MyAccount\Status_Presenter;
use Subly\Lifecycle\Cancellation_Policy;

$subly_state   = Status_Presenter::for( $subscription );
$subly_pending = Status_Presenter::pending_charge( $subscription );
$subly_by_link = Status_Presenter::pays_by_link( $subscription );
$subly_live    = ! in_array(
	$subscription->get_status_enum(),
	array( Subscription_Status::Cancelled, Subscription_Status::Expired, Subscription_Status::Switched, Subscription_Status::PendingCancel ),
	true
);

// A term the customer agreed to, such as a minimum number of payments, can hold both buttons back.
$subly_term_allows    = $subly_live && apply_filters( 'subly_can_cancel', true, $subscription, get_current_user_id() );
$subly_can_cancel     = $subly_term_allows && Cancellation_Policy::is_offered();
$subly_cancel_refused = $subly_live && ! $subly_can_cancel ? \Subly\Frontend\MyAccount\Account_Endpoint::cancel_refused_message( $subscription ) : '';
?>

<p class="subly-back">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
		&larr; <?php esc_html_e( 'All subscriptions', 'subly' ); ?>
	</a>
</p>

<div class="subly-detail">
	<div class="subly-card__head">
		<h2><?php echo esc_html( Status_Presenter::title( $subscription ) ); ?></h2>
		<span class="subly-badge subly-badge--<?php echo esc_attr( $subly_state['tone'] ); ?>">
			<?php echo esc_html( $subly_state['label'] ); ?>
		</span>
	</div>

	<?php if ( $subly_state['detail'] ) : ?>
		<p class="subly-detail__state"><?php echo esc_html( $subly_state['detail'] ); ?></p>
	<?php endif; ?>

	<?php $subly_action = \Subly\Frontend\MyAccount\Status_Presenter::primary_action( $subscription ); ?>
	<?php if ( $subly_action ) : ?>
		<p class="subly-detail__action">
			<a class="subly-btn subly-btn--primary" href="<?php echo esc_url( $subly_action['url'] ); ?>">
				<?php echo esc_html( $subly_action['label'] ); ?>
			</a>
		</p>
	<?php endif; ?>

	<h3><?php esc_html_e( "What's included", 'subly' ); ?></h3>
	<ul class="subly-items">
		<?php foreach ( $subscription->get_items() as $subly_item ) : ?>
			<li>
				<?php echo esc_html( $subly_item->get_name() ); ?>
				<?php if ( $subly_item->get_quantity() > 1 ) : ?>
					<span class="subly-qty">&times; <?php echo esc_html( (string) $subly_item->get_quantity() ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="subly-detail__total">
		<strong><?php esc_html_e( 'Recurring total', 'subly' ); ?>:</strong>
		<?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?>
	</p>

	<?php
	/**
	 * Renders after the subscription's totals, for anything an extension needs to show
	 * the customer here - licence keys, downloads, delivery dates.
	 *
	 * @param \Subly\Domain\Subscription $subscription
	 */
	do_action( 'subly_after_subscription_totals', $subscription );
	?>

	<?php
	/**
	 * Extra actions a customer can take on this subscription.
	 *
	 * @param array                        $actions      slug => button label
	 * @param \Subly\Domain\Subscription $subscription
	 */
	$subly_extra_actions = (array) apply_filters( 'subly_myaccount_actions', array(), $subscription );
	?>
	<?php if ( $subly_extra_actions ) : ?>
		<p class="subly-actions">
			<?php foreach ( $subly_extra_actions as $subly_slug => $subly_label ) : ?>
				<form method="post" style="display:inline-block;margin:0 .4em .4em 0">
					<?php wp_nonce_field( 'subly_pro_' . $subscription->get_id() ); ?>
					<input type="hidden" name="subly_pro_action" value="<?php echo esc_attr( (string) $subly_slug ); ?>" />
					<input type="hidden" name="subly_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
					<?php
					/**
					 * Fields an extension needs on one of its action forms, such as a reason for pausing.
					 *
					 * @param string                      $slug
					 * @param \Subly\Domain\Subscription $subscription
					 */
					do_action( 'subly_myaccount_action_fields', (string) $subly_slug, $subscription );
					?>
					<button type="submit" class="button subly-btn"><?php echo esc_html( (string) $subly_label ); ?></button>
				</form>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( \Subly\Lifecycle\Early_Renewal::is_available_for( $subscription ) ) : ?>
		<h3><?php esc_html_e( 'Pay early', 'subly' ); ?></h3>

		<p>
			<?php
			printf(
				/* translators: 1: amount, 2: the date it would otherwise be taken */
				esc_html__( 'You can pay your next %1$s now instead of on %2$s. Your renewal date stays the same.', 'subly' ),
				wp_kses_post( $subscription->get_formatted_order_total() ),
				esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
			);
			?>
		</p>

		<form method="post" class="subly-renew-early">
			<?php wp_nonce_field( 'subly_renew_early_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subly_action" value="renew_early" />
			<input type="hidden" name="subly_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
			<button type="submit" class="button subly-btn"><?php esc_html_e( 'Pay now', 'subly' ); ?></button>
		</form>
	<?php endif; ?>

	<?php
	$subly_auto_offered = \Subly\Lifecycle\Auto_Renewal::is_offered() && $subly_term_allows;
	$subly_auto_on      = \Subly\Lifecycle\Auto_Renewal::is_on( $subscription );
	?>
	<?php if ( $subly_auto_offered ) : ?>
		<h3><?php echo $subly_by_link ? esc_html__( 'Renewal', 'subly' ) : esc_html__( 'Automatic renewal', 'subly' ); ?></h3>

		<p class="subly-auto-renew__state">
			<?php
			if ( $subly_by_link ) {
				echo $subly_auto_on
					? esc_html__( 'At the end of each period we send you a renewal to pay.', 'subly' )
					: esc_html__( 'Renewal is off. You keep access until the end of the period you have paid for, and you will not be asked to pay again.', 'subly' );
			} else {
				echo $subly_auto_on
					? esc_html__( 'This subscription renews automatically.', 'subly' )
					: esc_html__( 'Automatic renewal is off. You keep access until the end of the period you have paid for, and you will not be charged again.', 'subly' );
			}
			?>
		</p>

		<form method="post" class="subly-auto-renew">
			<?php wp_nonce_field( 'subly_auto_renew_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subly_action" value="auto_renew" />
			<input type="hidden" name="subly_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />
			<input type="hidden" name="subly_auto_renew" value="<?php echo $subly_auto_on ? 'off' : 'on'; ?>" />

			<button type="submit" class="button subly-btn">
				<?php
				if ( $subly_by_link ) {
					echo $subly_auto_on
						? esc_html__( 'Stop renewing', 'subly' )
						: esc_html__( 'Turn renewal back on', 'subly' );
				} else {
					echo $subly_auto_on
						? esc_html__( 'Turn off automatic renewal', 'subly' )
						: esc_html__( 'Turn automatic renewal back on', 'subly' );
				}
				?>
			</button>
		</form>
	<?php endif; ?>

	<?php if ( $subly_can_cancel ) : ?>
		<h3><?php esc_html_e( 'Cancel this subscription', 'subly' ); ?></h3>

		<form method="post" class="subly-cancel">
			<?php wp_nonce_field( 'subly_cancel_' . $subscription->get_id() ); ?>
			<input type="hidden" name="subly_action" value="cancel" />
			<input type="hidden" name="subly_subscription" value="<?php echo esc_attr( (string) $subscription->get_id() ); ?>" />

			<p class="subly-cancel__effect">
				<?php
				if ( Cancellation_Policy::is_immediate() ) {
					esc_html_e( 'Your subscription ends now, and access ends with it. There is no refund for the time left.', 'subly' );
				} elseif ( $subscription->get_next_payment() && ! $subly_pending ) {
					printf(
						/* translators: %s: date the current period ends */
						esc_html__( 'Your subscription stays active until %s. You will not be charged again.', 'subly' ),
						esc_html( date_i18n( (string) get_option( 'date_format' ), strtotime( $subscription->get_next_payment() . ' UTC' ) ) )
					);
				} else {
					esc_html_e( 'Your subscription stays active until the end of the current period. You will not be charged again.', 'subly' );
				}
				?>
			</p>

			<div class="subly-cancel__actions">
				<button type="submit" class="button subly-btn"><?php esc_html_e( 'Cancel subscription', 'subly' ); ?></button>
				<a class="button subly-btn" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
					<?php esc_html_e( 'Keep subscription', 'subly' ); ?>
				</a>
			</div>
		</form>
	<?php elseif ( $subly_cancel_refused ) : ?>
		<p class="subly-cancel__refused"><?php echo esc_html( $subly_cancel_refused ); ?></p>
	<?php endif; ?>
</div>
