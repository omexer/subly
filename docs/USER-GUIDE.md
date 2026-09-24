# SubKit — User Guide

**SubKit 0.18.0 · SubKit Pro 0.14.0 · development release**

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

1. Install and activate **SubKit – Subscriptions for WooCommerce**.
2. For Pro, install **SubKit Subscriptions Pro** and activate it **after** the free plugin.

Pro does nothing on its own and says so if you activate it alone. It needs the free plugin
version 0.9.0 or newer.

> **The folder name does not matter.** Downloading from GitHub gives you a folder called
> `subkit-subscriptions-main`. That is fine — Pro finds the free plugin either way.

Then go to **SubKit** in the admin menu, just below WooCommerce.

---

## Your first subscription in five minutes

On **SubKit → Home** there is a checklist. It walks you through four things, and
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
**Subscription** and **Variable subscription**. Pick one and the subscription settings appear
under General, in sections — see [The subscription panel](#the-subscription-panel).

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
product. **Shipping required** in the panel is the same setting as the Virtual box, so you can
set it from either place. Delivery schedules (Pro) apply only to products that are actually
shipped.

### The subscription panel

The General tab is laid out in sections. The free plugin fills **Pricing**, **Billing
settings** and **Shipping settings**; Pro adds rows to those, plus **Custom renewal pricing**
and **More settings**. A section with nothing in it is not shown, and every field has a **?**
beside it that explains it in one line.

With Pro, the first row of Billing settings is **Payment type**: **Recurring** or
**Installment**. The rows below change to match — a field that does not apply is hidden, and a
hidden field is never saved, so what you see is exactly what the product does.

**Pricing**

| Field | What it means |
|---|---|
| **Regular price** / **Sale price** | WooCommerce's own. The regular price is what recurs. |
| **Sign-up fee** | A one-off amount charged *today*, on top of the first payment. Leave empty for none. |

**Billing settings**

| Field | Shown for | What it means |
|---|---|---|
| **Payment type** *(Pro)* | Both | **Recurring** bills until the customer cancels. **Installment** charges the price a set number of times, then stops. |
| **Bill every** | Both | A number and a unit on one row. `1` `Month(s)` is monthly; `2` `Week(s)` is fortnightly. |
| **Number of payments** *(Pro)* | Installment | How many times the price is charged, counting checkout. |
| **Fixed expiry date** *(Pro)* | Recurring | No payment is taken on or after this date; the period already paid for runs to its end. The product cannot be bought once the date has passed. A season pass that ends on 31 May. |
| **Maximum payments** *(Pro)* | Recurring | Ends the subscription after this many charges, counting checkout. Empty bills until cancelled. |
| **Minimum billing period** *(Pro)* | Both | How many payments, counting checkout, before the customer can cancel from **My Account**. Until then the Cancel button is replaced by a note saying when they can. You can still cancel for them. |
| **Free trial** | Both | A number and a unit — `14` `Day(s)`, `2` `Week(s)`, `1` `Month(s)`. Nothing is charged until it ends, apart from any sign-up fee. Empty means no trial. |
| **Subscription limit** *(Pro)* | Both | No limit, one live subscription per customer, one ever, or a maximum number — which shows a **Maximum per customer** box. |
| **Access limit** *(Pro)* | Installment | What the customer keeps once it is paid off: **Lifetime access after completion**, **Until the last paid period ends**, or **A fixed time from the first payment** — which shows an **Access for** box. |

**Custom renewal pricing** *(Pro, Recurring only)*

| Field | What it means |
|---|---|
| **Enable custom renewal pricing** | Yes to charge a different price from a later payment on — an introductory rate, say. |
| **New renewal price** | The price from then on, entered the same way as the regular price. |
| **Apply after payment number** | Payments counted from checkout. `3` charges the regular price for payments 1–3 and the new price from payment 4. |

The customer sees both prices before buying, and the terms they bought on are kept: changing
the product later does not reprice anybody who already subscribed. It is not offered for
Installment, which is sold as a fixed total.

**Shipping settings**

| Field | Shown for | What it means |
|---|---|---|
| **Shipping required** | Both | The Virtual box, from the other side: **No** makes the product virtual. |
| **Ship every** + **Delivery day** *(Pro)* | Recurring | A shipping cadence of its own — pay monthly, ship weekly — optionally pinned to a weekday. Leave **Ship every** empty to ship once per payment. |
| **Shipping charge** *(Pro)* | Recurring | **Free shipping for renewals** — how SubKit has always billed: shipping is paid at checkout only. **Charge shipping on every renewal** adds the product's shipping to each renewal, by the method the customer chose at checkout. |

**More settings** *(Pro)*

| Field | What it does | Use it for |
|---|---|---|
| **Divide the price** + **Number of parts** | Treats the price as a total and splits it into equal charges, then stops. £300 as 3 × £100. | Paying off a course or a product over time |
| **Payment methods** | Tick the only methods this product accepts. Tick none to offer them all. | A product you only sell by card |
| **Automatic renewals only** | Hides payment methods that cannot renew by themselves | Products you refuse to invoice manually |
| **Grant role while active** | A WordPress role for this product only, overriding the store-wide setting | A "premium member" role for one tier |

Two of these are easy to confuse:

- **Divide the price** divides one total. £300 over 3 parts = £100 each.
- **Installment** repeats a price. £100 × 3 payments = £300 total.

They do the same arithmetic from opposite ends, and a product cannot use both — SubKit
refuses to save that.

> **PayPal and these terms.** PayPal bills from a fixed plan of its own, so it cannot honour a
> custom renewal price, a fixed expiry date or renewal shipping. A product that uses any of
> them does not offer PayPal at checkout; Stripe, Mollie, Razorpay, Xendit and manual payment
> are unaffected. A minimum billing period works with PayPal, except that a PayPal customer
> can always cancel inside PayPal itself.

A refused setting — a price left empty, a date in the past — is not saved, and a red notice at
the top of the screen says why.

### What the customer sees

SubKit writes the terms on the product page automatically — as separate plain facts, not one
long sentence. The first line is the product's own price, which now says how often it
recurs; the facts follow underneath:

```
$29.00 every month
14 days free
$20.00 sign-up fee today
First payment $29.00 on 1 September 2026
Then $29.00 every month
Cancel anytime
```

Pro terms add their own lines, in plain words — for example:

```
From payment 4: $19.00 every month
No payments on or after 31 May 2027
Shipping is charged with every renewal
Cancel anytime after 3 payments
```

The same terms follow the product into the cart and the checkout, and the sentence beside
**Place order** states them too.

The price says how often it recurs everywhere WooCommerce prints one — the shop, category
pages, related products — not only on the product page. A product on sale keeps its
struck-through old price, with the interval after it. An instalment or split plan shows the
plan instead (for example *3 payments of £100*), because the price plus "every month" would
state a total the customer is not going to pay.

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

All of these live under **SubKit → Settings**, a screen of sections: pick one on the left,
change what you need, then **Save changes**. Each section saves on its own.

### System status

Not settings — a read-out, in the panel on the right of every section. Three lines telling
you whether billing is actually working:

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

**If a Xendit renewal's answer never arrives** — a timeout, a dropped connection — SubKit asks
Xendit again for the same charge, which cannot bill the customer twice. It can only do that for
24 hours. After that it stops and marks the charge as unresolved, so you can check your Xendit
dashboard and settle it by hand, rather than risk charging again.

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

SubKit's gateways work on both checkouts — the classic one and the newer block checkout —
and on either they are offered only when the cart actually contains a subscription.

A gateway with no credentials is never offered at checkout, even if enabled — so a
half-configured gateway cannot be chosen by a customer and then fail. **SubKit tells you
when this is happening**, and names the field that is empty: seeing *Active* on the
WooCommerce payments screen and *There are no payment methods available* at the checkout is
almost always a gateway switched on with an empty key, or keys typed into Test while the
environment is set to Live.

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

### SubKit → Home

Where SubKit opens. Until setup is finished, the checklist comes first. After that:

- **The numbers** — monthly recurring revenue, active subscriptions, how many are on a free
  trial, and how many are on hold after a failed payment.
- **Needs your attention** — subscriptions on hold, and ones about to end. Each links
  straight to that filtered list. With nothing to act on, it says so rather than showing an
  empty box.
- **Recent subscriptions** — the latest six, each opening its own screen.
- **Shortcuts** to Integrations, Settings and Help.

### SubKit → All subscriptions

Everyone who pays you on a schedule. You can **search** by name, email or id, **sort** by id,
next payment or total, and **filter** by status.

Tick rows to **cancel**, **put on hold** or **reactivate** several at once. Each row also
offers **View**, **Renew now** and **Parent order**.

Bulk changes follow the same rules as everything else: a subscription that cannot legally
make that change is skipped, not forced, and you are told how many were left alone and why.

Searching, sorting, filtering and paging all happen without reloading the page. Sorting asks
the server for the order, so it sorts every subscription you have, not just the page you can
see.

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
| `/dashboard` | GET | Everything the SubKit Home screen shows: setup steps, the numbers, what needs attention, recent subscriptions. |
| `/overview` | GET | Recurring revenue, live count and the status breakdown, on their own. |
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

Once the scheduler is running again you do not need to do anything about the renewals it
missed. An hourly check picks up every subscription whose renewal is overdue, the most overdue
first, however many subscriptions the store has.

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
