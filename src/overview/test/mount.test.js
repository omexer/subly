/**
 * The overview replaces a working server-rendered summary. These cover the two outcomes
 * that decide whether that is safe: data arrives, or the request fails.
 */
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { mount } from '../index';

jest.mock( '@wordpress/api-fetch' );

const DATA = {
	mrr: { formatted: '£1,240.00', minor: 124000, change: 12.5 },
	active: 42,
	excluded: 3,
	statuses: [
		{ key: 'sk-active', label: 'Active', count: 40, colour: '#00a32a' },
		{ key: 'sk-on-hold', label: 'On hold', count: 2, colour: '#d63638' },
		{ key: 'sk-expired', label: 'Expired', count: 0, colour: '#646970' },
	],
	history: [
		{ date: '2026-08-01', mrr: 110000, active: 38 },
		{ date: '2026-09-01', mrr: 124000, active: 42 },
	],
};

function fallbackNode() {
	document.body.innerHTML =
		'<div id="subkit-overview-fallback">server rendered</div>';

	return document.getElementById( 'subkit-overview-fallback' );
}

describe( 'the overview', () => {
	it( 'shows the figures and hides the server-rendered copy once data arrives', async () => {
		apiFetch.mockResolvedValue( DATA );

		const fallback = fallbackNode();
		let node;

		await act( async () => {
			node = mount( fallback );
		} );

		expect( node.textContent ).toContain( '£1,240.00' );
		expect( node.textContent ).toContain( '+12.5%' );
		expect( node.textContent ).toContain( '42' );
		expect( node.textContent ).toContain( 'in another currency' );
		expect( fallback.hidden ).toBe( true );
	} );

	it( 'leaves only the statuses that have subscriptions in them', async () => {
		apiFetch.mockResolvedValue( DATA );

		let node;

		await act( async () => {
			node = mount( fallbackNode() );
		} );

		expect( node.textContent ).toContain( 'Active' );
		expect( node.textContent ).toContain( 'On hold' );
		expect( node.textContent ).not.toContain( 'Expired' );
	} );

	it( 'keeps the server-rendered summary when the request fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'nope' ) );

		const fallback = fallbackNode();

		await act( async () => {
			mount( fallback );
		} );

		expect( fallback.hidden ).toBe( false );
		expect( document.body.textContent ).toContain( 'server rendered' );
		expect( document.querySelectorAll( '.subkit-ui' ) ).toHaveLength( 0 );
	} );

	it( 'does nothing at all without the fallback node', () => {
		expect( mount( null ) ).toBeNull();
	} );
} );
