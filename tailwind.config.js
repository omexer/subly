/**
 * Tailwind, made safe for wp-admin.
 *
 * Two settings do that work. Preflight is off, because its global reset would restyle
 * every core admin element on the page, not just ours. Every class is prefixed, so a
 * utility of ours can never match a core rule or the other way round.
 */
module.exports = {
	prefix: 'sk-',
	darkMode: [ 'class', '.subkit-ui--dark' ],
	corePlugins: { preflight: false },
	content: [ './src/**/*.{js,jsx}' ],
	theme: {
		extend: {
			colors: {
				border: 'hsl(var(--sk-border))',
				input: 'hsl(var(--sk-input))',
				ring: 'hsl(var(--sk-ring))',
				background: 'hsl(var(--sk-background))',
				foreground: 'hsl(var(--sk-foreground))',
				primary: {
					DEFAULT: 'hsl(var(--sk-primary))',
					foreground: 'hsl(var(--sk-primary-foreground))',
				},
				secondary: {
					DEFAULT: 'hsl(var(--sk-secondary))',
					foreground: 'hsl(var(--sk-secondary-foreground))',
				},
				muted: {
					DEFAULT: 'hsl(var(--sk-muted))',
					foreground: 'hsl(var(--sk-muted-foreground))',
				},
				accent: {
					DEFAULT: 'hsl(var(--sk-accent))',
					foreground: 'hsl(var(--sk-accent-foreground))',
				},
				destructive: {
					DEFAULT: 'hsl(var(--sk-destructive))',
					foreground: 'hsl(var(--sk-destructive-foreground))',
				},
				success: {
					DEFAULT: 'hsl(var(--sk-success))',
					foreground: 'hsl(var(--sk-success-foreground))',
				},
				card: {
					DEFAULT: 'hsl(var(--sk-card))',
					foreground: 'hsl(var(--sk-card-foreground))',
				},
			},
			borderRadius: {
				lg: 'var(--sk-radius)',
				md: 'calc(var(--sk-radius) - 2px)',
				sm: 'calc(var(--sk-radius) - 4px)',
			},
		},
	},
	plugins: [ require( 'tailwindcss-animate' ) ],
};
