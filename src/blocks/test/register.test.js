/**
 * The block checkout shows a payment method only if this registers it. These cover which
 * gateways get registered, and the case that made the bug invisible: the server deciding
 * a gateway is not available for this cart.
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';

// Virtual: WooCommerce puts these on the page at runtime, so there is no module on disk
// for jest to find and replace.
jest.mock(
	'@woocommerce/blocks-registry',
	() => ( { registerPaymentMethod: jest.fn() } ),
	{ virtual: true }
);
jest.mock( '@woocommerce/settings', () => ( { getSetting: jest.fn() } ), {
	virtual: true,
} );

const load = () => {
	jest.isolateModules( () => {
		require( '../index' );
	} );
};

describe( 'registering EasySubscription with the block checkout', () => {
	beforeEach( () => {
		registerPaymentMethod.mockClear();
		getSetting.mockReset();
	} );

	it( 'registers both gateways when the server says they are available', () => {
		getSetting.mockImplementation(
			( key ) =>
				( {
					easysubscription_stripe_data: {
						title: 'Credit or debit card',
						description: 'Pay by card.',
						supports: [ 'products' ],
					},
					easysubscription_paypal_data: {
						title: 'PayPal',
						description: 'Pay with PayPal.',
						supports: [ 'products' ],
					},
				} )[ key ] || null
		);

		load();

		expect( registerPaymentMethod ).toHaveBeenCalledTimes( 2 );
		expect(
			registerPaymentMethod.mock.calls.map( ( call ) => call[ 0 ].name )
		).toEqual( [ 'easysubscription_stripe', 'easysubscription_paypal' ] );
		expect( registerPaymentMethod.mock.calls[ 0 ][ 0 ].ariaLabel ).toBe(
			'Credit or debit card'
		);
	} );

	it( 'registers nothing for a gateway the server left out', () => {
		getSetting.mockImplementation( ( key ) =>
			'easysubscription_stripe_data' === key
				? { title: 'Card', description: '', supports: [ 'products' ] }
				: null
		);

		load();

		expect( registerPaymentMethod ).toHaveBeenCalledTimes( 1 );
		expect( registerPaymentMethod.mock.calls[ 0 ][ 0 ].name ).toBe(
			'easysubscription_stripe'
		);
	} );

	it( 'registers nothing at all when no EasySubscription gateway is available', () => {
		getSetting.mockReturnValue( null );

		load();

		expect( registerPaymentMethod ).not.toHaveBeenCalled();
	} );

	it( 'decodes a title the server encoded', () => {
		getSetting.mockImplementation( ( key ) =>
			'easysubscription_stripe_data' === key
				? { title: 'Card &amp; wallet', description: '', supports: [] }
				: null
		);

		load();

		expect( registerPaymentMethod.mock.calls[ 0 ][ 0 ].ariaLabel ).toBe(
			'Card & wallet'
		);
	} );
} );
