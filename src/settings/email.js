import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Skeleton } from '@easysubscription/ui';
import { Row } from './field';

const SAVED_FOR = 4000;

// DOM ids for the email's own keys, which are as plain as "subject".
const PREFIX = 'easysubscription-email-';

function messageOf( error ) {
	return (
		error?.message ||
		__( 'That did not work. Try again.', 'easysubscription' )
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
			path: `/easysubscription/v1/settings/emails/${ encodeURIComponent(
				id
			) }`,
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
			className="easysubscription-btn easysubscription-btn--sm es-self-start"
			onClick={ onBack }
		>
			{ __( '← Back to notifications', 'easysubscription' ) }
		</button>
	);

	if ( failure ) {
		return (
			<>
				{ back }
				<div
					className="easysubscription-notice easysubscription-notice--bad"
					role="alert"
				>
					{ failure }
				</div>
			</>
		);
	}

	if ( ! email ) {
		return (
			<section
				className="easysubscription-settings__card"
				aria-busy="true"
			>
				<Skeleton className="es-my-6 es-h-40 es-w-full" />
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
			path: `/easysubscription/v1/settings/emails/${ encodeURIComponent(
				id
			) }`,
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
						setToast( __( 'Email saved.', 'easysubscription' ) );
					}
				},
				( error ) => setErrors( [ messageOf( error ) ] )
			)
			.finally( () => setSaving( false ) );
	};

	// The switch on the list is the one place this email is turned on and off.
	const fields = email.fields.filter( ( field ) => field.key !== 'enabled' );

	return (
		<form className="easysubscription-settings__main" onSubmit={ save }>
			{ back }
			<section className="easysubscription-settings__card">
				<header className="easysubscription-settings__card-head">
					<h2>{ email.title }</h2>
					{ email.description ? <p>{ email.description }</p> : null }
				</header>
				<div className="easysubscription-settings__rows">
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
				<section className="easysubscription-settings__card">
					<header className="easysubscription-settings__card-head">
						<h2>{ __( 'Preview', 'easysubscription' ) }</h2>
						<p>
							{ __(
								'The saved email, with sample details. Save to see your changes here.',
								'easysubscription'
							) }
						</p>
					</header>
					<iframe
						key={ shown }
						className="easysubscription-email-preview"
						src={ email.preview_url }
						title={ __( 'Email preview', 'easysubscription' ) }
					/>
				</section>
			) : null }
			{ errors.length ? (
				<div
					className="easysubscription-notice easysubscription-notice--bad"
					role="alert"
				>
					{ errors.map( ( error ) => (
						<p key={ error } className="es-m-0">
							{ error }
						</p>
					) ) }
				</div>
			) : null }
			<div className="easysubscription-settings__save">
				{ email.woo_url ? (
					<a
						className="easysubscription-settings__save-note"
						href={ email.woo_url }
					>
						{ __( 'Open in WooCommerce', 'easysubscription' ) }
					</a>
				) : (
					<span />
				) }
				<button
					type="submit"
					className="button button-primary easysubscription-btn easysubscription-btn--primary"
					disabled={ ! dirty || saving }
				>
					{ saving
						? __( 'Saving…', 'easysubscription' )
						: __( 'Save changes', 'easysubscription' ) }
				</button>
			</div>
			<div
				className="es-fixed es-bottom-24 es-right-8 es-z-50"
				role="status"
				aria-live="polite"
			>
				{ toast ? (
					<div className="easysubscription-notice easysubscription-notice--good es-m-0 es-shadow-lg">
						{ toast }
					</div>
				) : null }
			</div>
		</form>
	);
}
