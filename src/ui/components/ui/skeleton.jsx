import { cn } from '../../lib/utils';

export function Skeleton( { className, ...props } ) {
	return (
		<div
			className={ cn(
				'es-animate-pulse es-rounded-md es-bg-muted',
				className
			) }
			{ ...props }
		/>
	);
}
