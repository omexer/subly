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
} from '@subkit/ui';

function Fact( { label, children } ) {
	return (
		<div className="sk-flex sk-justify-between sk-gap-4 sk-border-b sk-border-border sk-py-2.5 last:sk-border-0">
			<span className="sk-text-muted-foreground">{ label }</span>
			<span className="sk-text-right">{ children }</span>
		</div>
	);
}

function statusVariant( status ) {
	if ( 'sk-active' === status || 'sk-trialling' === status ) {
		return 'success';
	}

	if ( 'sk-on-hold' === status ) {
		return 'destructive';
	}

	return 'secondary';
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
			<div className="sk-mb-4 sk-flex sk-flex-wrap sk-items-center sk-gap-3">
				<h2 className="sk-text-xl sk-font-semibold">
					{ sprintf(
						/* translators: %d: subscription id. */
						__( 'Subscription #%d', 'subkit-subscriptions' ),
						data.id
					) }
				</h2>
				<Badge variant={ statusVariant( data.status ) }>
					{ data.status_label }
				</Badge>
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
					className={ `sk-mb-4 sk-rounded-md sk-border sk-p-3 sk-text-sm ${
						notice.ok
							? 'sk-border-success sk-text-success'
							: 'sk-border-destructive sk-text-destructive'
					}` }
					role="status"
				>
					{ notice.message }
				</div>
			) : null }

			<div className="sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(320px,1fr))]">
				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'The facts', 'subkit-subscriptions' ) }
						</CardTitle>
					</CardHeader>
					<CardContent className="sk-text-sm">
						<Fact
							label={ __( 'Customer', 'subkit-subscriptions' ) }
						>
							{ data.customer_name || data.customer_email || '—' }
						</Fact>
						<Fact
							label={ __(
								'Recurring total',
								'subkit-subscriptions'
							) }
						>
							{ data.total_formatted }
						</Fact>
						<Fact label={ __( 'Billing', 'subkit-subscriptions' ) }>
							{ sprintf(
								/* translators: 1: interval, 2: period. */
								__( 'every %1$d %2$s', 'subkit-subscriptions' ),
								data.billing_interval,
								data.billing_period
							) }
						</Fact>
						<Fact
							label={ __(
								'Next payment',
								'subkit-subscriptions'
							) }
						>
							{ data.next_payment_formatted || '—' }
						</Fact>
						<Fact
							label={ __( 'Trial ends', 'subkit-subscriptions' ) }
						>
							{ data.trial_end || '—' }
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
					<CardContent className="sk-flex sk-flex-col sk-gap-4">
						<div className="sk-flex sk-flex-wrap sk-gap-2">
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
										'Process renewal now',
										'subkit-subscriptions'
									) }
								</Button>
							) : null }
							<Button
								variant="outline"
								size="sm"
								disabled={ busy }
								onClick={ () => act( 'reactivate' ) }
							>
								{ __( 'Reactivate', 'subkit-subscriptions' ) }
							</Button>
							<Button
								variant="outline"
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
								{ __( 'Cancel', 'subkit-subscriptions' ) }
							</Button>
						</div>

						<div className="sk-flex sk-flex-col sk-gap-2">
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
								className="sk-self-start"
							>
								{ __(
									'Save the schedule',
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
						<ol className="sk-flex sk-flex-col sk-gap-3 sk-text-sm">
							{ activity.map( ( entry, index ) => (
								<li
									key={ `${ entry.date }-${ index }` }
									className="sk-flex sk-gap-3"
								>
									<span className="sk-w-40 sk-shrink-0 sk-text-muted-foreground">
										{ entry.date }
									</span>
									<span>
										{ entry.message }
										{ entry.actor ? (
											<span className="sk-text-muted-foreground">
												{ ' ' }
												· { entry.actor }
											</span>
										) : null }
									</span>
								</li>
							) ) }
						</ol>
					) : (
						<p className="sk-text-sm sk-text-muted-foreground">
							{ __(
								'Nothing has happened to this subscription yet.',
								'subkit-subscriptions'
							) }
						</p>
					) }
				</CardContent>
			</Card>
		</div>
	);
}
