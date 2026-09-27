/**
 * The Help route: where to look first, and the system report to send.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardContent, Skeleton } from '@easysubscription/ui';
import { registerRoute } from '@easysubscription/shell';

function Heading() {
	return (
		<div className="es-mb-6">
			<h2 className="es-text-2xl es-font-semibold es-tracking-tight">
				{ __( 'Help', 'easysubscription' ) }
			</h2>
			<p className="es-mt-1 es-text-sm es-text-muted-foreground">
				{ __(
					'Where to look first, and the report to send if you still need a hand.',
					'easysubscription'
				) }
			</p>
		</div>
	);
}

/**
 * Copies the report, selecting it where the page has no clipboard API (plain http admin).
 *
 * @param {HTMLTextAreaElement} field
 * @return {Promise<void>} Settles once copied.
 */
export function copy( field ) {
	if ( window.navigator.clipboard && window.isSecureContext ) {
		return window.navigator.clipboard.writeText( field.value );
	}

	field.select();
	field.ownerDocument.execCommand( 'copy' );

	return Promise.resolve();
}

export function Help( { onFail } ) {
	const [ data, setData ] = useState( null );
	const [ copied, setCopied ] = useState( false );
	const field = useRef( null );

	useEffect( () => {
		apiFetch( { path: '/easysubscription/v1/help' } )
			.then( setData )
			.catch( () => onFail() );
		// Loaded once: onFail is a new closure on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		if ( ! copied ) {
			return;
		}

		const timer = setTimeout( () => setCopied( false ), 1600 );

		return () => clearTimeout( timer );
	}, [ copied ] );

	if ( ! data ) {
		return (
			<div>
				<Heading />
				<Skeleton className="es-mb-6 es-h-40" />
				<Skeleton className="es-h-64" />
			</div>
		);
	}

	const lines = data.report.split( '\n' ).length;

	return (
		<div>
			<Heading />

			<h2 className="es-mb-3 es-text-base es-font-semibold">
				{ __( 'Check these first', 'easysubscription' ) }
			</h2>
			<div className="es-mb-8 es-grid es-gap-4 es-grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
				{ data.tiles.map( ( tile, index ) => (
					<Card
						key={ tile.url }
						className="es-flex es-flex-col es-gap-3 es-p-5"
					>
						<div className="es-flex es-items-center es-gap-3">
							<span
								className="es-grid es-h-8 es-w-8 es-shrink-0 es-place-items-center es-rounded-full es-bg-accent es-text-sm es-font-semibold es-text-primary"
								aria-hidden="true"
							>
								{ index + 1 }
							</span>
							<h3 className="es-text-sm es-font-medium">
								{ tile.title }
							</h3>
						</div>
						<p className="es-text-sm es-text-muted-foreground">
							{ tile.body }
						</p>
						<div className="es-mt-auto">
							<Button asChild variant="outline" size="sm">
								<a href={ tile.url }>{ tile.label }</a>
							</Button>
						</div>
					</Card>
				) ) }
			</div>

			<h2 className="es-mb-3 es-text-base es-font-semibold">
				{ __( 'System report', 'easysubscription' ) }
			</h2>
			<Card>
				<CardContent className="es-flex es-flex-col es-gap-3 es-p-5">
					<div className="es-flex es-flex-wrap es-items-center es-justify-between es-gap-3">
						<p className="es-text-sm es-text-muted-foreground">
							{ __(
								'Paste this into your support request. It contains no keys and no customer data.',
								'easysubscription'
							) }
						</p>
						<Button
							size="sm"
							onClick={ () =>
								copy( field.current ).then( () =>
									setCopied( true )
								)
							}
						>
							{ copied
								? __( 'Copied', 'easysubscription' )
								: __( 'Copy report', 'easysubscription' ) }
						</Button>
					</div>
					<textarea
						ref={ field }
						readOnly
						aria-label={ __( 'System report', 'easysubscription' ) }
						rows={ Math.min( 24, lines ) }
						value={ data.report }
						className="es-w-full es-rounded-md es-border es-border-input es-bg-muted/40 es-p-3 es-font-mono es-text-xs"
					/>
				</CardContent>
			</Card>
		</div>
	);
}

registerRoute( {
	page: 'easysubscription-help',
	title: __( 'Help', 'easysubscription' ),
	render: ( ctx ) => <Help onFail={ ctx.fail } />,
} );
