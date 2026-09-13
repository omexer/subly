import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
	'sk-inline-flex sk-items-center sk-gap-1.5 sk-whitespace-nowrap sk-rounded-full sk-border sk-px-2.5 sk-py-0.5 sk-text-xs sk-font-medium sk-transition-colors before:sk-h-1.5 before:sk-w-1.5 before:sk-rounded-full before:sk-bg-current before:sk-content-[""]',
	{
		// Tinted, not solid: a column of saturated pills shouts louder than the data it labels.
		variants: {
			variant: {
				default:
					'sk-border-transparent sk-bg-primary/10 sk-text-primary',
				secondary:
					'sk-border-transparent sk-bg-muted sk-text-muted-foreground',
				success:
					'sk-border-transparent sk-bg-success/10 sk-text-success',
				destructive:
					'sk-border-transparent sk-bg-destructive/10 sk-text-destructive',
				outline: 'sk-text-foreground',
			},
		},
		defaultVariants: { variant: 'default' },
	}
);

export function Badge( { className, variant, ...props } ) {
	return (
		<div
			className={ cn( badgeVariants( { variant } ), className ) }
			{ ...props }
		/>
	);
}

export { badgeVariants };
