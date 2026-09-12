import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Card = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn(
			'sk-rounded-lg sk-border sk-border-border sk-bg-card sk-text-card-foreground sk-shadow-sm',
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
			'sk-flex sk-flex-col sk-space-y-1.5 sk-p-5 sk-pb-3',
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
			'sk-text-sm sk-font-medium sk-text-muted-foreground',
			className
		) }
		{ ...props }
	/>
) );
CardTitle.displayName = 'CardTitle';

export const CardContent = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'sk-p-5 sk-pt-0', className ) }
		{ ...props }
	/>
) );
CardContent.displayName = 'CardContent';

export const CardFooter = forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'sk-flex sk-items-center sk-p-5 sk-pt-0', className ) }
		{ ...props }
	/>
) );
CardFooter.displayName = 'CardFooter';
