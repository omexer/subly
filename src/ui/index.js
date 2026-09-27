/**
 * EasySubscription's shared admin UI: shadcn/ui components, plus the few composites every EasySubscription
 * screen needs.
 *
 * Published on a global rather than as a package. EasySubscription Pro builds separately and cannot
 * import from this plugin at build time, so it consumes these as an external, the same way
 * everything consumes wp.components.
 */
import { __ } from '@wordpress/i18n';

import './globals.css';

export {
	Card,
	CardHeader,
	CardTitle,
	CardContent,
	CardFooter,
} from './components/ui/card';
export { Badge, badgeVariants } from './components/ui/badge';
export { Button, buttonVariants } from './components/ui/button';
export { Skeleton } from './components/ui/skeleton';
export {
	Table,
	TableHeader,
	TableBody,
	TableRow,
	TableHead,
	TableCell,
} from './components/ui/table';
export { Input, Select, Checkbox } from './components/ui/input';
export { cn } from './lib/utils';

import { Card, CardContent } from './components/ui/card';

function toneFor( delta ) {
	if ( typeof delta !== 'number' ) {
		return 'es-text-muted-foreground';
	}

	return delta < 0 ? 'es-text-destructive' : 'es-text-success';
}

export function Stat( { label, value, meta, delta, children } ) {
	const tone = toneFor( delta );

	return (
		<Card>
			<CardContent className="es-p-5">
				<span className="es-block es-text-sm es-text-muted-foreground">
					{ label }
				</span>
				<span className="es-mt-1 es-block es-text-3xl es-font-semibold es-tabular-nums es-leading-tight">
					{ value }
				</span>
				{ meta ? (
					<span
						className={ `es-mt-1.5 es-block es-text-xs ${ tone }` }
					>
						{ meta }
					</span>
				) : null }
				{ children }
			</CardContent>
		</Card>
	);
}

// A line over time: one series, no axes, no library.
export function Sparkline( { points, label } ) {
	const values = ( points || [] ).map( ( point ) => Number( point ) || 0 );

	if ( values.length < 2 ) {
		return null;
	}

	const top = Math.max( ...values );
	const floor = Math.min( ...values );
	// A flat line belongs in the middle, not on the floor or off the top.
	const span = top - floor || Math.max( top, 1 );
	const step = 100 / ( values.length - 1 );

	const coords = values.map( ( value, index ) => {
		const y = top === floor ? 50 : 100 - ( ( value - floor ) / span ) * 100;

		return `${ ( index * step ).toFixed( 2 ) },${ y.toFixed( 2 ) }`;
	} );

	return (
		<svg
			className="es-mt-3 es-block es-h-12 es-w-full es-overflow-visible"
			viewBox="0 0 100 100"
			preserveAspectRatio="none"
			role="img"
			aria-label={ label || __( 'Trend over time', 'easysubscription' ) }
			focusable="false"
		>
			<polyline
				points={ coords.join( ' ' ) }
				fill="none"
				stroke="hsl(var(--es-primary))"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
				vectorEffect="non-scaling-stroke"
			/>
		</svg>
	);
}

// One bar, every status in proportion, with a legend naming the colours.
export function StatusBar( { parts } ) {
	const rows = ( parts || [] ).filter( ( part ) => part.count > 0 );
	const total = rows.reduce( ( sum, part ) => sum + part.count, 0 );

	if ( ! total ) {
		return (
			<p className="es-text-sm es-text-muted-foreground">
				{ __( 'Nothing to show yet.', 'easysubscription' ) }
			</p>
		);
	}

	return (
		<div>
			<div className="es-flex es-h-2.5 es-overflow-hidden es-rounded-full es-bg-muted">
				{ rows.map( ( part ) => (
					<span
						key={ part.key }
						className="es-h-full"
						style={ {
							width: `${ ( part.count / total ) * 100 }%`,
							background: part.colour,
						} }
						title={ `${ part.label }: ${ part.count }` }
					/>
				) ) }
			</div>
			<ul className="es-mt-3.5 es-flex es-flex-wrap es-gap-x-5 es-gap-y-2 es-text-sm">
				{ rows.map( ( part ) => (
					<li
						key={ part.key }
						className="es-flex es-items-center es-gap-2"
					>
						<span
							className="es-h-2.5 es-w-2.5 es-shrink-0 es-rounded-sm"
							style={ { background: part.colour } }
							aria-hidden="true"
						/>
						<span>{ part.label }</span>
						<span className="es-tabular-nums es-text-muted-foreground">
							{ part.count }
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

export const version = '1';
