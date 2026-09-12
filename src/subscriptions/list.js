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

function statusVariant( status ) {
	if ( 'sk-active' === status || 'sk-trialling' === status ) {
		return 'success';
	}

	if ( 'sk-on-hold' === status ) {
		return 'destructive';
	}

	return 'secondary';
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

	return (
		<div className={ busy ? 'sk-opacity-60 sk-transition-opacity' : '' }>
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

			<div className="sk-mb-3 sk-flex sk-flex-wrap sk-items-center sk-gap-2">
				<button
					type="button"
					aria-pressed={ '' === query.status }
					onClick={ () => set( { status: '', page: 1 } ) }
					className={ `sk-rounded-md sk-px-2.5 sk-py-1 sk-text-xs sk-font-medium ${
						'' === query.status
							? 'sk-bg-primary sk-text-primary-foreground'
							: 'sk-bg-secondary sk-text-secondary-foreground hover:sk-bg-accent'
					}` }
				>
					{ __( 'All', 'subkit-subscriptions' ) }
				</button>

				{ statuses
					.filter( ( status ) => status.count > 0 )
					.map( ( status ) => (
						<button
							key={ status.key }
							type="button"
							aria-pressed={ query.status === status.key }
							onClick={ () =>
								set( { status: status.key, page: 1 } )
							}
							className={ `sk-rounded-md sk-px-2.5 sk-py-1 sk-text-xs sk-font-medium ${
								query.status === status.key
									? 'sk-bg-primary sk-text-primary-foreground'
									: 'sk-bg-secondary sk-text-secondary-foreground hover:sk-bg-accent'
							}` }
						>
							{ status.label } ({ status.count })
						</button>
					) ) }

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

			<div className="sk-mb-3 sk-flex sk-flex-wrap sk-items-center sk-gap-2">
				<Select
					value={ bulk }
					onChange={ ( event ) => setBulk( event.target.value ) }
					aria-label={ __( 'Bulk action', 'subkit-subscriptions' ) }
				>
					{ BULK.map( ( item ) => (
						<option key={ item.label } value={ item.label }>
							{ item.label }
						</option>
					) ) }
				</Select>
				<Button
					variant="outline"
					size="sm"
					disabled={ ! chosen.length || busy }
					onClick={ runBulk }
				>
					{ __( 'Apply', 'subkit-subscriptions' ) }
				</Button>
				{ chosen.length ? (
					<span className="sk-text-sm sk-text-muted-foreground">
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
				) : null }
			</div>

			<Card>
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
									{ __( 'Customer', 'subkit-subscriptions' ) }
								</TableHead>
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
								/>
								<TableHead>
									{ __(
										'Payment method',
										'subkit-subscriptions'
									) }
								</TableHead>
							</TableRow>
						</TableHeader>

						<TableBody>
							{ rows.length ? (
								rows.map( ( row ) => (
									<TableRow key={ row.id }>
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
												className="sk-font-medium"
											>
												#{ row.id }
											</a>
											<div className="sk-mt-1 sk-flex sk-gap-2 sk-text-xs">
												{ row.billable ? (
													<button
														type="button"
														onClick={ () =>
															renew( row )
														}
														className="sk-text-primary hover:sk-underline"
													>
														{ __(
															'Renew now',
															'subkit-subscriptions'
														) }
													</button>
												) : null }
											</div>
										</TableCell>
										<TableCell>
											{ row.customer_name ||
												row.customer_email ||
												'—' }
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
											{ row.next_payment_formatted ||
												'—' }
										</TableCell>
										<TableCell className="sk-tabular-nums">
											{ row.total_formatted }
										</TableCell>
										<TableCell>
											{ row.payment_method_title ||
												row.payment_method ||
												'—' }
										</TableCell>
									</TableRow>
								) )
							) : (
								<TableRow>
									<TableCell
										colSpan={ 7 }
										className="sk-p-6 sk-text-center sk-text-muted-foreground"
									>
										{ __(
											'No subscriptions match that.',
											'subkit-subscriptions'
										) }
									</TableCell>
								</TableRow>
							) }
						</TableBody>
					</Table>
				</CardContent>
			</Card>

			<div className="sk-mt-3 sk-flex sk-items-center sk-gap-3 sk-text-sm">
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
					<>
						<Button
							variant="outline"
							size="sm"
							disabled={ query.page <= 1 || busy }
							onClick={ () => set( { page: query.page - 1 } ) }
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
							onClick={ () => set( { page: query.page + 1 } ) }
						>
							{ __( 'Next', 'subkit-subscriptions' ) }
						</Button>
					</>
				) : null }
			</div>
		</div>
	);
}
