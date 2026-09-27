import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Card = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn(
			'es-rounded-lg es-border es-border-border es-bg-card es-text-card-foreground es-shadow-sm',
			className
		) }
		{ ...props }
	/>
) );
Card.displayName = 'Card';

export const CardHeader = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn(
			'es-flex es-flex-col es-space-y-1.5 es-p-5 es-pb-3',
			className
		) }
		{ ...props }
	/>
) );
CardHeader.displayName = 'CardHeader';

export const CardTitle = forwardRef( ( { className, ...props }, ref ) => (
	// eslint-disable-next-line jsx-a11y/heading-has-content -- content comes from the call site.
	<h3
		ref={ ref }
		className={ cn(
			'es-text-sm es-font-medium es-text-muted-foreground',
			className
		) }
		{ ...props }
	/>
) );
CardTitle.displayName = 'CardTitle';

export const CardContent = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'es-p-5 es-pt-0', className ) }
		{ ...props }
	/>
) );
CardContent.displayName = 'CardContent';

export const CardFooter = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'es-flex es-items-center es-p-5 es-pt-0', className ) }
		{ ...props }
	/>
) );
CardFooter.displayName = 'CardFooter';
