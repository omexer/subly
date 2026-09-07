# SubKit — Tester's Guide

**Version 0.1.0 · development release**

Thanks for trying this. Please read the [What does not work yet](#what-does-not-work-yet) section before you start — it will save you from testing something that was never built.

---

## What SubKit is

A WooCommerce plugin that turns products into subscriptions: a customer buys once, a subscription record is created, and it bills on a repeating schedule. It also gives customers a place to see and cancel their own subscriptions, and gives you an admin screen to manage them.

## What state it is in

**This is a development release. Do not install it on a live store.** Use a staging site or a local test store with fake products.

The subscription lifecycle, the renewal engine and all the screens are built and working. What is **not** built is the ability to take an automatic recurring payment from a card or PayPal account. See below.

---

## Requirements

| | |
|---|---|
| WordPress | 6.5 or newer |
| WooCommerce | 8.0 or newer |
| PHP | 8.1 or newer |
| Order storage | High-Performance Order Storage (HPOS) recommended |

SubKit refuses to load and shows a notice if any of these are missing, rather than breaking your site.

---

## Install

1. Download the repository as a ZIP, or clone it into `wp-content/plugins/subkit-subscriptions`.
2. Activate **SubKit — Subscriptions & Recurring Payments for WooCommerce** from the Plugins screen.
3. Go to **WooCommerce → Subscriptions**. You will see a setup checklist.

---

## Setting up your first subscription

1. Go to **Products → Add New** (or edit an existing simple product).
2. Set a price.
3. In the **Product data** panel, tick **Subscription**.
4. Choose how often to bill: day, week, month or year, and an interval (2 + Month = every two months).
5. Optionally add a **Free trial** in days, and a **Sign-up fee**.
6. Publish.

Visit the product page. You should see the terms under the price, for example:

```
$29.00 every month
14 days free
$20.00 sign-up fee today
First payment $29.00 on 1 September 2026
Then $29.00 every month
Cancel anytime
```

Those same terms appear in the cart and at checkout, on both the classic and block checkout.

---

## Buying one

Because there is no automatic-payment method yet (see below), buy using any payment method your store already offers — **Cash on delivery**, **Direct bank transfer** or **Check payments** are easiest to enable under **WooCommerce → Settings → Payments**.

After checkout you should get:

- A subscription in **WooCommerce → Subscriptions**
- A "Your subscription is active" email to the customer, and a "New subscription started" email to the store
- An entry under **My account → Subscriptions**

> **Only one subscription per cart.** Adding a second one is blocked on purpose. A one-off product alongside a subscription is fine.

---

## Making a renewal happen

Renewals are normally scheduled automatically, but on a quiet test site WordPress's scheduler rarely runs, and there is no automatic payment method yet. So trigger them by hand:

**Option A — process it yourself (as the shop owner)**
1. **WooCommerce → Subscriptions**, click a subscription.
2. Click **Process renewal now**. It will ask you to confirm, and it names the amount.
3. A renewal order is created. With manual payment methods it will sit as pending, and the customer gets a "payment needed" email with a link to pay it.

**Option B — let the customer pay it**
1. As the customer, go to **My account → Subscriptions**.
2. A subscription needing payment shows a **Pay now** button.
3. Pay the renewal order. The subscription returns to Active and the next payment date moves forward.

**Option C — the built-in self-test**
On **WooCommerce → Subscriptions**, the setup checklist has **Run test renewal**. It creates a throwaway subscription, renews it, confirms it worked and deletes everything. Nobody is charged. Use this to check the plumbing works on your server.

---

## What to test

Please try all of these and tell us what felt wrong, confusing, or broken.

**Setting up**
- [ ] The setup checklist makes sense and the links go somewhere useful
- [ ] Creating a subscription product is obvious without instructions
- [ ] **Run test renewal** succeeds on your hosting

**As a customer**
- [ ] The terms on the product page are clear and correct
- [ ] The terms are still correct with a trial, a sign-up fee, or both
- [ ] Checkout explains what you are signing up for before you pay
- [ ] **My account → Subscriptions** shows the right status and next payment date
- [ ] Cancelling is easy to find and the two options (end of period / immediately) are clear
- [ ] Emails read like a human wrote them and arrive when expected

**As the shop owner**
- [ ] The Subscriptions list shows what you need at a glance
- [ ] The subscription detail screen answers "what happened to this customer?"
- [ ] The activity log is understandable
- [ ] **WooCommerce → Settings → Subscriptions** — the Status section reports honestly

**Try to break it**
- [ ] Cancel, then try to cancel again
- [ ] Process the same renewal twice in a row
- [ ] Set a subscription to bill on the 31st and check the following months
- [ ] Change the store currency and re-check the product page
- [ ] Use a different theme

---

## What does not work yet

Please do not report these — they are known and deliberate for this release.

| Not built | Consequence |
|---|---|
| **Automatic recurring payment** | SubKit adds **no payment method** to checkout. PayPal's renewal handling exists, but nothing creates the PayPal subscription during checkout, so cards and PayPal cannot bill automatically. Renewals must be triggered as described above. |
| **Unattended renewals unproven** | Scheduled renewals are wired up but have never been observed firing on their own. If yours do fire by themselves, please tell us — that is useful information. |
| Variable / variable-subscription products | Simple products only |
| Pause and resume | Planned |
| Upgrade / downgrade between plans | Planned |
| Installments and split payments | Planned |
| Coupons on renewals | Coupons apply to the first order only |
| Reporting, MRR and churn | Planned |
| Multiple subscriptions in one cart | Blocked on purpose |

---

## Safety

A few things are deliberately protective, and worth knowing about:

- **Copied sites will not bill.** A subscription records the site it was created on. If you clone your store to staging, the copy refuses to process renewals rather than charging real customers twice.
- **A renewal can only be charged once.** The database enforces one charge per billing period, so a retry or a duplicate webhook cannot double-charge.
- **Cancelling is always available to the customer** and cannot be disabled by the shop owner.
- **Erasing personal data will not touch an active subscription.** It refuses and tells you to cancel first, so billing stops at the payment provider before the record is scrubbed.

---

## Reporting a problem

Open an issue on the repository with:

1. What you were doing
2. What you expected
3. What happened instead
4. WordPress, WooCommerce and PHP versions, plus your theme
5. Anything from the subscription's **Activity** log on the detail screen

If it involves money — a wrong amount, a charge that should not have happened, a renewal that did not — please say so in the first line. Those get looked at first.

---

## A note on what we most want to hear

Feature requests are welcome, but the most useful feedback at this stage is about **trust**: anywhere the plugin left you unsure whether something had worked, whether a customer had been charged, or what would happen next. Those moments matter more than missing features.
