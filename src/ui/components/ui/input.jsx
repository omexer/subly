import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Input = forwardRef(
	( { className, type = 'text', ...props }, ref ) => (
		<input
			ref={ ref }
			type={ type }
			className={ cn(
				'es-h-9 es-w-full es-rounded-md es-border es-border-input es-bg-background es-px-3 es-py-1 es-text-sm es-shadow-sm es-transition-colors placeholder:es-text-muted-foreground focus-visible:es-outline-none focus-visible:es-ring-2 focus-visible:es-ring-ring disabled:es-cursor-not-allowed disabled:es-opacity-50',
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
				'es-h-9 es-rounded-md es-border es-border-input es-bg-background es-px-3 es-text-sm es-shadow-sm focus-visible:es-outline-none focus-visible:es-ring-2 focus-visible:es-ring-ring disabled:es-opacity-50',
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
			'es-h-4 es-w-4 es-shrink-0 es-cursor-pointer es-rounded es-border-input es-accent-primary',
			className
		) }
		{ ...props }
	/>
) );
Checkbox.displayName = 'Checkbox';
