import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
	'es-inline-flex es-items-center es-gap-1.5 es-whitespace-nowrap es-rounded-full es-border es-px-2.5 es-py-0.5 es-text-xs es-font-medium es-transition-colors before:es-h-1.5 before:es-w-1.5 before:es-rounded-full before:es-bg-current before:es-content-[""]',
	{
		// Tinted, not solid: a column of saturated pills shouts louder than the data it labels.
		variants: {
			variant: {
				default:
					'es-border-transparent es-bg-primary/10 es-text-primary',
				secondary:
					'es-border-transparent es-bg-muted es-text-muted-foreground',
				success:
					'es-border-transparent es-bg-success/10 es-text-success',
				destructive:
					'es-border-transparent es-bg-destructive/10 es-text-destructive',
				outline: 'es-text-foreground',
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
