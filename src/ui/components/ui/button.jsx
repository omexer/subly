import { forwardRef } from '@wordpress/element';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const buttonVariants = cva(
	'sk-inline-flex sk-items-center sk-justify-center sk-gap-2 sk-whitespace-nowrap sk-rounded-md sk-text-sm sk-font-medium sk-transition-colors focus-visible:sk-outline-none focus-visible:sk-ring-2 focus-visible:sk-ring-ring focus-visible:sk-ring-offset-2 disabled:sk-pointer-events-none disabled:sk-opacity-50',
	{
		variants: {
			variant: {
				default:
					'sk-bg-primary sk-text-primary-foreground hover:sk-bg-primary/90',
				secondary:
					'sk-bg-secondary sk-text-secondary-foreground hover:sk-bg-secondary/80',
				outline:
					'sk-border sk-border-input sk-bg-background hover:sk-bg-accent',
				ghost: 'hover:sk-bg-accent hover:sk-text-accent-foreground',
				link: 'sk-text-primary sk-underline-offset-4 hover:sk-underline',
			},
			size: {
				default: 'sk-h-9 sk-px-4 sk-py-2',
				sm: 'sk-h-8 sk-px-3 sk-text-xs',
				lg: 'sk-h-10 sk-px-6',
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
