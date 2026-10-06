import { Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { cn } from '@subly/ui';

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
			<span className="subly-switch">
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
					className="subly-switch__track"
					aria-hidden="true"
				/>
			</span>
		);
	}

	if ( field.type === 'select' ) {
		return (
			<select
				{ ...common }
				className="subly-input"
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
				className="subly-input subly-input--wide"
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
				'subly-input',
				field.type === 'number'
					? 'subly-input--short'
					: 'subly-input--wide'
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
				'subly-settings__row',
				stacked && 'subly-settings__row--stacked'
			) }
		>
			<div className="subly-settings__label">
				<label
					className="subly-settings__title"
					htmlFor={ field.id }
				>
					{ field.title }
				</label>
				{ field.help ? (
					<p
						className="subly-settings__help"
						id={ `${ field.id }-help` }
						// Kept to wp_kses_post() by the server, as the PHP page prints it.
						dangerouslySetInnerHTML={ { __html: field.help } }
					/>
				) : null }
			</div>
			<div className="subly-settings__control">
				{ field.preview_url ? (
					<a
						className="subly-btn subly-btn--sm"
						href={ field.preview_url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Preview', 'subly' ) }
						<span className="screen-reader-text">
							{ ' ' }
							{ sprintf(
								/* translators: %s: the email's name, such as "Renewal reminder" */
								__(
									'%s (opens in a new tab)',
									'subly'
								),
								field.title
							) }
						</span>
					</a>
				) : null }
				{ field.subly_email && onEdit ? (
					<button
						type="button"
						className="subly-btn subly-btn--sm"
						aria-label={ sprintf(
							/* translators: %s: the email's name, such as "Renewal reminder" */
							__( 'Edit %s', 'subly' ),
							field.title
						) }
						onClick={ () => onEdit( field.subly_email ) }
					>
						{ __( 'Edit', 'subly' ) }
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
						className="subly-settings__suffix"
						dangerouslySetInnerHTML={ { __html: field.suffix } }
					/>
				) : null }
			</div>
		</div>
	);
}
