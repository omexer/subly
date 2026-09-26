import { __ } from '@wordpress/i18n';
import { PAGE, Settings } from './settings';

window.subkit?.shell?.registerRoute( {
	page: PAGE,
	title: __( 'Settings', 'subkit-subscriptions' ),
	render: ( { params, setParams } ) => (
		<Settings params={ params } setParams={ setParams } />
	),
} );
