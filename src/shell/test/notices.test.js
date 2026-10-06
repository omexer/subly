/**
 * Important Subly notices stay under the header as compact cards; every other notice goes in the bell.
 */
const ADMIN = 'http://localhost/wp-admin/admin.php';

let router;
let app;
let root;
let act;
let added;

const important = ( id, text, extra = '' ) =>
	`<div id="${ id }" class="notice notice-warning subly-notice--important ${ extra }"><p><strong>${ text }</strong></p><ul><li>Why it matters, at some length so the card needs its details to show the whole story of what went wrong.</li><li>Second reason.</li></ul><p><a class="button" href="admin.php?page=elsewhere">Fix ${ id }</a></p></div>`;

const SERVER = `
	<ul id="adminmenu"><li><ul class="wp-submenu">
		<li class="current"><a class="current" href="admin.php?page=first">First</a></li>
		<li><a href="admin.php?page=second">Second</a></li>
	</ul></li></ul>
	<div id="wpbody-content">
		<div class="notice notice-info" id="top-other"><p>Another plugin, printed at the top.</p></div>
		<div id="subly-app" class="subly-ui" data-page="first"></div><div id="subly-fallback">
			<header><details class="subly-notify" data-subly-notify><summary class="subly-notify__bell">1</summary>
				<div class="subly-notify__panel"><div class="subly-notify__list"><div class="notice notice-warning" id="tucked"><p>Subly Pro is in test licence mode.</p></div><script>window.ranTucked = true;</script></div></div>
			</details></header>
			<div class="wrap">
				<h1>First</h1>
				<hr class="wp-header-end">
				${ important( 'a', 'PayPal renewals will not be recorded.' ) }
				${ important( 'b', 'Tax added twice.' ) }
				${ important( 'c', 'WooPayments cannot renew.' ) }
				${ important( 'd', 'Unapplied plans.' ) }
				<div id="saved" class="notice notice-success is-dismissible subly-notice--important subly-notice--feedback"><p>3 subscriptions updated.</p></div>
				<p>Server first</p>
			</div>
		</div>
	</div>
	<a id="outside" href="#x">Outside</a>
`;

async function boot() {
	jest.resetModules();
	act = require( 'react' ).act;
	router = require( '../router' );
	app = require( '../app' );
	router.registerRoute( {
		page: 'first',
		title: 'First',
		render: () => <p>Route one</p>,
	} );
	router.registerRoute( {
		page: 'second',
		title: 'Second',
		render: () => (
			<div className="notice notice-error" id="route-own">
				<p>The route’s own.</p>
			</div>
		),
	} );

	await act( async () => {
		root = app.boot();
	} );
}

async function fire( node, type, init = {} ) {
	await act( async () => {
		node.dispatchEvent(
			new window.MouseEvent( type, {
				bubbles: true,
				cancelable: true,
				button: 0,
				...init,
			} )
		);
	} );
}

async function key( node, name, init = {} ) {
	await act( async () => {
		node.dispatchEvent(
			new window.KeyboardEvent( 'keydown', {
				key: name,
				bubbles: true,
				cancelable: true,
				...init,
			} )
		);
	} );
}

const $ = ( selector ) => document.querySelector( selector );
const ids = ( selector ) =>
	[ ...document.querySelectorAll( `${ selector } > [id]` ) ].map(
		( node ) => node.id
	);
const bell = () => $( '.subly-notify__bell' );
const panel = () => $( '#subly-notify-panel' );

beforeEach( () => {
	added = [];
	const add = document.addEventListener.bind( document );
	jest.spyOn( document, 'addEventListener' ).mockImplementation(
		( type, fn, options ) => {
			added.push( [ type, fn ] );
			add( type, fn, options );
		}
	);
	window.history.replaceState( {}, '', `${ ADMIN }?page=first` );
	document.body.innerHTML = SERVER;
	document.body.className = 'js subly-app-page';
	window.sublyShellData = { links: {} };
	window.scrollTo = jest.fn();
} );

afterEach( async () => {
	if ( root ) {
		await act( async () => root.unmount() );
	}
	root = null;
	added.forEach( ( [ type, fn ] ) =>
		document.removeEventListener( type, fn )
	);
	jest.restoreAllMocks();
} );

describe( 'notices on an app page', () => {
	it( 'keeps three important notices under the header and puts the rest in the bell', async () => {
		await boot();

		expect( ids( '.subly-alerts' ) ).toEqual( [
			'a',
			'b',
			'c',
		] );
		expect( ids( '.subly-notify__list' ) ).toEqual( [
			'd',
			'saved',
			'tucked',
			'top-other',
		] );
		expect( bell().textContent ).toBe( '4' );
		expect( bell().getAttribute( 'aria-label' ) ).toBe( '4 notifications' );
		expect( $( '.subly-alerts__more' ).textContent ).toBe(
			'and 2 more'
		);
		expect( $( '#subly-fallback' ).hidden ).toBe( true );
	} );

	it( 'draws each important notice as one compact card, with its action on the row and its details a click away', async () => {
		await boot();

		const card = $( '#a' );

		expect( card.classList.contains( 'subly-alert' ) ).toBe(
			true
		);
		expect(
			card.classList.contains( 'subly-alert--warning' )
		).toBe( true );
		expect(
			card.querySelector( '.subly-alert__icon svg' )
		).not.toBeNull();
		expect(
			card.querySelector( '.subly-alert__title' ).textContent
		).toBe( 'PayPal renewals will not be recorded.' );
		expect(
			card.querySelector( '.subly-alert__detail' ).textContent
		).toContain( 'Why it matters' );
		expect(
			card.querySelector( '.subly-alert__actions a.button' )
				.textContent
		).toBe( 'Fix a' );

		const more = card.querySelector( '.subly-alert__more' );
		expect( more.getAttribute( 'aria-expanded' ) ).toBe( 'false' );

		await fire( more, 'click' );

		expect( card.classList.contains( 'is-open' ) ).toBe( true );
		expect( more.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'takes the first sentence as the title when a notice has no bold lead, and keeps its own dismiss link as the close button', async () => {
		document.querySelector( '#b' ).outerHTML =
			'<div id="b" class="notice notice-error subly-notice--important"><p>Subly: 2 subscriptions renew with tax added twice. Customers may be owed refunds.</p><p><a class="button button-primary" href="#review">Review and repair</a> <a class="button" href="admin-post.php?action=subly_dismiss_notice&amp;notice=x">Dismiss</a></p></div>';

		await boot();

		const card = $( '#b' );

		expect(
			card.querySelector( '.subly-alert__title' ).textContent
		).toBe(
			'Subly: 2 subscriptions renew with tax added twice.'
		);
		expect(
			card
				.querySelector( '.subly-alert__detail' )
				.textContent.trim()
		).toBe( 'Customers may be owed refunds.' );
		expect(
			card.querySelector( '.subly-alert__more' )
		).toBeNull();
		expect(
			card.classList.contains( 'subly-alert--error' )
		).toBe( true );

		const dismiss = card.querySelector(
			'.subly-alert__dismiss'
		);
		expect( dismiss.getAttribute( 'href' ) ).toContain(
			'action=subly_dismiss_notice'
		);
		expect( dismiss.textContent ).toBe( 'Dismiss' );
	} );

	it( 'catches notices printed or moved in after load, and never the route’s own', async () => {
		await boot();

		await act( async () => {
			const late = document.createElement( 'div' );
			late.className = 'notice notice-warning';
			late.id = 'late';
			late.innerHTML = '<p>Milo Subscriptions: Staging Site Detected</p>';
			$( '#wpbody-content' ).prepend( late );
			await Promise.resolve();
		} );

		expect( ids( '.subly-notify__list' ) ).toContain( 'late' );
		expect( bell().textContent ).toBe( '5' );

		// What WordPress does on ready: every notice, ours too, under the header marker.
		await act( async () => {
			const marker = $( '.subly-app__landing .wp-header-end' );
			[ ...document.querySelectorAll( 'div.notice' ) ].forEach(
				( node ) => marker.after( node )
			);
			await Promise.resolve();
		} );

		expect( ids( '.subly-alerts' ) ).toEqual( [
			'a',
			'b',
			'c',
		] );
		expect( ids( '.subly-notify__list' ) ).toEqual( [
			'd',
			'saved',
			'tucked',
			'top-other',
			'late',
		] );
		expect( ids( '.subly-app__landing' ) ).toEqual( [] );

		await fire( $( 'a[href="admin.php?page=second"]' ), 'click' );
		await act( async () => Promise.resolve() );

		expect( $( '.subly-app__route #route-own' ) ).not.toBeNull();
		expect( ids( '.subly-notify__list' ) ).not.toContain(
			'route-own'
		);
	} );

	it( 'counts a notice that is dismissed away', async () => {
		await boot();

		await act( async () => {
			$( '#tucked' ).remove();
			await Promise.resolve();
		} );

		expect( bell().textContent ).toBe( '3' );

		await act( async () => {
			$( '#a' ).remove();
			await Promise.resolve();
		} );

		expect( ids( '.subly-alerts' ) ).toEqual( [
			'b',
			'c',
			'd',
		] );
		expect( $( '.subly-alerts__more' ).textContent ).toBe(
			'and 1 more'
		);
		expect( bell().textContent ).toBe( '2' );
	} );

	it( 'opens and closes the notification centre by click, Escape and a click outside', async () => {
		await boot();

		expect( bell().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( bell().getAttribute( 'aria-controls' ) ).toBe(
			'subly-notify-panel'
		);
		expect( panel().hidden ).toBe( true );

		await fire( bell(), 'click' );

		expect( panel().hidden ).toBe( false );
		expect( bell().getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( document.activeElement ).toBe( panel() );

		await key( panel(), 'Escape' );

		expect( panel().hidden ).toBe( true );
		expect( document.activeElement ).toBe( bell() );

		await fire( bell(), 'click' );
		await fire( $( '#saved' ), 'mousedown' );

		expect( panel().hidden ).toBe( false );

		await fire( $( '#outside' ), 'mousedown' );

		expect( panel().hidden ).toBe( true );

		await fire( bell(), 'click' );
		await fire( bell(), 'click' );

		expect( panel().hidden ).toBe( true );
	} );

	it( 'keeps keyboard focus inside the open centre', async () => {
		await boot();
		await fire( bell(), 'click' );

		const links = [ ...panel().querySelectorAll( 'a[href], button' ) ];
		const first = links[ 0 ];
		const last = links[ links.length - 1 ];

		expect( links.length ).toBeGreaterThan( 1 );

		await key( panel(), 'Tab', { shiftKey: true } );
		expect( document.activeElement ).toBe( last );

		await key( last, 'Tab' );
		expect( document.activeElement ).toBe( first );
	} );

	it( 'opens the centre from “and N more”', async () => {
		await boot();
		await fire( $( '.subly-alerts__more' ), 'click' );

		expect( panel().hidden ).toBe( false );
		expect( panel().contains( $( '#d' ) ) ).toBe( true );
	} );

	it( 'drops only the last page’s action feedback on moving to another route', async () => {
		await boot();
		await fire( $( 'a[href="admin.php?page=second"]' ), 'click' );

		expect( $( '#saved' ) ).toBeNull();
		expect( ids( '.subly-alerts' ) ).toEqual( [
			'a',
			'b',
			'c',
		] );
		expect( ids( '.subly-notify__list' ) ).toEqual( [
			'd',
			'tucked',
			'top-other',
		] );
		expect( $( '#subly-fallback' ) ).toBeNull();
	} );

	it( 'shows no bell when there is nothing to put in it', async () => {
		document.body.innerHTML = `<div id="wpbody-content"><div id="subly-app" data-page="first"></div><div id="subly-fallback"><div class="wrap"><hr class="wp-header-end">${ important(
			'a',
			'Only one.'
		) }</div></div></div>`;

		await boot();

		expect( bell() ).toBeNull();
		expect( ids( '.subly-alerts' ) ).toEqual( [ 'a' ] );
		expect( $( '.subly-alerts__more' ) ).toBeNull();
	} );

	it( 'puts every notice back where the server printed it when the first route fails', async () => {
		jest.resetModules();
		act = require( 'react' ).act;
		router = require( '../router' );
		app = require( '../app' );

		function Fails( { fail } ) {
			require( '@wordpress/element' ).useEffect( () => fail(), [ fail ] );
			return null;
		}

		router.registerRoute( {
			page: 'first',
			title: 'First',
			render: ( ctx ) => <Fails fail={ ctx.fail } />,
		} );

		await act( async () => {
			root = app.boot();
		} );

		expect( $( '#subly-fallback' ).hidden ).toBe( false );
		expect(
			document.body.classList.contains( 'subly-app-page' )
		).toBe( false );
		expect( $( '.subly-notify__list #tucked' ) ).not.toBeNull();
		expect( $( '#wpbody-content > #top-other' ) ).not.toBeNull();
		expect( $( '#subly-fallback .wrap > #a' ) ).not.toBeNull();
	} );
} );
