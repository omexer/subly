=== SubKit – WooCommerce Subscriptions ===
Contributors: pronob1010
Tags: woocommerce, subscriptions, recurring payments, billing, memberships
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn any WooCommerce product into a subscription and let it bill itself, with full customer self-service.

== Description ==

SubKit turns WooCommerce products into subscriptions and runs the renewal billing loop for you.

**This is an early development release and is not ready for production stores.**

= Working today =

* Simple products can be sold as subscriptions on any daily, weekly, monthly or yearly schedule.
* Free trials.
* A renewal engine built on Action Scheduler, with an hourly sweeper that catches renewals WP-Cron missed.
* Charge-slot ledger with a database-level guarantee against double charging.
* Clear recurring-payment disclosure on the product page, in the cart and at checkout.
* Customers can view and cancel their own subscriptions from My Account.
* Admin subscription list and detail screens under WooCommerce, with a full activity trail.
* HPOS native.

= Not built yet =

Stripe and PayPal adapters, block checkout integration, renewal emails, reporting, and everything else on the roadmap.

== Installation ==

1. Upload the `subkit-subscriptions` folder to `/wp-content/plugins/`.
2. Activate through the Plugins screen.
3. Edit a simple product and tick **Subscription** in the Product data panel.

== Frequently Asked Questions ==

= Is this ready for a live store? =

No. It is a development release. The renewal loop works and is tested, but there are no production payment gateway adapters yet.

= Does it work with High-Performance Order Storage? =

Yes. Subscriptions are stored as a native WooCommerce order type in the HPOS tables.

== Changelog ==

= 0.11.0 =
* The free plugin now has a REST API of its own: list and read subscriptions, change their status, move their dates, and read their history. These routes used to be part of Pro; they are free because the admin screens read them, and a screen that only works with a licence is not a screen.
* Pro's Reports and Health screens are rebuilt in React. Reports gets a date range you can change without a page load; Health gets live filtering, search, and its fixes applied in place.
* Both new screens keep their old server-rendered version behind them, and only replace it once real figures arrive.

= 0.10.0 =
* The figures above the subscriptions list are now a live panel: monthly recurring revenue with a 30-day trend line, live subscriptions, and a bar showing where every subscription stands. Built with React and shadcn/ui.
* The panel is drawn beside the old one and only replaces it once real figures arrive, so a failed request leaves the working summary on screen rather than an error.
* New for developers: a read-only /subkit/v1/overview endpoint, and the shared admin components SubKit Pro's screens will be rebuilt on.

= 0.9.2 =
* The plugin is now called SubKit – WooCommerce Subscriptions. Nothing else changes; the same plugin, updated in place.
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
