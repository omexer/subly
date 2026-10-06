import { cn } from '../../lib/utils';

export function Skeleton( { className, ...props } ) {
	return (
		<div
			className={ cn(
				'sb-animate-pulse sb-rounded-md sb-bg-muted',
				className
			) }
			{ ...props }
		/>
	);
}
