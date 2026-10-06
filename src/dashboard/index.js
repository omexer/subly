/**
 * The Subly home screen.
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
} from '@subly/ui';
import { registerRoute } from '@subly/shell';

const TONE = {
	bad: 'sb-bg-destructive',
	warn: 'sb-bg-amber-500',
	good: 'sb-bg-success',
};

function statusVariant( status ) {
	if ( 'subly-active' === status || 'subly-trialling' === status ) {
		return 'success';
	}

	return 'subly-on-hold' === status ? 'destructive' : 'secondary';
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
			className="sb-mt-3 sb-flex sb-flex-wrap sb-items-end sb-gap-2"
		>
			<input type="hidden" name="_wpnonce" value={ create.nonce } />
			<input
				type="hidden"
				name="action"
				value="subly_create_product"
			/>
			<label
				htmlFor="subly-new-name"
				className="sb-flex sb-flex-col sb-gap-1 sb-text-xs sb-text-muted-foreground"
			>
				{ __( 'Name', 'subly' ) }
				<Input
					id="subly-new-name"
					name="subly_name"
					required
					className="sb-w-48"
				/>
			</label>
			<label
				htmlFor="subly-new-price"
				className="sb-flex sb-flex-col sb-gap-1 sb-text-xs sb-text-muted-foreground"
			>
				{ __( 'Price', 'subly' ) }
				<Input
					id="subly-new-price"
					name="subly_price"
					required
					inputMode="decimal"
					className="sb-w-24"
				/>
			</label>
			<label
				htmlFor="subly-new-interval"
				className="sb-flex sb-flex-col sb-gap-1 sb-text-xs sb-text-muted-foreground"
			>
				{ __( 'Every', 'subly' ) }
				<span className="sb-flex sb-gap-1">
					<Input
						id="subly-new-interval"
						name="subly_interval"
						type="number"
						min="1"
						max="365"
						defaultValue="1"
						className="sb-w-16"
					/>
					<Select
						name="subly_period"
						defaultValue="month"
						aria-label={ __(
							'Billing period',
							'subly'
						) }
					>
						<option value="day">
							{ __( 'Days', 'subly' ) }
						</option>
						<option value="week">
							{ __( 'Weeks', 'subly' ) }
						</option>
						<option value="month">
							{ __( 'Months', 'subly' ) }
						</option>
						<option value="year">
							{ __( 'Years', 'subly' ) }
						</option>
					</Select>
				</span>
			</label>
			<label
				htmlFor="subly-new-trial"
				className="sb-flex sb-flex-col sb-gap-1 sb-text-xs sb-text-muted-foreground"
			>
				{ __( 'Free trial (days)', 'subly' ) }
				<Input
					id="subly-new-trial"
					name="subly_trial"
					type="number"
					min="0"
					max="365"
					defaultValue="0"
					className="sb-w-24"
				/>
			</label>
			<Button type="submit" size="sm">
				{ __( 'Create it', 'subly' ) }
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
		<Card className="sb-mb-6 sb-overflow-hidden">
			<div className="sb-flex sb-flex-wrap sb-items-center sb-justify-between sb-gap-4 sb-border-b sb-border-border sb-bg-accent/40 sb-px-5 sb-py-4">
				<div>
					<h2 className="sb-text-base sb-font-semibold">
						{ __(
							'Get your first subscription running',
							'subly'
						) }
					</h2>
					<p className="sb-mt-1 sb-text-sm sb-text-muted-foreground">
						{ sprintf(
							/* translators: 1: steps done, 2: steps in total. */
							__( '%1$d of %2$d done', 'subly' ),
							setup.done,
							setup.total
						) }
					</p>
				</div>
				<div
					className="sb-h-2 sb-w-48 sb-overflow-hidden sb-rounded-full sb-bg-muted"
					role="progressbar"
					aria-valuenow={ percent }
					aria-valuemin="0"
					aria-valuemax="100"
					aria-label={ __( 'Setup progress', 'subly' ) }
				>
					<div
						className="sb-h-full sb-rounded-full sb-bg-primary sb-transition-all"
						style={ { width: `${ percent }%` } }
					/>
				</div>
			</div>

			<ol className="sb-divide-y sb-divide-border">
				{ setup.steps.map( ( step, index ) => (
					<li
						key={ step.title }
						className={ `sb-flex sb-gap-4 sb-px-5 sb-py-4 ${
							index === next ? 'sb-bg-background' : ''
						}` }
					>
						<span
							className={ `sb-mt-0.5 sb-grid sb-h-6 sb-w-6 sb-shrink-0 sb-place-items-center sb-rounded-full sb-text-xs sb-font-semibold ${
								step.done
									? 'sb-bg-primary sb-text-primary-foreground'
									: 'sb-border sb-border-border sb-text-muted-foreground'
							}` }
							aria-hidden="true"
						>
							{ step.done ? '✓' : index + 1 }
						</span>
						<div className="sb-min-w-0 sb-flex-1">
							<p
								className={ `sb-text-sm sb-font-medium ${
									step.done ? 'sb-text-muted-foreground' : ''
								}` }
							>
								{ step.title }
								{ step.done ? (
									<span className="sb-sr-only">
										{ ' ' }
										({ __( 'done', 'subly' ) })
									</span>
								) : null }
							</p>
							<p className="sb-mt-0.5 sb-text-sm sb-text-muted-foreground">
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
								className="sb-self-start"
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

		apiFetch( { path: '/subly/v1/dashboard' } )
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
			<div className="sb-grid sb-gap-4">
				<Skeleton className="sb-h-10 sb-w-72" />
				<Skeleton className="sb-h-48" />
				<div className="sb-grid sb-gap-4 sb-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
					<Skeleton className="sb-h-24" />
					<Skeleton className="sb-h-24" />
					<Skeleton className="sb-h-24" />
				</div>
			</div>
		);
	}

	const { setup, stats, attention, recent, links, create } = data;
	const setupDone = setup.done >= setup.total;

	return (
		<div>
			<div className="sb-mb-6 sb-flex sb-flex-wrap sb-items-end sb-justify-between sb-gap-4">
				<div>
					<h2 className="sb-text-2xl sb-font-semibold sb-tracking-tight">
						{ data.greeting }
					</h2>
					<p className="sb-mt-1 sb-text-sm sb-text-muted-foreground">
						{ __(
							'Here is how your subscriptions are doing.',
							'subly'
						) }
					</p>
				</div>
				<div className="sb-flex sb-flex-wrap sb-gap-2">
					<Button asChild variant="outline">
						<a href={ links.list }>
							{ __( 'All subscriptions', 'subly' ) }
						</a>
					</Button>
					<Button asChild>
						<a href={ links.new_product }>
							{ __(
								'New subscription product',
								'subly'
							) }
						</a>
					</Button>
				</div>
			</div>

			{ setupDone ? null : <Setup setup={ setup } create={ create } /> }

			<div className="sb-mb-6 sb-grid sb-gap-4 sb-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
				<Stat
					label={ __(
						'Monthly recurring revenue',
						'subly'
					) }
					value={ stats.mrr }
				/>
				<Stat
					label={ __( 'Active subscriptions', 'subly' ) }
					value={ stats.active }
				/>
				<Stat
					label={ __( 'On a free trial', 'subly' ) }
					value={ stats.trialling }
				/>
				<Stat
					label={ __( 'On hold', 'subly' ) }
					value={ stats.on_hold }
					meta={
						stats.on_hold
							? __( 'Payments failed', 'subly' )
							: __( 'Nothing failing', 'subly' )
					}
					delta={ stats.on_hold ? -1 : undefined }
				/>
			</div>

			<div className="sb-mb-6 sb-grid sb-gap-4 lg:sb-grid-cols-[2fr_3fr]">
				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'Needs your attention', 'subly' ) }
						</CardTitle>
					</CardHeader>
					<CardContent>
						{ attention.length ? (
							<ul className="sb-flex sb-flex-col sb-gap-2">
								{ attention.map( ( item ) => (
									<li key={ item.key }>
										<a
											href={ item.url }
											className="sb-flex sb-items-center sb-gap-3 sb-rounded-md sb-border sb-border-border sb-px-3 sb-py-2.5 sb-text-sm sb-text-foreground sb-no-underline hover:sb-bg-muted/60"
										>
											<span
												className={ `sb-h-2 sb-w-2 sb-shrink-0 sb-rounded-full ${
													TONE[ item.tone ] ||
													'sb-bg-muted-foreground'
												}` }
												aria-hidden="true"
											/>
											<span className="sb-flex-1">
												{ item.label }
											</span>
											<span
												aria-hidden="true"
												className="sb-text-muted-foreground"
											>
												→
											</span>
										</a>
									</li>
								) ) }
							</ul>
						) : (
							<div className="sb-flex sb-flex-col sb-items-center sb-py-6 sb-text-center">
								<span
									className="sb-mb-2 sb-grid sb-h-10 sb-w-10 sb-place-items-center sb-rounded-full sb-bg-success/10 sb-text-success"
									aria-hidden="true"
								>
									✓
								</span>
								<p className="sb-text-sm sb-font-medium">
									{ __(
										'Nothing needs you right now',
										'subly'
									) }
								</p>
								<p className="sb-mt-1 sb-text-sm sb-text-muted-foreground">
									{ __(
										'Failed payments and subscriptions about to end will show up here.',
										'subly'
									) }
								</p>
							</div>
						) }
					</CardContent>
				</Card>

				<Card>
					<CardHeader className="sb-flex-row sb-items-center sb-justify-between sb-space-y-0">
						<CardTitle>
							{ __( 'Recent subscriptions', 'subly' ) }
						</CardTitle>
						<a
							href={ links.list }
							className="sb-text-sm sb-font-medium sb-text-primary sb-no-underline hover:sb-underline"
						>
							{ __( 'View all', 'subly' ) }
						</a>
					</CardHeader>
					<CardContent className="sb-px-2">
						{ recent.length ? (
							<ul className="sb-flex sb-flex-col">
								{ recent.map( ( row ) => (
									<li key={ row.id }>
										<a
											href={ row.url }
											className="sb-flex sb-items-center sb-gap-3 sb-rounded-md sb-px-3 sb-py-2.5 sb-text-foreground sb-no-underline hover:sb-bg-muted/60"
										>
											<span
												className="sb-grid sb-h-9 sb-w-9 sb-shrink-0 sb-place-items-center sb-rounded-full sb-bg-accent sb-text-xs sb-font-semibold sb-text-primary"
												aria-hidden="true"
											>
												{ initials( row.customer ) }
											</span>
											<span className="sb-min-w-0 sb-flex-1">
												<span className="sb-block sb-truncate sb-text-sm sb-font-medium">
													{ row.customer ||
														`#${ row.id }` }
												</span>
												<span className="sb-block sb-truncate sb-text-xs sb-text-muted-foreground">
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
											<span className="sb-w-24 sb-text-right sb-text-sm sb-tabular-nums">
												{ row.total }
											</span>
										</a>
									</li>
								) ) }
							</ul>
						) : (
							<p className="sb-px-3 sb-py-6 sb-text-center sb-text-sm sb-text-muted-foreground">
								{ __(
									'Your first subscription will appear here the moment someone buys one.',
									'subly'
								) }
							</p>
						) }
					</CardContent>
				</Card>
			</div>

			<div className="sb-grid sb-gap-4 sb-grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
				{ [
					{
						href: links.integrations,
						title: __( 'Integrations', 'subly' ),
						body: __(
							'Connect courses, mailing lists and licence keys to a subscription.',
							'subly'
						),
					},
					{
						href: links.settings,
						title: __( 'Settings', 'subly' ),
						body: __(
							'Payment methods, renewals, grace periods and customer access.',
							'subly'
						),
					},
					{
						href: links.help,
						title: __( 'Help', 'subly' ),
						body: __(
							'Guides, the system report, and what to check before you ask.',
							'subly'
						),
					},
				].map( ( link ) => (
					<a
						key={ link.title }
						href={ link.href }
						className="sb-group sb-rounded-lg sb-border sb-border-border sb-bg-card sb-p-5 sb-text-foreground sb-no-underline sb-transition-shadow hover:sb-shadow-md"
					>
						<span className="sb-flex sb-items-center sb-justify-between sb-text-sm sb-font-semibold">
							{ link.title }
							<span
								aria-hidden="true"
								className="sb-text-muted-foreground group-hover:sb-text-primary"
							>
								→
							</span>
						</span>
						<span className="sb-mt-1 sb-block sb-text-sm sb-text-muted-foreground">
							{ link.body }
						</span>
					</a>
				) ) }
			</div>
		</div>
	);
}

registerRoute( {
	page: 'subly',
	title: __( 'Home', 'subly' ),
	render: ( ctx ) => <Dashboard onFail={ ctx.fail } />,
} );
