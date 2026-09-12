# SubKit — User Guide

**SubKit 0.11.0 · SubKit Pro 0.11.0 · development release**

This guide explains everything SubKit does and every setting it has, in plain language. You
do not need to be technical to follow it.

Please read [What is not proven yet](#what-is-not-proven-yet) before taking real money.

---

## Contents

1. [What SubKit does](#what-subkit-does)
2. [Installing](#installing)
3. [Your first subscription in five minutes](#your-first-subscription-in-five-minutes)
4. [Making subscription products](#making-subscription-products)
5. [Every setting, explained](#every-setting-explained)
6. [Taking payment](#taking-payment)
7. [What your customers see](#what-your-customers-see)
8. [Running the shop day to day](#running-the-shop-day-to-day)
9. [Pro features](#pro-features)
10. [The REST API](#the-rest-api)
11. [When something looks wrong](#when-something-looks-wrong)
12. [What is not proven yet](#what-is-not-proven-yet)

Then, once you know what the settings do, [BUSINESS-EXAMPLES.md](BUSINESS-EXAMPLES.md) builds
twelve real businesses with it — a coffee box, a course library, a SaaS licence, a gym — with
the field values to type and the integrations each one needs.

---

## What SubKit does

A normal WooCommerce product is bought once. A **subscription** product is bought once and
then charges again on a schedule — every month, every year, every two weeks, whatever you
choose.

SubKit adds that. It creates a subscription record when somebody buys, charges them when the
next payment falls due, emails them when something needs their attention, and gives them a
place to see and cancel it themselves.

**What you need:** WordPress 6.5+, WooCommerce 8.0+, PHP 8.1+. SubKit refuses to start and
tells you if any of these is missing, rather than half-working.

**Free or Pro?** The free plugin is complete on its own — products, billing, renewals,
emails, customer self-service, Stripe and PayPal. Pro adds instalments, plan switching,
pausing, failed-payment recovery, delivery schedules, reports, content access, three more
payment gateways and nine integrations.

---

## Installing

1. Install and activate **SubKit – WooCommerce Subscriptions**.
2. For Pro, install **SubKit Subscriptions Pro** and activate it **after** the free plugin.

Pro does nothing on its own and says so if you activate it alone. It needs the free plugin
version 0.9.0 or newer.

> **The folder name does not matter.** Downloading from GitHub gives you a folder called
> `subkit-subscriptions-main`. That is fine — Pro finds the free plugin either way.

Then go to **SubKit** in the admin menu, just below WooCommerce.

---

## Your first subscription in five minutes

On **SubKit → All subscriptions** there is a checklist. It walks you through four things, and
the fourth is the one that matters.

**1. Connect a payment method.** Stripe or PayPal — both are included free, and the checklist
names whichever you connect. You can also skip it entirely and test with Cash on delivery;
renewals then become invoices the customer pays by hand, which still works.

**2. Create a subscription product.** There is a short form right there: name, price, how
often, optional free trial. Fill it in, press **Create it**, and the product is published.

**3. Check renewals can run.** This tells you whether WordPress's scheduler is working. On a
quiet site it often is not, which is the single most common reason subscriptions stop
billing.

**4. Run a test renewal.** This is the important one. It creates a throwaway subscription,
renews it, checks the whole chain worked, and deletes everything. Nobody is charged. If this
passes, your billing works. If it fails, it tells you why.

---

## Making subscription products

In **Products → Add New**, the **Product data** dropdown now has two extra entries:
**Subscription** and **Variable subscription**. Pick one and the **Billing schedule** fields
appear under General.

### Which type should I choose?

The question to ask is simple: **does the customer have a choice to make before buying?**

| | Choose **Subscription** | Choose **Variable subscription** *(Pro)* |
|---|---|---|
| The customer picks | Nothing — one price, one schedule | A plan, a size, a tier |
| Price | One price | A different price per option |
| Product page | Add to cart | A dropdown, then Add to cart |
| Shop page shows | `£29.00 every month` | `From £10.00 every month` |

**Choose Subscription when there is one way to buy it:**

- A £29/month membership
- A weekly veg box, one size
- Software billed yearly at one price
- A £5/month supporter tier

**Choose Variable subscription when the same thing is sold several ways:**

- **Monthly £10 / Yearly £100** — the classic. Same product, two billing schedules, and the
  yearly one is cheaper per month. This is the most common reason to use it.
- **Small box £15 / Medium £25 / Large £40**, all monthly
- **Basic / Pro / Team**, each a different price
- **Ground coffee / Whole bean**, same price, both monthly — a choice that does not change the
  billing at all is still a variation

Each variation can have its **own** schedule, or share the parent's. On a variation you get
**Different billing schedule** — tick it to give that one its own period, interval, trial and
fee. That is how Monthly-£10 and Yearly-£100 live on one product.

Twelve worked examples, with the values to type, are in
[BUSINESS-EXAMPLES.md](BUSINESS-EXAMPLES.md).

**Still not sure?** Start with **Subscription**. If you later need a second price or plan, you
can change the type on the same product — WooCommerce keeps the product, its URL and its
reviews.

> **Variable subscription needs Pro**, and only appears in the dropdown while Pro is active.
> A variation holds none of its parent's meta, so without Pro's resolver nothing gives it a
> billing schedule and it would sell as a one-off purchase. If you already have a variable
> subscription and Pro is switched off, the product keeps its type and SubKit says so on the
> edit screen — it does not quietly turn into a simple product.

**What about physical and digital?** Either type works for both. Tick **Virtual** for
something not shipped and **Downloadable** for a file, exactly as you would on a normal
product. Delivery schedules (Pro) apply only to products that are actually shipped.

### The billing schedule fields

| Field | What it means |
|---|---|
| **Bill every** | Day, Week, Month or Year. |
| **Interval** | How many of those. `2` + `Month` = every two months. |
| **Free trial (days)** | Days before the first charge. `0` means charge immediately. A customer on a 14-day trial pays nothing today and is charged in two weeks. |
| **Sign-up fee** | A one-off amount charged *today*, on top of the first payment. Leave empty for none. |

The **Regular price** is what recurs. So price `29`, Bill every `1` `Month`, sign-up fee `50`
means: £79 today, then £29 every month.

### The Pro fields on a product

Pro adds more fields to the same panel. All are optional; leave them alone and the product
bills as a plain subscription.

| Field | What it does | Use it for |
|---|---|---|
| **Installment plan** + **Number of payments** | Splits one fixed total into N equal charges, then **stops**. £300 as 3 × £100. | Paying off a course or a product over time |
| **Payment type** + **Number of payments** | A set price charged N times, then stops. Unlike instalments, the price is per payment, not a total divided up. | A 6-month programme at £50 a month |
| **Access ends** / **Access duration** | Whether access outlives the payments: lifetime, when the payments end, or a custom period after | "Pay for 3 months, keep it forever" |
| **Purchase limit** + **Maximum per customer** | One active, one ever, or a set number per customer | Stopping somebody buying the same membership twice |
| **Maximum payments** | Ends the subscription after this many charges, whatever else is set | A 12-month contract that must not auto-renew into year two |
| **Automatic renewals only** | Hides payment methods that cannot renew by themselves | Products you refuse to invoice manually |
| **Delivery schedule** + **Deliver every** / **Deliver on** | A shipping cadence separate from billing | Pay monthly, ship weekly |
| **Grant role while active** | A WordPress role for this product only, overriding the store-wide setting | A "premium member" role for one tier |

Two of these are easy to confuse:

- **Instalment plan** divides one total. £300 over 3 payments = £100 each.
- **Payment type / split payments** repeats a price. £100 × 3 payments = £300 total.

They do the same arithmetic from opposite ends, and a product cannot use both — SubKit
refuses to save that.

### What the customer sees

SubKit writes the terms on the product page automatically — as separate plain facts, not one
long sentence:

```
$29.00 every month
14 days free
$20.00 sign-up fee today
First payment $29.00 on 1 September 2026
Then $29.00 every month
Cancel anytime
```

The same terms follow the product into the cart and the checkout.

> **One subscription per cart.** A customer cannot buy two subscriptions at once — that is
> deliberate. A subscription plus ordinary products is fine.

### Variable subscriptions

*(Pro.)* Choose **Variable subscription**, set up your variations as normal, and each variation can
have its own schedule. On each variation you will find **Different billing schedule** — tick
it to give that variation its own period, interval, trial and fee. Leave it unticked and the
variation follows the parent product.

The shop page shows "From £10.00 every month", using the cheapest variation, because until
somebody picks one there is no single price to state.

---

## Every setting, explained

All of these live under **SubKit → Settings**.

### General → Health

Not settings — a read-out. Three lines telling you whether billing is actually working:

| Line | What it means | If it is red |
|---|---|---|
| **Renewal queue** | WordPress's background task system is running | Renewals are not happening. On a quiet site, ask your host to set up a real server cron. |
| **Unresolved charges** | A charge whose outcome nobody ever learned | That subscription has **stopped billing** on purpose, because charging again might charge twice. Open it and check your payment provider. |
| **Double-charge protection** | The database safeguard is in place | Deactivate and reactivate SubKit. |

### General → Renewals

**Missed renewals** — what to do when a subscription is overdue by more than one period,
which almost always means your site had no traffic and the scheduler stalled.

| Choice | What happens |
|---|---|
| **Charge once and move the schedule forward** *(recommended)* | The customer is charged once and the next date moves on. |
| **Charge for every missed period** | The customer is charged for each missed period, all at once. |

Only use the second if you physically ship goods for every period regardless. Otherwise you
are billing a customer several times over for an outage that was not their fault.

**Grace period (days)** — default `7`. After a payment fails, how long to keep trying before
giving up. **The customer keeps their access during this window.** Set it to `0` to give up
immediately; `14` gives someone a fortnight to notice their card expired.

### General → Access

**Buying without an account** — a subscription has to belong to someone, so that they can
manage and cancel it.

| Choice | What happens |
|---|---|
| **Create an account for them automatically** *(default)* | A guest who buys a subscription gets an account made at checkout. Smoothest for the customer. |
| **Require them to log in first** | They must sign in or register before buying. |

If someone checks out with an email that already has an account, SubKit stops and asks them
to log in. It will not attach the subscription to an account they have not proved is theirs.

> If you choose **Require them to log in** but WooCommerce is not showing a login on the
> checkout page, SubKit warns you — otherwise every subscription customer hits a dead end.

**Let customers turn off renewal** — off by default. Turn it on to show a switch in My
Account.

This is **not** cancelling. The customer keeps everything they have paid for until the
current period ends, and is simply never charged again. Many people who would otherwise
cancel in frustration will use this instead, and some turn it back on.

**Role while subscribed** / **Role once it ends** — the WordPress user role a customer gets
while paying, and what they drop to when they stop. This is how you gate content without a
membership plugin.

Leave both as **Leave the role alone** if you do not use roles.

Two safeguards: an **administrator is never changed** (you cannot demote yourself by buying
your own product), and a customer is only dropped when **no other live subscription** of
theirs still grants the role.

**Health digest** *(Pro)* — how often to email you a summary of subscriptions needing
attention: Daily, Weekly *(default)*, Monthly or Never.

---

## Taking payment

SubKit adds its own payment methods under **SubKit → Settings**. All are off until you enter
credentials.

| Gateway | Who keeps the schedule | Tier |
|---|---|---|
| **Stripe** | SubKit. Stripe stores the card; we charge it when a payment falls due. | Free |
| **PayPal** | PayPal. It bills on its own schedule and tells us by webhook. | Free |
| **Mollie**, **Razorpay**, **Xendit** | SubKit, using the mandate those plugins already stored | Pro |

Every gateway has an **Environment** setting: **Test/Sandbox** or **Live**. Always start in
test.

### Stripe

Enter your **Test secret key** and **Live secret key** from your Stripe dashboard (they start
`sk_test_` and `sk_live_`). Switch Environment to Live when you are ready.

### PayPal

PayPal needs four things, and one of them catches everybody:

1. **Client ID** and **Secret** from your PayPal app.
2. **Webhook URL** — SubKit shows it. Copy it into your PayPal app and subscribe it to the
   billing-subscription and payment-sale events.
3. **Webhook ID** — PayPal gives you this *after* you add the URL. Paste it back.

**Without the Webhook ID, SubKit rejects every webhook** — because it cannot prove the
message really came from PayPal — and no renewal is ever recorded. If PayPal is taking money
and your subscriptions are not updating, this is why.

### Mollie, Razorpay and Xendit (Pro)

These three renew against a payment mandate that the merchant's **existing** plugin captured
at checkout. Keep Mollie Payments for WooCommerce, WooCommerce Razorpay or the Xendit plugin
installed and configured for the first payment. SubKit only handles the renewals.

### Every gateway field

| Field | Appears on | What to put in it |
|---|---|---|
| **Enable PayPal** / **Enable Stripe** | Free gateways | Tick to offer it at checkout. Off until you do. |
| **Enable Mollie renewals**, **Enable Razorpay renewals**, **Enable Xendit renewals** | Pro gateways | Tick to let SubKit renew against that gateway's stored mandate. |
| **Environment** | All | **Test**/**Sandbox** while you are setting up, **Live** when real money should move. |
| **Test secret key** / **Live secret key** | Stripe | From Stripe → Developers → API keys. `sk_test_…` and `sk_live_…`. |
| **Client ID** / **Secret** | PayPal | From your PayPal app. |
| **Webhook URL** | PayPal | SubKit shows it — copy it into PayPal. |
| **Webhook ID** | PayPal | PayPal gives you this after you add the URL. Paste it back. |
| **Test API key** / **Live API key** | Mollie, Xendit | From that gateway's dashboard. |
| **Key ID** / **Key secret** | Razorpay | From the Razorpay dashboard. |

A gateway with no credentials is never offered at checkout, even if enabled — so a
half-configured gateway cannot be chosen by a customer and then fail.

### No gateway at all

You can run without any of them. Enable **Cash on delivery** or **Direct bank transfer**, and
each renewal becomes an invoice the customer pays by hand.

---

## What your customers see

Under **My account → Subscriptions** they get a list and a detail page, where they can:

- See the status, next payment date and what they are paying
- **Cancel** — either at the end of the period they have paid for, or immediately. You cannot
  switch this off; being unable to cancel is what causes chargebacks.
- **Turn off automatic renewal**, if you enabled it
- **Pay** a renewal that failed
- **Pause**, **Resume** and **Switch plan** (Pro, where the gateway supports it)

### Emails

Six emails, all editable under **WooCommerce → Settings → Emails**:

| Email | When |
|---|---|
| Subscription started | The first payment succeeds |
| Renewal receipt | A renewal is paid |
| Payment failed | A charge is declined — includes a link to pay |
| Confirm your payment | The bank wants the customer to authenticate |
| Subscription cancelled | It ends |
| New subscription *(to you)* | Somebody subscribes |

---

## Running the shop day to day

### SubKit → All subscriptions

Recurring revenue and live count at the top, then the list. You can **search** by name, email
or id, **sort** by id, next payment or total, and **filter** by status.

Tick rows to **cancel**, **put on hold** or **reactivate** several at once. Each row also
offers **View**, **Renew now** and **Parent order**.

Bulk changes follow the same rules as everything else: a subscription that cannot legally
make that change is skipped, not forced, and you are told how many were left alone and why.

### A single subscription

Everything about it, plus an **Activity log** — every charge attempt, every status change,
with the reason. When a customer asks "why was I charged?", the answer is here.

**Process renewal now** charges it immediately, naming the amount before you confirm.

### The statuses

| Status | Meaning |
|---|---|
| **Pending** | Created, not started |
| **Trialling** | In a free trial, not yet charged |
| **Active** | Billing normally |
| **On hold** | A payment failed, or it was paused. Not billing. |
| **Pending cancel** | Cancelled, running out the paid period |
| **Cancelled** | Ended |
| **Expired** | Ran its course |
| **Switched** | Replaced by a different plan |

---

## Pro features

| | |
|---|---|
| **Instalment plans** | A fixed total over N payments, then it stops. £300 as 3 × £100. |
| **Split payments** | A set price × N payments, with access that can outlive the plan |
| **Pause and resume** | Remaining time is preserved |
| **Plan switching** | Upgrade or downgrade, crediting unused time |
| **Recurring coupons** | Discounts that apply to renewals, not only the first order |
| **Failed payment recovery** | Retries, dunning emails, and the grace period |
| **Subscription limits** | One active, one ever, or N per customer; and a cap on total payments |
| **Delivery schedules** | Ship on a different cadence from billing, with a printable manifest |
| **Subscription health** | Everything at risk, why, and one-click fixes |
| **Reports** | Revenue over time, signups, status breakdown, churn, lifetime value |
| **Content access** | Roles and downloadable files follow the subscription |
| **Live QR** | A code for the packing slip linking to a private status page |
| **REST API** | Pause and resume over the API, plus reports and health. The subscriptions API itself is free — see [the REST API](#the-rest-api). |
| **Nine integrations** | See [INTEGRATIONS.md](INTEGRATIONS.md), and [BUSINESS-EXAMPLES.md](BUSINESS-EXAMPLES.md) for which to connect for what |

### Live QR settings

**SubKit → Settings → Live QR.** Prints a QR code on the subscription so you can put it on a
packing slip; the customer scans it and sees where their subscription stands.

| Setting | Default | What it does |
|---|---|---|
| **Enable Live QR** | On | Show the code and serve its status page. |
| **Show on the status page: what the subscription is for** | On | The product name. |
| **…the billing schedule and recurring total** | On | What they pay and how often. |
| **…a timeline of what has happened so far** | Off | Status changes over time. |

Each code carries its own secret link. Nobody can reach a subscription they were not given the
code for, and regenerating a code retires every slip already printed with it.

**The page never shows a name, email address, phone number, postal address or payment
details** — whichever of the three sections you switch on.

### The Pro licence

**SubKit → Settings → Licence.** Paste your key and activate.

The licence controls **updates and support only**. If it lapses or expires, or the licence
server is unreachable, **your Pro features keep working and your customers keep being
charged.** A licence problem will never stop you taking money.

---

## The REST API

Free. Everything below works without a licence, authenticated the way WooCommerce
authenticates everything else: **WooCommerce → Settings → Advanced → REST API**, a key with
read/write on an account that can manage WooCommerce.

All of it lives under `/wp-json/subkit/v1/`.

| Route | Method | What it does |
|---|---|---|
| `/subscriptions` | GET | A page of subscriptions. `page`, `per_page`, `status`, `customer`, `search`. Totals come back in the `X-WP-Total` and `X-WP-TotalPages` headers. |
| `/subscriptions/<id>` | GET | One subscription. |
| `/subscriptions/<id>` | POST | Move `next_payment` or `end_date`. |
| `/subscriptions/<id>/actions` | POST | `cancel`, `expire`, `reactivate`, `change_status` — and `pause` / `resume` with Pro. |
| `/subscriptions/<id>/activity` | GET | What has happened to it, newest first. |
| `/subscriptions/statuses` | GET | The statuses and what they are called, so you do not hard-code either. |
| `/overview` | GET | The figures on the SubKit landing screen. |
| `/reports` | GET | *(Pro)* Every figure on the Reports screen. `days` sets the range. |
| `/health` | GET | *(Pro)* Subscriptions at risk, why, and what would fix them. |
| `/health/<id>/actions` | POST | *(Pro)* `retry_now`, `requeue`, `email_customer`, `dismiss`, `restore`. |

Three behaviours worth knowing before you build on it:

- **An action that repeats itself succeeds.** Cancelling a cancelled subscription returns
  200, not a conflict — a call retried after a dropped connection must not look like a
  failure.
- **An action the status forbids returns 409**, and says which status it was in. That is a
  refusal, not an error.
- **Asking for `pause` without Pro returns 400**, because the action is not in the schema on
  that site. It is never a 500.

---

## When something looks wrong

**Renewals are not happening.** Check **Settings → General → Health**. If the renewal queue is
red, WordPress's scheduler is not running — on a quiet site it needs real traffic or a server
cron. Ask your host for a "server cron" pointing at `wp-cron.php`.

**A subscription stopped billing and nothing explains it.** Check **Unresolved charges** in
that same panel. If a charge's outcome was never learned, SubKit stops rather than risk
charging twice. Look the payment up at your provider, then act on the subscription.

**PayPal is charging but subscriptions are not updating.** The Webhook ID is missing or
wrong. See the PayPal section above.

**An integration is not doing anything.** **SubKit → Integrations** shows whether its plugin
is active. An integration whose plugin is missing does nothing, silently.

**I need to ask for help.** **SubKit → Help** has a system report — versions, settings, queue
health — to paste into your request. It contains no passwords and no customer data.

---

## What is not proven yet

This is a development release. Please read this before taking real money.

| | |
|---|---|
| **No payment gateway has been tested against a real account** | Every Stripe, PayPal, Mollie, Razorpay and Xendit code path has been checked only against simulated responses. **No real card has ever been charged by this plugin.** This is the single biggest reason not to run it on a live store yet. |
| **There is no automated test suite** | Everything has been verified by hand. |
| **Unattended renewals have not been watched** | Renewals work when triggered, but no renewal has been observed happening on its own overnight. |
| ~~Concurrency~~ | **Tested.** Eight processes released together on one subscription produce exactly one charge. |
| Integrations | None of the nine plugins they connect to is installed on the development machine. See [INTEGRATIONS.md](INTEGRATIONS.md). |
| Browser testing | Very little has been checked visually. |

If it involves money — a wrong amount, a charge that should not have happened, a renewal that
did not — please say so in the first line of your report. Those get looked at first.
