<?php

namespace SubKit\Rest;

use SubKit\Data\Activity_Repository;
use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST access to subscriptions.
 *
 * Authenticated as WooCommerce itself: a consumer key with write access and the
 * manage_woocommerce capability. Deliberately not a second shared secret of our own -
 * one more all-powerful key in an option row is a liability, and WooCommerce already
 * has key management, revocation and per-key permissions.
 *
 * Lives in the free plugin because the admin screens read it, and a screen that only
 * works with a licence is not a screen. Pro adds its own actions and fields through the
 * filters below rather than registering these routes a second time.
 */
class Subscriptions_Controller {

	public const NAMESPACE = 'subkit/v1';

	private const REST_BASE = 'subscriptions';

	public function __construct( private readonly Activity_Repository $activity ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_subscriptions' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => $this->collection_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_subscription' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_subscription' ),
					'permission_callback' => array( $this, 'can_write' ),
					'args'                => array(
						'next_payment' => array( 'type' => 'string' ),
						'end_date'     => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)/actions',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_action' ),
					'permission_callback' => array( $this, 'can_write' ),
					'args'                => array(
						'action' => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => self::actions(),
						),
						'status' => array( 'type' => 'string' ),
						'reason' => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)/activity',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_activity' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'per_page' => array(
							'type'    => 'integer',
							'default' => 50,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/statuses',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_statuses' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
			)
		);
	}

	/**
	 * Every action this store accepts. Pro adds pause and resume here.
	 *
	 * @return string[]
	 */
	public static function actions(): array {
		$actions = array( 'cancel', 'expire', 'reactivate', 'change_status' );

		/**
		 * Filter the actions the subscriptions endpoint accepts.
		 *
		 * An action named here must also be handled on subkit_rest_subscription_action,
		 * or it will be accepted by the schema and then refused as unknown.
		 *
		 * @param string[] $actions
		 */
		return array_values( array_unique( (array) apply_filters( 'subkit_rest_subscription_actions', $actions ) ) );
	}

	public function can_read(): bool {
		return current_user_can( 'read_private_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	public function can_write(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function list_subscriptions( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$args = array(
			'limit'   => $per_page,
			'page'    => $page,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$status = $request->get_param( 'status' );

		if ( $status ) {
			$args['status'] = $status;
		}

		$customer = (int) $request->get_param( 'customer' );

		if ( $customer > 0 ) {
			$args['customer_id'] = $customer;
		}

		$search = trim( (string) $request->get_param( 'search' ) );

		if ( '' !== $search ) {
			// wc_get_orders spells it s, and it matches the customer's name, email and id.
			$args['s'] = $search;
		}

		$results = Subscription_Query::paginate( $args );

		$items = array();

		foreach ( $results['items'] as $subscription ) {
			if ( $subscription instanceof Subscription ) {
				$items[] = $this->present( $subscription );
			}
		}

		$response = new \WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) $results['total'] );
		$response->header( 'X-WP-TotalPages', (string) $results['pages'] );

		return $response;
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_subscription( \WP_REST_Request $request ) {
		$subscription = $this->find( $request );

		return $subscription instanceof Subscription
			? new \WP_REST_Response( $this->present( $subscription ) )
			: $subscription;
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_subscription( \WP_REST_Request $request ) {
		$subscription = $this->find( $request );

		if ( ! $subscription instanceof Subscription ) {
			return $subscription;
		}

		foreach ( array( 'next_payment', 'end_date' ) as $field ) {
			$value = $request->get_param( $field );

			if ( null === $value ) {
				continue;
			}

			$date = $this->parse_date( (string) $value );

			if ( ! $date ) {
				return new \WP_Error(
					'subkit_invalid_date',
					/* translators: %s: the field that could not be read. */
					sprintf( __( 'Could not read %s as a date.', 'subkit-subscriptions' ), $field ),
					array( 'status' => 400 )
				);
			}

			$subscription->{"set_$field"}( $date );
		}

		$subscription->save();
		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_SCHEDULE_CHANGE,
			'Schedule changed over the REST API.',
			array(),
			'api'
		);

		return new \WP_REST_Response( $this->present( $subscription ) );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_action( \WP_REST_Request $request ) {
		$subscription = $this->find( $request );

		if ( ! $subscription instanceof Subscription ) {
			return $subscription;
		}

		$action = (string) $request->get_param( 'action' );
		$reason = (string) ( $request->get_param( 'reason' ) ?: 'Requested over the REST API.' );

		$done = match ( $action ) {
			'cancel'        => $this->transition( $subscription, Subscription_Status::Cancelled, $reason ),
			'expire'        => $this->transition( $subscription, Subscription_Status::Expired, $reason ),
			'reactivate'    => $this->transition( $subscription, Subscription_Status::Active, $reason ),
			'change_status' => $this->change_status( $subscription, (string) $request->get_param( 'status' ), $reason ),
			default         => null,
		};

		if ( null === $done ) {
			/**
			 * Run an action this plugin does not know about.
			 *
			 * Pro answers for pause and resume. Return true or false to say what
			 * happened, a WP_Error to explain, and null to disclaim the action.
			 *
			 * @param bool|\WP_Error|null $done
			 * @param string              $action
			 * @param Subscription        $subscription
			 * @param string              $reason
			 */
			$done = apply_filters( 'subkit_rest_subscription_action', null, $action, $subscription, $reason );
		}

		if ( $done instanceof \WP_Error ) {
			return $done;
		}

		if ( null === $done ) {
			return new \WP_Error(
				'subkit_unknown_action',
				/* translators: %s: the action that was asked for. */
				sprintf( __( 'Nothing on this site can %s a subscription.', 'subkit-subscriptions' ), $action ),
				array( 'status' => 400 )
			);
		}

		if ( ! $done ) {
			return new \WP_Error(
				'subkit_action_refused',
				sprintf(
					/* translators: 1: subscription id, 2: action, 3: current status. */
					__( 'Subscription %1$d cannot %2$s from %3$s.', 'subkit-subscriptions' ),
					$subscription->get_id(),
					$action,
					(string) $subscription->get_status()
				),
				array( 'status' => 409 )
			);
		}

		return new \WP_REST_Response( $this->present( wc_get_order( $subscription->get_id() ) ) );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_activity( \WP_REST_Request $request ) {
		$subscription = $this->find( $request );

		if ( ! $subscription instanceof Subscription ) {
			return $subscription;
		}

		$limit   = min( 200, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$entries = array();

		foreach ( $this->activity->for_subscription( $subscription->get_id(), $limit ) as $entry ) {
			$entries[] = array(
				'type'    => $entry->type ?? '',
				'message' => $entry->message ?? '',
				'actor'   => $entry->actor ?? '',
				'date'    => $entry->created_gmt ?? '',
			);
		}

		return new \WP_REST_Response( $entries );
	}

	/**
	 * The statuses and what they are called, so a screen does not hard-code either.
	 */
	public function get_statuses(): \WP_REST_Response {
		$out = array();

		foreach ( Subscription_Status::cases() as $status ) {
			$out[] = array(
				'key'   => $status->value,
				'label' => $status->label(),
			);
		}

		return new \WP_REST_Response( $out );
	}

	/**
	 * @return Subscription|\WP_Error
	 */
	private function find( \WP_REST_Request $request ) {
		$subscription = wc_get_order( (int) $request->get_param( 'id' ) );

		// Not 403: telling an authenticated shop manager that an id is not a subscription
		// is not a disclosure, and 404 is what makes a typo debuggable.
		return $subscription instanceof Subscription
			? $subscription
			: new \WP_Error( 'subkit_not_found', __( 'No subscription with that id.', 'subkit-subscriptions' ), array( 'status' => 404 ) );
	}

	/**
	 * @return bool|\WP_Error
	 */
	private function change_status( Subscription $subscription, string $status, string $reason ) {
		$target = Subscription_Status::tryFrom( $status ) ?? Subscription_Status::tryFrom( 'sk-' . ltrim( $status, 'sk-' ) );

		if ( ! $target ) {
			return new \WP_Error( 'subkit_unknown_status', __( 'Unknown subscription status.', 'subkit-subscriptions' ), array( 'status' => 400 ) );
		}

		return $this->transition( $subscription, $target, $reason );
	}

	private function transition( Subscription $subscription, Subscription_Status $to, string $reason ): bool {
		$from = $subscription->get_status_enum();

		// Already there is success, not a conflict: an API call that repeats itself after
		// a dropped connection must not look like a failure.
		if ( $from === $to ) {
			return true;
		}

		if ( $from && ! $from->can_transition_to( $to ) ) {
			return false;
		}

		$subscription->transition_to( $to, $reason );
		$subscription->save();

		return true;
	}

	/**
	 * @param mixed $subscription
	 * @return array<string, mixed>
	 */
	private function present( $subscription ): array {
		if ( ! $subscription instanceof Subscription ) {
			return array();
		}

		$status = $subscription->get_status_enum();

		$data = array(
			'id'               => $subscription->get_id(),
			'status'           => (string) $subscription->get_status(),
			'status_label'     => $status ? $status->label() : '',
			'customer_id'      => $subscription->get_customer_id(),
			'customer_name'    => trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() ),
			'customer_email'   => $subscription->get_billing_email(),
			'currency'         => $subscription->get_currency(),
			'total'            => $subscription->get_total(),
			'total_formatted'  => html_entity_decode( wp_strip_all_tags( $subscription->get_formatted_order_total() ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'billing_period'   => $subscription->get_billing_period(),
			'billing_interval' => $subscription->get_billing_interval(),
			'trial_end'        => $subscription->get_trial_end(),
			'next_payment'     => $subscription->get_next_payment(),
			'end_date'         => $subscription->get_end_date(),
			'period_index'     => $subscription->get_period_index(),
			'payment_method'   => $subscription->get_payment_method(),
			'parent_order_id'  => $subscription->get_parent_order_id(),
			'date_created'     => $subscription->get_date_created() ? $subscription->get_date_created()->date( 'c' ) : null,
			'edit_url'         => admin_url( 'admin.php?page=subkit-subscriptions&subscription=' . $subscription->get_id() ),
		);

		/**
		 * Filter a subscription as the REST API presents it.
		 *
		 * @param array<string, mixed> $data
		 * @param Subscription         $subscription
		 */
		return (array) apply_filters( 'subkit_rest_subscription_data', $data, $subscription );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collection_args(): array {
		return array(
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 20,
			),
			'status'   => array(
				'type' => 'string',
				'enum' => array_map( static fn( Subscription_Status $s ): string => $s->value, Subscription_Status::cases() ),
			),
			'customer' => array( 'type' => 'integer' ),
			'search'   => array( 'type' => 'string' ),
		);
	}

	private function parse_date( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}
}
