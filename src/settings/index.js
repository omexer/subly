import { __ } from '@wordpress/i18n';
import { PAGE, Settings } from './settings';

export { registerEmailEditor } from './extend';
export { Row } from './field';

window.subly?.shell?.registerRoute( {
	page: PAGE,
	title: __( 'Settings', 'subly' ),
	render: ( { params, setParams } ) => (
		<Settings params={ params } setParams={ setParams } />
	),
} );
