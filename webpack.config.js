const defaults = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Two bundles: the shared UI, and the screen that uses it.
 *
 * SubKit Pro cannot import from this package at build time - it is a separate plugin with
 * its own build - so the shared UI is published on a global and consumed as an external,
 * exactly how WordPress ships wp.components to everyone else.
 */
module.exports = {
	...defaults,
	entry: {
		ui: {
			import: './src/ui/index.js',
			library: { name: [ 'subkit', 'ui' ], type: 'window' },
		},
		overview: './src/overview/index.js',
		subscriptions: './src/subscriptions/index.js',
	},
	externals: {
		...( defaults.externals || {} ),
		'@subkit/ui': [ 'subkit', 'ui' ],
	},
};
