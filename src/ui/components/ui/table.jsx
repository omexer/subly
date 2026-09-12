import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Table = forwardRef( ( { className, ...props }, ref ) => (
	<div className="sk-w-full sk-overflow-x-auto">
		<table
			ref={ ref }
			className={ cn(
				'sk-w-full sk-caption-bottom sk-text-sm',
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
			'[&_tr]:sk-border-b [&_tr]:sk-border-border',
			className
		) }
		{ ...props }
	/>
) );
TableHeader.displayName = 'TableHeader';

export const TableBody = forwardRef( ( { className, ...props }, ref ) => (
	<tbody
		ref={ ref }
		className={ cn( '[&_tr:last-child]:sk-border-0', className ) }
		{ ...props }
	/>
) );
TableBody.displayName = 'TableBody';

export const TableRow = forwardRef( ( { className, ...props }, ref ) => (
	<tr
		ref={ ref }
		className={ cn(
			'sk-border-b sk-border-border sk-transition-colors hover:sk-bg-muted/50',
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
			'sk-h-10 sk-px-3 sk-text-left sk-align-middle sk-text-xs sk-font-medium sk-text-muted-foreground',
			className
		) }
		{ ...props }
	/>
) );
TableHead.displayName = 'TableHead';

export const TableCell = forwardRef( ( { className, ...props }, ref ) => (
	<td
		ref={ ref }
		className={ cn( 'sk-px-3 sk-py-3 sk-align-middle', className ) }
		{ ...props }
	/>
) );
TableCell.displayName = 'TableCell';
