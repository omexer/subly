module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	settings: {
		// Resolved by webpack from a global at runtime, so there is no module to find.
		'import/core-modules': [ '@subkit/ui' ],
	},
	overrides: [
		{
			files: [ '**/test/**/*.js' ],
			env: { jest: true },
			rules: { 'import/no-extraneous-dependencies': 'off' },
		},
	],
};
