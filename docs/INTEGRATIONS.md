# Integrations

An integration hands a subscription to the plugin that grants what the customer is paying
for — a course, a mailing list, a licence key — and takes it back when they stop paying.

All of them are **SubKit Pro**. Find them under **SubKit → Integrations**: a card for each,
grouped by what it connects to, saying what it does, which plugin it needs, whether that
plugin is active right now, and — for plugins on WordPress.org — an Install button.

For *which* integration a given business needs — and the two builds that need four at once —
see [BUSINESS-EXAMPLES.md](BUSINESS-EXAMPLES.md).

---

## How they all behave

Three rules apply to every integration, and they are worth knowing before the list.

**Nothing happens until you configure the product.** Each integration adds its own fields to
the subscription product — which courses, which lists, which tags. A product with none
configured does nothing, silently and on purpose. It is not a bug that installing LearnDash
did not suddenly enrol anybody.

**Access follows the subscription's status, not the order.** It is granted when the
subscription becomes active, and taken back when it is cancelled, expires, or otherwise
stops being live. A renewal that succeeds changes nothing, because nothing was lost.

**A customer's other subscriptions protect what they still pay for.** If two subscriptions
both grant the same course and one is cancelled, the course stays. Access is only released
when no live subscription of theirs still covers it. This is the rule that most integrations
elsewhere get wrong, and it is the one that generates angry support tickets when it is
missing.

**An integration whose plugin is not active does nothing at all.** It registers no hooks and
takes no action. If an integration seems not to fire, the Integrations screen is the first
place to look — it will say *Plugin not active*.

---

## Courses

### LearnDash

**Configure:** the courses on the subscription product.

**Active subscription** → the customer is given access to those courses.
**Subscription ends** → access is removed, unless another live subscription of theirs
includes the same course.

Not on WordPress.org, so the Integrations screen links out to buy it rather than offering an
install button.

### Tutor LMS

**Configure:** the courses on the subscription product.

**Active subscription** → the customer is enrolled.
**Subscription ends** → they are un-enrolled, subject to the same protection above.

### LearnPress

**Configure:** the courses on the subscription product.

**Active subscription** → enrolment is set.
**Subscription ends** → enrolment is removed.

> The LearnPress and Tutor LMS enrolment calls are written against those plugins' documented
> APIs but have never been run against a live install. Check the first enrolment by hand.

---

## Email and CRM

### MailPoet

**Configure:** the MailPoet lists on the subscription product.

**Active subscription** → the customer's email is subscribed to those lists.
**Subscription ends** → they are removed from them.

> MailPoet may send its own confirmation email when somebody is added to a list, depending on
> your MailPoet settings. Check that before a launch, so customers are not double-mailed.

### FluentCRM

**Configure:** lists and tags on the subscription product. Either, or both.

**Active subscription** → the contact gains those lists and tags.
**Subscription ends** → they are removed, unless another live subscription still grants them.

### WP Fusion

**Configure:** a tag bucket per subscription status — one for Active, one for On hold, one
for Cancelled, and so on for all eight. Plus a *Remove stale tags* switch.

**Any status change** → the tags for the new status are applied. With *Remove stale tags* on,
the tags belonging to statuses the customer no longer holds are removed — but a tag stays if
another of their subscriptions is in that status.

This is the most flexible of the three, because your CRM automations key off the tags rather
than off SubKit. A "win back" sequence triggers on the Cancelled tag; a dunning nudge on the
On hold one.

Sold commercially, so the screen links out rather than installing.

---

## Automation

### AutomatorWP

**Configure:** nothing on the product. Triggers are available as soon as AutomatorWP is
active.

Thirteen triggers are registered:

| Trigger | Fires when |
|---|---|
| Subscription activated | a subscription becomes active |
| Status changed to … | one trigger per status, all eight |
| Trial ended | a trial finishes |
| Renewal paid | a renewal charge succeeds |
| Renewal failed | a renewal charge is declined |
| Recovered | a failed subscription is brought back |

Each fires **once per product** on the subscription, so an automation can act on the
specific thing that was bought rather than on the subscription as a whole. Triggers can be
filtered by product and by status inside AutomatorWP.

---

## Licence keys

### License Manager for WooCommerce

**Configure:** nothing on the product. The licence keys the original order generated are
found automatically.

**Active subscription** → the keys are activated, and their expiry is set to the
subscription's next payment date.
**Renewal succeeds** → the expiry is pushed forward to the new next payment date.
**Cancelled, expired, or a failed payment** → the keys are deactivated.
**A renewal order** → licence generation is suppressed, so a renewal extends the existing key
rather than minting a new one. That last point is what stops a customer accumulating a fresh
licence key every month.

### WP Software License

**Configure:** nothing. Keys are found from the related orders the same way.

**Active subscription** → licences are set active.
**Subscription ends or payment fails** → they are set inactive.

> The call that changes licence state on this plugin is an **assumption**. The reference
> implementation we compared against never changes state, so there was nothing to copy, and
> the plugin is not installed here to check against. It is guarded: if the method is not
> what we expect, the integration does nothing rather than failing loudly, and fires
> `subkit_integration_unsupported`. **Verify this one against a real install before relying
> on it.**

---

## Installing them

Where the plugin is on WordPress.org, **SubKit → Integrations** installs and activates it in
place — no round trip through the Plugins screen. Seven of the nine work this way.

LearnDash and WP Software License are sold commercially, so they show a link to buy instead
of a button that could only fail.

---

## What has not been proven

Be clear about this before you rely on any of it.

| | |
|---|---|
| **None of the nine host plugins is installed on the development machine** | Every active path was proved against a stand-in written to the signature our code calls. If a real signature differs, our code calls the stand-in correctly and the real plugin incorrectly. |
| **What *is* proven, thoroughly** | That an integration with its plugin absent registers no hooks, writes no data and takes no action — verified by diffing every hook in WordPress across a registration, then firing every lifecycle event and all 64 status-change pairs. This is the case that happens on most stores. |
| `WOO_SL_functions::update_licence_status` | A guess. See the warning above. |
| LearnPress and Tutor LMS enrolment | Written to the documented API, never run live. |
| MailPoet confirmation emails | May fire on subscribe; unverified. |
