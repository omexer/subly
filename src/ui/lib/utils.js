import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// Taught the prefix, or it cannot tell sk-p-2 and sk-p-4 are the same property.
const twMerge = extendTailwindMerge( { prefix: 'sk-' } );

export function cn( ...inputs ) {
	return twMerge( clsx( inputs ) );
}
