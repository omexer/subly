/**
 * The detail screen can charge a card and move a billing date, so these cover the
 * confirmation, what a date change sends, and what happens when the id is not real.
 */
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Detail } from '../detail';

jest.mock( '@wordpress/api-fetch' );

const SUBSCRIPTION = {
	id: 812,
	status: 'sk-active',
	status_label: 'Active',
	customer_name: 'Ada Lovelace',
	customer_email: 'ada@example.com',
	total_formatted: '£24.00',
	billing_interval: 1,
	billing_period: 'month',
	next_payment: '2026-10-12 09:00:00',
	next_payment_formatted: '12 October 2026',
	end_date: '',
	trial_end: '',
	payment_method_title: 'Stripe',
	payment_method: 'subkit_stripe',
	parent_order_id: 811,
	billable: true,
};

const ACTIVITY = [
	{
		type: 'status_change',
		message: 'Activated.',
		actor: 'system',
		date: '2026-09-12 08:00:00',
	},
];

function respond( subscription = SUBSCRIPTION ) {
	apiFetch.mockImplementation( ( options ) => {
		if ( options.path.endsWith( '/activity' ) ) {
			return Promise.resolve( ACTIVITY );
		}

		return Promise.resolve( subscription );
	} );
}

let container;

async function render( props = {} ) {
	container = document.createElement( 'div' );
	document.body.appendChild( container );

	await act( async () => {
		createRoot( container ).render(
			<Detail
				id={ 812 }
				onReady={ () => {} }
				onFail={ () => {} }
				{ ...props }
			/>
		);
	} );
}

const byText = ( text ) =>
	[ ...container.querySelectorAll( 'button' ) ].find(
		( node ) => node.textContent.trim() === text
	);

describe( 'the subscription detail screen', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		document.body.innerHTML = '';
		delete window.confirm;
	} );

	it( 'shows the facts and the activity', async () => {
		respond();
		await render();

		expect( container.textContent ).toContain( 'Subscription #812' );
		expect( container.textContent ).toContain( 'Ada Lovelace' );
		expect( container.textContent ).toContain( '£24.00' );
		expect( container.textContent ).toContain( 'every month' );
		expect( container.textContent ).toContain( '#811' );
		expect( container.textContent ).toContain( 'Activated.' );
	} );

	it( 'asks before charging, and charges through the same action the scheduler uses', async () => {
		respond();
		await render();

		window.confirm = jest.fn( () => true );
		apiFetch.mockClear();

		await act( async () => {
			byText( 'Renew now' ).click();
		} );

		expect( window.confirm ).toHaveBeenCalledWith(
			expect.stringContaining( '£24.00' )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toEqual( {
			path: '/subkit/v1/subscriptions/812/actions',
			method: 'POST',
			data: { action: 'renew_now' },
		} );
	} );

	it( 'does not charge when the confirmation is refused', async () => {
		respond();
		await render();

		window.confirm = jest.fn( () => false );
		apiFetch.mockClear();

		await act( async () => {
			byText( 'Renew now' ).click();
		} );

		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'sends a changed date back in the format the endpoint reads', async () => {
		respond();
		await render();

		apiFetch.mockClear();

		const field = container.querySelector( '#subkit-next-payment' );

		expect( field.value ).toBe( '2026-10-12T09:00' );

		// React overrides the value setter, so assigning to .value directly never reaches
		// onChange - the native setter is what a real keystroke ends up calling.
		const nativeValue = Object.getOwnPropertyDescriptor(
			window.HTMLInputElement.prototype,
			'value'
		).set;

		await act( async () => {
			nativeValue.call( field, '2026-11-01T10:30' );
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );

		await act( async () => {
			byText( 'Save the schedule' ).click();
		} );

		expect( apiFetch.mock.calls[ 0 ][ 0 ].data.next_payment ).toBe(
			'2026-11-01 10:30'
		);
	} );

	it( 'hands back to the server-rendered screen when the id is not real', async () => {
		apiFetch.mockRejectedValue( { code: 'subkit_not_found' } );
		const onFail = jest.fn();

		await render( { onFail } );

		expect( onFail ).toHaveBeenCalled();
	} );

	it( 'hides Renew now on a subscription that cannot be billed', async () => {
		respond( {
			...SUBSCRIPTION,
			billable: false,
			status: 'sk-cancelled',
			status_label: 'Cancelled',
		} );
		await render();

		expect( byText( 'Renew now' ) ).toBeUndefined();
		expect( byText( 'Cancel subscription' ) ).toBeTruthy();
	} );
} );
