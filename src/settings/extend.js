let emailEditor = null;

/**
 * An editor for one email, drawn in place of the page when a row's Edit is pressed.
 *
 * @param {Function} component Takes { id, onBack, onDirty }.
 */
export function registerEmailEditor( component ) {
	emailEditor = component;
}

export function getEmailEditor() {
	return emailEditor;
}
