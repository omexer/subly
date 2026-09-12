const preset = require( '@wordpress/jest-preset-default/jest-preset' );

module.exports = {
	...preset,
	moduleNameMapper: {
		...preset.moduleNameMapper,
		// The shared UI is a build-time external; tests read the source it is built from.
		'^@subkit/ui$': '<rootDir>/src/ui/index.js',
	},
	setupFiles: [ ...preset.setupFiles, require.resolve( './tools/jest-setup.js' ) ],
};
