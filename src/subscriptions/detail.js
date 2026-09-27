/**
 * One subscription: what it is, what happens next, and the things you can do to it.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Badge,
	Button,
	Card,
	CardHeader,
	CardTitle,
	CardContent,
	Input,
	Skeleton,
	Stat,
} from '@easysubscription/ui';
import {
	daysAgo,
	describeSchedule,
	formatDay,
	formatTime,
	statusVariant,
	whenDue,
} from './format';

function Fact( { label, children } ) {
	return (
		<div className="es-flex es-justify-between es-gap-4 es-border-b es-border-border es-py-2.5 last:es-border-0">
			<span className="es-text-muted-foreground">{ label }</span>
			<span className="es-text-right">{ children }</span>
		</div>
	);
}

/**
 * What kind of entry this is, in a word.
 *
 * @param {string} type The entry type the API reports.
 * @return {string} A label for it.
 */
function entryLabel( type ) {
	switch ( type ) {
		case 'status_change':
			return __( 'Status', 'easysubscription' );
		case 'charge_attempt':
			return __( 'Charge', 'easysubscription' );
		case 'schedule_change':
			return __( 'Schedule', 'easysubscription' );
		default:
			return __( 'Note', 'easysubscription' );
	}
}

/**
 * The log, oldest entries last, split into the days they happened on.
 *
 * @param {Array} entries Activity entries from the API.
 * @return {Array} One entry per day, each with its own entries.
 */
function byDay( entries ) {
	const days = [];

	entries.forEach( ( entry ) => {
		const day = String( entry.date || '' ).slice( 0, 10 );
		const last = days[ days.length - 1 ];

		if ( last && last.day === day ) {
			last.entries.push( entry );
			return;
		}

		days.push( { day, entries: [ entry ] } );
	} );

	return days;
}

export function Detail( { id, onBack, onFail } ) {
	const [ data, setData ] = useState( null );
	const [ activity, setActivity ] = useState( [] );
	const [ panels, setPanels ] = useState( '' );
	const [ dates, setDates ] = useState( { next_payment: '', end_date: '' } );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ missing, setMissing ] = useState( false );

	const load = useCallback( () => {
		setBusy( true );

		return Promise.all( [
			apiFetch( { path: `/easysubscription/v1/subscriptions/${ id }` } ),
			apiFetch( {
				path: `/easysubscription/v1/subscriptions/${ id }/activity`,
			} ).catch( () => [] ),
			apiFetch( {
				path: `/easysubscription/v1/subscriptions/${ id }/panels`,
			} ).catch( () => ( {} ) ),
		] )
			.then( ( [ subscription, entries, extra ] ) => {
				setData( subscription );
				setActivity( entries );
				setPanels( extra?.html || '' );
				setDates( {
					next_payment: ( subscription.next_payment || '' )
						.replace( ' ', 'T' )
						.slice( 0, 16 ),
					end_date: ( subscription.end_date || '' )
						.replace( ' ', 'T' )
						.slice( 0, 16 ),
				} );
				setBusy( false );
			} )
			.catch( ( error ) => {
				setBusy( false );

				setData( ( current ) => {
					if ( current ) {
						// Already on screen: say the reload failed, keep what is there.
						setNotice( {
							ok: false,
							message: __(
								'That could not be reloaded.',
								'easysubscription'
							),
						} );
					} else if ( 'easysubscription_not_found' === error?.code ) {
						setMissing( true );
					} else {
						onFail();
					}

					return current;
				} );
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ id ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const act = ( action, question ) => {
		// eslint-disable-next-line no-alert
		if ( question && ! window.confirm( question ) ) {
			return;
		}

		setBusy( true );
		setNotice( null );

		apiFetch( {
			path: `/easysubscription/v1/subscriptions/${ id }/actions`,
			method: 'POST',
			data: { action },
		} )
			.then( () => {
				setNotice( {
					ok: true,
					message: __( 'Done.', 'easysubscription' ),
				} );

				return load();
			} )
			.catch( ( error ) => {
				setBusy( false );
				setNotice( {
					ok: false,
					message:
						error?.message ||
						__( 'That did not work.', 'easysubscription' ),
				} );
			} );
	};

	const saveDates = () => {
		setBusy( true );
		setNotice( null );

		apiFetch( {
			path: `/easysubscription/v1/subscriptions/${ id }`,
			method: 'POST',
			data: {
				next_payment: dates.next_payment
					? dates.next_payment.replace( 'T', ' ' )
					: undefined,
				end_date: dates.end_date
					? dates.end_date.replace( 'T', ' ' )
					: undefined,
			},
		} )
			.then( () => {
				setNotice( {
					ok: true,
					message: __(
						'The schedule was changed.',
						'easysubscription'
					),
				} );

				return load();
			} )
			.catch( ( error ) => {
				setBusy( false );
				setNotice( {
					ok: false,
					message:
						error?.message ||
						__(
							'That date could not be read.',
							'easysubscription'
						),
				} );
			} );
	};

	if ( missing ) {
		return (
			<Card>
				<CardContent className="es-p-6">
					<p>
						{ __(
							'No subscription with that id.',
							'easysubscription'
						) }
					</p>
				</CardContent>
			</Card>
		);
	}

	if ( ! data ) {
		return <Skeleton className="es-my-4 es-h-64" />;
	}

	let nextMeta = __( 'Nothing scheduled', 'easysubscription' );

	// The date only moves once the provider confirms, so "overdue" would mislead.
	if ( data.payment_pending ) {
		nextMeta = __( 'Waiting for the payment', 'easysubscription' );
	} else if ( data.next_payment ) {
		nextMeta = whenDue( data.next_payment );
	}

	return (
		<div className={ busy ? 'es-opacity-60 es-transition-opacity' : '' }>
			<div className="es-mb-4 es-flex es-flex-wrap es-items-start es-gap-3">
				<div>
					<div className="es-flex es-flex-wrap es-items-center es-gap-2">
						<h2 className="es-m-0 es-text-xl es-font-semibold">
							{ sprintf(
								/* translators: %d: subscription id. */
								__( 'Subscription #%d', 'easysubscription' ),
								data.id
							) }
						</h2>
						<Badge variant={ statusVariant( data.status ) }>
							{ data.status_label }
						</Badge>
					</div>
					<p className="es-mt-1 es-text-sm es-text-muted-foreground">
						{ data.customer_name || data.customer_email || '—' }
						{ data.customer_name && data.customer_email
							? ` · ${ data.customer_email }`
							: '' }
					</p>
				</div>

				{ onBack ? (
					<Button
						variant="ghost"
						size="sm"
						onClick={ onBack }
						className="es-ml-auto"
					>
						{ __( '← All subscriptions', 'easysubscription' ) }
					</Button>
				) : null }
			</div>

			{ notice ? (
				<div
					className={ `es-mb-4 es-rounded-lg es-border es-p-3 es-text-sm ${
						notice.ok
							? 'es-border-success es-text-success'
							: 'es-border-destructive es-text-destructive'
					}` }
					role="status"
				>
					{ notice.message }
				</div>
			) : null }

			{ data.payment_pending ? (
				<div
					className="es-mb-4 es-rounded-lg es-border es-p-3 es-text-sm"
					role="status"
				>
					<strong>
						{ __( 'Payment processing', 'easysubscription' ) }
					</strong>{ ' ' }
					{ sprintf(
						/* translators: 1: renewal order number, 2: how long ago, such as "3 days ago". */
						__(
							'Renewal order #%1$s was submitted %2$s and is waiting for the payment provider to confirm it.',
							'easysubscription'
						),
						data.payment_pending_order.number,
						daysAgo( data.payment_pending_since )
					) }{ ' ' }
					<a href={ data.payment_pending_order.url }>
						{ __( 'View order', 'easysubscription' ) }
					</a>
				</div>
			) : null }

			<div className="es-mb-4 es-grid es-gap-4 es-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
				<Stat
					label={ __( 'Recurring total', 'easysubscription' ) }
					value={ data.total_formatted }
					meta={ describeSchedule( data ) }
				/>
				<Stat
					label={ __( 'Next payment', 'easysubscription' ) }
					value={ data.next_payment_formatted || '—' }
					meta={ nextMeta }
				/>
				<Stat
					label={ __( 'Payments made', 'easysubscription' ) }
					value={ String( Number( data.period_index || 0 ) + 1 ) }
					meta={
						formatDay( data.date_created )
							? sprintf(
									/* translators: %s: date the subscription started. */
									__( 'since %s', 'easysubscription' ),
									formatDay( data.date_created )
							  )
							: ''
					}
				/>
			</div>

			<div className="es-grid es-gap-4 es-grid-cols-[repeat(auto-fit,minmax(320px,1fr))]">
				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'Details', 'easysubscription' ) }
						</CardTitle>
					</CardHeader>
					<CardContent className="es-text-sm">
						<Fact label={ __( 'Customer', 'easysubscription' ) }>
							{ data.customer_name || data.customer_email || '—' }
						</Fact>
						<Fact label={ __( 'Email', 'easysubscription' ) }>
							{ data.customer_email || '—' }
						</Fact>
						<Fact
							label={ __( 'Payment method', 'easysubscription' ) }
						>
							{ data.payment_method_title ||
								data.payment_method ||
								'—' }
						</Fact>
						<Fact label={ __( 'Trial ends', 'easysubscription' ) }>
							{ formatDay( data.trial_end ) || '—' }
						</Fact>
						<Fact label={ __( 'Ends', 'easysubscription' ) }>
							{ formatDay( data.end_date ) ||
								__( 'Not set', 'easysubscription' ) }
						</Fact>
						<Fact
							label={ __( 'Parent order', 'easysubscription' ) }
						>
							{ data.parent_order_id
								? `#${ data.parent_order_id }`
								: '—' }
						</Fact>
					</CardContent>
				</Card>

				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'Things you can do', 'easysubscription' ) }
						</CardTitle>
					</CardHeader>
					<CardContent className="es-flex es-flex-col es-gap-5">
						<div className="es-flex es-flex-wrap es-items-center es-gap-2">
							{ data.billable && ! data.payment_pending ? (
								<Button
									disabled={ busy }
									onClick={ () =>
										act(
											'renew_now',
											sprintf(
												/* translators: %s: recurring total. */
												__(
													'This charges the customer %s right now. Continue?',
													'easysubscription'
												),
												data.total_formatted
											)
										)
									}
								>
									{ __( 'Renew now', 'easysubscription' ) }
								</Button>
							) : null }
							<Button
								variant="outline"
								disabled={ busy }
								onClick={ () => act( 'reactivate' ) }
							>
								{ __( 'Reactivate', 'easysubscription' ) }
							</Button>
						</div>

						{ data.billable && ! data.payment_pending ? (
							<p className="es-m-0 es-text-xs es-text-muted-foreground">
								{ __(
									'Charging now takes the next payment immediately and moves the schedule on. It does not skip the queue for a failed one - the activity log below says what happened last.',
									'easysubscription'
								) }
							</p>
						) : null }

						<div className="es-flex es-flex-col es-gap-2 es-border-t es-pt-4">
							<h4 className="es-m-0 es-text-sm es-font-medium">
								{ __(
									'Change the schedule',
									'easysubscription'
								) }
							</h4>
							<p className="es-m-0 es-mb-1 es-text-xs es-text-muted-foreground">
								{ __(
									'Moving the next payment changes when the customer is next charged; nothing is charged by saving. An end date stops renewals on or after it.',
									'easysubscription'
								) }
							</p>

							<label
								className="es-text-xs es-text-muted-foreground"
								htmlFor="easysubscription-next-payment"
							>
								{ __( 'Next payment', 'easysubscription' ) }
							</label>
							<Input
								id="easysubscription-next-payment"
								type="datetime-local"
								value={ dates.next_payment }
								onChange={ ( event ) =>
									setDates( {
										...dates,
										next_payment: event.target.value,
									} )
								}
							/>

							<label
								className="es-text-xs es-text-muted-foreground"
								htmlFor="easysubscription-end-date"
							>
								{ __( 'Ends', 'easysubscription' ) }
							</label>
							<Input
								id="easysubscription-end-date"
								type="datetime-local"
								value={ dates.end_date }
								onChange={ ( event ) =>
									setDates( {
										...dates,
										end_date: event.target.value,
									} )
								}
							/>

							<Button
								variant="secondary"
								size="sm"
								disabled={ busy }
								onClick={ saveDates }
								className="es-mt-1 es-self-start"
							>
								{ __(
									'Save the schedule',
									'easysubscription'
								) }
							</Button>
						</div>

						<div className="es-flex es-flex-col es-items-start es-gap-2 es-border-t es-pt-4">
							<h4 className="es-m-0 es-text-sm es-font-medium">
								{ __(
									'End this subscription',
									'easysubscription'
								) }
							</h4>
							<p className="es-m-0 es-text-xs es-text-muted-foreground">
								{ __(
									'Billing stops and the customer keeps what the last payment covered. This cannot be undone.',
									'easysubscription'
								) }
							</p>
							<Button
								variant="destructive"
								size="sm"
								disabled={ busy }
								onClick={ () =>
									act(
										'cancel',
										__(
											'Cancel this subscription? It cannot be reactivated afterwards.',
											'easysubscription'
										)
									)
								}
							>
								{ __(
									'Cancel subscription',
									'easysubscription'
								) }
							</Button>
						</div>
					</CardContent>
				</Card>
			</div>

			<Card className="es-mt-4">
				<CardHeader>
					<CardTitle>
						{ __( 'Activity', 'easysubscription' ) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					{ activity.length ? (
						<div className="es-flex es-flex-col es-gap-5">
							{ byDay( activity ).map( ( group ) => (
								<div key={ group.day }>
									<h4 className="es-m-0 es-mb-2 es-text-xs es-font-semibold es-uppercase es-tracking-wide es-text-muted-foreground">
										{ formatDay( group.day ) || group.day }
									</h4>
									<ol className="es-m-0 es-flex es-list-none es-flex-col es-gap-2 es-p-0 es-text-sm">
										{ group.entries.map(
											( entry, index ) => (
												<li
													key={ `${ entry.date }-${ index }` }
													className="es-flex es-gap-3"
												>
													<span className="es-w-20 es-shrink-0 es-whitespace-nowrap es-tabular-nums es-text-muted-foreground">
														{ formatTime(
															entry.date
														) }
													</span>
													<span className="es-w-20 es-shrink-0">
														<Badge variant="secondary">
															{ entryLabel(
																entry.type
															) }
														</Badge>
													</span>
													<span className="es-min-w-0">
														{ entry.message }
														{ entry.actor ? (
															<span className="es-text-muted-foreground">
																{ ' · ' }
																{ entry.actor }
															</span>
														) : null }
													</span>
												</li>
											)
										) }
									</ol>
								</div>
							) ) }
						</div>
					) : (
						<p className="es-m-0 es-text-sm es-text-muted-foreground">
							{ __(
								'Nothing has happened to this subscription yet. Charges, status changes and schedule edits are all recorded here.',
								'easysubscription'
							) }
						</p>
					) }
				</CardContent>
			</Card>

			{ panels ? (
				<div
					className="es-mt-4"
					// Drawn by other plugins' PHP on easysubscription_admin_subscription_detail, as the server screen draws it.
					dangerouslySetInnerHTML={ { __html: panels } }
				/>
			) : null }
		</div>
	);
}
