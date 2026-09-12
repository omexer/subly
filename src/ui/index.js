/**
 * SubKit's shared admin UI: shadcn/ui components, plus the few composites every SubKit
 * screen needs.
 *
 * Published on a global rather than as a package. SubKit Pro builds separately and cannot
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
export { cn } from './lib/utils';

import { Card, CardContent } from './components/ui/card';

function toneFor( delta ) {
	if ( typeof delta !== 'number' ) {
		return 'sk-text-muted-foreground';
	}

	return delta < 0 ? 'sk-text-destructive' : 'sk-text-success';
}

export function Stat( { label, value, meta, delta, children } ) {
	const tone = toneFor( delta );

	return (
		<Card>
			<CardContent className="sk-p-5">
				<span className="sk-block sk-text-sm sk-text-muted-foreground">
					{ label }
				</span>
				<span className="sk-mt-1 sk-block sk-text-3xl sk-font-semibold sk-tabular-nums sk-leading-tight">
					{ value }
				</span>
				{ meta ? (
					<span
						className={ `sk-mt-1.5 sk-block sk-text-xs ${ tone }` }
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
			className="sk-mt-3 sk-block sk-h-12 sk-w-full sk-overflow-visible"
			viewBox="0 0 100 100"
			preserveAspectRatio="none"
			role="img"
			aria-label={
				label || __( 'Trend over time', 'subkit-subscriptions' )
			}
			focusable="false"
		>
			<polyline
				points={ coords.join( ' ' ) }
				fill="none"
				stroke="hsl(var(--sk-primary))"
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
			<p className="sk-text-sm sk-text-muted-foreground">
				{ __( 'Nothing to show yet.', 'subkit-subscriptions' ) }
			</p>
		);
	}

	return (
		<div>
			<div className="sk-flex sk-h-2.5 sk-overflow-hidden sk-rounded-full sk-bg-muted">
				{ rows.map( ( part ) => (
					<span
						key={ part.key }
						className="sk-h-full"
						style={ {
							width: `${ ( part.count / total ) * 100 }%`,
							background: part.colour,
						} }
						title={ `${ part.label }: ${ part.count }` }
					/>
				) ) }
			</div>
			<ul className="sk-mt-3.5 sk-flex sk-flex-wrap sk-gap-x-5 sk-gap-y-2 sk-text-sm">
				{ rows.map( ( part ) => (
					<li
						key={ part.key }
						className="sk-flex sk-items-center sk-gap-2"
					>
						<span
							className="sk-h-2.5 sk-w-2.5 sk-shrink-0 sk-rounded-sm"
							style={ { background: part.colour } }
							aria-hidden="true"
						/>
						<span>{ part.label }</span>
						<span className="sk-tabular-nums sk-text-muted-foreground">
							{ part.count }
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

export const version = '1';
