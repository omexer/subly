import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Card = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn(
			'sb-rounded-lg sb-border sb-border-border sb-bg-card sb-text-card-foreground sb-shadow-sm',
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
			'sb-flex sb-flex-col sb-space-y-1.5 sb-p-5 sb-pb-3',
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
			'sb-text-sm sb-font-medium sb-text-muted-foreground',
			className
		) }
		{ ...props }
	/>
) );
CardTitle.displayName = 'CardTitle';

export const CardContent = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'sb-p-5 sb-pt-0', className ) }
		{ ...props }
	/>
) );
CardContent.displayName = 'CardContent';

export const CardFooter = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'sb-flex sb-items-center sb-p-5 sb-pt-0', className ) }
		{ ...props }
	/>
) );
CardFooter.displayName = 'CardFooter';
