import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// Taught the prefix, or it cannot tell sb-p-2 and sb-p-4 are the same property.
const twMerge = extendTailwindMerge( { prefix: 'sb-' } );

export function cn( ...inputs ) {
	return twMerge( clsx( inputs ) );
}
