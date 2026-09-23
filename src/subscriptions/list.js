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
} from '@subkit/ui';
import { describeSchedule, statusVariant, whenDue } from './format';

const PER_PAGE = 20;

const BULK = [
	{ key: 'cancel', label: __( 'Cancel', 'subkit-subscriptions' ) },
	{
		key: 'change_status',
		status: 'sk-on-hold',
		label: __( 'Put on hold', 'subkit-subscriptions' ),
	},
	{ key: 'reactivate', label: __( 'Reactivate', 'subkit-subscriptions' ) },
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
		<div className="sk-flex sk-flex-col sk-items-center sk-gap-2 sk-p-10 sk-text-center">
			<p className="sk-m-0 sk-font-medium">
				{ filtered
					? __(
							'No subscriptions match that',
							'subkit-subscriptions'
					  )
					: __( 'No subscriptions yet', 'subkit-subscriptions' ) }
			</p>
			<p className="sk-m-0 sk-max-w-md sk-text-sm sk-text-muted-foreground">
				{ filtered
					? __(
							'Try a different status, or clear the search.',
							'subkit-subscriptions'
					  )
					: __(
							'One appears here the moment somebody buys a subscription product. Nothing is charged until a gateway is connected.',
							'subkit-subscriptions'
					  ) }
			</p>
			{ filtered ? (
				<Button variant="outline" size="sm" onClick={ onClear }>
					{ __( 'Clear filters', 'subkit-subscriptions' ) }
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
					{ __( 'New subscription product', 'subkit-subscriptions' ) }
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
				className="sk-inline-flex sk-items-center sk-gap-1 sk-font-medium hover:sk-text-foreground"
				aria-label={ sprintf(
					/* translators: %s: column name. */
					__( 'Sort by %s', 'subkit-subscriptions' ),
					label
				) }
			>
				{ label }
				<span
					aria-hidden="true"
					className={ active ? '' : 'sk-opacity-30' }
				>
					{ active && 'ASC' === sort.order ? '▲' : '▼' }
				</span>
			</button>
		</TableHead>
	);
}

export function List( { onOpen, onReady, onFail } ) {
	const [ rows, setRows ] = useState( null );
	const [ statuses, setStatuses ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ query, setQuery ] = useState( {
		status: '',
		search: '',
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
			path: `/subkit/v1/subscriptions?${ params.toString() }`,
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
					onReady();
				} )
			)
			.catch( () => {
				setBusy( false );

				// Only the first load may tear this down: after that the server-rendered
				// list is already hidden, and removing ours would leave nothing at all.
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
			apiFetch( { path: '/subkit/v1/subscriptions/statuses' } )
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
			path: '/subkit/v1/subscriptions/actions',
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
								'subkit-subscriptions'
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
										'subkit-subscriptions'
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
						__( 'That did not work.', 'subkit-subscriptions' ),
				} );
			} );
	};

	const renew = ( row ) => {
		// This charges a real customer, so it names the amount and asks first.
		const question = sprintf(
			/* translators: %s: recurring total. */
			__(
				'This charges the customer %s right now. Continue?',
				'subkit-subscriptions'
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
			path: `/subkit/v1/subscriptions/${ row.id }/actions`,
			method: 'POST',
			data: { action: 'renew_now' },
		} )
			.then( () => {
				setNotice( {
					ok: true,
					message: __(
						'The renewal was put through. The activity log says what happened.',
						'subkit-subscriptions'
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
						__( 'That did not work.', 'subkit-subscriptions' ),
				} );
			} );
	};

	if ( ! rows ) {
		return <Skeleton className="sk-my-4 sk-h-64" />;
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
			className={ `sk-rounded-md sk-px-2.5 sk-py-1 sk-text-xs sk-font-medium ${
				query.status === key
					? 'sk-bg-primary sk-text-primary-foreground'
					: 'sk-text-muted-foreground hover:sk-bg-accent hover:sk-text-foreground'
			}` }
		>
			{ label }
			{ undefined === count ? null : (
				<span className="sk-ml-1 sk-opacity-70">{ count }</span>
			) }
		</button>
	);

	return (
		<div className={ busy ? 'sk-opacity-60 sk-transition-opacity' : '' }>
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

			<Card>
				<div className="sk-flex sk-flex-wrap sk-items-center sk-gap-2 sk-border-b sk-p-3">
					<div className="sk-flex sk-flex-wrap sk-items-center sk-gap-1">
						{ tab(
							'',
							__( 'All', 'subkit-subscriptions' ),
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
							'subkit-subscriptions'
						) }
						onChange={ ( event ) =>
							set( { search: event.target.value, page: 1 } )
						}
						className="sk-ml-auto sk-w-64"
					/>
				</div>

				{ chosen.length ? (
					<div className="sk-flex sk-flex-wrap sk-items-center sk-gap-2 sk-border-b sk-bg-secondary sk-px-3 sk-py-2">
						<span className="sk-text-sm sk-font-medium">
							{ sprintf(
								/* translators: %d: how many rows are selected. */
								_n(
									'%d selected',
									'%d selected',
									chosen.length,
									'subkit-subscriptions'
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
								'subkit-subscriptions'
							) }
							className="sk-h-8"
						>
							{ BULK.map( ( item ) => (
								<option key={ item.label } value={ item.label }>
									{ item.label }
								</option>
							) ) }
						</Select>
						<Button size="sm" disabled={ busy } onClick={ runBulk }>
							{ __( 'Apply', 'subkit-subscriptions' ) }
						</Button>
						<Button
							variant="ghost"
							size="sm"
							onClick={ () => setChosen( [] ) }
						>
							{ __( 'Clear', 'subkit-subscriptions' ) }
						</Button>
					</div>
				) : null }

				<CardContent className="sk-p-0">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead className="sk-w-10">
									<Checkbox
										checked={ allChosen }
										aria-label={ __(
											'Select all',
											'subkit-subscriptions'
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
									{ __( 'Customer', 'subkit-subscriptions' ) }
								</TableHead>
								<SortHeader
									column="id"
									label={ __(
										'Subscription',
										'subkit-subscriptions'
									) }
									sort={ query }
									onSort={ set }
								/>
								<TableHead>
									{ __( 'Status', 'subkit-subscriptions' ) }
								</TableHead>
								<SortHeader
									column="next_payment"
									label={ __(
										'Next payment',
										'subkit-subscriptions'
									) }
									sort={ query }
									onSort={ set }
								/>
								<SortHeader
									column="total"
									label={ __(
										'Recurring total',
										'subkit-subscriptions'
									) }
									sort={ query }
									onSort={ set }
									className="sk-text-right"
								/>
								<TableHead className="sk-text-right">
									{ __( 'Actions', 'subkit-subscriptions' ) }
								</TableHead>
							</TableRow>
						</TableHeader>

						<TableBody>
							{ rows.length ? (
								rows.map( ( row ) => (
									<TableRow
										key={ row.id }
										className="hover:sk-bg-muted/40"
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
														'subkit-subscriptions'
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
												className="sk-font-medium sk-text-foreground hover:sk-text-primary"
											>
												{ row.customer_name ||
													row.customer_email ||
													__(
														'Guest',
														'subkit-subscriptions'
													) }
											</a>
											{ row.customer_name &&
											row.customer_email ? (
												<div className="sk-text-xs sk-text-muted-foreground">
													{ row.customer_email }
												</div>
											) : null }
										</TableCell>
										<TableCell>
											<span className="sk-tabular-nums sk-text-muted-foreground">
												#{ row.id }
											</span>
											<div className="sk-text-xs sk-text-muted-foreground">
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
										</TableCell>
										<TableCell>
											{ row.next_payment_formatted ? (
												<>
													<div>
														{
															row.next_payment_formatted
														}
													</div>
													<div className="sk-text-xs sk-text-muted-foreground">
														{ whenDue(
															row.next_payment
														) }
													</div>
												</>
											) : (
												<span className="sk-text-muted-foreground">
													—
												</span>
											) }
										</TableCell>
										<TableCell className="sk-text-right sk-font-medium sk-tabular-nums">
											{ row.total_formatted }
											<div className="sk-text-xs sk-font-normal sk-text-muted-foreground">
												{ row.payment_method_title ||
													row.payment_method ||
													'—' }
											</div>
										</TableCell>
										<TableCell className="sk-text-right">
											{ row.billable ? (
												<Button
													variant="outline"
													size="sm"
													onClick={ () =>
														renew( row )
													}
												>
													{ __(
														'Renew now',
														'subkit-subscriptions'
													) }
												</Button>
											) : null }
										</TableCell>
									</TableRow>
								) )
							) : (
								<TableRow>
									<TableCell colSpan={ 7 } className="sk-p-0">
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
					<div className="sk-flex sk-flex-wrap sk-items-center sk-gap-3 sk-border-t sk-p-3 sk-text-sm">
						<span className="sk-text-muted-foreground">
							{ sprintf(
								/* translators: %d: how many subscriptions matched. */
								_n(
									'%d subscription',
									'%d subscriptions',
									total,
									'subkit-subscriptions'
								),
								total
							) }
						</span>
						{ pages > 1 ? (
							<div className="sk-ml-auto sk-flex sk-items-center sk-gap-3">
								<Button
									variant="outline"
									size="sm"
									disabled={ query.page <= 1 || busy }
									onClick={ () =>
										set( { page: query.page - 1 } )
									}
								>
									{ __( 'Previous', 'subkit-subscriptions' ) }
								</Button>
								<span className="sk-text-muted-foreground">
									{ sprintf(
										/* translators: 1: current page, 2: total pages. */
										__(
											'Page %1$d of %2$d',
											'subkit-subscriptions'
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
									{ __( 'Next', 'subkit-subscriptions' ) }
								</Button>
							</div>
						) : null }
					</div>
				) : null }
			</Card>
		</div>
	);
}
