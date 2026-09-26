/**
 * The Help route: where to look first, and the system report to send.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardContent, Skeleton } from '@subkit/ui';
import { registerRoute } from '@subkit/shell';

function Heading() {
	return (
		<div className="sk-mb-6">
			<h2 className="sk-text-2xl sk-font-semibold sk-tracking-tight">
				{ __( 'Help', 'subkit-subscriptions' ) }
			</h2>
			<p className="sk-mt-1 sk-text-sm sk-text-muted-foreground">
				{ __(
					'Where to look first, and the report to send if you still need a hand.',
					'subkit-subscriptions'
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
		apiFetch( { path: '/subkit/v1/help' } )
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
				<Skeleton className="sk-mb-6 sk-h-40" />
				<Skeleton className="sk-h-64" />
			</div>
		);
	}

	const lines = data.report.split( '\n' ).length;

	return (
		<div>
			<Heading />

			<h2 className="sk-mb-3 sk-text-base sk-font-semibold">
				{ __( 'Check these first', 'subkit-subscriptions' ) }
			</h2>
			<div className="sk-mb-8 sk-grid sk-gap-4 sk-grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
				{ data.tiles.map( ( tile, index ) => (
					<Card
						key={ tile.url }
						className="sk-flex sk-flex-col sk-gap-3 sk-p-5"
					>
						<div className="sk-flex sk-items-center sk-gap-3">
							<span
								className="sk-grid sk-h-8 sk-w-8 sk-shrink-0 sk-place-items-center sk-rounded-full sk-bg-accent sk-text-sm sk-font-semibold sk-text-primary"
								aria-hidden="true"
							>
								{ index + 1 }
							</span>
							<h3 className="sk-text-sm sk-font-medium">
								{ tile.title }
							</h3>
						</div>
						<p className="sk-text-sm sk-text-muted-foreground">
							{ tile.body }
						</p>
						<div className="sk-mt-auto">
							<Button asChild variant="outline" size="sm">
								<a href={ tile.url }>{ tile.label }</a>
							</Button>
						</div>
					</Card>
				) ) }
			</div>

			<h2 className="sk-mb-3 sk-text-base sk-font-semibold">
				{ __( 'System report', 'subkit-subscriptions' ) }
			</h2>
			<Card>
				<CardContent className="sk-flex sk-flex-col sk-gap-3 sk-p-5">
					<div className="sk-flex sk-flex-wrap sk-items-center sk-justify-between sk-gap-3">
						<p className="sk-text-sm sk-text-muted-foreground">
							{ __(
								'Paste this into your support request. It contains no keys and no customer data.',
								'subkit-subscriptions'
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
								? __( 'Copied', 'subkit-subscriptions' )
								: __( 'Copy report', 'subkit-subscriptions' ) }
						</Button>
					</div>
					<textarea
						ref={ field }
						readOnly
						aria-label={ __(
							'System report',
							'subkit-subscriptions'
						) }
						rows={ Math.min( 24, lines ) }
						value={ data.report }
						className="sk-w-full sk-rounded-md sk-border sk-border-input sk-bg-muted/40 sk-p-3 sk-font-mono sk-text-xs"
					/>
				</CardContent>
			</Card>
		</div>
	);
}

registerRoute( {
	page: 'subkit-subscriptions-help',
	title: __( 'Help', 'subkit-subscriptions' ),
	render: ( ctx ) => <Help onFail={ ctx.fail } />,
} );
