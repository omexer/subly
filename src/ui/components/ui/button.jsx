import { forwardRef } from '@wordpress/element';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const buttonVariants = cva(
	'es-inline-flex es-items-center es-justify-center es-gap-2 es-whitespace-nowrap es-rounded-md es-text-sm es-font-medium es-transition-colors focus-visible:es-outline-none focus-visible:es-ring-2 focus-visible:es-ring-ring focus-visible:es-ring-offset-2 disabled:es-pointer-events-none disabled:es-opacity-50',
	{
		variants: {
			variant: {
				default:
					'es-bg-primary es-text-primary-foreground hover:es-bg-primary/90',
				secondary:
					'es-bg-secondary es-text-secondary-foreground hover:es-bg-secondary/80',
				outline:
					'es-border es-border-input es-bg-background hover:es-bg-accent',
				destructive:
					'es-border es-border-destructive/30 es-bg-background es-text-destructive hover:es-bg-destructive/10',
				ghost: 'hover:es-bg-accent hover:es-text-accent-foreground',
				link: 'es-text-primary es-underline-offset-4 hover:es-underline',
			},
			size: {
				default: 'es-h-9 es-px-4 es-py-2',
				sm: 'es-h-8 es-px-3 es-text-xs',
				lg: 'es-h-10 es-px-6',
			},
		},
		defaultVariants: { variant: 'default', size: 'default' },
	}
);

export const Button = forwardRef(
	( { className, variant, size, asChild = false, ...props }, ref ) => {
		const Comp = asChild ? Slot : 'button';

		return (
			<Comp
				ref={ ref }
				className={ cn(
					buttonVariants( { variant, size } ),
					className
				) }
				{ ...props }
			/>
		);
	}
);
Button.displayName = 'Button';

export { buttonVariants };
