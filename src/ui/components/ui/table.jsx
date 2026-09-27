import { forwardRef } from '@wordpress/element';
import { cn } from '../../lib/utils';

export const Table = forwardRef( ( { className, ...props }, ref ) => (
	<div className="es-w-full es-overflow-x-auto">
		<table
			ref={ ref }
			className={ cn(
				'es-w-full es-caption-bottom es-text-sm',
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
			'[&_tr]:es-border-b [&_tr]:es-border-border',
			className
		) }
		{ ...props }
	/>
) );
TableHeader.displayName = 'TableHeader';

export const TableBody = forwardRef( ( { className, ...props }, ref ) => (
	<tbody
		ref={ ref }
		className={ cn( '[&_tr:last-child]:es-border-0', className ) }
		{ ...props }
	/>
) );
TableBody.displayName = 'TableBody';

export const TableRow = forwardRef( ( { className, ...props }, ref ) => (
	<tr
		ref={ ref }
		className={ cn(
			'es-border-b es-border-border es-transition-colors hover:es-bg-muted/50',
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
			'es-h-10 es-px-3 es-text-left es-align-middle es-text-xs es-font-medium es-text-muted-foreground',
			className
		) }
		{ ...props }
	/>
) );
TableHead.displayName = 'TableHead';

export const TableCell = forwardRef( ( { className, ...props }, ref ) => (
	<td
		ref={ ref }
		className={ cn( 'es-px-3 es-py-3 es-align-middle', className ) }
		{ ...props }
	/>
) );
TableCell.displayName = 'TableCell';
