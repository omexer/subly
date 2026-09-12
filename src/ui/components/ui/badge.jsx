import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
	'sk-inline-flex sk-items-center sk-rounded-full sk-border sk-px-2.5 sk-py-0.5 sk-text-xs sk-font-medium sk-transition-colors',
	{
		variants: {
			variant: {
				default:
					'sk-border-transparent sk-bg-primary sk-text-primary-foreground',
				secondary:
					'sk-border-transparent sk-bg-secondary sk-text-secondary-foreground',
				success:
					'sk-border-transparent sk-bg-success sk-text-success-foreground',
				destructive:
					'sk-border-transparent sk-bg-destructive sk-text-destructive-foreground',
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
