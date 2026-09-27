/**
 * The subscriptions list.
 *
 * Everything the WP_List_Table did: status tabs with counts, search, sorting, per-row
 * actions, bulk actions and pagination - without a page load between any of them.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Badge,
	Button,
	Card,
	CardContent,
	Checkbox,
	Input,
	Select,
	Skeleton,
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '@easysubscription/ui';
import { daysAgo, describeSchedule, statusVariant, whenDue } from './format';

const PER_PAGE = 20;

const BULK = [
	{ key: 'cancel', label: __( 'Cancel', 'easysubscription' ) },
	{
		key: 'change_status',
		status: 'es-on-hold',
		label: __( 'Put on hold', 'easysubscription' ),
	},
	{ key: 'reactivate', label: __( 'Reactivate', 'easysubscription' ) },
];

const SORTABLE = {
	id: 'ID',
	next_payment: 'next_payment',
	total: 'total',
};

/**
 * Nothing to show is two different situations, and the way out of each differs.
 * @param {Object}   props          Component props.
 * @param {boolean}  props.filtered Whether a status or search is narrowing the list.
 * @param {Function} props.onClear  Clears them.
 * @return {JSX.Element} The empty state.
 */
function Empty( { filtered, onClear } ) {
	return (
		<div className="es-flex es-flex-col es-items-center es-gap-2 es-p-10 es-text-center">
			<p className="es-m-0 es-font-medium">
				{ filtered
					? __( 'No subscriptions match that', 'easysubscription' )
					: __( 'No subscriptions yet', 'easysubscription' ) }
			</p>
			<p className="es-m-0 es-max-w-md es-text-sm es-text-muted-foreground">
				{ filtered
					? __(
							'Try a different status, or clear the search.',
							'easysubscription'
					  )
					: __(
							'One appears here the moment somebody buys a subscription product. Nothing is charged until a gateway is connected.',
							'easysubscription'
					  ) }
			</p>
			{ filtered ? (
				<Button variant="outline" size="sm" onClick={ onClear }>
					{ __( 'Clear filters', 'easysubscription' ) }
				</Button>
			) : (
				<Button
					variant="outline"
					size="sm"
					onClick={ () => {
						window.location.href = new URL(
							'post-new.php?post_type=product',
							window.location.href
						).href;
					} }
				>
					{ __( 'New subscription product', 'easysubscription' ) }
				</Button>
			) }
		</div>
	);
}

function SortHeader( { column, label, sort, onSort, className } ) {
	const active = sort.orderby === SORTABLE[ column ];
	const next = active && 'ASC' === sort.order ? 'DESC' : 'ASC';

	return (
		<TableHead className={ className }>
			<button
				type="button"
				onClick={ () =>
					onSort( { orderby: SORTABLE[ column ], order: next } )
				}
				className="es-inline-flex es-items-center es-gap-1 es-font-medium hover:es-text-foreground"
				aria-label={ sprintf(
					/* translators: %s: column name. */
					__( 'Sort by %s', 'easysubscription' ),
					label
				) }
			>
				{ label }
				<span
					aria-hidden="true"
					className={ active ? '' : 'es-opacity-30' }
				>
					{ active && 'ASC' === sort.order ? '▲' : '▼' }
				</span>
			</button>
		</TableHead>
	);
}

export function List( {
	initialStatus = '',
	initialSearch = '',
	onOpen,
	onFail,
} ) {
	const [ rows, setRows ] = useState( null );
	const [ statuses, setStatuses ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ query, setQuery ] = useState( {
		status: initialStatus,
		search: initialSearch,
		page: 1,
		orderby: 'date',
		order: 'DESC',
	} );
	const [ chosen, setChosen ] = useState( [] );
	const [ bulk, setBulk ] = useState( BULK[ 0 ].label );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const load = useCallback( ( next ) => {
		const params = new URLSearchParams( {
			page: String( next.page ),
			per_page: String( PER_PAGE ),
			orderby: next.orderby,
			order: next.order,
		} );

		if ( next.status ) {
			params.set( 'status', next.status );
		}

		if ( next.search ) {
			params.set( 'search', next.search );
		}

		setBusy( true );

		return apiFetch( {
			path: `/easysubscription/v1/subscriptions?${ params.toString() }`,
			parse: false,
		} )
			.then( ( response ) =>
				response.json().then( ( items ) => {
					setRows( items );
					setTotal(
						Number(
							response.headers.get( 'X-WP-Total' ) || items.length
						)
					);
					setBusy( false );
				} )
			)
			.catch( () => {
				setBusy( false );

				// Only the first load fails the screen: after that there are rows worth keeping.
				setRows( ( current ) => {
					if ( ! current ) {
						onFail();
					}

					return current;
				} );
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const refreshStatuses = useCallback(
		() =>
			apiFetch( { path: '/easysubscription/v1/subscriptions/statuses' } )
				.then( setStatuses )
				.catch( () => {} ),
		[]
	);

	useEffect( () => {
		const timer = setTimeout( () => load( query ), query.search ? 300 : 0 );

		return () => clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ query.status, query.search, query.page, query.orderby, query.order ] );

	useEffect( () => {
		refreshStatuses();
	}, [ refreshStatuses ] );

	const set = ( changes ) => {
		setChosen( [] );
		setQuery( ( current ) => ( { ...current, ...changes } ) );
	};

	const runBulk = () => {
		const chosenAction = BULK.find( ( item ) => item.label === bulk );

		if ( ! chosenAction || ! chosen.length ) {
			return;
		}

		setBusy( true );
		setNotice( null );

		apiFetch( {
			path: '/easysubscription/v1/subscriptions/actions',
			method: 'POST',
			data: {
				ids: chosen,
				action: chosenAction.key,
				status: chosenAction.status,
			},
		} )
			.then( ( result ) => {
				const changed = result.changed?.length || 0;
				const held = Object.keys( result.held || {} ).length;

				setNotice( {
					ok: changed > 0,
					message:
						sprintf(
							/* translators: %d: how many subscriptions changed. */
							_n(
								'%d subscription updated.',
								'%d subscriptions updated.',
								changed,
								'easysubscription'
							),
							changed
						) +
						( held
							? ' ' +
							  sprintf(
									/* translators: %d: how many were left alone. */
									_n(
										'%d was left alone because that change is not allowed from its current status.',
										'%d were left alone because that change is not allowed from their current status.',
										held,
										'easysubscription'
									),
									held
							  )
							: '' ),
				} );

				setChosen( [] );
				refreshStatuses();

				return load( query );
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

	const renew = ( row ) => {
		// This charges a real customer, so it names the amount and asks first.
		const question = sprintf(
			/* translators: %s: recurring total. */
			__(
				'This charges the customer %s right now. Continue?',
				'easysubscription'
			),
			row.total_formatted
		);

		// eslint-disable-next-line no-alert
		if ( ! window.confirm( question ) ) {
			return;
		}

		setBusy( true );
		setNotice( null );

		apiFetch( {
			path: `/easysubscription/v1/subscriptions/${ row.id }/actions`,
			method: 'POST',
			data: { action: 'renew_now' },
		} )
			.then( () => {
				setNotice( {
					ok: true,
					message: __(
						'The renewal was put through. The activity log says what happened.',
						'easysubscription'
					),
				} );

				return load( query );
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

	if ( ! rows ) {
		return <Skeleton className="es-my-4 es-h-64" />;
	}

	const pages = Math.max( 1, Math.ceil( total / PER_PAGE ) );
	const allChosen = rows.length > 0 && chosen.length === rows.length;
	const filtered = '' !== query.status || '' !== query.search;

	const tab = ( key, label, count ) => (
		<button
			key={ key || 'all' }
			type="button"
			aria-pressed={ query.status === key }
			onClick={ () => set( { status: key, page: 1 } ) }
			className={ `es-rounded-md es-px-2.5 es-py-1 es-text-xs es-font-medium ${
				query.status === key
					? 'es-bg-primary es-text-primary-foreground'
					: 'es-text-muted-foreground hover:es-bg-accent hover:es-text-foreground'
			}` }
		>
			{ label }
			{ undefined === count ? null : (
				<span className="es-ml-1 es-opacity-70">{ count }</span>
			) }
		</button>
	);

	return (
		<div className={ busy ? 'es-opacity-60 es-transition-opacity' : '' }>
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

			<Card>
				<div className="es-flex es-flex-wrap es-items-center es-gap-2 es-border-b es-p-3">
					<div className="es-flex es-flex-wrap es-items-center es-gap-1">
						{ tab(
							'',
							__( 'All', 'easysubscription' ),
							total && ! filtered ? total : undefined
						) }
						{ statuses
							.filter( ( status ) => status.count > 0 )
							.map( ( status ) =>
								tab( status.key, status.label, status.count )
							) }
					</div>

					<Input
						type="search"
						value={ query.search }
						placeholder={ __(
							'Search name, email or id',
							'easysubscription'
						) }
						onChange={ ( event ) =>
							set( { search: event.target.value, page: 1 } )
						}
						className="es-ml-auto es-w-64"
					/>
				</div>

				{ chosen.length ? (
					<div className="es-flex es-flex-wrap es-items-center es-gap-2 es-border-b es-bg-secondary es-px-3 es-py-2">
						<span className="es-text-sm es-font-medium">
							{ sprintf(
								/* translators: %d: how many rows are selected. */
								_n(
									'%d selected',
									'%d selected',
									chosen.length,
									'easysubscription'
								),
								chosen.length
							) }
						</span>
						<Select
							value={ bulk }
							onChange={ ( event ) =>
								setBulk( event.target.value )
							}
							aria-label={ __(
								'Bulk action',
								'easysubscription'
							) }
							className="es-h-8"
						>
							{ BULK.map( ( item ) => (
								<option key={ item.label } value={ item.label }>
									{ item.label }
								</option>
							) ) }
						</Select>
						<Button size="sm" disabled={ busy } onClick={ runBulk }>
							{ __( 'Apply', 'easysubscription' ) }
						</Button>
						<Button
							variant="ghost"
							size="sm"
							onClick={ () => setChosen( [] ) }
						>
							{ __( 'Clear', 'easysubscription' ) }
						</Button>
					</div>
				) : null }

				<CardContent className="es-p-0">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead className="es-w-10">
									<Checkbox
										checked={ allChosen }
										aria-label={ __(
											'Select all',
											'easysubscription'
										) }
										onChange={ ( event ) =>
											setChosen(
												event.target.checked
													? rows.map(
															( row ) => row.id
													  )
													: []
											)
										}
									/>
								</TableHead>
								<TableHead>
									{ __( 'Customer', 'easysubscription' ) }
								</TableHead>
								<SortHeader
									column="id"
									label={ __(
										'Subscription',
										'easysubscription'
									) }
									sort={ query }
									onSort={ set }
								/>
								<TableHead>
									{ __( 'Status', 'easysubscription' ) }
								</TableHead>
								<SortHeader
									column="next_payment"
									label={ __(
										'Next payment',
										'easysubscription'
									) }
									sort={ query }
									onSort={ set }
								/>
								<SortHeader
									column="total"
									label={ __(
										'Recurring total',
										'easysubscription'
									) }
									sort={ query }
									onSort={ set }
									className="es-text-right"
								/>
								<TableHead className="es-text-right">
									{ __( 'Actions', 'easysubscription' ) }
								</TableHead>
							</TableRow>
						</TableHeader>

						<TableBody>
							{ rows.length ? (
								rows.map( ( row ) => (
									<TableRow
										key={ row.id }
										className="hover:es-bg-muted/40"
									>
										<TableCell>
											<Checkbox
												checked={ chosen.includes(
													row.id
												) }
												aria-label={ sprintf(
													/* translators: %d: subscription id. */
													__(
														'Select subscription %d',
														'easysubscription'
													),
													row.id
												) }
												onChange={ ( event ) =>
													setChosen( ( current ) =>
														event.target.checked
															? [
																	...current,
																	row.id,
															  ]
															: current.filter(
																	( id ) =>
																		id !==
																		row.id
															  )
													)
												}
											/>
										</TableCell>
										<TableCell>
											<a
												href={ row.edit_url }
												onClick={ ( event ) => {
													if ( onOpen ) {
														event.preventDefault();
														onOpen( row.id );
													}
												} }
												className="es-font-medium es-text-foreground hover:es-text-primary"
											>
												{ row.customer_name ||
													row.customer_email ||
													__(
														'Guest',
														'easysubscription'
													) }
											</a>
											{ row.customer_name &&
											row.customer_email ? (
												<div className="es-text-xs es-text-muted-foreground">
													{ row.customer_email }
												</div>
											) : null }
										</TableCell>
										<TableCell>
											<span className="es-tabular-nums es-text-muted-foreground">
												#{ row.id }
											</span>
											<div className="es-text-xs es-text-muted-foreground">
												{ describeSchedule( row ) }
											</div>
										</TableCell>
										<TableCell>
											<Badge
												variant={ statusVariant(
													row.status
												) }
											>
												{ row.status_label }
											</Badge>
											{ row.payment_pending ? (
												<div className="es-mt-1 es-text-xs es-text-muted-foreground">
													{ __(
														'Payment processing',
														'easysubscription'
													) }
													{ ' · ' }
													<a
														href={
															row
																.payment_pending_order
																.url
														}
													>
														{ sprintf(
															/* translators: %s: renewal order number. */
															__(
																'order #%s',
																'easysubscription'
															),
															row
																.payment_pending_order
																.number
														) }
													</a>
													{ ' · ' }
													{ sprintf(
														/* translators: %s: how long ago, such as "3 days ago". */
														__(
															'submitted %s',
															'easysubscription'
														),
														daysAgo(
															row.payment_pending_since
														)
													) }
												</div>
											) : null }
										</TableCell>
										<TableCell>
											{ row.next_payment_formatted ? (
												<>
													<div>
														{
															row.next_payment_formatted
														}
													</div>
													<div className="es-text-xs es-text-muted-foreground">
														{ row.payment_pending
															? __(
																	'waiting for the payment',
																	'easysubscription'
															  )
															: whenDue(
																	row.next_payment
															  ) }
													</div>
												</>
											) : (
												<span className="es-text-muted-foreground">
													—
												</span>
											) }
										</TableCell>
										<TableCell className="es-text-right es-font-medium es-tabular-nums">
											{ row.total_formatted }
											<div className="es-text-xs es-font-normal es-text-muted-foreground">
												{ row.payment_method_title ||
													row.payment_method ||
													'—' }
											</div>
										</TableCell>
										<TableCell className="es-text-right">
											{ row.billable &&
											! row.payment_pending ? (
												<Button
													variant="outline"
													size="sm"
													onClick={ () =>
														renew( row )
													}
												>
													{ __(
														'Renew now',
														'easysubscription'
													) }
												</Button>
											) : null }
										</TableCell>
									</TableRow>
								) )
							) : (
								<TableRow>
									<TableCell colSpan={ 7 } className="es-p-0">
										<Empty
											filtered={ filtered }
											onClear={ () =>
												set( {
													status: '',
													search: '',
													page: 1,
												} )
											}
										/>
									</TableCell>
								</TableRow>
							) }
						</TableBody>
					</Table>
				</CardContent>

				{ rows.length ? (
					<div className="es-flex es-flex-wrap es-items-center es-gap-3 es-border-t es-p-3 es-text-sm">
						<span className="es-text-muted-foreground">
							{ sprintf(
								/* translators: %d: how many subscriptions matched. */
								_n(
									'%d subscription',
									'%d subscriptions',
									total,
									'easysubscription'
								),
								total
							) }
						</span>
						{ pages > 1 ? (
							<div className="es-ml-auto es-flex es-items-center es-gap-3">
								<Button
									variant="outline"
									size="sm"
									disabled={ query.page <= 1 || busy }
									onClick={ () =>
										set( { page: query.page - 1 } )
									}
								>
									{ __( 'Previous', 'easysubscription' ) }
								</Button>
								<span className="es-text-muted-foreground">
									{ sprintf(
										/* translators: 1: current page, 2: total pages. */
										__(
											'Page %1$d of %2$d',
											'easysubscription'
										),
										query.page,
										pages
									) }
								</span>
								<Button
									variant="outline"
									size="sm"
									disabled={ query.page >= pages || busy }
									onClick={ () =>
										set( { page: query.page + 1 } )
									}
								>
									{ __( 'Next', 'easysubscription' ) }
								</Button>
							</div>
						) : null }
					</div>
				) : null }
			</Card>
		</div>
	);
}
