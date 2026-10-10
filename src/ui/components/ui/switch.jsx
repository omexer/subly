import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

// A real checkbox under the track, as in the settings screen's toggle, so keyboards, forms and screen readers all work.
export const Switch = forwardRef(
	( { className, onChange, onCheckedChange, ...props }, ref ) => (
		<span
			className={ cn(
				'sb-relative sb-inline-block sb-h-[22px] sb-w-10 sb-shrink-0 sb-align-middle',
				className
			) }
		>
			<input
				ref={ ref }
				type="checkbox"
				role="switch"
				className="sb-peer sb-absolute sb-inset-0 sb-z-10 sb-m-0 sb-h-full sb-w-full sb-cursor-pointer sb-opacity-0 disabled:sb-cursor-not-allowed"
				onChange={ ( event ) => {
					onChange?.( event );
					onCheckedChange?.( event.target.checked );
				} }
				{ ...props }
			/>
			<span
				aria-hidden="true"
				className="sb-pointer-events-none sb-absolute sb-inset-0 sb-rounded-full sb-bg-muted-foreground/70 sb-transition-colors peer-checked:sb-bg-primary peer-focus-visible:sb-ring-2 peer-focus-visible:sb-ring-ring peer-focus-visible:sb-ring-offset-2 peer-disabled:sb-opacity-50 motion-reduce:sb-transition-none"
			/>
			<span
				aria-hidden="true"
				className="sb-pointer-events-none sb-absolute sb-left-[3px] sb-top-[3px] sb-h-4 sb-w-4 sb-rounded-full sb-bg-background sb-shadow-sm sb-transition-[left] peer-checked:sb-left-[21px] motion-reduce:sb-transition-none"
			/>
		</span>
	)
);
Switch.displayName = 'Switch';
