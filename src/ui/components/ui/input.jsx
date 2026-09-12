import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Input = forwardRef(
	( { className, type = 'text', ...props }, ref ) => (
		<input
			ref={ ref }
			type={ type }
			className={ cn(
				'sk-h-9 sk-w-full sk-rounded-md sk-border sk-border-input sk-bg-background sk-px-3 sk-py-1 sk-text-sm sk-shadow-sm sk-transition-colors placeholder:sk-text-muted-foreground focus-visible:sk-outline-none focus-visible:sk-ring-2 focus-visible:sk-ring-ring disabled:sk-cursor-not-allowed disabled:sk-opacity-50',
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
				'sk-h-9 sk-rounded-md sk-border sk-border-input sk-bg-background sk-px-3 sk-text-sm sk-shadow-sm focus-visible:sk-outline-none focus-visible:sk-ring-2 focus-visible:sk-ring-ring disabled:sk-opacity-50',
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
			'sk-h-4 sk-w-4 sk-shrink-0 sk-cursor-pointer sk-rounded sk-border-input sk-accent-primary',
			className
		) }
		{ ...props }
	/>
) );
Checkbox.displayName = 'Checkbox';
