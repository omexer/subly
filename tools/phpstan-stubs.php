<?php
/**
 * Action Scheduler ships with WooCommerce but is not in the WooCommerce stubs.
 *
 * @phpstan-ignore-next-line
 */

/**
 * @param int    $timestamp
 * @param int    $interval_in_seconds
 * @param string $hook
 * @param array  $args
 * @param string $group
 * @param bool   $unique
 * @param int    $priority
 */
function as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ): int {}

/**
 * @param int    $timestamp
 * @param string $hook
 * @param array  $args
 * @param string $group
 * @param bool   $unique
 * @param int    $priority
 */
function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ): int {}

/**
 * @param string $hook
 * @param array|null $args
 * @param string $group
 * @return int|bool|null
 */
function as_next_scheduled_action( $hook, $args = null, $group = '' ) {}

/**
 * @param string $hook
 * @param array|null $args
 * @param string $group
 * @param int $priority
 */
function as_unschedule_action( $hook, $args = array(), $group = '', $priority = 10 ): ?int {}

/**
 * @param string $hook
 * @param array|null $args
 * @param string $group
 */
function as_unschedule_all_actions( $hook = '', $args = array(), $group = '' ): void {}

/**
 * @param array  $args
 * @param string $return_format
 * @return array
 */
function as_get_scheduled_actions( $args = array(), $return_format = OBJECT ) {}

/**
 * @param string $hook
 * @param array $args
 * @param string $group
 */
function as_has_scheduled_action( $hook, $args = null, $group = '' ): bool {}

class ActionScheduler_Store {
	const STATUS_COMPLETE = 'complete';
	const STATUS_PENDING  = 'pending';
	const STATUS_RUNNING  = 'in-progress';
	const STATUS_FAILED   = 'failed';
	const STATUS_CANCELED = 'canceled';

	public static function instance(): self {}

	/**
	 * @param array $query
	 * @param string $query_type
	 * @return int|array
	 */
	public function query_actions( $query = array(), $query_type = 'select' ) {}
}

/**
 * FluentCRM's API accessor, not installed here; every call is behind function_exists().
 *
 * @param string $key
 * @return mixed
 */
function FluentCrmApi( $key ) {}
