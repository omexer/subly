import { cn } from '../../lib/utils';

export function Skeleton( { className, ...props } ) {
	return (
		<div
			className={ cn(
				'sk-animate-pulse sk-rounded-md sk-bg-muted',
				className
			) }
			{ ...props }
		/>
	);
}
