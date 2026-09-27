import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Skeleton } from '@subkit/ui';
import { Row } from './field';

const SAVED_FOR = 4000;

// DOM ids for the email's own keys, which are as plain as "subject".
const PREFIX = 'subkit-email-';

function messageOf( error ) {
	return (
		error?.message ||
		__( 'That did not work. Try again.', 'subkit-subscriptions' )
	);
}

function asRow( field ) {
	return {
		id: PREFIX + field.key,
		type: field.type,
		title: field.title,
		help: field.help || field.label,
		options: field.options,
		placeholder: field.placeholder,
		custom_attributes: {},
		joined: [],
		suffix: '',
	};
}

// One email's own WooCommerce settings, edited without leaving the app. Its switch stays on the list.
export function EmailEditor( { id, onBack, onDirty } ) {
	const [ email, setEmail ] = useState( null );
	const [ drafts, setDrafts ] = useState( {} );
	const [ failure, setFailure ] = useState( '' );
	const [ errors, setErrors ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ toast, setToast ] = useState( '' );
	const [ shown, setShown ] = useState( 0 );

	useEffect( () => {
		setEmail( null );
		setDrafts( {} );
		setFailure( '' );

		apiFetch( {
			path: `/subkit/v1/settings/emails/${ encodeURIComponent( id ) }`,
		} ).then( setEmail, ( error ) => setFailure( messageOf( error ) ) );
	}, [ id ] );

	const dirty = Object.keys( drafts ).length > 0;

	useEffect( () => {
		onDirty( dirty );
	}, [ dirty, onDirty ] );

	useEffect( () => () => onDirty( false ), [ onDirty ] );

	useEffect( () => {
		if ( ! toast ) {
			return undefined;
		}

		const timer = setTimeout( () => setToast( '' ), SAVED_FOR );

		return () => clearTimeout( timer );
	}, [ toast ] );

	const back = (
		<button
			type="button"
			className="subkit-btn subkit-btn--sm sk-self-start"
			onClick={ onBack }
		>
			{ __( '← Back to notifications', 'subkit-subscriptions' ) }
		</button>
	);

	if ( failure ) {
		return (
			<>
				{ back }
				<div className="subkit-notice subkit-notice--bad" role="alert">
					{ failure }
				</div>
			</>
		);
	}

	if ( ! email ) {
		return (
			<section className="subkit-settings__card" aria-busy="true">
				<Skeleton className="sk-my-6 sk-h-40 sk-w-full" />
			</section>
		);
	}

	const saved = {};

	email.fields.forEach( ( field ) => {
		saved[ field.key ] = field.value;
	} );

	const valueOf = ( domId ) => {
		const key = domId.slice( PREFIX.length );

		return key in drafts ? drafts[ key ] : saved[ key ] ?? '';
	};

	const onChange = ( domId, value ) => {
		const key = domId.slice( PREFIX.length );

		setDrafts( ( was ) => {
			const next = { ...was };

			if ( value === saved[ key ] ) {
				delete next[ key ];
			} else {
				next[ key ] = value;
			}

			return next;
		} );
	};

	const save = ( event ) => {
		event.preventDefault();

		if ( ! dirty || saving ) {
			return;
		}

		setSaving( true );
		setErrors( [] );
		setToast( '' );

		apiFetch( {
			path: `/subkit/v1/settings/emails/${ encodeURIComponent( id ) }`,
			method: 'POST',
			data: { values: drafts },
		} )
			.then(
				( result ) => {
					setEmail( result );
					setDrafts( {} );
					setShown( ( was ) => was + 1 );

					if ( result.errors?.length ) {
						setErrors( result.errors );
					} else {
						setToast(
							__( 'Email saved.', 'subkit-subscriptions' )
						);
					}
				},
				( error ) => setErrors( [ messageOf( error ) ] )
			)
			.finally( () => setSaving( false ) );
	};

	// The switch on the list is the one place this email is turned on and off.
	const fields = email.fields.filter( ( field ) => field.key !== 'enabled' );

	return (
		<form className="subkit-settings__main" onSubmit={ save }>
			{ back }
			<section className="subkit-settings__card">
				<header className="subkit-settings__card-head">
					<h2>{ email.title }</h2>
					{ email.description ? <p>{ email.description }</p> : null }
				</header>
				<div className="subkit-settings__rows">
					{ fields.map( ( field ) => (
						<Row
							key={ field.key }
							field={ asRow( field ) }
							valueOf={ valueOf }
							onChange={ onChange }
							stacked={ field.type !== 'checkbox' }
						/>
					) ) }
				</div>
			</section>
			{ email.preview_url ? (
				<section className="subkit-settings__card">
					<header className="subkit-settings__card-head">
						<h2>{ __( 'Preview', 'subkit-subscriptions' ) }</h2>
						<p>
							{ __(
								'The saved email, with sample details. Save to see your changes here.',
								'subkit-subscriptions'
							) }
						</p>
					</header>
					<iframe
						key={ shown }
						className="subkit-email-preview"
						src={ email.preview_url }
						title={ __( 'Email preview', 'subkit-subscriptions' ) }
					/>
				</section>
			) : null }
			{ errors.length ? (
				<div className="subkit-notice subkit-notice--bad" role="alert">
					{ errors.map( ( error ) => (
						<p key={ error } className="sk-m-0">
							{ error }
						</p>
					) ) }
				</div>
			) : null }
			<div className="subkit-settings__save">
				{ email.woo_url ? (
					<a
						className="subkit-settings__save-note"
						href={ email.woo_url }
					>
						{ __( 'Open in WooCommerce', 'subkit-subscriptions' ) }
					</a>
				) : (
					<span />
				) }
				<button
					type="submit"
					className="button button-primary subkit-btn subkit-btn--primary"
					disabled={ ! dirty || saving }
				>
					{ saving
						? __( 'Saving…', 'subkit-subscriptions' )
						: __( 'Save changes', 'subkit-subscriptions' ) }
				</button>
			</div>
			<div
				className="sk-fixed sk-bottom-24 sk-right-8 sk-z-50"
				role="status"
				aria-live="polite"
			>
				{ toast ? (
					<div className="subkit-notice subkit-notice--good sk-m-0 sk-shadow-lg">
						{ toast }
					</div>
				) : null }
			</div>
		</form>
	);
}
