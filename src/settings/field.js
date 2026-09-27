import { Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { cn } from '@easysubscription/ui';

const RENAMED = { readonly: 'readOnly', maxlength: 'maxLength' };

// WooCommerce's attributes as React props; inline handlers are dropped, React cannot take them as strings.
function attributes( field ) {
	const props = {};

	Object.entries( field.custom_attributes || {} ).forEach(
		( [ name, value ] ) => {
			if ( /^on/i.test( name ) || ! /^[a-z][a-z-]*$/i.test( name ) ) {
				return;
			}

			props[ RENAMED[ name ] || name ] = value;
		}
	);

	if ( field.placeholder ) {
		props.placeholder = field.placeholder;
	}

	return props;
}

export function Control( { field, value, onChange, described } ) {
	const common = {
		id: field.id,
		'aria-describedby': described ? `${ field.id }-help` : undefined,
	};

	if ( field.type === 'checkbox' ) {
		return (
			<span className="easysubscription-switch">
				<input
					{ ...common }
					type="checkbox"
					role="switch"
					checked={ value === 'yes' }
					onChange={ ( event ) =>
						onChange( event.target.checked ? 'yes' : 'no' )
					}
				/>
				<span
					className="easysubscription-switch__track"
					aria-hidden="true"
				/>
			</span>
		);
	}

	if ( field.type === 'select' ) {
		return (
			<select
				{ ...common }
				className="easysubscription-input"
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				{ field.options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
		);
	}

	const extra = attributes( field );

	if ( field.type === 'textarea' ) {
		return (
			<textarea
				{ ...common }
				{ ...extra }
				className="easysubscription-input easysubscription-input--wide"
				rows="4"
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		);
	}

	return (
		<input
			{ ...common }
			{ ...extra }
			type={ field.type }
			className={ cn(
				'easysubscription-input',
				field.type === 'number'
					? 'easysubscription-input--short'
					: 'easysubscription-input--wide'
			) }
			value={ value }
			onFocus={
				extra.readOnly ? ( event ) => event.target.select() : undefined
			}
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	);
}

export function Row( { field, valueOf, onChange, onEdit, stacked } ) {
	return (
		<div
			className={ cn(
				'easysubscription-settings__row',
				stacked && 'easysubscription-settings__row--stacked'
			) }
		>
			<div className="easysubscription-settings__label">
				<label
					className="easysubscription-settings__title"
					htmlFor={ field.id }
				>
					{ field.title }
				</label>
				{ field.help ? (
					<p
						className="easysubscription-settings__help"
						id={ `${ field.id }-help` }
						// Kept to wp_kses_post() by the server, as the PHP page prints it.
						dangerouslySetInnerHTML={ { __html: field.help } }
					/>
				) : null }
			</div>
			<div className="easysubscription-settings__control">
				{ field.easysubscription_email && onEdit ? (
					<button
						type="button"
						className="easysubscription-btn easysubscription-btn--sm"
						aria-label={ sprintf(
							/* translators: %s: the email's name, such as "Renewal reminder" */
							__( 'Edit %s', 'easysubscription' ),
							field.title
						) }
						onClick={ () => onEdit( field.easysubscription_email ) }
					>
						{ __( 'Edit', 'easysubscription' ) }
					</button>
				) : null }
				<Control
					field={ field }
					value={ valueOf( field.id ) }
					onChange={ ( next ) => onChange( field.id, next ) }
					described={ !! field.help }
				/>
				{ ( field.joined || [] ).map( ( extra ) => (
					<Fragment key={ extra.id }>
						<label
							className="screen-reader-text"
							htmlFor={ extra.id }
						>
							{ extra.title }
						</label>
						<Control
							field={ extra }
							value={ valueOf( extra.id ) }
							onChange={ ( next ) => onChange( extra.id, next ) }
						/>
					</Fragment>
				) ) }
				{ field.suffix ? (
					<span
						className="easysubscription-settings__suffix"
						dangerouslySetInnerHTML={ { __html: field.suffix } }
					/>
				) : null }
			</div>
		</div>
	);
}
