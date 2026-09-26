/**
 * The SubKit home screen.
 *
 * What a merchant needs on arrival, in the order they need it: finish setting up, see
 * how the business is doing, see what needs them, and get to everything else.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Badge,
	Button,
	Card,
	CardContent,
	CardHeader,
	CardTitle,
	Input,
	Select,
	Skeleton,
	Stat,
} from '@subkit/ui';
import { registerRoute } from '@subkit/shell';

const TONE = {
	bad: 'sk-bg-destructive',
	warn: 'sk-bg-amber-500',
	good: 'sk-bg-success',
};

function statusVariant( status ) {
	if ( 'sk-active' === status || 'sk-trialling' === status ) {
		return 'success';
	}

	return 'sk-on-hold' === status ? 'destructive' : 'secondary';
}

function initials( name ) {
	const parts = String( name || '?' )
		.trim()
		.split( /\s+/ );

	return (
		(
			( parts[ 0 ]?.[ 0 ] || '' ) + ( parts[ 1 ]?.[ 0 ] || '' )
		).toUpperCase() || '?'
	);
}

function CreateProduct( { create } ) {
	return (
		<form
			method="post"
			action={ create.url }
			className="sk-mt-3 sk-flex sk-flex-wrap sk-items-end sk-gap-2"
		>
			<input type="hidden" name="_wpnonce" value={ create.nonce } />
			<input type="hidden" name="action" value="subkit_create_product" />
			<label
				htmlFor="subkit-new-name"
				className="sk-flex sk-flex-col sk-gap-1 sk-text-xs sk-text-muted-foreground"
			>
				{ __( 'Name', 'subkit-subscriptions' ) }
				<Input
					id="subkit-new-name"
					name="subkit_name"
					required
					className="sk-w-48"
				/>
			</label>
			<label
				htmlFor="subkit-new-price"
				className="sk-flex sk-flex-col sk-gap-1 sk-text-xs sk-text-muted-foreground"
			>
				{ __( 'Price', 'subkit-subscriptions' ) }
				<Input
					id="subkit-new-price"
					name="subkit_price"
					required
					inputMode="decimal"
					className="sk-w-24"
				/>
			</label>
			<label
				htmlFor="subkit-new-interval"
				className="sk-flex sk-flex-col sk-gap-1 sk-text-xs sk-text-muted-foreground"
			>
				{ __( 'Every', 'subkit-subscriptions' ) }
				<span className="sk-flex sk-gap-1">
					<Input
						id="subkit-new-interval"
						name="subkit_interval"
						type="number"
						min="1"
						max="365"
						defaultValue="1"
						className="sk-w-16"
					/>
					<Select
						name="subkit_period"
						defaultValue="month"
						aria-label={ __(
							'Billing period',
							'subkit-subscriptions'
						) }
					>
						<option value="day">
							{ __( 'Days', 'subkit-subscriptions' ) }
						</option>
						<option value="week">
							{ __( 'Weeks', 'subkit-subscriptions' ) }
						</option>
						<option value="month">
							{ __( 'Months', 'subkit-subscriptions' ) }
						</option>
						<option value="year">
							{ __( 'Years', 'subkit-subscriptions' ) }
						</option>
					</Select>
				</span>
			</label>
			<label
				htmlFor="subkit-new-trial"
				className="sk-flex sk-flex-col sk-gap-1 sk-text-xs sk-text-muted-foreground"
			>
				{ __( 'Free trial (days)', 'subkit-subscriptions' ) }
				<Input
					id="subkit-new-trial"
					name="subkit_trial"
					type="number"
					min="0"
					max="365"
					defaultValue="0"
					className="sk-w-24"
				/>
			</label>
			<Button type="submit" size="sm">
				{ __( 'Create it', 'subkit-subscriptions' ) }
			</Button>
		</form>
	);
}

function Setup( { setup, create } ) {
	const percent = setup.total
		? Math.round( ( setup.done / setup.total ) * 100 )
		: 0;
	const next = setup.steps.findIndex( ( step ) => ! step.done );

	return (
		<Card className="sk-mb-6 sk-overflow-hidden">
			<div className="sk-flex sk-flex-wrap sk-items-center sk-justify-between sk-gap-4 sk-border-b sk-border-border sk-bg-accent/40 sk-px-5 sk-py-4">
				<div>
					<h2 className="sk-text-base sk-font-semibold">
						{ __(
							'Get your first subscription running',
							'subkit-subscriptions'
						) }
					</h2>
					<p className="sk-mt-1 sk-text-sm sk-text-muted-foreground">
						{ sprintf(
							/* translators: 1: steps done, 2: steps in total. */
							__( '%1$d of %2$d done', 'subkit-subscriptions' ),
							setup.done,
							setup.total
						) }
					</p>
				</div>
				<div
					className="sk-h-2 sk-w-48 sk-overflow-hidden sk-rounded-full sk-bg-muted"
					role="progressbar"
					aria-valuenow={ percent }
					aria-valuemin="0"
					aria-valuemax="100"
					aria-label={ __(
						'Setup progress',
						'subkit-subscriptions'
					) }
				>
					<div
						className="sk-h-full sk-rounded-full sk-bg-primary sk-transition-all"
						style={ { width: `${ percent }%` } }
					/>
				</div>
			</div>

			<ol className="sk-divide-y sk-divide-border">
				{ setup.steps.map( ( step, index ) => (
					<li
						key={ step.title }
						className={ `sk-flex sk-gap-4 sk-px-5 sk-py-4 ${
							index === next ? 'sk-bg-background' : ''
						}` }
					>
						<span
							className={ `sk-mt-0.5 sk-grid sk-h-6 sk-w-6 sk-shrink-0 sk-place-items-center sk-rounded-full sk-text-xs sk-font-semibold ${
								step.done
									? 'sk-bg-primary sk-text-primary-foreground'
									: 'sk-border sk-border-border sk-text-muted-foreground'
							}` }
							aria-hidden="true"
						>
							{ step.done ? '✓' : index + 1 }
						</span>
						<div className="sk-min-w-0 sk-flex-1">
							<p
								className={ `sk-text-sm sk-font-medium ${
									step.done ? 'sk-text-muted-foreground' : ''
								}` }
							>
								{ step.title }
								{ step.done ? (
									<span className="sk-sr-only">
										{ ' ' }
										(
										{ __( 'done', 'subkit-subscriptions' ) }
										)
									</span>
								) : null }
							</p>
							<p className="sk-mt-0.5 sk-text-sm sk-text-muted-foreground">
								{ step.detail }
							</p>
							{ 'create_product' === step.form && ! step.done ? (
								<CreateProduct create={ create } />
							) : null }
						</div>
						{ step.action ? (
							<Button
								asChild
								variant={
									index === next ? 'default' : 'outline'
								}
								size="sm"
								className="sk-self-start"
							>
								<a href={ step.action.url }>
									{ step.action.label }
								</a>
							</Button>
						) : null }
					</li>
				) ) }
			</ol>
		</Card>
	);
}

export function Dashboard( { onFail } ) {
	const [ data, setData ] = useState( null );

	useEffect( () => {
		let live = true;

		apiFetch( { path: '/subkit/v1/dashboard' } )
			.then( ( result ) => {
				if ( live ) {
					setData( result );
				}
			} )
			.catch( () => live && onFail() );

		return () => {
			live = false;
		};
		// Loaded once: the callbacks are new closures on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( ! data ) {
		return (
			<div className="sk-grid sk-gap-4">
				<Skeleton className="sk-h-10 sk-w-72" />
				<Skeleton className="sk-h-48" />
				<div className="sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
					<Skeleton className="sk-h-24" />
					<Skeleton className="sk-h-24" />
					<Skeleton className="sk-h-24" />
				</div>
			</div>
		);
	}

	const { setup, stats, attention, recent, links, create } = data;
	const setupDone = setup.done >= setup.total;

	return (
		<div>
			<div className="sk-mb-6 sk-flex sk-flex-wrap sk-items-end sk-justify-between sk-gap-4">
				<div>
					<h2 className="sk-text-2xl sk-font-semibold sk-tracking-tight">
						{ data.greeting }
					</h2>
					<p className="sk-mt-1 sk-text-sm sk-text-muted-foreground">
						{ __(
							'Here is how your subscriptions are doing.',
							'subkit-subscriptions'
						) }
					</p>
				</div>
				<div className="sk-flex sk-flex-wrap sk-gap-2">
					<Button asChild variant="outline">
						<a href={ links.list }>
							{ __(
								'All subscriptions',
								'subkit-subscriptions'
							) }
						</a>
					</Button>
					<Button asChild>
						<a href={ links.new_product }>
							{ __(
								'New subscription product',
								'subkit-subscriptions'
							) }
						</a>
					</Button>
				</div>
			</div>

			{ setupDone ? null : <Setup setup={ setup } create={ create } /> }

			<div className="sk-mb-6 sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
				<Stat
					label={ __(
						'Monthly recurring revenue',
						'subkit-subscriptions'
					) }
					value={ stats.mrr }
				/>
				<Stat
					label={ __(
						'Active subscriptions',
						'subkit-subscriptions'
					) }
					value={ stats.active }
				/>
				<Stat
					label={ __( 'On a free trial', 'subkit-subscriptions' ) }
					value={ stats.trialling }
				/>
				<Stat
					label={ __( 'On hold', 'subkit-subscriptions' ) }
					value={ stats.on_hold }
					meta={
						stats.on_hold
							? __( 'Payments failed', 'subkit-subscriptions' )
							: __( 'Nothing failing', 'subkit-subscriptions' )
					}
					delta={ stats.on_hold ? -1 : undefined }
				/>
			</div>

			<div className="sk-mb-6 sk-grid sk-gap-4 lg:sk-grid-cols-[2fr_3fr]">
				<Card>
					<CardHeader>
						<CardTitle>
							{ __(
								'Needs your attention',
								'subkit-subscriptions'
							) }
						</CardTitle>
					</CardHeader>
					<CardContent>
						{ attention.length ? (
							<ul className="sk-flex sk-flex-col sk-gap-2">
								{ attention.map( ( item ) => (
									<li key={ item.key }>
										<a
											href={ item.url }
											className="sk-flex sk-items-center sk-gap-3 sk-rounded-md sk-border sk-border-border sk-px-3 sk-py-2.5 sk-text-sm sk-text-foreground sk-no-underline hover:sk-bg-muted/60"
										>
											<span
												className={ `sk-h-2 sk-w-2 sk-shrink-0 sk-rounded-full ${
													TONE[ item.tone ] ||
													'sk-bg-muted-foreground'
												}` }
												aria-hidden="true"
											/>
											<span className="sk-flex-1">
												{ item.label }
											</span>
											<span
												aria-hidden="true"
												className="sk-text-muted-foreground"
											>
												→
											</span>
										</a>
									</li>
								) ) }
							</ul>
						) : (
							<div className="sk-flex sk-flex-col sk-items-center sk-py-6 sk-text-center">
								<span
									className="sk-mb-2 sk-grid sk-h-10 sk-w-10 sk-place-items-center sk-rounded-full sk-bg-success/10 sk-text-success"
									aria-hidden="true"
								>
									✓
								</span>
								<p className="sk-text-sm sk-font-medium">
									{ __(
										'Nothing needs you right now',
										'subkit-subscriptions'
									) }
								</p>
								<p className="sk-mt-1 sk-text-sm sk-text-muted-foreground">
									{ __(
										'Failed payments and subscriptions about to end will show up here.',
										'subkit-subscriptions'
									) }
								</p>
							</div>
						) }
					</CardContent>
				</Card>

				<Card>
					<CardHeader className="sk-flex-row sk-items-center sk-justify-between sk-space-y-0">
						<CardTitle>
							{ __(
								'Recent subscriptions',
								'subkit-subscriptions'
							) }
						</CardTitle>
						<a
							href={ links.list }
							className="sk-text-sm sk-font-medium sk-text-primary sk-no-underline hover:sk-underline"
						>
							{ __( 'View all', 'subkit-subscriptions' ) }
						</a>
					</CardHeader>
					<CardContent className="sk-px-2">
						{ recent.length ? (
							<ul className="sk-flex sk-flex-col">
								{ recent.map( ( row ) => (
									<li key={ row.id }>
										<a
											href={ row.url }
											className="sk-flex sk-items-center sk-gap-3 sk-rounded-md sk-px-3 sk-py-2.5 sk-text-foreground sk-no-underline hover:sk-bg-muted/60"
										>
											<span
												className="sk-grid sk-h-9 sk-w-9 sk-shrink-0 sk-place-items-center sk-rounded-full sk-bg-accent sk-text-xs sk-font-semibold sk-text-primary"
												aria-hidden="true"
											>
												{ initials( row.customer ) }
											</span>
											<span className="sk-min-w-0 sk-flex-1">
												<span className="sk-block sk-truncate sk-text-sm sk-font-medium">
													{ row.customer ||
														`#${ row.id }` }
												</span>
												<span className="sk-block sk-truncate sk-text-xs sk-text-muted-foreground">
													{ row.product } ·{ ' ' }
													{ row.created }
												</span>
											</span>
											<Badge
												variant={ statusVariant(
													row.status
												) }
											>
												{ row.status_label }
											</Badge>
											<span className="sk-w-24 sk-text-right sk-text-sm sk-tabular-nums">
												{ row.total }
											</span>
										</a>
									</li>
								) ) }
							</ul>
						) : (
							<p className="sk-px-3 sk-py-6 sk-text-center sk-text-sm sk-text-muted-foreground">
								{ __(
									'Your first subscription will appear here the moment someone buys one.',
									'subkit-subscriptions'
								) }
							</p>
						) }
					</CardContent>
				</Card>
			</div>

			<div className="sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
				{ [
					{
						href: links.integrations,
						title: __( 'Integrations', 'subkit-subscriptions' ),
						body: __(
							'Connect courses, mailing lists and licence keys to a subscription.',
							'subkit-subscriptions'
						),
					},
					{
						href: links.settings,
						title: __( 'Settings', 'subkit-subscriptions' ),
						body: __(
							'Payment methods, renewals, grace periods and customer access.',
							'subkit-subscriptions'
						),
					},
					{
						href: links.help,
						title: __( 'Help', 'subkit-subscriptions' ),
						body: __(
							'Guides, the system report, and what to check before you ask.',
							'subkit-subscriptions'
						),
					},
				].map( ( link ) => (
					<a
						key={ link.title }
						href={ link.href }
						className="sk-group sk-rounded-lg sk-border sk-border-border sk-bg-card sk-p-5 sk-text-foreground sk-no-underline sk-transition-shadow hover:sk-shadow-md"
					>
						<span className="sk-flex sk-items-center sk-justify-between sk-text-sm sk-font-semibold">
							{ link.title }
							<span
								aria-hidden="true"
								className="sk-text-muted-foreground group-hover:sk-text-primary"
							>
								→
							</span>
						</span>
						<span className="sk-mt-1 sk-block sk-text-sm sk-text-muted-foreground">
							{ link.body }
						</span>
					</a>
				) ) }
			</div>
		</div>
	);
}

registerRoute( {
	page: 'subkit-subscriptions',
	title: __( 'Home', 'subkit-subscriptions' ),
	render: ( ctx ) => <Dashboard onFail={ ctx.fail } />,
} );
