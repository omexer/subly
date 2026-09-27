/**
 * The EasySubscription home screen.
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
} from '@easysubscription/ui';
import { registerRoute } from '@easysubscription/shell';

const TONE = {
	bad: 'es-bg-destructive',
	warn: 'es-bg-amber-500',
	good: 'es-bg-success',
};

function statusVariant( status ) {
	if ( 'es-active' === status || 'es-trialling' === status ) {
		return 'success';
	}

	return 'es-on-hold' === status ? 'destructive' : 'secondary';
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
			className="es-mt-3 es-flex es-flex-wrap es-items-end es-gap-2"
		>
			<input type="hidden" name="_wpnonce" value={ create.nonce } />
			<input
				type="hidden"
				name="action"
				value="easysubscription_create_product"
			/>
			<label
				htmlFor="easysubscription-new-name"
				className="es-flex es-flex-col es-gap-1 es-text-xs es-text-muted-foreground"
			>
				{ __( 'Name', 'easysubscription' ) }
				<Input
					id="easysubscription-new-name"
					name="easysubscription_name"
					required
					className="es-w-48"
				/>
			</label>
			<label
				htmlFor="easysubscription-new-price"
				className="es-flex es-flex-col es-gap-1 es-text-xs es-text-muted-foreground"
			>
				{ __( 'Price', 'easysubscription' ) }
				<Input
					id="easysubscription-new-price"
					name="easysubscription_price"
					required
					inputMode="decimal"
					className="es-w-24"
				/>
			</label>
			<label
				htmlFor="easysubscription-new-interval"
				className="es-flex es-flex-col es-gap-1 es-text-xs es-text-muted-foreground"
			>
				{ __( 'Every', 'easysubscription' ) }
				<span className="es-flex es-gap-1">
					<Input
						id="easysubscription-new-interval"
						name="easysubscription_interval"
						type="number"
						min="1"
						max="365"
						defaultValue="1"
						className="es-w-16"
					/>
					<Select
						name="easysubscription_period"
						defaultValue="month"
						aria-label={ __(
							'Billing period',
							'easysubscription'
						) }
					>
						<option value="day">
							{ __( 'Days', 'easysubscription' ) }
						</option>
						<option value="week">
							{ __( 'Weeks', 'easysubscription' ) }
						</option>
						<option value="month">
							{ __( 'Months', 'easysubscription' ) }
						</option>
						<option value="year">
							{ __( 'Years', 'easysubscription' ) }
						</option>
					</Select>
				</span>
			</label>
			<label
				htmlFor="easysubscription-new-trial"
				className="es-flex es-flex-col es-gap-1 es-text-xs es-text-muted-foreground"
			>
				{ __( 'Free trial (days)', 'easysubscription' ) }
				<Input
					id="easysubscription-new-trial"
					name="easysubscription_trial"
					type="number"
					min="0"
					max="365"
					defaultValue="0"
					className="es-w-24"
				/>
			</label>
			<Button type="submit" size="sm">
				{ __( 'Create it', 'easysubscription' ) }
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
		<Card className="es-mb-6 es-overflow-hidden">
			<div className="es-flex es-flex-wrap es-items-center es-justify-between es-gap-4 es-border-b es-border-border es-bg-accent/40 es-px-5 es-py-4">
				<div>
					<h2 className="es-text-base es-font-semibold">
						{ __(
							'Get your first subscription running',
							'easysubscription'
						) }
					</h2>
					<p className="es-mt-1 es-text-sm es-text-muted-foreground">
						{ sprintf(
							/* translators: 1: steps done, 2: steps in total. */
							__( '%1$d of %2$d done', 'easysubscription' ),
							setup.done,
							setup.total
						) }
					</p>
				</div>
				<div
					className="es-h-2 es-w-48 es-overflow-hidden es-rounded-full es-bg-muted"
					role="progressbar"
					aria-valuenow={ percent }
					aria-valuemin="0"
					aria-valuemax="100"
					aria-label={ __( 'Setup progress', 'easysubscription' ) }
				>
					<div
						className="es-h-full es-rounded-full es-bg-primary es-transition-all"
						style={ { width: `${ percent }%` } }
					/>
				</div>
			</div>

			<ol className="es-divide-y es-divide-border">
				{ setup.steps.map( ( step, index ) => (
					<li
						key={ step.title }
						className={ `es-flex es-gap-4 es-px-5 es-py-4 ${
							index === next ? 'es-bg-background' : ''
						}` }
					>
						<span
							className={ `es-mt-0.5 es-grid es-h-6 es-w-6 es-shrink-0 es-place-items-center es-rounded-full es-text-xs es-font-semibold ${
								step.done
									? 'es-bg-primary es-text-primary-foreground'
									: 'es-border es-border-border es-text-muted-foreground'
							}` }
							aria-hidden="true"
						>
							{ step.done ? '✓' : index + 1 }
						</span>
						<div className="es-min-w-0 es-flex-1">
							<p
								className={ `es-text-sm es-font-medium ${
									step.done ? 'es-text-muted-foreground' : ''
								}` }
							>
								{ step.title }
								{ step.done ? (
									<span className="es-sr-only">
										{ ' ' }
										({ __( 'done', 'easysubscription' ) })
									</span>
								) : null }
							</p>
							<p className="es-mt-0.5 es-text-sm es-text-muted-foreground">
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
								className="es-self-start"
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

		apiFetch( { path: '/easysubscription/v1/dashboard' } )
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
			<div className="es-grid es-gap-4">
				<Skeleton className="es-h-10 es-w-72" />
				<Skeleton className="es-h-48" />
				<div className="es-grid es-gap-4 es-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
					<Skeleton className="es-h-24" />
					<Skeleton className="es-h-24" />
					<Skeleton className="es-h-24" />
				</div>
			</div>
		);
	}

	const { setup, stats, attention, recent, links, create } = data;
	const setupDone = setup.done >= setup.total;

	return (
		<div>
			<div className="es-mb-6 es-flex es-flex-wrap es-items-end es-justify-between es-gap-4">
				<div>
					<h2 className="es-text-2xl es-font-semibold es-tracking-tight">
						{ data.greeting }
					</h2>
					<p className="es-mt-1 es-text-sm es-text-muted-foreground">
						{ __(
							'Here is how your subscriptions are doing.',
							'easysubscription'
						) }
					</p>
				</div>
				<div className="es-flex es-flex-wrap es-gap-2">
					<Button asChild variant="outline">
						<a href={ links.list }>
							{ __( 'All subscriptions', 'easysubscription' ) }
						</a>
					</Button>
					<Button asChild>
						<a href={ links.new_product }>
							{ __(
								'New subscription product',
								'easysubscription'
							) }
						</a>
					</Button>
				</div>
			</div>

			{ setupDone ? null : <Setup setup={ setup } create={ create } /> }

			<div className="es-mb-6 es-grid es-gap-4 es-grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
				<Stat
					label={ __(
						'Monthly recurring revenue',
						'easysubscription'
					) }
					value={ stats.mrr }
				/>
				<Stat
					label={ __( 'Active subscriptions', 'easysubscription' ) }
					value={ stats.active }
				/>
				<Stat
					label={ __( 'On a free trial', 'easysubscription' ) }
					value={ stats.trialling }
				/>
				<Stat
					label={ __( 'On hold', 'easysubscription' ) }
					value={ stats.on_hold }
					meta={
						stats.on_hold
							? __( 'Payments failed', 'easysubscription' )
							: __( 'Nothing failing', 'easysubscription' )
					}
					delta={ stats.on_hold ? -1 : undefined }
				/>
			</div>

			<div className="es-mb-6 es-grid es-gap-4 lg:es-grid-cols-[2fr_3fr]">
				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'Needs your attention', 'easysubscription' ) }
						</CardTitle>
					</CardHeader>
					<CardContent>
						{ attention.length ? (
							<ul className="es-flex es-flex-col es-gap-2">
								{ attention.map( ( item ) => (
									<li key={ item.key }>
										<a
											href={ item.url }
											className="es-flex es-items-center es-gap-3 es-rounded-md es-border es-border-border es-px-3 es-py-2.5 es-text-sm es-text-foreground es-no-underline hover:es-bg-muted/60"
										>
											<span
												className={ `es-h-2 es-w-2 es-shrink-0 es-rounded-full ${
													TONE[ item.tone ] ||
													'es-bg-muted-foreground'
												}` }
												aria-hidden="true"
											/>
											<span className="es-flex-1">
												{ item.label }
											</span>
											<span
												aria-hidden="true"
												className="es-text-muted-foreground"
											>
												→
											</span>
										</a>
									</li>
								) ) }
							</ul>
						) : (
							<div className="es-flex es-flex-col es-items-center es-py-6 es-text-center">
								<span
									className="es-mb-2 es-grid es-h-10 es-w-10 es-place-items-center es-rounded-full es-bg-success/10 es-text-success"
									aria-hidden="true"
								>
									✓
								</span>
								<p className="es-text-sm es-font-medium">
									{ __(
										'Nothing needs you right now',
										'easysubscription'
									) }
								</p>
								<p className="es-mt-1 es-text-sm es-text-muted-foreground">
									{ __(
										'Failed payments and subscriptions about to end will show up here.',
										'easysubscription'
									) }
								</p>
							</div>
						) }
					</CardContent>
				</Card>

				<Card>
					<CardHeader className="es-flex-row es-items-center es-justify-between es-space-y-0">
						<CardTitle>
							{ __( 'Recent subscriptions', 'easysubscription' ) }
						</CardTitle>
						<a
							href={ links.list }
							className="es-text-sm es-font-medium es-text-primary es-no-underline hover:es-underline"
						>
							{ __( 'View all', 'easysubscription' ) }
						</a>
					</CardHeader>
					<CardContent className="es-px-2">
						{ recent.length ? (
							<ul className="es-flex es-flex-col">
								{ recent.map( ( row ) => (
									<li key={ row.id }>
										<a
											href={ row.url }
											className="es-flex es-items-center es-gap-3 es-rounded-md es-px-3 es-py-2.5 es-text-foreground es-no-underline hover:es-bg-muted/60"
										>
											<span
												className="es-grid es-h-9 es-w-9 es-shrink-0 es-place-items-center es-rounded-full es-bg-accent es-text-xs es-font-semibold es-text-primary"
												aria-hidden="true"
											>
												{ initials( row.customer ) }
											</span>
											<span className="es-min-w-0 es-flex-1">
												<span className="es-block es-truncate es-text-sm es-font-medium">
													{ row.customer ||
														`#${ row.id }` }
												</span>
												<span className="es-block es-truncate es-text-xs es-text-muted-foreground">
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
											<span className="es-w-24 es-text-right es-text-sm es-tabular-nums">
												{ row.total }
											</span>
										</a>
									</li>
								) ) }
							</ul>
						) : (
							<p className="es-px-3 es-py-6 es-text-center es-text-sm es-text-muted-foreground">
								{ __(
									'Your first subscription will appear here the moment someone buys one.',
									'easysubscription'
								) }
							</p>
						) }
					</CardContent>
				</Card>
			</div>

			<div className="es-grid es-gap-4 es-grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
				{ [
					{
						href: links.integrations,
						title: __( 'Integrations', 'easysubscription' ),
						body: __(
							'Connect courses, mailing lists and licence keys to a subscription.',
							'easysubscription'
						),
					},
					{
						href: links.settings,
						title: __( 'Settings', 'easysubscription' ),
						body: __(
							'Payment methods, renewals, grace periods and customer access.',
							'easysubscription'
						),
					},
					{
						href: links.help,
						title: __( 'Help', 'easysubscription' ),
						body: __(
							'Guides, the system report, and what to check before you ask.',
							'easysubscription'
						),
					},
				].map( ( link ) => (
					<a
						key={ link.title }
						href={ link.href }
						className="es-group es-rounded-lg es-border es-border-border es-bg-card es-p-5 es-text-foreground es-no-underline es-transition-shadow hover:es-shadow-md"
					>
						<span className="es-flex es-items-center es-justify-between es-text-sm es-font-semibold">
							{ link.title }
							<span
								aria-hidden="true"
								className="es-text-muted-foreground group-hover:es-text-primary"
							>
								→
							</span>
						</span>
						<span className="es-mt-1 es-block es-text-sm es-text-muted-foreground">
							{ link.body }
						</span>
					</a>
				) ) }
			</div>
		</div>
	);
}

registerRoute( {
	page: 'easysubscription',
	title: __( 'Home', 'easysubscription' ),
	render: ( ctx ) => <Dashboard onFail={ ctx.fail } />,
} );
