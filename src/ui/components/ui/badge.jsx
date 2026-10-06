import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
	'sb-inline-flex sb-items-center sb-gap-1.5 sb-whitespace-nowrap sb-rounded-full sb-border sb-px-2.5 sb-py-0.5 sb-text-xs sb-font-medium sb-transition-colors before:sb-h-1.5 before:sb-w-1.5 before:sb-rounded-full before:sb-bg-current before:sb-content-[""]',
	{
		// Tinted, not solid: a column of saturated pills shouts louder than the data it labels.
		variants: {
			variant: {
				default:
					'sb-border-transparent sb-bg-primary/10 sb-text-primary',
				secondary:
					'sb-border-transparent sb-bg-muted sb-text-muted-foreground',
				success:
					'sb-border-transparent sb-bg-success/10 sb-text-success',
				destructive:
					'sb-border-transparent sb-bg-destructive/10 sb-text-destructive',
				outline: 'sb-text-foreground',
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
