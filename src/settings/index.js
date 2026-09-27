import { __ } from '@wordpress/i18n';
import { PAGE, Settings } from './settings';

window.easysubscription?.shell?.registerRoute( {
	page: PAGE,
	title: __( 'Settings', 'easysubscription' ),
	render: ( { params, setParams } ) => (
		<Settings params={ params } setParams={ setParams } />
	),
} );
