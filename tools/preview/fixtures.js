// Believable data for every screen, shaped exactly like the REST responses.
const people = [
	[ 'Ada Lovelace', 'Coffee box — Large', 'es-active', 'Active', '£40.00' ],
	[ 'Grace Hopper', 'Course library — Yearly', 'es-active', 'Active', '£390.00' ],
	[ 'Alan Turing', 'Pet food — weekly', 'es-on-hold', 'On hold', '£80.00' ],
	[ 'Katherine Johnson', 'Community', 'es-trialling', 'Free trial', '£12.00' ],
	[ 'Linus Torvalds', 'Plugin — 5 sites', 'es-pending-cancel', 'Cancelling', '£149.00' ],
	[ 'Margaret Hamilton', 'Coffee box — Small', 'es-cancelled', 'Cancelled', '£15.00' ],
];

const rows = people.map( ( [ name, product, status, label, total ], i ) => ( {
	id: 812 + i,
	status,
	status_label: label,
	customer_name: name,
	customer_email: name.toLowerCase().replace( /\s+/g, '.' ) + '@example.com',
	total_formatted: total,
	next_payment_formatted: [ '14 October 2026', '2 September 2027', '—', '19 September 2026', '30 September 2026', '' ][ i ],
	payment_method_title: [ 'Stripe', 'PayPal', 'Stripe', 'Stripe', 'Stripe', 'PayPal' ][ i ],
	billable: [ 'es-active', 'es-on-hold', 'es-trialling' ].includes( status ),
	edit_url: '#detail',
} ) );

const statuses = [
	[ 'es-active', 'Active', 2 ],
	[ 'es-trialling', 'Free trial', 1 ],
	[ 'es-on-hold', 'On hold', 1 ],
	[ 'es-pending-cancel', 'Cancelling', 1 ],
	[ 'es-cancelled', 'Cancelled', 1 ],
	[ 'es-expired', 'Ended', 0 ],
].map( ( [ key, label, count ] ) => ( { key, label, count } ) );

export const fixtures = {
	'/easysubscription/v1/dashboard': ( setupDone ) => ( {
		greeting: 'Good afternoon, Sam',
		setup: {
			done: setupDone ? 4 : 2,
			total: 4,
			steps: [
				{ done: true, title: 'Connect a payment method', detail: 'Stripe can charge renewals automatically.', action: { label: 'Change', url: '#' }, form: '' },
				{ done: true, title: 'Create a subscription product', detail: '"Coffee box" is ready to sell.', action: { label: 'Edit', url: '#' }, form: '' },
				{ done: !! setupDone, title: 'Check automatic renewals can run', detail: 'Renewals are processing normally.', action: null, form: '' },
				{ done: !! setupDone, title: 'Run a test renewal', detail: 'See a renewal happen end to end, on a throwaway subscription. Nobody is charged and everything is deleted afterwards.', action: { label: 'Run test renewal', url: '#' }, form: '' },
			],
		},
		stats: { mrr: '£1,240.00', active: 42, trialling: 5, on_hold: 2 },
		attention: [
			{ key: 'on_hold', label: '2 subscriptions on hold after a failed payment', count: 2, url: '#list', tone: 'bad' },
			{ key: 'pending_cancel', label: '1 subscription ends when its period runs out', count: 1, url: '#list', tone: 'warn' },
		],
		recent: rows.slice( 0, 5 ).map( ( r ) => ( {
			id: r.id,
			customer: r.customer_name,
			product: people.find( ( p ) => p[ 0 ] === r.customer_name )[ 1 ],
			status: r.status,
			status_label: r.status_label,
			total: r.total_formatted,
			created: '12 Sep 2026',
			url: '#detail',
		} ) ),
		links: { new_product: '#', list: '#list', integrations: '#', settings: '#', help: '#' },
		create: { url: '#', nonce: 'x' },
	} ),
	'/easysubscription/v1/subscriptions': rows,
	'/easysubscription/v1/subscriptions/statuses': statuses,
	'/easysubscription/v1/subscriptions/812': {
		...rows[ 0 ],
		billing_interval: 1,
		billing_period: 'month',
		next_payment: '2026-10-14 09:00:00',
		end_date: '',
		trial_end: '',
		parent_order_id: 801,
		payment_method: 'easysubscription_stripe',
	},
	'/easysubscription/v1/subscriptions/812/activity': [
		{ type: 'charge_attempt', message: 'Renewal charged £40.00 through Stripe.', actor: 'system', date: '2026-09-14 09:00:12' },
		{ type: 'status_change', message: 'Activated after the first payment.', actor: 'system', date: '2026-08-14 10:21:40' },
	],
	'/easysubscription/v1/reports': {
		currency: 'GBP',
		tiles: [
			[ 'mrr', 'Monthly recurring revenue', '£1,240.00' ],
			[ 'arr', 'Annual run rate', '£14,880.00' ],
			[ 'active', 'Active subscriptions', '42' ],
			[ 'churn', 'Churn (30 days)', '2.4%' ],
			[ 'ltv', 'Average lifetime value', '£318.00' ],
			[ 'collected', 'Revenue collected', '£9,412.00' ],
		].map( ( [ key, label, formatted ] ) => ( { key, label, formatted, raw: 0 } ) ),
		revenue: Array.from( { length: 60 }, ( _, i ) => ( {
			date: `2026-07-${ i }`,
			label: i % 10 === 0 ? `${ 1 + ( i % 28 ) } Aug` : '',
			value: Math.round( 820 + i * 7 + Math.sin( i / 4 ) * 40 ),
		} ) ),
		signups: Array.from( { length: 60 }, ( _, i ) => ( { date: `d${ i }`, label: `${ i }`, value: Math.max( 0, Math.round( 2 + Math.sin( i / 3 ) * 2 + ( i % 7 === 0 ? 3 : 0 ) ) ) } ) ),
		statuses: [
			{ key: 'es-active', label: 'Active', count: 42 },
			{ key: 'es-trialling', label: 'Free trial', count: 5 },
			{ key: 'es-on-hold', label: 'On hold', count: 2 },
			{ key: 'es-cancelled', label: 'Cancelled', count: 9 },
		],
		reasons: [
			{ label: 'Too expensive', count: 4 },
			{ label: 'Not using it enough', count: 3 },
			{ label: 'Switched to a competitor', count: 1 },
		],
	},
	'/easysubscription/v1/health': {
		generated_at: 1757000000,
		scanned: 58,
		total: 2,
		page: 1,
		pages: 1,
		at_risk: '£120.00',
		dismissed: 1,
		bands: [
			{ key: 'healthy', label: 'Healthy', count: 55 },
			{ key: 'watch', label: 'Watch', count: 1 },
			{ key: 'at_risk', label: 'At risk', count: 2 },
		],
		signals: [
			{ key: 'payment_failed', label: 'Payment failed', count: 2, severity: 'critical' },
			{ key: 'grace_ending', label: 'Grace period ending', count: 1, severity: 'warning' },
		],
		entries: [
			{ id: 814, score: 40, band: 'at_risk', band_label: 'At risk', status: 'es-on-hold', status_label: 'On hold', customer_name: 'Alan Turing', customer_email: 'alan@example.com', product_name: 'Pet food — weekly', value: '£80.00', next_payment: null, dismissed: false, edit_url: '#detail',
				signals: [ { key: 'payment_failed', label: 'Payment failed', explanation: 'The last charge was declined and the billing period is still unpaid.', remedy: 'Ask the customer to update their payment method.', severity: 'critical' } ] },
			{ id: 819, score: 55, band: 'at_risk', band_label: 'At risk', status: 'es-on-hold', status_label: 'On hold', customer_name: 'Ada Lovelace', customer_email: 'ada@example.com', product_name: 'Coffee box — Large', value: '£40.00', next_payment: null, dismissed: false, edit_url: '#detail',
				signals: [ { key: 'grace_ending', label: 'Grace period ending', explanation: 'Payment recovery is about to give up and cancel this subscription.', remedy: 'Contact the customer before the subscription is cancelled.', severity: 'warning' } ] },
		],
	},
};
