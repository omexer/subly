import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Input = forwardRef(
	( { className, type = 'text', ...props }, ref ) => (
		<input
			ref={ ref }
			type={ type }
			className={ cn(
				'sb-h-9 sb-w-full sb-rounded-md sb-border sb-border-input sb-bg-background sb-px-3 sb-py-1 sb-text-sm sb-shadow-sm sb-transition-colors placeholder:sb-text-muted-foreground focus-visible:sb-outline-none focus-visible:sb-ring-2 focus-visible:sb-ring-ring disabled:sb-cursor-not-allowed disabled:sb-opacity-50',
				className
			) }
			{ ...props }
		/>
	)
);
Input.displayName = 'Input';

export const Select = forwardRef(
	( { className, children, ...props }, ref ) => (
		<select
			ref={ ref }
			className={ cn(
				'sb-h-9 sb-rounded-md sb-border sb-border-input sb-bg-background sb-px-3 sb-text-sm sb-shadow-sm focus-visible:sb-outline-none focus-visible:sb-ring-2 focus-visible:sb-ring-ring disabled:sb-opacity-50',
				className
			) }
			{ ...props }
		>
			{ children }
		</select>
	)
);
Select.displayName = 'Select';

// A real checkbox, styled: a div pretending to be one loses keyboard behaviour, the
// indeterminate state, and every screen reader's idea of what it is.
export const Checkbox = forwardRef( ( { className, ...props }, ref ) => (
	<input
		ref={ ref }
		type="checkbox"
		className={ cn(
			'sb-h-4 sb-w-4 sb-shrink-0 sb-cursor-pointer sb-rounded sb-border-input sb-accent-primary',
			className
		) }
		{ ...props }
	/>
) );
Checkbox.displayName = 'Checkbox';
