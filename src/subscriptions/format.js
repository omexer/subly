/**
 * The wording the list and the detail screen share, so one subscription never reads two
 * different ways depending on where you look at it.
 */
import { __, sprintf, _n } from '@wordpress/i18n';

export function statusVariant( status ) {
	if ( 'sk-active' === status || 'sk-trialling' === status ) {
		return 'success';
	}

	if ( 'sk-on-hold' === status ) {
		return 'destructive';
	}

	return 'secondary';
}

/**
 * "every month", "every 2 weeks" - the same phrasing the customer is shown.
 *
 * @param {Object} row One subscription, as the REST API presents it.
 * @return {string} The interval in words.
 */
export function describeSchedule( row ) {
	const count = Number( row.billing_interval ) || 1;

	switch ( row.billing_period ) {
		case 'day':
			return 1 === count
				? __( 'every day', 'subkit-subscriptions' )
				: sprintf(
						/* translators: %d: number of days. */
						_n(
							'every %d day',
							'every %d days',
							count,
							'subkit-subscriptions'
						),
						count
				  );
		case 'week':
			return 1 === count
				? __( 'every week', 'subkit-subscriptions' )
				: sprintf(
						/* translators: %d: number of weeks. */
						_n(
							'every %d week',
							'every %d weeks',
							count,
							'subkit-subscriptions'
						),
						count
				  );
		case 'year':
			return 1 === count
				? __( 'every year', 'subkit-subscriptions' )
				: sprintf(
						/* translators: %d: number of years. */
						_n(
							'every %d year',
							'every %d years',
							count,
							'subkit-subscriptions'
						),
						count
				  );
		case 'month':
			return 1 === count
				? __( 'every month', 'subkit-subscriptions' )
				: sprintf(
						/* translators: %d: number of months. */
						_n(
							'every %d month',
							'every %d months',
							count,
							'subkit-subscriptions'
						),
						count
				  );
		default:
			return '';
	}
}

/**
 * A UTC stamp from the API as a Date, or null when it is empty or unreadable.
 *
 * @param {string} stamp A date in UTC, as the API writes them.
 * @return {Date|null} The date.
 */
export function toDate( stamp ) {
	if ( ! stamp ) {
		return null;
	}

	const date = new Date( `${ String( stamp ).replace( ' ', 'T' ) }Z` );

	return isNaN( date.getTime() ) ? null : date;
}

/**
 * How far off the next payment is. The date alone does not say "this one is late".
 *
 * @param {string} stamp The next payment, in UTC.
 * @return {string} How long until it falls due.
 */
export function whenDue( stamp ) {
	const due = toDate( stamp );

	if ( ! due ) {
		return '';
	}

	const days = Math.round( ( due.getTime() - Date.now() ) / 86400000 );

	if ( days < 0 ) {
		return sprintf(
			/* translators: %d: number of days. */
			_n(
				'%d day overdue',
				'%d days overdue',
				Math.abs( days ),
				'subkit-subscriptions'
			),
			Math.abs( days )
		);
	}

	if ( 0 === days ) {
		return __( 'today', 'subkit-subscriptions' );
	}

	return sprintf(
		/* translators: %d: number of days. */
		_n( 'in %d day', 'in %d days', days, 'subkit-subscriptions' ),
		days
	);
}

/**
 * A date the way the reader's own browser writes one.
 *
 * @param {string} stamp A date in UTC.
 * @return {string} The date, or an empty string.
 */
export function formatDay( stamp ) {
	const date = toDate( stamp );

	return date
		? date.toLocaleDateString( undefined, {
				day: 'numeric',
				month: 'long',
				year: 'numeric',
		  } )
		: '';
}

/**
 * @param {string} stamp A date in UTC.
 * @return {string} The time of day, or an empty string.
 */
export function formatTime( stamp ) {
	const date = toDate( stamp );

	return date
		? date.toLocaleTimeString( undefined, {
				hour: '2-digit',
				minute: '2-digit',
		  } )
		: '';
}

/**
 * How long ago something happened, in whole days.
 *
 * @param {string} stamp A date in UTC.
 * @return {string} "today", "3 days ago", or an empty string.
 */
export function daysAgo( stamp ) {
	const date = toDate( stamp );

	if ( ! date ) {
		return '';
	}

	const days = Math.floor( ( Date.now() - date.getTime() ) / 86400000 );

	if ( days < 1 ) {
		return __( 'today', 'subkit-subscriptions' );
	}

	return sprintf(
		/* translators: %d: number of days. */
		_n( '%d day ago', '%d days ago', days, 'subkit-subscriptions' ),
		days
	);
}
