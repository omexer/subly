=== EasySubscription – Subscriptions for WooCommerce ===
Contributors: pronob1010
Tags: woocommerce, subscriptions, recurring payments, billing, memberships
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.30.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell subscriptions in WooCommerce: recurring billing, free trials, PayPal, and customers who manage their own plan.

== Description ==

EasySubscription turns WooCommerce products into subscriptions and runs the renewal billing for you — scheduling each charge, taking it, retrying it and recording every attempt — with a place in My Account where customers can see and cancel what they pay for.

**This is a development release. Do not use it on a live store yet.** The billing engine is built and tested against a running WordPress, but no payment has yet gone through a real PayPal account (or any other gateway's sandbox), and no renewal has yet been watched happening on its own over time.

= Selling subscriptions =

* A **Subscription** product type in the Product data panel, billed every day, week, month or year, or any multiple of them.
* Free trials in days, weeks, months or years, and sign-up fees.
* The price says how often it recurs wherever WooCommerce shows it, and the terms — the trial, the first payment, what follows, cancel anytime — are spelled out on the product page, in the cart and at checkout.
* Works with both the classic checkout and the block checkout.
* A guest can buy a subscription and have an account made at checkout, or you can require a login first.
* A checkout that costs nothing today — a free trial, or a coupon worth the whole first payment — still asks for a payment method whenever a later renewal will charge something, so the first renewal has a card to charge. Plans that renew for nothing are not asked.

= Taking payment =

* **PayPal** runs the schedule itself, and EasySubscription keeps in step through PayPal's webhooks.
* With no gateway at all, renewals become invoices the customer pays by hand.
* Payments that are confirmed days later — Direct Debit and bank payments — wait as pending: the customer keeps access, the renewal order waits on hold so it cannot be paid twice, and EasySubscription never retries or re-charges it. The gateway settles it once the money is confirmed or refused.
* A failed or waiting renewal can be paid from its payment link. Paying it restarts the subscription exactly once, keeps the card it was paid with for later renewals, and is never charged again.
* A safeguard in the database means a renewal can never be charged twice, even when two processes try at the same moment.
* A charge whose result never arrived is checked with the gateway, not simply tried again.
* The next renewal is queued as soon as one is charged. Renewals missed while the site's scheduler was not running are picked up by an hourly check, which by default charges once for the gap rather than once per missed period.
* In stores that enter prices including tax, renewals charge exactly what the checkout charged. VAT-exempt customers stay exempt on every renewal.
* A copy of the site on staging refuses to bill anyone.

= For your customers =

* My Account lists their subscriptions, what they pay and when the next payment is.
* They can cancel at the end of the period they paid for, or straight away. You cannot switch cancelling off. A subscription cancelled at the end of the period ends when that period runs out: access stops and nothing more is charged.
* They can turn off automatic renewal and keep what they paid for until the period ends.
* They can pay a renewal that failed, or one waiting on them, from its payment link.
* If you allow it, they can pay the next period early without moving their renewal date.

= Running your store =

* **Home** walks you through setting up — connect a payment method, create a product, run a test renewal that charges nobody — then shows recurring revenue (net of tax), what needs your attention and your latest subscriptions.
* **All subscriptions** lets you search, filter, sort and change subscriptions in bulk, and open any one to see its history, charge it now or move its dates.
* **Health** checks, under Settings: whether the renewal queue is running, charges whose outcome is still unknown, renewals waiting more than 10 days for their payment provider to confirm them, whether the double-charge safeguard is in place, and subscriptions whose renewals add tax twice — listed with a Repair action, since subscriptions sold before 0.19.4 in tax-inclusive stores are never changed silently.
* Ten emails for the moments that matter — seven to the customer (including a reminder before each renewal) and three to you — each with a preview under Email Notifications on WooCommerce 9.6 and later.
* Customer roles and downloadable files that follow the subscription.
* **FluentCRM:** choose tags and lists on each subscription product. The contact has them while the subscription is active or in a free trial and loses them when it stops, and only tags and lists EasySubscription added are ever removed.
* Integrations and Help screens, with a system report that never contains your keys.
* A REST API for subscriptions, open to logged-in store managers.

= EasySubscription Pro =

A separate plugin adds:

* **Plans and pricing:** variable subscriptions, several plans per product, instalment and split payment plans, introductory renewal prices, fixed expiry dates, minimum terms, payment caps, purchase limits, shipping on renewals and delivery schedules.
* **Keeping customers:** pause and resume, plan switching, upgrade suggestions and "switch instead of cancelling", a retention offer on cancellation, a win-back email campaign, anniversary thank-yous with an optional gift, and members-only content.
* **Getting paid:** configurable payment retries and a grace period, with a recovery report, card updates from My Account with expiring-card and "update your payment details" emails, recurring coupons and sign-up fee coupons, subscription webhooks, and WhatsApp notifications.
* **More gateways:** Stripe, Square, Braintree, Authorize.net, Mollie, Xendit, Razorpay (UPI Autopay), GoCardless Direct Debit, Adyen, WooPayments, Paddle, and bKash and SSLCommerz by payment link.
* **Checkout and emails:** one-click checkout, your own Subscribe button text, trial ending and expiring soon reminders, and editing each email's subject, heading and content inside EasySubscription.
* **Integrations:** LearnDash, Tutor LMS, LearnPress, MailPoet, WP Fusion, AutomatorWP, AutomateWoo, AffiliateWP recurring referrals, BuddyBoss and BuddyPress groups, License Manager for WooCommerce and WP Software License.
* Reports, subscription health with a digest email, a live QR status page, and WooCommerce API keys on the subscription API.

None of Pro's gateways has been run against a real sandbox yet either.

== Installation ==

1. Install and activate WooCommerce 8.0 or newer.
2. Upload the `easysubscription` folder to `/wp-content/plugins/`, or upload the ZIP from **Plugins → Add New → Upload Plugin**.
3. Activate EasySubscription.
4. Open **EasySubscription → Home** and follow the setup checklist: connect a payment method, create a subscription product, and run a test renewal.

To turn a product into a subscription yourself, edit it and choose **Subscription** in the **Product data** dropdown.

== Frequently Asked Questions ==

= Is this ready for a live store? =

Not yet. The renewal engine is built and tested against a running WordPress, and PayPal is included. What has not happened yet is a payment through a real PayPal account, and a renewal watched firing on its own over days. Until both have, use it on a staging site.

= Which payment methods can renew automatically? =

PayPal, included. EasySubscription Pro adds Stripe, which saves the card at checkout and charges each renewal itself, and Square, Braintree, Authorize.net, Mollie, Xendit and WooPayments renewals against the payment method those plugins saved at checkout, and its own checkouts for Razorpay (UPI Autopay), GoCardless Direct Debit and Adyen; Paddle bills its own schedule, as PayPal does. bKash and SSLCommerz cannot charge a customer again automatically, so Pro emails each renewal as a payment link. Without any of them, each renewal is an invoice the customer pays.

= What happens to a Direct Debit renewal while it clears? =

The renewal is pending: the customer keeps access, the renewal order waits on hold, and nothing is charged again or retried. When the payment provider confirms the payment the renewal is marked paid and the next one is scheduled; if the payment fails, it follows the normal failed-payment path, once.

= Can a customer be charged twice for the same renewal? =

No. Each billing period can hold exactly one charge, enforced by a unique index in the database, so a second attempt is refused before it reaches the payment gateway — even if two processes try at the same moment.

= Can customers cancel on their own? =

Yes, from My Account, and that cannot be turned off. A customer who cannot cancel is a chargeback waiting to happen.

= Does it work with High-Performance Order Storage? =

Yes. Subscriptions are a native WooCommerce order type, stored in the HPOS tables. WooCommerce's older post-based order storage is supported in the code as well, but has not been tested end to end.

== External services ==

EasySubscription talks to a payment provider only when you have switched that provider on and entered
its credentials. Nothing is sent anywhere by default, and EasySubscription sends nothing to its own
author or to any analytics service.

= PayPal =

Used to create and run the billing plan, when PayPal is enabled under
**WooCommerce → Settings → Subscriptions → PayPal**.

Requests go to `https://api-m.paypal.com`, or to `https://api-m.sandbox.paypal.com` when the
environment is set to Sandbox. They are made when a subscription product is bought, and
whenever PayPal notifies the site of a payment so EasySubscription can verify that the notification is
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

The JavaScript for EasySubscription's admin screens is bundled, and its unbundled source ships in this
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

= 0.30.2 =
* **Fix:** with the real FluentCRM plugin, no contact was created or tagged when a subscription started.

= 0.30.1 =
* **Fix:** FluentCRM tags and lists did not appear on subscription products, and were not applied, with the real FluentCRM plugin active.

= 0.30.0 =
* **FluentCRM integration.** Choose FluentCRM tags and lists on each subscription product: they are added when the subscription starts and removed when it ends, after any grace period. Tags a customer already had are never removed.

= 0.29.0 =
* Email Notifications (renamed from Notifications): turn each email on or off, and preview it as your customers will see it.
* Cart & Checkout keeps guest checkout and mixed checkout.
* Payments: PayPal. Stripe, one-click checkout, custom button labels, the trial ending and expiring soon reminders, in-app email editing and API access are part of EasySubscription Pro.
* The product's Shipping settings appear only when an add-on uses them; WooCommerce's own Virtual option decides whether a subscription ships.
* Tested with WordPress 7.1.

= 0.28.1 =
* The notification bell sits beside Help and Settings with its count as a small pill next to it, instead of a badge that covered the bell.

= 0.28.0 =
* **SubKit is now EasySubscription in every name:** folder, files, code, settings, database tables, hooks, the API (`easysubscription/v1`) and the repository. It is a new plugin: nothing is carried over from SubKit.
* **Quieter admin notices.** Only what needs you now — renewals or money at risk, or the result of something you just did — shows under the header, as compact cards (three at most). Everything else, including other plugins' notices, waits in a bell with a count in the top bar.

= 0.27.0 =
* **Your logo.** The EasySubscription logo leads every screen, and its icon marks the admin menu.
* **Notifications.** Every EasySubscription email is listed with one switch — the same switch WooCommerce uses, so both screens always agree — and edited inside EasySubscription: subject, heading, additional content and format, with a preview. The renewal reminder is now set in hours before the renewal (existing stores are converted automatically).
* **New emails:** a trial ending reminder (3 days before a free trial turns into a paid subscription), an expiring soon reminder for subscriptions with an end date, and a subscription reactivated email.
* **Cart & Checkout:** choose whether subscriptions and one-time products can be bought together, send customers straight to checkout when they subscribe, and set the Subscribe button text.
* **API Settings:** let WooCommerce API keys use the subscription API (off by default; a read-only key can only read), with the list of endpoints and a link to create keys. Payment webhooks are unaffected.

= 0.26.1 =
* Settings show a number's unit in its title, such as "Grace period after due date (Days)", instead of beside the box.

= 0.26.0 =
* **Settings without reloads.** Switching sections is instant, settings that depend on a switch appear and disappear as you toggle it, and Save stores your changes in place with a confirmation. Unsaved changes are kept while you move between sections, and you are asked before leaving with changes unsaved.

= 0.25.0 =
* **The admin is one app.** Home, All subscriptions, Settings, Integrations and Help open inside one screen: moving between them, from the sidebar or from links, no longer reloads the page, and the browser's back and forward buttons work. Links and bookmarks keep working as before. Integrations and Help are rebuilt for it.
* The dashboard's "needs attention" links now open the subscription list already filtered.

= 0.24.0 =
* **EasySubscription is now EasySubscription.** The plugin, its menus, screens, notices and emails use the new name. Nothing else changes: settings, subscriptions, data and URLs stay exactly as they were.

= 0.23.0 =
* **Customers keep access during the grace period.** When a renewal payment fails, the subscription waits on hold but the customer keeps their role, downloads and members-only content until the grace period ends. If it is still unpaid then, access ends. My Account and the payment-failed email say until when.
* Renewal & Billing reads more plainly: the grace period explains that retries happen within it, and the renewal reminder is set as "days before renewal" (0 turns it off). A reminder queued before it was turned off is no longer sent.

= 0.22.0 =
* **New settings screen.** EasySubscription → Settings is reorganised into General, Customer Controls, Renewal & Billing, Upgrade & Downgrade, Cart & Checkout, Shipping, Notifications, Payments and Integrations, with switches for on/off settings. Settings that depend on another appear only when it is switched on. Every setting keeps its value.
* **Customer controls.** Choose whether customers may cancel from their account, and when a cancellation takes effect: at the end of the billing cycle (the default) or immediately. Customers are no longer asked to choose; the cancel form says which applies.

= 0.21.1 =
* **Fixed: deleting EasySubscription left its scheduled renewals and reminders behind.** Uninstalling with data removal now clears every scheduled EasySubscription job.
* Integration tiles link to where each integration is set up, or say it is set per product.
* The grace-period setting is shown only when EasySubscription Pro, which uses it, is active.

= 0.21.0 =
* **Stores are told when subscriptions renew with tax added twice.** An admin notice gives the count and links to the list, where each can be repaired; it can be dismissed and comes back if more appear. Repair now returns you to the screen you started from, and says so when a subscription changed since the list was made.
* "Payments awaiting confirmation" now lists each renewal waiting more than 10 days — subscription, amount, days waiting and renewal order — and counts the wait from when the payment was submitted, not from when the period began.
* Database: the charge ledger records when a payment was submitted (upgraded automatically).

= 0.20.0 =
* **A renewal waiting for the bank to confirm it now says so.** Customers see "Active · Payment processing" with the date the payment was submitted, instead of a next-payment date in the past, and "Pay early" is not offered until it settles. The subscription list and screen show the same state to the store, and "Renew now" is hidden while it waits.
* **Pay-by-link renewals no longer read as failed payments.** For subscriptions paid by invoice or payment link, the account page says "Renewal due — your renewal is ready to pay" with a "Pay renewal" button, and never claims the subscription renews automatically.
* If a store marks a waiting renewal paid by hand and the payment provider later reports that payment failed, the order and the subscription's activity now say so once, so the store can collect or cancel. Nothing is changed automatically.

= 0.19.5 =
* **Fixed: a renewal waiting for its payment to clear could be paid a second time.** When a renewal first asked the customer to confirm their payment and later went to "awaiting confirmation" from the payment provider, the order stayed payable from the customer's account. The confirmation link is now cleared once the payment is submitted.

= 0.19.4 =
* **Fixed: stores that enter prices including tax charged tax twice on every renewal.** A subscription sold for 12.00 including 20% tax renewed at 14.40. New subscriptions now renew at exactly what the checkout charged, in both tax modes and every rounding setting. Existing subscriptions are not changed silently: WooCommerce → Settings → Subscriptions → Health lists the ones renewing with tax added twice, each with a Repair action — and customers may be owed a refund for renewals already taken.
* **Fixed: VAT-exempt customers were taxed on renewals.** The exemption now carries from the checkout to the subscription and every renewal, including for existing subscriptions.
* A subscription's shown total now includes its tax, so it matches what each renewal charges, and the dashboard's revenue figures exclude tax.

= 0.19.3 =
* **Fixed: a coupon that made the first payment free left the subscription unable to renew.** Only free trials asked for a card when nothing was due today, so a checkout brought to zero by a coupon saved no payment method and every renewal then failed. A checkout that costs nothing today now asks for a card whenever a later payment will actually charge something. Free-forever plans, and coupons that make every renewal free, are still not asked.
* The "collect payment on free trials" setting is now called "Free first payments", since it also covers coupons.

= 0.19.2 =
* **Fixed: two background workers could both believe they held a subscription's lock.** On current WordPress the lock that keeps renewal runs and follow-up emails from overlapping was not truly exclusive, so a rare race could send a win-back or anniversary email (and its coupon) twice. Payments were never at risk. The lock now has exactly one holder, and a worker whose lock expired can no longer release someone else's.

= 0.19.1 =
* The hourly renewal check no longer spends its batch on payments that are still clearing, so a store with many Direct Debits pending cannot starve renewals that genuinely need picking up.
* A retry that finds a renewal still clearing from its payment page now keeps the subscription on hold instead of leaving it active and unpaid.
* New status check: renewals awaiting payment confirmation for more than 10 days are flagged, in case the payment provider's confirmation never arrived.
* Once a pending payment is confirmed, the next renewal is scheduled for its date straight away rather than waiting for the hourly check.

= 0.19.0 =
* **New for payment gateways: payments confirmed days later.** A gateway can now report a renewal as pending — Direct Debit and bank payments that clear over several days. While it is pending the customer keeps their access, the renewal order waits on hold so it cannot be paid twice, and EasySubscription never retries or re-charges it; the gateway settles it once the money is confirmed or refused. Used by EasySubscription Pro's GoCardless, Razorpay and other gateways.
* A subscription that is ending waits for a pending renewal before it closes, and a renewal whose payment is still clearing from its pay page is not charged again.

= 0.18.7 =
* **Fixed: renewals ran up to an hour late, and busy stores fell behind.** After charging a renewal, EasySubscription failed to queue the next one, so every renewal waited for the hourly check — which queues at most 50 at a time, so a store with more renewals than that each hour slipped further behind. The next renewal is now queued as soon as one is charged.
* A payment gateway that keeps timing out is now retried with growing waits (2, 4, 8, 16 and 32 minutes) and then left to the hourly check, instead of the intended limit being ignored.

= 0.18.6 =
* **Fixed: subscriptions could not be read on stores using WooCommerce's legacy order storage.** Loading a subscription through the legacy (posts) order store failed, which broke those stores, stores running HPOS with compatibility sync turned on, and `wp wc hpos sync`. A save after a partial read could also wipe the subscription's next payment date, end date and parent order. Subscriptions now load with every field intact on both storages.

= 0.18.5 =
* **Fixed: subscriptions cancelled "at the end of the period" never ended.** They stayed in Cancelling for ever, so the customer kept their downloads, role and access, and nothing that reacts to a cancellation ever ran. They now end when the paid period runs out: access stops, a PayPal agreement is told to stop billing, and nothing is charged. Subscriptions already stuck in Cancelling are ended by the next hourly check.
* A renewal whose outcome was unknown when the customer cancelled is checked with the payment gateway before the subscription ends, so a payment that did go through is honoured rather than lost.

= 0.18.4 =
* **Fixed: paying a declined renewal could charge the customer twice.** Paying a failed renewal from its payment link (or marking a bank transfer paid) left the renewal unsettled, so the next automatic retry charged the card again — or, once retries ran out, cancelled a customer who had paid. A paid renewal now restarts the subscription exactly once, keeps the card it was paid with for future renewals, and is never charged again.
* **Fixed: renewals waiting on the customer could not be paid.** Bank-transfer renewals and renewals needing 3-D Secure authentication sat on hold with no way to pay them. They can now be paid from their payment link, and the 3-D Secure link opens Stripe's own page instead of a link that did nothing.
* **Security:** the 3-D Secure link no longer carries a Stripe client secret, which ended up in emails and server logs.
* A renewal's payment page now offers only the subscription's own payment method, and never PayPal, which would have started a second PayPal billing agreement.

= 0.18.3 =
* **Fixed: Stripe renewals always failed.** Stripe checkout saved the card without a Stripe customer, and a card with no customer cannot be charged again, so every Stripe subscription went on hold at its first renewal with "No stored Stripe payment method". Checkout now creates the customer. Subscriptions already affected repair themselves: the card from their first payment is attached to a new Stripe customer on the next attempt. Reactivate any that are on hold to charge the renewal they missed.

= 0.18.2 =
* **Security: "Role while subscribed" could grant Administrator.** A Shop Manager can change that setting, so they could make themselves — or any customer — an administrator by buying a subscription. It now offers, and will only ever apply, roles that cannot run the site or the store. A role saved by an earlier version that no longer qualifies is ignored; check the setting after updating.
* **Fixed: a Shop Manager who bought a subscription lost the store.** The role change replaced every role a user had. Staff are now left as they are.

= 0.18.1 =
* **Fixed: renewals silently failed until an administrator opened wp-admin.** EasySubscription created its tables only on an admin page view, so a store activated from WP-CLI, deployed by a host, or updated in the background had none — and every renewal until someone logged in failed against a table that did not exist. They are now created on the first request of any kind.
* **Fixed: the "Missed renewals" setting did nothing.** Its default — charge once and move the schedule forward — was saved and shown but never applied, so a subscription whose site had stopped running its scheduler was charged once for every missed period when it came back: three months down meant three charges within three hours. It now takes one charge covering the whole gap and resumes on the original day of the month. Choosing "Charge for every missed period" keeps the old behaviour.

= 0.18.0 =
* New filter `easysubscription_gateway_for_subscription`, so an extension can answer for a subscription whose WooCommerce payment method is not the id of the thing that renews it. EasySubscription Pro needs this to charge Mollie, Razorpay, Xendit, Square, Authorize.net or Braintree at all.

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
* New filters let an extension sell one product on more than one plan — see EasySubscription Pro's new Plans.
* The plugin is now called EasySubscription – Subscriptions for WooCommerce. Only the name changes: same plugin, same settings, updated in place.
* Deleting the plugin can now remove its data with it, if you ask it to under WooCommerce → Settings → Subscriptions. Off by default, and subscriptions and their orders are never deleted either way.

= 0.16.0 =
* The subscriptions list leads with the customer, says how far off the next payment is ("today", "11 days overdue") rather than only its date, and puts the status tabs, search and bulk actions in one toolbar - the bulk bar appears when you select something instead of sitting there disabled. An empty list now says what will fill it, and a filtered one offers to clear the filters.
* A subscription's own screen leads with the three figures that answer "what is this and what happens next", keeps the rest as details, and groups the activity log under the day each thing happened. Changing the schedule and ending the subscription are separate sections that say what they do first, and cancelling is no longer a button the same size and weight as Reactivate.
* Fixed: every outlined button and bordered panel in EasySubscription's screens was drawing no border, because the stylesheet's own reset outranked the border it was meant to leave alone.
* Notices from other plugins no longer open every EasySubscription screen. They are one line in the header bar that opens them; EasySubscription's own and WooCommerce's stay where they are.

= 0.15.0 =
* EasySubscription -> Settings is a screen of its own instead of a jump into the WooCommerce settings tab: sections down the left, the settings in cards, and a panel on the right with quick links and whether renewals can actually run. Everything EasySubscription and EasySubscription Pro add appears here, and the old WooCommerce tab still works.
* Fixed: an edited stylesheet or script could stay cached in the browser until the next release. Admin assets now carry the file's own timestamp.

= 0.14.0 =
* The subscription settings on the product screen are laid out in sections - Pricing, Billing settings, Shipping settings - with the number and unit of "Bill every" and "Free trial" on one row. The sign-up fee sits with the regular and sale price.
* Free trials can be set in weeks, months or years as well as days. A one-month trial that starts on 31 January ends on 28 February. Existing trials are unchanged: they are read as days.
* "Shipping required" in the panel is the same setting as WooCommerce's Virtual box, from the other side.
* Fixed: a subscription's end date was stored but never enforced, so renewals carried on past it. No renewal is now charged on or after the end date; the period already paid for runs to its end, and the subscription then expires.
* Fixed: on the classic checkout, the sentence beside Place order showed "<bdi>" tags around the amounts.
* When a subscription cannot be cancelled online, My Account now says so and why, in place of a Cancel button that only refused once pressed. For developers: the `easysubscription_cancel_refused_message` and `easysubscription_disclosure_sentence` filters are new, and the product panel's sections are actions extensions can add rows to.
* EasySubscription Pro 0.13.0 needs this version.

= 0.13.2 =
* The plugin description now says what EasySubscription does today - the Stripe and PayPal gateways, the block checkout, Home and the rest - and why it is still a development release. It had not been updated since the first release.
* No code changes.

= 0.13.1 =
* Fixed: on a store with more than 50 active subscriptions, the hourly check for missed renewals could skip overdue ones indefinitely. It looked at the 50 oldest subscriptions and only then checked which were due, so a newer subscription whose renewal had been missed was never picked up. It now asks for due subscriptions directly, soonest first.

= 0.13.0 =
* EasySubscription has a Home screen. Until setup is finished it leads with the checklist; after that it shows recurring revenue, what needs your attention, and your most recent subscriptions.
* The subscriptions list has its own page, EasySubscription → All subscriptions. Old links to a subscription or a filtered list still work: they are sent to the new address.
* Every EasySubscription screen sits in the same frame now - a header with breadcrumbs, the page heading and a footer - in a new design. Integrations are cards grouped by what they connect to, and Help collects where to look first, with a one-click copy of the system report.

= 0.12.3 =
* Fixed: the product page showed the price twice - "$5.00", then "$5.00 every month" - because WooCommerce printed the plain price and the terms block printed it again. The price itself now says how often it recurs, and the terms block starts with the facts.
* The price now says how often it recurs wherever WooCommerce prints one: the shop, category pages, related products. A product on sale keeps its struck-through old price. An instalment or split plan shows the plan instead of a price that would misstate it.

= 0.12.2 =
* Fixed: EasySubscription's payment methods never appeared on the block checkout, which is the default checkout in current WooCommerce. A classic gateway is invisible there until it registers itself with the blocks registry, so a shop using blocks saw "There are no payment methods available" while the payments screen said Active. Both gateways now register.
* Fixed: the rule that keeps EasySubscription's gateways off a cart with no subscription in it read the classic checkout only, so it could not answer correctly over the Store API the block checkout uses. It now looks at the cart, and gives the same answer to both.

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
* New for developers: a read-only /easysubscription/v1/overview endpoint, and the shared admin components EasySubscription Pro's screens will be rebuilt on.

= 0.9.2 =
* The plugin is now called EasySubscription – Subscriptions for WooCommerce. Nothing else changes; the same plugin, updated in place.
* Variable subscription is only offered as a product type when EasySubscription Pro can actually bill it. Free registered the type but could not give a variation a schedule, so a customer buying one was charged once and never again. A product that is already a variable subscription keeps its type and says so on the edit screen.
* EasySubscription Pro now refuses to activate without EasySubscription, and is deactivated along with it, instead of sitting in the plugin list doing nothing.
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
* The admin menu is now called EasySubscription rather than Subscriptions.
* EasySubscription Pro can be activated whatever folder the free plugin sits in, including the easysubscription-main that a GitHub ZIP produces.
* Developers can exercise the licence screens without a store; see docs/TESTING.md.

= 0.6.0 =
* Subscriptions now has its own top-level admin menu instead of four separate entries under WooCommerce.
* New Integrations screen: what EasySubscription can connect to, and whether each connection is live.
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
EasySubscription now opens on a Home screen, and the subscriptions list moves to EasySubscription → All subscriptions. Old links still work.
