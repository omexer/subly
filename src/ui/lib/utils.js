import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// Taught the prefix, or it cannot tell es-p-2 and es-p-4 are the same property.
const twMerge = extendTailwindMerge( { prefix: 'es-' } );

export function cn( ...inputs ) {
	return twMerge( clsx( inputs ) );
}
