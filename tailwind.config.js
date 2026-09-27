/**
 * Tailwind, made safe for wp-admin.
 *
 * Two settings do that work. Preflight is off, because its global reset would restyle
 * every core admin element on the page, not just ours. Every class is prefixed, so a
 * utility of ours can never match a core rule or the other way round.
 */
module.exports = {
	prefix: 'es-',
	darkMode: [ 'class', '.easysubscription-ui--dark' ],
	corePlugins: { preflight: false },
	content: [ './src/**/*.{js,jsx}' ],
	theme: {
		extend: {
			colors: {
				border: 'hsl(var(--es-border))',
				input: 'hsl(var(--es-input))',
				ring: 'hsl(var(--es-ring))',
				background: 'hsl(var(--es-background))',
				foreground: 'hsl(var(--es-foreground))',
				primary: {
					DEFAULT: 'hsl(var(--es-primary))',
					foreground: 'hsl(var(--es-primary-foreground))',
				},
				secondary: {
					DEFAULT: 'hsl(var(--es-secondary))',
					foreground: 'hsl(var(--es-secondary-foreground))',
				},
				muted: {
					DEFAULT: 'hsl(var(--es-muted))',
					foreground: 'hsl(var(--es-muted-foreground))',
				},
				accent: {
					DEFAULT: 'hsl(var(--es-accent))',
					foreground: 'hsl(var(--es-accent-foreground))',
				},
				destructive: {
					DEFAULT: 'hsl(var(--es-destructive))',
					foreground: 'hsl(var(--es-destructive-foreground))',
				},
				success: {
					DEFAULT: 'hsl(var(--es-success))',
					foreground: 'hsl(var(--es-success-foreground))',
				},
				card: {
					DEFAULT: 'hsl(var(--es-card))',
					foreground: 'hsl(var(--es-card-foreground))',
				},
			},
			borderRadius: {
				lg: 'var(--es-radius)',
				md: 'calc(var(--es-radius) - 2px)',
				sm: 'calc(var(--es-radius) - 4px)',
			},
		},
	},
	plugins: [ require( 'tailwindcss-animate' ) ],
};
