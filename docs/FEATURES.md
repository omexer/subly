# SubKit — Feature Inventory

Everything either plugin does, what tier it is in, and **how well it is actually proven**. Measured against the committed code at 0.6.0, not from memory.

Verification key:

| | |
|---|---|
| **Run** | Exercised against a live WordPress with real data, including the failure cases |
| **Mocked** | Exercised, but with the outside world simulated — no real gateway or third-party plugin was contacted |
| **Wired** | Code exists and loads; behaviour has not been driven end to end |
| **In flight** | Being written now; not committed, not reviewed |

---

## Core engine — Free

| Feature | Verification | Notes |
|---|---|---|
| Subscription as a native HPOS order type | Run | Not a custom post type; inherits line items, tax, refunds, notes |
| Status machine (8 states, illegal transitions refused) | Run | Terminal states cannot be reactivated |
| Billing schedule, UTC, anchored months | Run | Jan 31 → Feb 28 → Mar 31 → Apr 30 |
| Integer-minor-unit money with deterministic remainder split | Run | `300.01 / 3 = 100.01 + 100.00 + 100.00` |
| Charge-slot ledger, one charge per period | Run | `UNIQUE(subscription_id, period_index)` is the guard, not the lock |
| Idempotency key per attempt, rotating only on a definitive decline | Run | A timeout keeps its key |
| Reconciliation of unknown outcomes | Mocked | Every gateway implements it; no real timeout has ever occurred |
| Renewal pipeline | Run | Nothing contacts a gateway before the slot is claimed |
| Retry and scheduling via Action Scheduler | Wired | Never observed firing unattended |
| Hourly sweeper for missed renewals | Wired | |
| Staging-clone protection | Run | A copied site refuses to bill |
| Activity log per subscription | Run | |

## Products and checkout — Free

| Feature | Verification | Notes |
|---|---|---|
| Simple subscription products | Run | |
| Interval, period, free trial, sign-up fee | Run | |
| Create the first product from the setup guide | Run | Invalid input refused, not coerced |
| Disclosure as independent facts, not prose | Run | Survives trial + fee + interval combined |
| Classic checkout | Run | |
| Block checkout via the Store API | Run | |
| One subscription per cart | Run | Enforced deliberately |
| Guest checkout with account creation | Run | A taken email is refused, never silently claimed |
| Require login instead | Run | Warns the merchant if Woo offers no login at checkout |

## Payments — Free

| Feature | Verification | Notes |
|---|---|---|
| Stripe checkout + off-session renewals | **Mocked** | Never called a real Stripe sandbox |
| PayPal Subscriptions + webhook mirroring | **Mocked** | Never called a real PayPal sandbox |
| Manual / invoice renewals | Run | |
| Test gateway (scripted outcomes) | Run | Refuses to run unless `WP_DEBUG` |
| Decline classification: soft / hard / transient / needs-action | Run | Only soft declines enter dunning |

## Customer self-service — Free

| Feature | Verification | Notes |
|---|---|---|
| My Account subscription list and detail | Run | |
| Cancel at period end or immediately | Run | Cannot be disabled by the merchant |
| Turn off automatic renewal | Run | Keeps access to the end of the paid period |
| Pay a failed renewal | Wired | |
| Extension point for Pro actions | Run | Pause / Resume / Renew now render here |
| Cancellation survey | Wired | |

## Admin — Free

| Feature | Verification | Notes |
|---|---|---|
| Subscriptions list and detail screen | Run | |
| MRR and live count above the list | Run | Yearly, weekly, every-2-months all normalise correctly |
| Daily MRR snapshot, capped at 400 days | Run | History cannot be recomputed, so it is recorded |
| Setup checklist | Run | |
| One top-level SubKit menu | Run | Seven screens, verified through WordPress's own menu globals |
| Integrations screen | Run | Lists all nine and whether each host plugin is active |
| Help screen with a system report | Run | Proven to exclude Stripe, PayPal and licence credentials |
| Self-test renewal (creates, renews, deletes) | Run | |
| Health panel: queue, unresolved charges, ledger index | Run | Flips on at 3h, silent for an in-flight charge |
| Six transactional emails | Run | Merchant mail goes to the merchant, not the customer |
| Privacy exporter and eraser | Wired | Refuses to erase an active subscription |

## Access — Free

| Feature | Verification | Notes |
|---|---|---|
| Role while subscribed / once ended | Run | Administrators never demoted; kept while another subscription is live |
| Downloadable file gating | Run | Active + cancelled pair keeps the files |

---

## Pro

| Feature | Verification | Notes |
|---|---|---|
| **Licensing** (activate, validate, updates, seat release) | Mocked | Gates updates and support only — **never billing** |
| **Variable subscriptions** | Run | Per-variation schedule, inheritance, "From" price |
| **Instalment plans** | Run | Fixed total over N charges, then stops |
| **Pause and resume** | Run | Remaining time preserved |
| **Plan switching** | Run | Credits unused time; configures the new plan |
| **Recurring coupons** | Wired | Discounts that survive into renewals |
| **Failed payment recovery** (dunning, grace period) | Wired | |
| **Subscription health** (6 signals, digest email) | Run | Every signal has a positive *and* a negative case |
| **Delivery schedules** (cadence, manifest, print) | Run | Guards refuse rather than coerce |
| **Reports** (MRR, ARR, churn, LTV) | Run | |
| **Content access** (per-product roles, download capability) | Wired | |
| **REST API** | Run | 401 / 404 / 409 / 400 paths all proven |
| **Mollie renewals** | Mocked | Idempotency-Key + payment metadata |
| **Razorpay renewals** | Mocked | Order receipt as the idempotency handle |
| **Xendit renewals** | Mocked | Refuses to replay past the 24h key window |
| **Subscription limits** (one active / one ever / N per customer) | Run | Guests refused, not waved through |
| **Payment cap** (end after N charges) | Run | |
| **Gateway restriction** for subscription purchases | Run | Through Woo's own filter, never the renewal registry |
| **Split payments** (per-payment price x N) | Run | Totals reconcile exactly; distinct from instalments |
| **Retained access** after a completed split plan | Run | Lifetime or a dated window, honoured after expiry |
| **Live QR status page** | Run | Unguessable token; enumeration proven to fail |
| **QR encoder** (ours, versions 1-10) | Run | Proven by decoding what it draws |
| LearnDash, TutorLMS, LearnPress | Wired | Host plugins not installed |
| MailPoet, FluentCRM | Wired | Host plugins not installed |
| WP Fusion, AutomatorWP | Mocked | Proven inert without the host; active path against fakes |
| License Manager for WooCommerce | Mocked | Same |
| WP Software License | Mocked | `update_licence_status` is a **guess**; guarded, needs a real install |

---

## Deliberately not built

| | Why |
|---|---|
| Migration importer from another subscriptions plugin | Neither plugin we benchmarked has one either |
| Multiple subscriptions per cart | Blocked on purpose |
| Removing WordPress comment metaboxes | A custom-post-type problem we do not have |
| Vendored 11,000-line QR library | Wrote a focused encoder instead, verified by decoding its own output |
| A status page keyed on the subscription id | The reference does this with no ownership check; it leaks every customer's subscription |

---

## The gap that is not a feature

None of the below has moved. It is the reason nothing above should go near a live store yet.

| | |
|---|---|
| **Automated tests** | None. No unit tests, no integration tests. Every "Run" above was done by hand. |
| ~~CI~~ | **Done.** Both plugins run PHPCS and PHPStan on every push and pull request. |
| ~~Static analysis~~ | **Done.** PHPStan level 5 and PHPCS clean on both plugins. See `docs/TESTING.md`. |
| **Real gateway calls** | Still zero, but unblocked: `tools/sandbox-stripe.php` drives the whole reconciliation path against a real Stripe test account. Needs a test key. |
| **Unattended renewals** | Never observed firing on their own. |
| **Concurrency** | Two workers racing on `claim_next()` is the guarantee the architecture rests on. The index is verified; two real processes colliding has never been staged. |
| **Browser testing** | Nothing driven through a real browser. Screens verified by output, not visually. |

Fix the concurrency test first. It is the one that invalidates the rest if it fails.
