import { forwardRef } from '@wordpress/element';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const buttonVariants = cva(
	'sb-inline-flex sb-items-center sb-justify-center sb-gap-2 sb-whitespace-nowrap sb-rounded-md sb-text-sm sb-font-medium sb-transition-colors focus-visible:sb-outline-none focus-visible:sb-ring-2 focus-visible:sb-ring-ring focus-visible:sb-ring-offset-2 disabled:sb-pointer-events-none disabled:sb-opacity-50',
	{
		variants: {
			variant: {
				default:
					'sb-bg-primary sb-text-primary-foreground hover:sb-bg-primary/90',
				secondary:
					'sb-bg-secondary sb-text-secondary-foreground hover:sb-bg-secondary/80',
				outline:
					'sb-border sb-border-input sb-bg-background hover:sb-bg-accent',
				destructive:
					'sb-border sb-border-destructive/30 sb-bg-background sb-text-destructive hover:sb-bg-destructive/10',
				ghost: 'hover:sb-bg-accent hover:sb-text-accent-foreground',
				link: 'sb-text-primary sb-underline-offset-4 hover:sb-underline',
			},
			size: {
				default: 'sb-h-9 sb-px-4 sb-py-2',
				sm: 'sb-h-8 sb-px-3 sb-text-xs',
				lg: 'sb-h-10 sb-px-6',
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
