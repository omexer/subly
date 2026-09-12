# Testing SubKit

Two kinds of check. **Static analysis** runs anywhere and catches type and standards problems before the code runs. **Sandbox tests** talk to a real payment gateway and are the only way to prove the parts that matter most.

---

## Static analysis

Both plugins carry PHPStan and PHP_CodeSniffer. Run them from either plugin directory.

```bash
composer install
```

```bash
composer check
```

That runs both. Individually:

| Command | What it does |
|---|---|
| `composer lint` | PHP_CodeSniffer against the WordPress standard |
| `composer lint:fix` | Fixes what it can automatically |
| `composer analyse` | PHPStan at level 5 |

Both are expected to pass with **zero errors**. The only thing you should see is a handful of `slow_db_query` warnings on deliberate meta lookups.

`.github/workflows/static-analysis.yml` runs both on every push and pull request, so a regression fails the build rather than reaching a release.

### What is deliberately switched off, and why

Every exclusion is written in `phpcs.xml.dist` / `phpstan.neon.dist` with its reason. The short version:

- **Documentation sniffs.** This codebase's standard is that a comment earns its place by explaining a non-obvious *why*. A rule demanding a docblock on every file, class, property and parameter produces exactly the restatement it is meant to prevent.
- **File naming.** Class files are named for their class so the autoloader maps namespace to path. WordPress's `class-foo-bar.php` convention assumes a manual require list.
- **A sniff that predates enums.** `PHPCompatibility` reads an enum body as a plain function and reports every `$this` inside it.
- **Stub gaps.** `WC_Data::update_meta_data()` declares no type for its value at all, and `get_items()` is stubbed as returning the base item class. Both stubs are narrower than WooCommerce itself.

If you add an exclusion, write the reason next to it.

---

## The concurrency test

The renewal engine promises a billing period can be charged **at most once**, even when
several workers reach it together. One process cannot race itself, so this starts eight
real ones, holds them on a wall-clock barrier until they are all spinning, and releases
them against the same subscription.

```bash
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/subkit-subscriptions/tools/concurrency-test.php
```

Two rounds. The first asks the narrow question - eight workers call `claim_next()` on the
same period, and exactly one should get through. The second asks the one that matters -
eight workers run the whole renewal pipeline, and exactly one charge should reach the
gateway.

It asserts thirteen things, and the load-bearing one is **exactly one charge attempt
reached the gateway**. More than one means a customer was charged twice.

Run it more than once. A race that passes a single time has told you very little: one run
in the first twenty-one failed here, which is why the harness now prints every worker's
outcome and the ledger rows whenever an assertion fails. Eighteen consecutive runs passed
after that, and the cause of the single failure was never captured - treat an occasional
failure as something to read, not to dismiss.

It cleans up after itself: the subscription, its renewal orders and its ledger rows.

---

## Sandbox testing

Everything above proves the code is *well formed*. It cannot prove a renewal charges the right amount once, and only once. That takes a real gateway.

### Why this matters more than it sounds

The renewal engine's central promise is that a charge can happen **at most once per billing period**, even when the answer never arrives. Simulated HTTP cannot test that honestly, because the assertion that matters is not what our code believes — it is how many charges actually exist at the gateway.

### Stripe

You need a Stripe **test** key. Get it from **Stripe Dashboard → Developers → API keys** with the **Test mode** toggle on. It starts `sk_test_`.

Never use a live key. The harness refuses anything that is not `sk_test_`, because a live key would charge real cards.

From the `wp-docker` directory:

```bash
SUBKIT_STRIPE_TEST_KEY='sk_test_your_key_here' docker compose exec -T -e SUBKIT_STRIPE_TEST_KEY wordpress php /var/www/html/wp-content/plugins/subkit-subscriptions/tools/sandbox-stripe.php
```

The key is read from the environment. It is never written to the database beyond the run, never printed, and never logged.

### What the harness does

1. Creates a real Stripe customer and attaches a test card
2. Builds a subscription that is due, wired to that customer
3. Charges the renewal — then **aborts the HTTP read after one second**. Stripe has taken the request; we never see the answer. That is a genuine unknown outcome, not a simulated one.
4. Asserts the charge slot is left `charging`, not `failed`
5. Asks Stripe directly whether a charge landed
6. Runs the pipeline again, so reconciliation resolves the slot
7. **Asks Stripe how many payment intents exist for that subscription.** Anything but exactly one is a double charge.
8. Runs a third time and confirms nothing moves

Step 7 is the whole point. It asks the gateway, not our own records.

Afterwards it deletes the subscription, its renewal orders, its ledger rows, the Stripe customer, and the key from the database.

### If it does not time out

On a fast connection the read can finish inside the second, and you will see an ordinary success instead of the timeout path. Re-run it. If it never times out, lower the timeout in the harness.

### The other gateways

Not written yet, and each needs its own sandbox account:

| Gateway | Testable the same way? |
|---|---|
| Stripe | Yes — the harness above |
| Mollie | Yes, with a Mollie test API key |
| Razorpay | Yes, with test key id and secret |
| Xendit | Yes, with a test secret key |
| PayPal | **No.** PayPal owns the billing schedule, so there is no charge of ours to interrupt. It needs a different test: create a sandbox subscription and confirm the webhook mirrors it. |

---

## Exercising the licence screens without a store

Pro has no store behind it yet, so every key comes back *"Licensing is not configured for
this build"* and the licence screens cannot be reached at all. To click through them, add
this to `wp-config.php`:

```php
define( 'SUBKIT_PRO_TEST_LICENCE', true );
```

Then **SubKit → Settings → Licence** gains a **Use a test licence** button. No store is
contacted and any key is accepted.

| Key | What it shows |
|---|---|
| anything, or the button | Active, receiving updates |
| `TEST-EXPIRED` | Expired: billing continues, updates stopped |
| `TEST-REVOKED` | Refused, with the revoked message |

Two things it deliberately will not do. It never offers an update, because there is no
package and pointing WordPress at a fake one would break the site rather than test
anything. And an empty key is still refused, so the validation path stays honest.

**Remove the constant when you are done.** A site left in this state accepts any licence
key, so it warns on the licence screen and on every admin page until you do. The constant
lives in `wp-config.php` and so cannot travel inside the plugin.

---

## What is still not tested

Be clear-eyed about this list. It is short and it is the important part.

| | |
|---|---|
| **Automated test suite** | There is none. No PHPUnit, no integration tests. Everything is verified by hand or by a harness like the one above. |
| ~~Concurrency~~ | **Tested.** See below. |
| **Unattended renewals** | Scheduled renewals fire correctly when run directly. No renewal has been watched happening on its own overnight. |
| **Browser** | Nothing has been driven through a real browser. Screens are verified by their output, not visually. |
| **Real gateway calls** | Until you run the harness above, every payment path in this plugin has only ever met a simulated response. |
