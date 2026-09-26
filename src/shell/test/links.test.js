/**
 * Which clicks the shell takes over, and which it leaves to the browser.
 */
const ADMIN = 'http://localhost/wp-admin/admin.php';

let router;
let links;
let clickListeners;
let prevented;
let spies;

beforeEach( () => {
	jest.resetModules();
	router = require( '../router' );
	links = require( '../links' );
	window.history.replaceState( {}, '', `${ ADMIN }?page=home` );

	clickListeners = [];
	const add = document.addEventListener.bind( document );
	spies = [ jest.spyOn( document, 'addEventListener' ) ];
	spies[ 0 ].mockImplementation( ( type, fn, options ) => {
		clickListeners.push( [ type, fn ] );
		add( type, fn, options );
	} );

	router.registerRoute( { page: 'home', title: 'Home', render: () => null } );
	router.registerRoute( { page: 'list', title: 'List', render: () => null } );
	router.start( 'home' );
	links.interceptLinks();

	// Registered after the shell's, so it sees what the shell decided; jsdom cannot follow links.
	document.addEventListener( 'click', ( event ) => {
		prevented = event.defaultPrevented;
		event.preventDefault();
	} );

	document.body.innerHTML = `
		<ul id="adminmenu"><li><ul class="wp-submenu">
			<li class="current"><a class="current" aria-current="page" href="admin.php?page=home">Home</a></li>
			<li><a href="admin.php?page=list">List</a></li>
			<li><a href="admin.php?page=other-plugin">Other plugin</a></li>
		</ul></li>
		<li><ul id="wc-menu" class="wp-submenu"><li class="current"><a class="current" href="admin.php?page=wc-orders">Orders</a></li></ul></li></ul>
		<a id="blank" href="admin.php?page=list" target="_blank">New tab</a>
		<a id="product" href="post-new.php?post_type=product">New product</a>
		<a id="hash" href="admin.php?page=list#top">With a fragment</a>
		<a id="own" href="admin.php?page=list"><span>Inner</span></a>
	`;
} );

afterEach( () => {
	clickListeners.forEach( ( [ type, fn ] ) =>
		document.removeEventListener( type, fn )
	);
	spies.forEach( ( spy ) => spy.mockRestore() );
} );

function click( node, init = {} ) {
	prevented = null;
	node.dispatchEvent(
		new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
			button: 0,
			...init,
		} )
	);
}

const menu = ( text ) =>
	[ ...document.querySelectorAll( '#adminmenu a' ) ].find(
		( a ) => a.textContent === text
	);

describe( 'link interception', () => {
	it( 'opens a plugin menu item in place, and moves the menu’s current mark to it', () => {
		click( menu( 'List' ) );

		expect( prevented ).toBe( true );
		expect( window.location.href ).toBe( `${ ADMIN }?page=list` );

		links.highlightMenu( 'list' );

		expect( menu( 'List' ).classList.contains( 'current' ) ).toBe( true );
		expect(
			menu( 'List' ).parentElement.classList.contains( 'current' )
		).toBe( true );
		expect( menu( 'List' ).getAttribute( 'aria-current' ) ).toBe( 'page' );
		expect( menu( 'Home' ).classList.contains( 'current' ) ).toBe( false );
		expect( menu( 'Home' ).hasAttribute( 'aria-current' ) ).toBe( false );
	} );

	it( 'clears the mark from a page in the same menu that has no route, and leaves other menus alone', () => {
		menu( 'Other plugin' ).classList.add( 'current' );
		menu( 'Other plugin' ).parentElement.classList.add( 'current' );

		links.highlightMenu( 'list' );

		expect( menu( 'Other plugin' ).classList.contains( 'current' ) ).toBe(
			false
		);
		expect(
			menu( 'Other plugin' ).parentElement.classList.contains( 'current' )
		).toBe( false );
		expect(
			document
				.querySelector( '#wc-menu a' )
				.classList.contains( 'current' )
		).toBe( true );
	} );

	it( 'follows a click on something inside the link', () => {
		click( document.querySelector( '#own span' ) );

		expect( prevented ).toBe( true );
		expect( router.current().page ).toBe( 'list' );
	} );

	it.each( [
		[ 'ctrl', { ctrlKey: true } ],
		[ 'cmd', { metaKey: true } ],
		[ 'shift', { shiftKey: true } ],
		[ 'alt', { altKey: true } ],
		[ 'middle button', { button: 1 } ],
	] )( 'leaves a %s click to the browser', ( label, init ) => {
		click( menu( 'List' ), init );

		expect( prevented ).toBe( false );
		expect( router.current().page ).toBe( 'home' );
	} );

	it( 'leaves links that open a new tab alone', () => {
		click( document.getElementById( 'blank' ) );

		expect( prevented ).toBe( false );
	} );

	it( 'leaves other plugins’ pages and other admin screens alone', () => {
		click( menu( 'Other plugin' ) );
		expect( prevented ).toBe( false );

		click( document.getElementById( 'product' ) );
		expect( prevented ).toBe( false );

		click( document.getElementById( 'hash' ) );
		expect( prevented ).toBe( false );

		expect( router.current().page ).toBe( 'home' );
		expect( menu( 'Other plugin' ).classList.contains( 'current' ) ).toBe(
			false
		);
	} );

	it( 'leaves a click a screen already handled', () => {
		const link = menu( 'List' );
		link.addEventListener( 'click', ( event ) => event.preventDefault() );

		click( link );

		expect( router.current().page ).toBe( 'home' );
	} );
} );
