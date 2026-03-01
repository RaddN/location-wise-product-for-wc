# Order Status Change Behavior Audit

Scope checked:
- Plugin: `multi-location-product-and-inventory-management-pro`
- WooCommerce core files used for baseline:
  - `woocommerce/includes/wc-stock-functions.php`
  - `woocommerce/includes/class-wc-order.php`
  - `woocommerce/includes/wc-order-functions.php`

---

## Payment Complete (status decision)

Default WC:
- On payment completion, WooCommerce picks `processing` or `completed` via `woocommerce_payment_complete_order_status` in `class-wc-order.php`.

Our plugin behavior:
- Overrides that filter and returns `on-hold` when assignment method is `manual` and order `_store_location` is empty.
- Also sets order to `on-hold` earlier in manual mode from `save_location_to_order_meta()` if location is still unassigned.

is correct?: **Yes** (intentional business rule)

Why:
- This is a deliberate workflow gate so manual location assignment happens before normal fulfillment flow.

Relevant plugin refs:
- `multi-location-product-and-inventory-management-pro.php:2976`
- `multi-location-product-and-inventory-management-pro.php:8386`
- `multi-location-product-and-inventory-management-pro.php:7201`

---

## Pending

Default WC:
- If stock was previously reduced, moving to `pending` restores stock (`wc_maybe_increase_stock_levels`).

Our plugin behavior:
- Location stock is also restored through `woocommerce_restore_order_stock` hook.
- Split parent -> child status sync does **not** include `pending`.

is correct?: **No** (split-order status gap)

Issue and why it happens:
- Parent split order moved to `pending` will not move child orders to `pending`.
- `sync_child_status_from_parent()` sync list excludes `pending`, so children can remain in stale statuses.

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-stock-functions.php:156`
- Plugin: `includes/stock-price-backorder-manage.php:974`
- Plugin: `includes/order-split-by-location.php:756`

---

## On-hold

Default WC:
- Reduces stock and releases reserved hold stock.

Our plugin behavior:
- Mirrors reduction/restoration lifecycle via `woocommerce_reduce_order_stock` and location stock logic.
- Split orders sync child statuses to `on-hold`.
- Manual-assignment flow can force this status for unassigned location orders.

is correct?: **Yes**

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-stock-functions.php:127`, `:491`
- Plugin: `includes/stock-price-backorder-manage.php:915`
- Plugin: `includes/order-split-by-location.php:758`

---

## Processing

Default WC:
- Reduces stock and releases reserved hold stock.

Our plugin behavior:
- Location stock is reduced on `woocommerce_reduce_order_stock`.
- Split parent status change syncs child orders to `processing`.

is correct?: **Yes**

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-stock-functions.php:126`, `:490`
- Plugin: `includes/stock-price-backorder-manage.php:915`
- Plugin: `includes/order-split-by-location.php:758`

---

## Completed

Default WC:
- Reduces stock (if not yet reduced) and releases reserved hold stock.

Our plugin behavior:
- Location stock reduction is applied.
- Social notification is triggered on completed.
- Split parent status syncs child orders to completed.

is correct?: **Yes**

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-stock-functions.php:125`, `:489`
- Plugin: `includes/stock-price-backorder-manage.php:915`
- Plugin: `multi-location-product-and-inventory-management-pro.php:2984`
- Plugin: `includes/order-split-by-location.php:758`

---

## Cancelled

Default WC:
- Restores stock (only previously reduced amounts) and releases reserved hold stock.

Our plugin behavior:
- Restores location stock on `woocommerce_restore_order_stock`.
- Sends social cancelled alert.
- Split parent status syncs children to cancelled.

is correct?: **No** (refund + cancel over-restock edge case)

Issue and why it happens:
- WooCommerce tracks per-line remaining reduced stock in `_reduced_stock` and decreases it when refunded with restock.
- Plugin restore path adds back full current line quantity on cancel/pending, not the remaining reduced amount.
- After partial/full refund with `restock_items=true`, cancel/pending can add stock again and over-increase location stock.

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-stock-functions.php:155`, `:368`, `:375`
- WooCommerce: `woocommerce/includes/wc-order-functions.php:819`, `:831`, `:834`
- Plugin: `includes/stock-price-backorder-manage.php:988`, `:1003`, `:1072`

---

## Failed

Default WC:
- No default stock increase/reduction hook on entering `failed`.

Our plugin behavior:
- Sends social failed alert.
- Split parent status sync includes failed.

is correct?: **Yes**

Relevant refs:
- Plugin: `multi-location-product-and-inventory-management-pro.php:2981`
- Plugin: `includes/order-split-by-location.php:758`

---

## Refunded

Default WC:
- `refunded` status itself does not directly restock line items.
- Restocking happens when creating refund with `restock_items=true`.

Our plugin behavior:
- Sends social refunded alert on status.
- Restocks location stock during `woocommerce_create_refund` when `restock_items` is true.
- Split parent status sync includes refunded.

is correct?: **Yes** (for normal refund flow)

Note:
- The over-restock bug is not in refund creation itself; it appears later if order is moved to `cancelled`/`pending` after restocked refunds.

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-order-functions.php:679`, `:807`
- Plugin: `includes/stock-price-backorder-manage.php:1010`
- Plugin: `multi-location-product-and-inventory-management-pro.php:2982`
- Plugin: `includes/order-split-by-location.php:758`

---

## Plugin-specific gateway status behavior (Cash on Pickup)

Default WC:
- Not applicable (plugin custom gateway behavior).

Our plugin behavior:
- For total > 0, sets order to `on-hold` with note "Payment to be collected on pickup."
- For zero total, calls `payment_complete()`.

is correct?: **Yes** (intentional)

Relevant ref:
- `includes/cash-on-pickup-payment-gateway.php:399`

---

## Revenue Status Rules (core revenue dependency)

Default WC:
- Paid-order logic is based on `wc_get_is_paid_statuses()` which defaults to `processing` and `completed`.

Our plugin behavior:
- Revenue status baseline is `['completed', 'processing', 'on-hold']` plus `wc_get_is_paid_statuses()`, then used in:
  - Dashboard revenue totals
  - Profitability recent-sales calculations
  - Daily social digest revenue

is correct?: **No** (for paid-only revenue semantics)

Issue and why it happens:
- `on-hold` is included by default, so revenue can include unpaid/awaiting-payment orders.
- This can inflate revenue compared to WooCommerce paid-status semantics.

Relevant refs:
- WooCommerce: `woocommerce/includes/wc-order-functions.php:134`
- Plugin: `multi-location-product-and-inventory-management-pro.php:12260`
- Plugin: `admin/dashboard.php:2233`
- Plugin: `admin/dashboard.php:2880`
- Plugin: `multi-location-product-and-inventory-management-pro.php:12806`

---

## Dashboard Status Filter = `all`

Default WC:
- "All statuses" queries usually include all order statuses unless explicitly constrained.

Our plugin behavior:
- Dashboard `status_filter=all` is hardcoded to: `completed`, `pending`, `processing`, `on-hold`.
- `failed`, `cancelled`, `refunded` are excluded from the "all" filter.

is correct?: **No**

Issue and why it happens:
- Filter label says "All", but query excludes several statuses.
- This causes order/revenue charts to differ from expected "all statuses" totals.

Relevant refs:
- Plugin: `admin/dashboard.php:2265`
- Plugin: `admin/dashboard.php:1279`

---

## Daily Social Digest (status-driven revenue)

Default WC:
- No direct equivalent feature.

Our plugin behavior:
- Uses revenue statuses and computes per-location orders/items/revenue for "today".
- Does not exclude split parent orders (`_mulopimfwc_split_parent = yes`), unlike dashboard revenue queries.

is correct?: **No**

Issue and why it happens:
- Split parent and child orders can both be counted, causing inflated order/revenue digest totals.
- This is inconsistent with dashboard logic where split parents are excluded.

Relevant refs:
- Plugin: `multi-location-product-and-inventory-management-pro.php:12818`
- Plugin: `multi-location-product-and-inventory-management-pro.php:12875`
- Plugin comparison: `admin/dashboard.php:2320`

---

## Customer Insights Fallback Purchases

Default WC:
- No direct equivalent feature.

Our plugin behavior:
- Fallback purchase stats pull **all** WooCommerce statuses via `wc_get_order_statuses()`.
- Every fallback order increments purchase count.
- Fallback result is merged into analytics using `max(tracked, fallback)`.

is correct?: **No**

Issue and why it happens:
- Failed/cancelled/refunded/pending orders are counted as purchases.
- Can overstate purchase/conversion analytics when tracking data is sparse or missing.

Relevant refs:
- Plugin: `includes/customer-location-insights.php:618`
- Plugin: `includes/customer-location-insights.php:627`
- Plugin: `includes/customer-location-insights.php:673`
- Plugin: `includes/customer-location-insights.php:573`

---

## Customer Insights Purchase Event Trigger

Default WC:
- `woocommerce_thankyou` runs after checkout/order placement, not strictly a paid-status event.

Our plugin behavior:
- `track_purchase()` is attached to `woocommerce_thankyou` and increments purchase stats without checking paid status.

is correct?: **No** (for paid-purchase analytics semantics)

Issue and why it happens:
- Pending/on-hold orders are treated as completed purchases in insights.

Relevant refs:
- Plugin: `includes/customer-location-insights.php:93`
- Plugin: `includes/customer-location-insights.php:339`
- Plugin: `includes/customer-location-insights.php:380`

---

## Processing Order Bubble (Analytics Page)

Default WC:
- No direct equivalent feature.

Our plugin behavior:
- Counts all `processing` orders in batches.
- No split-parent exclusion applied.

is correct?: **No** (inconsistent with dashboard totals that exclude split parents)

Issue and why it happens:
- If split orders are enabled, processing bubble can count parent + child order records.

Relevant refs:
- Plugin: `includes/customer-location-insights.php:1319`
- Plugin: `includes/customer-location-insights.php:1331`
- Plugin comparison: `admin/dashboard.php:2320`

---

## Unassigned Order Counter (Manual Mode)

Default WC:
- No direct equivalent feature.

Our plugin behavior:
- Unassigned counter queries do not filter by status.
- Main plugin path does not exclude split parents; admin class path does.

is correct?: **No** (logic inconsistency)

Issue and why it happens:
- Count can include historical closed statuses and split-parent artifacts.
- Different plugin code paths produce different unassigned counts.

Relevant refs:
- Plugin: `multi-location-product-and-inventory-management-pro.php:8443`
- Plugin: `admin/admin.php:3578`

---

## Summary of incorrect items

1. Split-order `pending` sync missing.
2. Location stock can over-increase after refund-restock followed by cancel/pending.
3. Revenue default statuses include `on-hold`, so unpaid orders can be counted as revenue.
4. Dashboard `status=all` excludes `failed/cancelled/refunded` (label/behavior mismatch).
5. Daily social digest can double count split parent + child orders.
6. Customer insights fallback counts all statuses as purchases.
7. Customer insights `track_purchase` runs on `thankyou` without paid-status gating.
8. Processing-order bubble can include split parents (inconsistent with dashboard totals).
9. Unassigned-order count logic is inconsistent across plugin code paths.

## Recommended fixes

1. Add `pending` to split sync statuses in `sync_child_status_from_parent()`.
2. In location restore logic, restore only the remaining reduced quantity (align with WooCommerce `_reduced_stock` semantics), not full current line quantity.
3. Change default revenue statuses to paid statuses only (or make `on-hold` opt-in via setting).
4. Make dashboard `status=all` truly include all statuses (or rename it to "Active statuses").
5. Exclude `_mulopimfwc_split_parent = yes` in daily digest queries.
6. In customer insights fallback, restrict purchase counting to paid statuses (and optionally exclude split parents).
7. In `track_purchase`, require paid-status check (or move to a paid-status hook).
8. Exclude split parents from processing bubble count.
9. Unify unassigned-order counting logic and optionally limit to actionable statuses (e.g., pending/on-hold/processing).
