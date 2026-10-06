import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Table = forwardRef( ( { className, ...props }, ref ) => (
	<div className="sb-w-full sb-overflow-x-auto">
		<table
			ref={ ref }
			className={ cn(
				'sb-w-full sb-caption-bottom sb-text-sm',
				className
			) }
			{ ...props }
		/>
	</div>
) );
Table.displayName = 'Table';

export const TableHeader = forwardRef( ( { className, ...props }, ref ) => (
	<thead
		ref={ ref }
		className={ cn(
			'[&_tr]:sb-border-b [&_tr]:sb-border-border',
			className
		) }
		{ ...props }
	/>
) );
TableHeader.displayName = 'TableHeader';

export const TableBody = forwardRef( ( { className, ...props }, ref ) => (
	<tbody
		ref={ ref }
		className={ cn( '[&_tr:last-child]:sb-border-0', className ) }
		{ ...props }
	/>
) );
TableBody.displayName = 'TableBody';

export const TableRow = forwardRef( ( { className, ...props }, ref ) => (
	<tr
		ref={ ref }
		className={ cn(
			'sb-border-b sb-border-border sb-transition-colors hover:sb-bg-muted/50',
			className
		) }
		{ ...props }
	/>
) );
TableRow.displayName = 'TableRow';

export const TableHead = forwardRef( ( { className, ...props }, ref ) => (
	<th
		ref={ ref }
		className={ cn(
			'sb-h-10 sb-px-3 sb-text-left sb-align-middle sb-text-xs sb-font-medium sb-text-muted-foreground',
			className
		) }
		{ ...props }
	/>
) );
TableHead.displayName = 'TableHead';

export const TableCell = forwardRef( ( { className, ...props }, ref ) => (
	<td
		ref={ ref }
		className={ cn( 'sb-px-3 sb-py-3 sb-align-middle', className ) }
		{ ...props }
	/>
) );
TableCell.displayName = 'TableCell';
