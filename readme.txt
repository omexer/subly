=== SubKit – Subscriptions for WooCommerce ===
Contributors: pronob1010
Tags: woocommerce, subscriptions, recurring payments, billing, memberships
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.17.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell subscriptions in WooCommerce: recurring billing, free trials, Stripe and PayPal, and customers who manage their own plan.

== Description ==

SubKit turns WooCommerce products into subscriptions and runs the renewal billing for you — scheduling each charge, taking it, retrying it and recording every attempt — with a place in My Account where customers can see and cancel what they pay for.

**This is a development release. Do not use it on a live store yet.** The billing engine is built and tested against a running WordPress, but no payment has yet gone through a real Stripe or PayPal account, and no renewal has yet been watched happening on its own over time.

= Selling subscriptions =

* A **Subscription** product type in the Product data panel, billed every day, week, month or year, or any multiple of them.
* Free trials in days, weeks, months or years, and sign-up fees.
* The price says how often it recurs wherever WooCommerce shows it, and the terms — the trial, the first payment, what follows, cancel anytime — are spelled out on the product page, in the cart and at checkout.
* Works with both the classic checkout and the block checkout.
* A guest can buy a subscription and have an account made at checkout, or you can require a login first.

= Taking payment =

* **Stripe** saves the card at checkout and renewals are charged automatically.
* **PayPal** runs the schedule itself, and SubKit keeps in step through PayPal's webhooks.
* With no gateway at all, renewals become invoices the customer pays by hand.
* A safeguard in the database means a renewal can never be charged twice, even when two processes try at the same moment.
* A charge whose result never arrived is checked with the gateway, not simply tried again.
* Renewals missed while the site's scheduler was not running are picked up by an hourly check.
* A copy of the site on staging refuses to bill anyone.

= For your customers =

* My Account lists their subscriptions, what they pay and when the next payment is.
* They can cancel at the end of the period they paid for, or straight away. You cannot switch cancelling off.
* They can turn off automatic renewal and keep what they paid for until the period ends.
* They can pay a renewal that failed.

= Running your store =

* **Home** walks you through setting up — connect a payment method, create a product, run a test renewal that charges nobody — then shows recurring revenue, what needs your attention and your latest subscriptions.
* **All subscriptions** lets you search, filter, sort and change subscriptions in bulk, and open any one to see its history, charge it now or move its dates.
* Six emails for the moments that matter, each to the right person.
* Customer roles and downloadable files that follow the subscription.
* Integrations and Help screens, with a system report that never contains your keys.
* A REST API for subscriptions, authenticated with WooCommerce's own API keys.

= SubKit Pro =

A separate plugin adds variable subscriptions, instalment and split payment plans, introductory renewal prices, fixed expiry dates, minimum terms, shipping on renewals, pause and resume, plan switching, recurring coupons, failed-payment recovery, reports, subscription health, delivery schedules, purchase limits, Mollie, Razorpay and Xendit renewals, and integrations with course, email, CRM, automation and licence-key plugins.

== Installation ==

1. Install and activate WooCommerce 8.0 or newer.
2. Upload the `subkit-subscriptions` folder to `/wp-content/plugins/`, or upload the ZIP from **Plugins → Add New → Upload Plugin**.
3. Activate SubKit.
4. Open **SubKit → Home** and follow the setup checklist: connect a payment method, create a subscription product, and run a test renewal.

To turn a product into a subscription yourself, edit it and choose **Subscription** in the **Product data** dropdown.

== Frequently Asked Questions ==

= Is this ready for a live store? =

Not yet. The renewal engine is built and tested against a running WordPress, and Stripe and PayPal are both included. What has not happened yet is a payment through a real Stripe or PayPal account, and a renewal watched firing on its own over days. Until both have, use it on a staging site.

= Which payment methods can renew automatically? =

Stripe and PayPal, both included. SubKit Pro adds renewals through Mollie, Razorpay and Xendit, using the payment details those plugins already saved at checkout. Without any of them, each renewal is an invoice the customer pays.

= Can a customer be charged twice for the same renewal? =

No. Each billing period can hold exactly one charge, enforced by a unique index in the database, so a second attempt is refused before it reaches the payment gateway — even if two processes try at the same moment.

= Can customers cancel on their own? =

Yes, from My Account, and that cannot be turned off. A customer who cannot cancel is a chargeback waiting to happen.

= Does it work with High-Performance Order Storage? =

Yes. Subscriptions are a native WooCommerce order type, stored in the HPOS tables. WooCommerce's older post-based order storage is supported in the code as well, but has not been tested end to end.

== External services ==

SubKit talks to a payment provider only when you have switched that provider on and entered
its credentials. Nothing is sent anywhere by default, and SubKit sends nothing to its own
author or to any analytics service.

= Stripe =

Used to take the first payment at checkout and to charge renewals against the saved card,
when Stripe is enabled under **WooCommerce → Settings → Subscriptions → Stripe**.

Requests go to `https://api.stripe.com`. They are made when a customer places an order
paying by card, when they return from Stripe's hosted checkout, and each time a renewal
falls due. What is sent: the order's total and currency, the order number, the customer's
billing email, the two addresses on this site that Stripe returns the customer to, and —
for renewals — the Stripe customer and payment-method identifiers Stripe itself issued at
checkout, plus the subscription and order numbers as metadata.

Stripe's terms: https://stripe.com/legal/ssa — Stripe's privacy policy: https://stripe.com/privacy

= PayPal =

Used to create and run the billing plan, when PayPal is enabled under
**WooCommerce → Settings → Subscriptions → PayPal**.

Requests go to `https://api-m.paypal.com`, or to `https://api-m.sandbox.paypal.com` when the
environment is set to Sandbox. They are made when a subscription product is bought, and
whenever PayPal notifies the site of a payment so SubKit can verify that the notification is
genuine. What is sent: the product name, the price, currency and billing cycle of the plan
including any free trial or sign-up fee, the order number, the customer's first name, last
name and billing email, this site's name and the addresses PayPal returns the customer to,
and — for verification — the notification PayPal sent back together with the signature
headers that came with it.

PayPal's terms: https://www.paypal.com/legalhub/useragreement-full — PayPal's privacy
statement: https://www.paypal.com/legalhub/privacy-full

= WordPress.org =

The Integrations screen can install a companion plugin for you. Doing so asks
`https://api.wordpress.org` for that plugin's download, exactly as **Plugins → Add New**
does. It happens only when you click Install, and sends only the plugin's slug.

== Source code ==

The JavaScript for SubKit's admin screens is bundled, and its unbundled source ships in this
plugin's `src/` directory alongside the build configuration. To rebuild it:

`npm install`
`npm run build`

The build uses @wordpress/scripts with the webpack, Tailwind and PostCSS configuration files
included in the plugin.

== Screenshots ==

1. The subscriptions list: who is subscribed, what they pay, and how far off the next payment is.
2. One subscription in full — status, next payment, schedule, and the activity log grouped by day.
3. The Subscription panel on a product: billing cycle, free trial, sign-up fee and shipping.
4. The subscription terms as a customer sees them on the product page.

== Changelog ==

= 0.17.0 =
* **Fixed: every renewal was skipping a billing period.** A monthly subscription charged on 20 September was next charged on 20 November. Every store was billing half as often as it sold.
* **Free trials work.** A trial product used to charge its full price at checkout and then sit in "trialling" for ever, never converting. The trial is now free, the sign-up fee the product page promised is actually taken, and the first payment falls on the day the trial ends — which is what the customer was told all along.
* **Trial signups now capture a card.** A trial that costs nothing makes WooCommerce skip the payment step entirely, so no payment method was stored and the first renewal had nothing to charge. The payment step is kept for trial checkouts, on the classic checkout and on blocks. Switch it off under WooCommerce → Settings → Subscriptions if you would rather chase customers for a card later.
* **Upcoming renewal email.** Sent to the customer a configurable number of days before their card is charged — three by default, 0 to switch it off. A subscription still on trial is told its trial is ending rather than that it is renewing.
* **The store is told when a subscription is cancelled or ends**, with the reason the customer gave. Cancelling from the admin screen now tells the customer too; only the My Account route ever did.
* **Customers can pay a period early.** Off by default. The renewal date does not move — paying early settles the payment that was already coming. The admin's "Renew now" also charges now, instead of quietly doing nothing unless the subscription was already overdue.
* PayPal's setup notice no longer claims a missing webhook ID stops PayPal being offered at checkout. It does something different and worse: every webhook is rejected, so renewals are never recorded. Both notices now link to the section of Settings that fixes them.
* Overdue trials are picked up by the hourly sweeper, which only looked at active subscriptions.
* A subscription's recurring amount comes from the product rather than from the first order, so a one-off checkout coupon no longer discounts every renewal for ever.
* New filters let an extension sell one product on more than one plan — see SubKit Pro's new Plans.
* The plugin is now called SubKit – Subscriptions for WooCommerce. Only the name changes: same plugin, same settings, updated in place.
* Deleting the plugin can now remove its data with it, if you ask it to under WooCommerce → Settings → Subscriptions. Off by default, and subscriptions and their orders are never deleted either way.

= 0.16.0 =
* The subscriptions list leads with the customer, says how far off the next payment is ("today", "11 days overdue") rather than only its date, and puts the status tabs, search and bulk actions in one toolbar - the bulk bar appears when you select something instead of sitting there disabled. An empty list now says what will fill it, and a filtered one offers to clear the filters.
* A subscription's own screen leads with the three figures that answer "what is this and what happens next", keeps the rest as details, and groups the activity log under the day each thing happened. Changing the schedule and ending the subscription are separate sections that say what they do first, and cancelling is no longer a button the same size and weight as Reactivate.
* Fixed: every outlined button and bordered panel in SubKit's screens was drawing no border, because the stylesheet's own reset outranked the border it was meant to leave alone.
* Notices from other plugins no longer open every SubKit screen. They are one line in the header bar that opens them; SubKit's own and WooCommerce's stay where they are.

= 0.15.0 =
* SubKit -> Settings is a screen of its own instead of a jump into the WooCommerce settings tab: sections down the left, the settings in cards, and a panel on the right with quick links and whether renewals can actually run. Everything SubKit and SubKit Pro add appears here, and the old WooCommerce tab still works.
* Fixed: an edited stylesheet or script could stay cached in the browser until the next release. Admin assets now carry the file's own timestamp.

= 0.14.0 =
* The subscription settings on the product screen are laid out in sections - Pricing, Billing settings, Shipping settings - with the number and unit of "Bill every" and "Free trial" on one row. The sign-up fee sits with the regular and sale price.
* Free trials can be set in weeks, months or years as well as days. A one-month trial that starts on 31 January ends on 28 February. Existing trials are unchanged: they are read as days.
* "Shipping required" in the panel is the same setting as WooCommerce's Virtual box, from the other side.
* Fixed: a subscription's end date was stored but never enforced, so renewals carried on past it. No renewal is now charged on or after the end date; the period already paid for runs to its end, and the subscription then expires.
* Fixed: on the classic checkout, the sentence beside Place order showed "<bdi>" tags around the amounts.
* When a subscription cannot be cancelled online, My Account now says so and why, in place of a Cancel button that only refused once pressed. For developers: the `subkit_cancel_refused_message` and `subkit_disclosure_sentence` filters are new, and the product panel's sections are actions extensions can add rows to.
* SubKit Pro 0.13.0 needs this version.

= 0.13.2 =
* The plugin description now says what SubKit does today - the Stripe and PayPal gateways, the block checkout, Home and the rest - and why it is still a development release. It had not been updated since the first release.
* No code changes.

= 0.13.1 =
* Fixed: on a store with more than 50 active subscriptions, the hourly check for missed renewals could skip overdue ones indefinitely. It looked at the 50 oldest subscriptions and only then checked which were due, so a newer subscription whose renewal had been missed was never picked up. It now asks for due subscriptions directly, soonest first.

= 0.13.0 =
* SubKit has a Home screen. Until setup is finished it leads with the checklist; after that it shows recurring revenue, what needs your attention, and your most recent subscriptions.
* The subscriptions list has its own page, SubKit → All subscriptions. Old links to a subscription or a filtered list still work: they are sent to the new address.
* Every SubKit screen sits in the same frame now - a header with breadcrumbs, the page heading and a footer - in a new design. Integrations are cards grouped by what they connect to, and Help collects where to look first, with a one-click copy of the system report.

= 0.12.3 =
* Fixed: the product page showed the price twice - "$5.00", then "$5.00 every month" - because WooCommerce printed the plain price and the terms block printed it again. The price itself now says how often it recurs, and the terms block starts with the facts.
* The price now says how often it recurs wherever WooCommerce prints one: the shop, category pages, related products. A product on sale keeps its struck-through old price. An instalment or split plan shows the plan instead of a price that would misstate it.

= 0.12.2 =
* Fixed: SubKit's payment methods never appeared on the block checkout, which is the default checkout in current WooCommerce. A classic gateway is invisible there until it registers itself with the blocks registry, so a shop using blocks saw "There are no payment methods available" while the payments screen said Active. Both gateways now register.
* Fixed: the rule that keeps SubKit's gateways off a cart with no subscription in it read the classic checkout only, so it could not answer correctly over the Store API the block checkout uses. It now looks at the cart, and gives the same answer to both.

= 0.12.1 =
* Fixed: subscription products had no Add to cart button on their own product page, so they could not be bought from it. WooCommerce draws that button per product type, and the subscription types were not asking it to.
* A gateway that is switched on but has no credentials now says so in the admin. It still hides itself at checkout — a customer must never pick a payment method that cannot work — but "Active" on the payments screen and "no payment methods available" at checkout are no longer two facts with nothing connecting them.

= 0.12.0 =
* The subscriptions list and the single subscription screen are rebuilt in React. Status tabs, search, sorting, bulk actions and paging all happen without a page load.
* Sorting is done by the server, so it orders every subscription rather than reordering the page you happen to be looking at.
* The single subscription screen can now change the next payment and end dates in place, and its activity is shown as it happens rather than after a reload.
* Anything another plugin adds to the subscription screen still renders, below the new one.
* New: a bulk actions endpoint, so changing twenty subscriptions is one request rather than twenty.

= 0.11.0 =
* The free plugin now has a REST API of its own: list and read subscriptions, change their status, move their dates, and read their history. These routes used to be part of Pro; they are free because the admin screens read them, and a screen that only works with a licence is not a screen.
* Pro's Reports and Health screens are rebuilt in React. Reports gets a date range you can change without a page load; Health gets live filtering, search, and its fixes applied in place.
* Both new screens keep their old server-rendered version behind them, and only replace it once real figures arrive.

= 0.10.0 =
* The figures above the subscriptions list are now a live panel: monthly recurring revenue with a 30-day trend line, live subscriptions, and a bar showing where every subscription stands. Built with React and shadcn/ui.
* The panel is drawn beside the old one and only replaces it once real figures arrive, so a failed request leaves the working summary on screen rather than an error.
* New for developers: a read-only /subkit/v1/overview endpoint, and the shared admin components SubKit Pro's screens will be rebuilt on.

= 0.9.2 =
* The plugin is now called SubKit – Subscriptions for WooCommerce. Nothing else changes; the same plugin, updated in place.
* Variable subscription is only offered as a product type when SubKit Pro can actually bill it. Free registered the type but could not give a variation a schedule, so a customer buying one was charged once and never again. A product that is already a variable subscription keeps its type and says so on the edit screen.
* SubKit Pro now refuses to activate without SubKit, and is deactivated along with it, instead of sitting in the plugin list doing nothing.
* New guide: worked setups for twelve kinds of subscription business, with the field values to type and the integrations each one needs. See docs/BUSINESS-EXAMPLES.md.

= 0.9.1 =
* Fixed: choosing Subscription showed a "Billing schedule" heading with no fields under it.
* Fixed: the setup checklist asked every shop to connect PayPal, even one already using Stripe. It now names whichever gateway you connected.
* Fixed: the grace period setting appeared twice on the settings screen, and the two could disagree.
* Deleting a subscription now removes its charge ledger and activity rows instead of leaving them behind forever.
* The user guide is now a complete manual: every setting explained, every product field, and which product type to choose.

= 0.9.0 =
* The subscriptions list gains search, sortable columns, checkboxes, bulk actions and per-row actions.
* Bulk changes respect the subscription's status rules: one that cannot legally change is skipped and reported, never forced.
* Integrations can install and activate the plugin they need, where that plugin is on WordPress.org.
* Subscription health gains Retry now and Ask the customer.
* Row actions no longer reload the page, and still work with JavaScript switched off.

= 0.8.0 =
* Reports opens with charts: recurring revenue over time, new subscriptions per day, and a status ring with a legend.
* Charts are drawn as inline SVG on the server, so they print, stay sharp on any screen, and need no charting library.

= 0.7.0 =
* Subscription and Variable subscription are now product types in the Product data dropdown, rather than a checkbox hidden inside Simple product.
* Products made with the old checkbox keep working and keep billing; convert them whenever convenient.
* The admin screens have a proper stylesheet: stat tiles, a numbered setup checklist, colour-coded status, and real empty states.
* Fixed: a variable subscription reported no variations at all, because WooCommerce fell back to the wrong data store for the new type.

= 0.6.1 =
* The admin menu is now called SubKit rather than Subscriptions.
* SubKit Pro can be activated whatever folder the free plugin sits in, including the subkit-subscriptions-main that a GitHub ZIP produces.
* Developers can exercise the licence screens without a store; see docs/TESTING.md.

= 0.6.0 =
* Subscriptions now has its own top-level admin menu instead of four separate entries under WooCommerce.
* New Integrations screen: what SubKit can connect to, and whether each connection is live.
* New Help screen with a system report to paste into a support request. It never includes credentials.
* Guest checkout: a customer can buy a subscription without an account, and gets one made at checkout.
* Customers can turn off automatic renewal and keep what they paid for until the period ends.
* Subscriber roles and downloadable-file gating follow the subscription's status.
* Monthly recurring revenue, live count and a daily snapshot on the Subscriptions screen.
* Create your first subscription product straight from the setup checklist.
* Fixed: schedule changes could silently vanish when set before anything read them.
* Fixed: a store selling in more than one currency could not open the Subscriptions screen.
* Fixed: a yearly plan normalised to 10.01 a month instead of 10.00.
* Static analysis (PHPStan and PHP_CodeSniffer) now runs on every change.

= 0.1.0 =
* First development release: subscription products, renewal pipeline, My Account screens, admin screens.

== Upgrade Notice ==

= 0.13.1 =
Fixes missed renewals being skipped on stores with more than 50 active subscriptions.

= 0.13.0 =
SubKit now opens on a Home screen, and the subscriptions list moves to SubKit → All subscriptions. Old links still work.
