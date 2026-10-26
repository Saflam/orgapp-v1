# Member Module

The Member module owns organization membership, member profiles, membership types, relationships, membership applications, member payment items, payment obligations, and member payments.

## Membership application lifecycle

`Draft → Submitted → Verified → Reviewed → Approved → Pending Payment → Pending Confirmation → Confirmed`

Applications do not activate a member until final confirmation.

## Member payment architecture

Member payment configuration is separated from actual money received:

1. `member_payment_items` defines what members may or must pay.
2. `member_payment_item_rates` stores versioned rates so historical amounts never change.
3. Each rate has allocation percentages such as Central/Organization 20% and Member Unit 80%.
4. `member_payment_obligations` is the member-specific occurrence of a payment item.
5. `member_payments` is the actual receipt and may settle multiple obligations in one payment.
6. `member_payment_lines` connects one payment to each settled obligation.
7. Allocation snapshots are stored on obligations and payment lines so Finance can later create central/unit payable and receivable entries without recalculating historical rules.

### Payment item example

A Super Admin can configure:

- Title: Service Fee
- Mandatory: Yes
- Recurring: Yes
- Period: Yearly
- Recurring by: Specific date
- Recurring date: `12-31`
- Rate: ₹50
- Organization/Central allocation: 20% (₹10)
- Unit allocation: 80% (₹40)

The rate and allocation are versioned. A later change affects future occurrences only.

### Occurrence generation

For an active member, the Member module evaluates all applicable mandatory payment items and generates missing occurrences up to the requested date. An occurrence is identified by the payment item and occurrence key, so the same yearly/monthly obligation cannot be generated twice.

The member payment screen therefore shows all outstanding mandatory items. Already settled or waived occurrences are excluded.

### Collector scope

A payment also records the scope of the person who collected it:

- `organization` — Central Committee / organization-level collector
- `unit` — Unit Committee collector

The collector scope is distinct from the payment item's allocation. For example, if a Unit A Treasurer collects ₹150 and one item contains a ₹10 Central share, Finance can later record:

- Unit A cash received: ₹150
- Unit A entitlement: ₹140
- Unit A payable to Central: ₹10
- Central receivable from Unit A: ₹10

If Central collects the same payment, the direction of the inter-scope balance can be reversed.

### Discounts and waivers

A discount preserves the original obligation amount. Allocation amounts are reduced pro-rata for the payable amount. A full waiver creates no payment. Discount history records the actor, reason, timestamp, original amount, discount, and payable amount.

## Finance integration

The Member module remains independent of Finance. When an actual member payment is recorded, it dispatches `MembershipPaymentReceived` after commit. The event carries the aggregate member payment and its payment lines/allocations.

If Finance is enabled, Finance consumes the event and owns the canonical financial receipt. If Finance is not enabled, Member issues a fallback receipt through Core notification infrastructure.

## Permissions

- `membership.payment-item.view`
- `membership.payment-item.manage`
- `membership.payment.view`
- `membership.payment.receive`
- `membership.payment.discount`
