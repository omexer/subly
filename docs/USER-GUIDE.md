# SubKit — User Guide

**SubKit 0.9.0 (free) · SubKit Pro 0.9.0 · development release**

Read [What is not proven yet](#what-is-not-proven-yet) before you put this anywhere near a real customer. It is short, and it is the honest part.

---

## What SubKit is

A WooCommerce plugin that turns products into subscriptions. A customer buys once; SubKit creates a subscription record and bills it on a repeating schedule. Customers manage their own subscriptions from My Account. You get an admin screen, a renewal engine and a health report.

There are two plugins. **SubKit** is free and complete on its own. **SubKit Pro** adds to it and cannot run without it.

---

## Requirements

| | |
|---|---|
| WordPress | 6.5 or newer |
| WooCommerce | 8.0 or newer |
| PHP | 8.1 or newer |
| Order storage | High-Performance Order Storage (HPOS) |

SubKit refuses to load and shows a notice if any of these are missing, rather than half-working.

## Install

1. Install and activate **SubKit — Subscriptions & Recurring Payments for WooCommerce**.
2. For Pro: install **SubKit Subscriptions Pro** and activate it *after* the free plugin. Pro boots from a hook the free plugin fires, so the order matters. If Pro is activated alone it says so and does nothing. Pro needs free 0.9.0 or newer and refuses to boot against an older one rather than half-working.

> The folder name does not matter. Installing the free plugin from a GitHub ZIP gives you a folder called `subkit-subscriptions-main`, and Pro is happy either way.
3. Go to **SubKit** in the admin menu, directly below WooCommerce, for the setup checklist.

---

## Your first subscription product

The fastest way: on **SubKit → All subscriptions**, the setup checklist has a short form — name, price, how often, optional free trial. Fill it in and press **Create it**. The product is published and ready.

The manual way, and how you edit one afterwards:

1. **Products → Add New**, or edit an existing simple product.
2. Set a price.
3. In the **Product data** panel, tick **Bill this product on a repeating schedule**. The schedule fields appear once it is ticked.
4. Choose the period and interval — `2` + `Month` means every two months.
5. Optionally add a **Free trial** in days and a **Sign-up fee**.
6. Publish.

On the product page the terms appear under the price, as separate facts rather than one long sentence:

```
$29.00 every month
14 days free
$20.00 sign-up fee today
First payment $29.00 on 1 September 2026
Then $29.00 every month
Cancel anytime
```

The same terms follow the product into the cart and checkout, on both the classic and block checkout.

> **One subscription per cart.** A second one is blocked on purpose. A one-off product alongside a subscription is fine.

---

## Taking payment

SubKit adds two payment methods of its own at **SubKit → Settings**. Both are off until you enter credentials.

| Gateway | Who owns the schedule | Where |
|---|---|---|
| **Stripe** | SubKit — we charge the saved card when a renewal falls due | Free |
| **PayPal** | PayPal — it bills on its own schedule and we mirror it | Free |
| **Mollie** | SubKit, against the mandate the Mollie plugin stored | Pro |
| **Razorpay** | SubKit, against the saved token | Pro |
| **Xendit** | SubKit, against the saved card token | Pro |

The three Pro gateways renew against a mandate that the merchant's **existing** payment plugin captured at checkout — keep Mollie Payments for WooCommerce, WooCommerce Razorpay or the Xendit plugin installed and configured for the initial payment. SubKit only handles the renewal.

You can also run without any of them: enable a manual method such as **Cash on delivery** or **Direct bank transfer**, and renewals become invoices the customer pays.

---

## Making a renewal happen on a test site

On a quiet site WordPress's scheduler rarely runs, so trigger renewals by hand.

**As the shop owner** — **SubKit → All subscriptions**, open one, click **Process renewal now**. It names the amount and asks you to confirm.

**As the customer** — **My account → Subscriptions**, a subscription needing payment shows **Pay now**.

**The self-test** — the setup checklist has **Run test renewal**. It creates a throwaway subscription, renews it, checks it worked and deletes everything. Nobody is charged. Use it to prove the plumbing works on your server.

---

## What customers can do

From **My account → Subscriptions**:

- See status, next payment date and what they are paying
- **Cancel** — either at the end of the period they have paid for, or immediately. This cannot be switched off by the shop owner.
- **Turn off automatic renewal** — if you enable it in settings. This is not cancelling: they keep everything they paid for until the period runs out, and are never charged again.
- Pay a renewal that needs paying
- **Pause and resume** (Pro), and **switch plan** (Pro), where the gateway supports it

### Buying without an account

A subscription has to belong to someone — it is managed from My Account and renews against a stored mandate. Under **SubKit → Settings → Access**, choose:

- **Create an account for them automatically** (default) — a guest who buys a subscription gets an account made at checkout.
- **Require them to log in first**.

An email address that already has an account is never claimed silently. Checkout stops and asks them to log in, because attaching the subscription would put a stranger's details inside somebody else's account.

---

## What a subscription grants

Under **SubKit → Settings → Access**:

- **Role while subscribed** / **Role once it ends** — how most membership setups gate content. Administrators are never demoted, and a customer is only demoted once no other live subscription is keeping them in.
- Downloadable files attached to a subscription product are withdrawn when no live subscription covers them. Someone who resubscribed after cancelling keeps their files.

Pro adds per-product role overrides and gates the download capability itself.

---

## The admin menu

Everything lives under one **SubKit** entry, directly below WooCommerce:

| | |
|---|---|
| **All subscriptions** | The list, and each subscription's detail and activity log |
| **Reports** | MRR, ARR, churn, lifetime value (Pro) |
| **Deliveries** | What ships when, with a printable manifest (Pro) |
| **Health** | Subscriptions at risk, and why (Pro) |
| **Integrations** | What SubKit can connect to, and whether each connection is live |
| **Help** | A system report to paste into a support request |
| **Settings** | Gateways, access, licence |

## Admin screens

**SubKit → All subscriptions** — the list, with monthly recurring revenue and live count above it. Search by name, email or id; sort by id, next payment or total; filter by status. Select rows to cancel, hold or reactivate in bulk, or use the row actions to view one, renew it now, or open its parent order.

Bulk changes go through the same rules as everything else: a subscription that cannot legally make the change is skipped rather than forced, and the notice says how many were left alone and why.

Open a subscription for its schedule, its orders and a full activity log of every charge attempt and status change.

**SubKit → Settings → General** — a Status panel that reports honestly:

| Check | Means |
|---|---|
| Renewal queue | Scheduled tasks are running |
| Unresolved charges | A charge whose outcome we never learned. That subscription is **not billing** until someone looks. |
| Double-charge protection | The database index that makes one charge per period impossible |

### Pro screens

- **Subscription reports** — recurring revenue over time, new subscriptions per day and a status ring, plus MRR, ARR, churn, lifetime value and cancellation reasons. The charts are drawn on the server, so they print and need no scripts.
- **Subscription health** — every subscription at risk, why, and what to do. Each row offers **Retry now** (charge again immediately, through the same pipeline as a scheduled renewal), **Ask the customer** (re-send the failed-payment email with its pay link), **Queue renewal** and **Dismiss**. Six signals over the charge ledger: overdue with nothing queued, a failed charge, a failure with no retry booked, a charge stuck with an unknown outcome, a renewal waiting on customer authentication, and recovery about to give up. Optional scheduled digest email.
- **Subscription deliveries** — what ships when, with a printable manifest, for physical subscriptions whose delivery cadence differs from billing

---

## Pro features

| | |
|---|---|
| **Variable subscriptions** | One product, several schedules — a yearly variation beside a monthly one, each with its own trial and fee |
| **Instalment plans** | A fixed total over N charges, then stop |
| **Pause and resume** | With the remaining time preserved |
| **Plan switching** | Upgrade or downgrade, crediting unused time |
| **Recurring coupons** | Discounts that apply to renewals, not just the first order |
| **Failed payment recovery** | Retry schedule, dunning emails and a grace period before giving up |
| **Delivery schedules** | Delivery cadence independent of billing, with a manifest |
| **Subscription health** | The report above |
| **Reports** | MRR, ARR, churn, LTV |
| **Content access** | Per-product roles and download gating |
| **REST API** | List, read, update, run lifecycle actions, read the activity log |
| **Subscription limits** | One active, one ever, or a fixed number per customer; plus a cap on total payments |
| **Gateway restriction** | Narrow which payment methods a subscription purchase may use |
| **Split payments** | A set price times N payments, with access that can outlive the plan |
| **QR status page** | A code for the packing slip, linking to a private status page |
| **Integrations** | LearnDash, TutorLMS, LearnPress, MailPoet, FluentCRM, WP Fusion, AutomatorWP, License Manager for WooCommerce, WP Software License |

### The licence

**SubKit → Settings → Licence**. Enter your key and activate.

The licence gates **updates and support only**. It never touches billing: if it lapses, expires, or the licence server is unreachable, your Pro features keep working and your customers keep being charged. An unreachable server is treated as unknown, not invalid — a dropped connection is not a revocation.

### The REST API

Authenticated as WooCommerce itself — a consumer key with the `manage_woocommerce` capability, created under **WooCommerce → Settings → Advanced → REST API**. There is no second password to manage.

```
GET    /wp-json/subkit/v1/subscriptions?status=sk-active&per_page=20
GET    /wp-json/subkit/v1/subscriptions/123
PUT    /wp-json/subkit/v1/subscriptions/123        { "next_payment": "2027-01-01 00:00:00" }
POST   /wp-json/subkit/v1/subscriptions/123/actions { "action": "cancel" }
GET    /wp-json/subkit/v1/subscriptions/123/activity
```

Actions: `cancel`, `pause`, `resume`, `reactivate`, `expire`, `change_status`. The API cannot reach a state the admin screens forbid — reactivating a cancelled subscription returns `409` and changes nothing. Repeating an action that already happened returns `200`, so a client retrying after a dropped connection is not an error.

---

## Safety

Worth knowing, because these are the parts that protect money:

- **A renewal can only be charged once.** A database index enforces one charge per billing period. A retry, a duplicate webhook or two servers racing cannot produce two charges.
- **An unknown outcome is never guessed.** If a gateway times out, SubKit asks it what actually happened before doing anything else. If the gateway has no record, the same charge is retried with the same idempotency key. If it cannot be resolved safely, the subscription stops and says so under **Unresolved charges** rather than risking a second charge.
- **Copied sites will not bill.** A subscription records the site it was created on. Clone your store to staging and the copy refuses to renew rather than charging real customers twice.
- **Cancelling is always available to the customer.**
- **Erasing personal data will not touch an active subscription.** It refuses and tells you to cancel first, so billing stops at the provider before the record is scrubbed.
- **A lapsed licence never stops billing.**
- **The QR status page cannot be enumerated.** It resolves by an unguessable token per subscription and refuses a subscription id outright, so nobody can walk through your customers' subscriptions. It never shows an email, phone, name, address or payment method.

---

## What is not proven yet

Please do not report these; they are known.

| | |
|---|---|
| **No gateway has been tested against a real sandbox** | Every Stripe, PayPal, Mollie, Razorpay and Xendit code path has been verified only against simulated HTTP responses. No real card has ever been charged by this plugin. **This is the single biggest reason not to run it on a live store.** |
| **There is no automated test suite** | No unit tests, no integration tests, no CI. Every claim above was verified by hand. |
| **Unattended renewals have never been observed** | Scheduled renewals are wired up and fire correctly when run directly, but no renewal has been watched happening on its own overnight. If yours does, that is useful — tell us. |
| ~~Concurrency untested~~ | **Tested.** Eight real processes released together against one subscription produce exactly one charge. See `docs/TESTING.md`. |
| Per-variation delivery cadences | Variable subscriptions and delivery schedules both work; combined, delivery reads the parent product only. |
| Browser testing | Nothing has been driven through a real browser. Screens are verified by their output, not visually. |

---

## Reporting a problem

Open an issue with:

1. What you were doing
2. What you expected
3. What happened instead
4. WordPress, WooCommerce and PHP versions, plus your theme
5. Anything from the subscription's **Activity** log

**If it involves money** — a wrong amount, a charge that should not have happened, a renewal that did not — say so in the first line. Those get looked at first.

---

## What we most want to hear

Feature requests are welcome, but the most useful feedback now is about **trust**: anywhere the plugin left you unsure whether something had worked, whether a customer had been charged, or what would happen next. Those moments matter more than missing features.
