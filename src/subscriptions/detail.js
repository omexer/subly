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
} from '@subkit/ui';
import {
	describeSchedule,
	formatDay,
	formatTime,
	statusVariant,
	whenDue,
} from './format';

function Fact( { label, children } ) {
	return (
		<div className="sk-flex sk-justify-between sk-gap-4 sk-border-b sk-border-border sk-py-2.5 last:sk-border-0">
			<span className="sk-text-muted-foreground">{ label }</span>
			<span className="sk-text-right">{ children }</span>
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
			return __( 'Status', 'subkit-subscriptions' );
		case 'charge_attempt':
			return __( 'Charge', 'subkit-subscriptions' );
		case 'schedule_change':
			return __( 'Schedule', 'subkit-subscriptions' );
		default:
			return __( 'Note', 'subkit-subscriptions' );
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

export function Detail( { id, onBack, onReady, onFail } ) {
	const [ data, setData ] = useState( null );
	const [ activity, setActivity ] = useState( [] );
	const [ dates, setDates ] = useState( { next_payment: '', end_date: '' } );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ missing, setMissing ] = useState( false );

	const load = useCallback( () => {
		setBusy( true );

		return Promise.all( [
			apiFetch( { path: `/subkit/v1/subscriptions/${ id }` } ),
			apiFetch( {
				path: `/subkit/v1/subscriptions/${ id }/activity`,
			} ).catch( () => [] ),
		] )
			.then( ( [ subscription, entries ] ) => {
				setData( subscription );
				setActivity( entries );
				setDates( {
					next_payment: ( subscription.next_payment || '' )
						.replace( ' ', 'T' )
						.slice( 0, 16 ),
					end_date: ( subscription.end_date || '' )
						.replace( ' ', 'T' )
						.slice( 0, 16 ),
				} );
				setBusy( false );
				onReady();
			} )
			.catch( () => {
				setBusy( false );

				setData( ( current ) => {
					if ( current ) {
						// Already on screen: say the reload failed, keep what is there.
						setNotice( {
							ok: false,
							message: __(
								'That could not be reloaded.',
								'subkit-subscriptions'
							),
						} );
					} else {
						setMissing( true );
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
			path: `/subkit/v1/subscriptions/${ id }/actions`,
			method: 'POST',
			data: { action },
		} )
			.then( () => {
				setNotice( {
					ok: true,
					message: __( 'Done.', 'subkit-subscriptions' ),
				} );

				return load();
			} )
			.catch( ( error ) => {
				setBusy( false );
				setNotice( {
					ok: false,
					message:
						error?.message ||
						__( 'That did not work.', 'subkit-subscriptions' ),
				} );
			} );
	};

	const saveDates = () => {
		setBusy( true );
		setNotice( null );

		apiFetch( {
			path: `/subkit/v1/subscriptions/${ id }`,
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
						'subkit-subscriptions'
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
							'subkit-subscriptions'
						),
				} );
			} );
	};

	if ( missing ) {
		return (
			<Card>
				<CardContent className="sk-p-6">
					<p>
						{ __(
							'No subscription with that id.',
							'subkit-subscriptions'
						) }
					</p>
				</CardContent>
			</Card>
		);
	}

	if ( ! data ) {
		return <Skeleton className="sk-my-4 sk-h-64" />;
	}

	return (
		<div className={ busy ? 'sk-opacity-60 sk-transition-opacity' : '' }>
			<div className="sk-mb-4 sk-flex sk-flex-wrap sk-items-start sk-gap-3">
				<div>
					<div className="sk-flex sk-flex-wrap sk-items-center sk-gap-2">
						<h2 className="sk-m-0 sk-text-xl sk-font-semibold">
							{ sprintf(
								/* translators: %d: subscription id. */
								__(
									'Subscription #%d',
									'subkit-subscriptions'
								),
								data.id
							) }
						</h2>
						<Badge variant={ statusVariant( data.status ) }>
							{ data.status_label }
						</Badge>
					</div>
					<p className="sk-mt-1 sk-text-sm sk-text-muted-foreground">
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
						className="sk-ml-auto"
					>
						{ __( '← All subscriptions', 'subkit-subscriptions' ) }
					</Button>
				) : null }
			</div>

			{ notice ? (
				<div
					className={ `sk-mb-4 sk-rounded-lg sk-border sk-p-3 sk-text-sm ${
						notice.ok
							? 'sk-border-success sk-text-success'
							: 'sk-border-destructive sk-text-destructive'
					}` }
					role="status"
				>
					{ notice.message }
				</div>
			) : null }

			<div className="sk-mb-4 sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
				<Stat
					label={ __( 'Recurring total', 'subkit-subscriptions' ) }
					value={ data.total_formatted }
					meta={ describeSchedule( data ) }
				/>
				<Stat
					label={ __( 'Next payment', 'subkit-subscriptions' ) }
					value={ data.next_payment_formatted || '—' }
					meta={
						data.next_payment
							? whenDue( data.next_payment )
							: __( 'Nothing scheduled', 'subkit-subscriptions' )
					}
				/>
				<Stat
					label={ __( 'Payments made', 'subkit-subscriptions' ) }
					value={ String( Number( data.period_index || 0 ) + 1 ) }
					meta={
						formatDay( data.date_created )
							? sprintf(
									/* translators: %s: date the subscription started. */
									__( 'since %s', 'subkit-subscriptions' ),
									formatDay( data.date_created )
							  )
							: ''
					}
				/>
			</div>

			<div className="sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(320px,1fr))]">
				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'Details', 'subkit-subscriptions' ) }
						</CardTitle>
					</CardHeader>
					<CardContent className="sk-text-sm">
						<Fact
							label={ __( 'Customer', 'subkit-subscriptions' ) }
						>
							{ data.customer_name || data.customer_email || '—' }
						</Fact>
						<Fact label={ __( 'Email', 'subkit-subscriptions' ) }>
							{ data.customer_email || '—' }
						</Fact>
						<Fact
							label={ __(
								'Payment method',
								'subkit-subscriptions'
							) }
						>
							{ data.payment_method_title ||
								data.payment_method ||
								'—' }
						</Fact>
						<Fact
							label={ __( 'Trial ends', 'subkit-subscriptions' ) }
						>
							{ formatDay( data.trial_end ) || '—' }
						</Fact>
						<Fact label={ __( 'Ends', 'subkit-subscriptions' ) }>
							{ formatDay( data.end_date ) ||
								__( 'Not set', 'subkit-subscriptions' ) }
						</Fact>
						<Fact
							label={ __(
								'Parent order',
								'subkit-subscriptions'
							) }
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
							{ __(
								'Things you can do',
								'subkit-subscriptions'
							) }
						</CardTitle>
					</CardHeader>
					<CardContent className="sk-flex sk-flex-col sk-gap-5">
						<div className="sk-flex sk-flex-wrap sk-items-center sk-gap-2">
							{ data.billable ? (
								<Button
									disabled={ busy }
									onClick={ () =>
										act(
											'renew_now',
											sprintf(
												/* translators: %s: recurring total. */
												__(
													'This charges the customer %s right now. Continue?',
													'subkit-subscriptions'
												),
												data.total_formatted
											)
										)
									}
								>
									{ __(
										'Renew now',
										'subkit-subscriptions'
									) }
								</Button>
							) : null }
							<Button
								variant="outline"
								disabled={ busy }
								onClick={ () => act( 'reactivate' ) }
							>
								{ __( 'Reactivate', 'subkit-subscriptions' ) }
							</Button>
						</div>

						{ data.billable ? (
							<p className="sk-m-0 sk-text-xs sk-text-muted-foreground">
								{ __(
									'Charging now takes the next payment immediately and moves the schedule on. It does not skip the queue for a failed one - the activity log below says what happened last.',
									'subkit-subscriptions'
								) }
							</p>
						) : null }

						<div className="sk-flex sk-flex-col sk-gap-2 sk-border-t sk-pt-4">
							<h4 className="sk-m-0 sk-text-sm sk-font-medium">
								{ __(
									'Change the schedule',
									'subkit-subscriptions'
								) }
							</h4>
							<p className="sk-m-0 sk-mb-1 sk-text-xs sk-text-muted-foreground">
								{ __(
									'Moving the next payment changes when the customer is next charged; nothing is charged by saving. An end date stops renewals on or after it.',
									'subkit-subscriptions'
								) }
							</p>

							<label
								className="sk-text-xs sk-text-muted-foreground"
								htmlFor="subkit-next-payment"
							>
								{ __( 'Next payment', 'subkit-subscriptions' ) }
							</label>
							<Input
								id="subkit-next-payment"
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
								className="sk-text-xs sk-text-muted-foreground"
								htmlFor="subkit-end-date"
							>
								{ __( 'Ends', 'subkit-subscriptions' ) }
							</label>
							<Input
								id="subkit-end-date"
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
								className="sk-mt-1 sk-self-start"
							>
								{ __(
									'Save the schedule',
									'subkit-subscriptions'
								) }
							</Button>
						</div>

						<div className="sk-flex sk-flex-col sk-items-start sk-gap-2 sk-border-t sk-pt-4">
							<h4 className="sk-m-0 sk-text-sm sk-font-medium">
								{ __(
									'End this subscription',
									'subkit-subscriptions'
								) }
							</h4>
							<p className="sk-m-0 sk-text-xs sk-text-muted-foreground">
								{ __(
									'Billing stops and the customer keeps what the last payment covered. This cannot be undone.',
									'subkit-subscriptions'
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
											'subkit-subscriptions'
										)
									)
								}
							>
								{ __(
									'Cancel subscription',
									'subkit-subscriptions'
								) }
							</Button>
						</div>
					</CardContent>
				</Card>
			</div>

			<Card className="sk-mt-4">
				<CardHeader>
					<CardTitle>
						{ __( 'Activity', 'subkit-subscriptions' ) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					{ activity.length ? (
						<div className="sk-flex sk-flex-col sk-gap-5">
							{ byDay( activity ).map( ( group ) => (
								<div key={ group.day }>
									<h4 className="sk-m-0 sk-mb-2 sk-text-xs sk-font-semibold sk-uppercase sk-tracking-wide sk-text-muted-foreground">
										{ formatDay( group.day ) || group.day }
									</h4>
									<ol className="sk-m-0 sk-flex sk-list-none sk-flex-col sk-gap-2 sk-p-0 sk-text-sm">
										{ group.entries.map(
											( entry, index ) => (
												<li
													key={ `${ entry.date }-${ index }` }
													className="sk-flex sk-gap-3"
												>
													<span className="sk-w-20 sk-shrink-0 sk-whitespace-nowrap sk-tabular-nums sk-text-muted-foreground">
														{ formatTime(
															entry.date
														) }
													</span>
													<span className="sk-w-20 sk-shrink-0">
														<Badge variant="secondary">
															{ entryLabel(
																entry.type
															) }
														</Badge>
													</span>
													<span className="sk-min-w-0">
														{ entry.message }
														{ entry.actor ? (
															<span className="sk-text-muted-foreground">
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
						<p className="sk-m-0 sk-text-sm sk-text-muted-foreground">
							{ __(
								'Nothing has happened to this subscription yet. Charges, status changes and schedule edits are all recorded here.',
								'subkit-subscriptions'
							) }
						</p>
					) }
				</CardContent>
			</Card>
		</div>
	);
}
