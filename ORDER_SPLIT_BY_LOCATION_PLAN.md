# Split Orders by Location - Updated Plan

## Goal
Create one child order per location for mixed-location carts while keeping payment captured once, preserving location-specific stock/shipping/tax logic, and avoiding duplicate emails/stock updates. This plan aligns with `ORDER_SPLIT_BY_LOCATION.md` and adds a production-safe implementation strategy.

## Recommended Decisions (Defaults)
1. **Unknown/invalid location items**: `block_checkout` (recommended). Alternative modes: `unassigned_child`, `keep_in_parent`.
2. **Payment handling**: parent order is the **only payment record**; children are fulfillment-only.
3. **Customer emails**: send customer-facing emails only for the parent; send admin/location notifications for children.
4. **Inter-location transfer fees**: **disable when split orders are enabled** (since each location fulfills its own items). Keep a filter to override if needed.
5. **Parent order contents**: remove product line items after creating children; keep the original parent total for payment. (If a gateway requires line items, use a filter to keep parent items but skip stock reduction.)

## New Settings (Admin UI)
- **Checkout Settings**
  - `Split Order by Location` (checkbox)
    - Location: immediately before `Auto-Populate Customer Addresses`.
    - Only enabled when `Allow Mixed-Location Cart` is enabled and premium is active.
    - Disabled in manual/inventory/proximity strict modes.
  - `Unknown Location Items` (select)
    - Options: `block_checkout`, `unassigned_child`, `keep_in_parent`.
    - Shown only when split is enabled.
- Persist under:
  - `mulopimfwc_display_options[split_order_by_location]` (default `off`)
  - `mulopimfwc_display_options[split_order_unknown_items]` (default `block_checkout`)

## Helper API
Add a helper in `multi-location-product-and-inventory-management-pro.php`:
```
mulopimfwc_is_split_order_enabled($options = null): bool
```
Behavior:
- returns false if manual assignment strict mode is on.
- returns false if premium is off.
- returns true only if `allow_mixed_location_cart` and `split_order_by_location` are both `on`.

## Order Data & Meta
Parent order:
- `_mulopimfwc_split_parent` = `yes`
- `_mulopimfwc_split_children` = array of child order IDs
- `_mulopimfwc_split_original_total` = original total before line-item removal

Child orders:
- `_mulopimfwc_split_child` = `yes`
- `_mulopimfwc_split_parent_id` = parent order ID
- `_store_location` = child location slug (or empty for unassigned)

Order notes:
- Parent: “Split into child orders: #123, #124”
- Child: “Split from parent order: #100”

## Core Flow (Professional & Safe)

### 1) Capture Shipping Package Location
Add `includes/order-split-by-location.php` (new).
Hook into `woocommerce_checkout_create_order_shipping_item` to store:
```
_mulopimfwc_package_location = $package['location_slug'] ?? ''
```
This enables precise shipping allocation later.

### 2) Split Orders After Checkout Order Creation
Add `includes/order-split-by-location.php` (new).
Hook into checkout order processed (classic + blocks equivalent).
Split only if:
- Mixed cart enabled
- Split enabled
- 2 or more distinct valid locations
- Order not already split

#### Grouping Logic
Group by `_mulopimfwc_location` on order items (fallback to cart item meta during checkout if needed).
Unknown/invalid locations handled by `split_order_unknown_items`:
- `block_checkout`: throw checkout error and abort.
- `unassigned_child`: group under `unassigned` (empty `_store_location`).
- `keep_in_parent`: leave items on parent only.

### 3) Create Child Orders
For each location group:
- `wc_create_order()` with:
  - customer ID
  - billing/shipping addresses
  - currency
  - payment method/title
  - customer note
- Copy line items (clone `WC_Order_Item_Product`) and preserve:
  - `_mulopimfwc_location`
  - all line item meta
- Apply shipping items only if `_mulopimfwc_package_location` matches location slug.
- Copy fees if they are location-specific. Otherwise keep fees on parent.
- Set `_store_location` on child.

### 4) Totals, Taxes, Coupons
Totals should be recalculated per child:
- Temporarily set location context (cookie or filter) to child location.
- Call `calculate_totals()` for each child order.
- Restore original location context after each child.

Coupons:
- Use existing line-item totals for discount allocations.
- Optional: create coupon order items per child with discount totals (sum of line item discounts per location).

### 5) Parent Order Finalization
- Remove product line items (except unknown items if `keep_in_parent`).
- Remove shipping items (if allocated to children).
- Keep `set_total()` as `_mulopimfwc_split_original_total`.
- Add parent meta + notes.

### 6) Payment & Status Handling
- Parent is charged once.
- On parent payment complete:
  - Set child orders to same status (`processing` or `completed`).
  - Set `date_paid` to parent’s paid date.
- Ensure gateways do not re-charge children:
  - Do not call `payment_complete()` on children.
  - Use `set_status()` with manual flag.

### 7) Emails & Notifications
Use email filters to prevent duplicate customer emails:
- For child orders: disable `customer_processing_order`, `customer_completed_order`, `customer_on_hold_order`, etc.
- Allow admin/location manager notifications to fire for children.
If desired, suppress parent admin emails when children exist.

### 8) Reporting & Admin UI
- Add a small panel/metabox on order edit screen:
  - Show “Parent” or “Children” with links.
- Update order list filters and dashboard queries to exclude parent orders by default in location-based reports:
  - Add meta query excluding `_mulopimfwc_split_parent` = `yes`
  - Keep children included with `_store_location`.
- For manual assignment dashboards, avoid counting split parents as “unassigned”.

### 9) Refunds, Cancellations, Stock
- Refunds: if parent refunded, mirror status to children and add notes.
- Cancellations: parent cancel → cancel children.
- Stock: ensure stock is reduced/restored only by child orders.
  - If parent keeps line items for gateway compatibility, add a filter to bypass stock reduction for `_mulopimfwc_split_parent` orders.

## Files to Update
- `admin/settings.php`
  - Add new settings fields.
  - Add sanitization for `split_order_by_location` and `split_order_unknown_items`.
- `multi-location-product-and-inventory-management-pro.php`
  - Add `mulopimfwc_is_split_order_enabled()` helper.
  - Load `includes/order-split-by-location.php`.
- `includes/order-split-by-location.php` (new)
  - Split logic, shipping package meta, payment sync, email filters.
- `admin/admin.php`, `admin/dashboard.php`
  - Exclude parent orders from location reports.
  - Add parent/child links on order screen.

## Testing Matrix
1. Mixed cart with 2 locations → 2 child orders.
2. Single-location cart → no split.
3. Unknown location items:
   - block checkout
   - unassigned child
   - keep in parent
4. Shipping per location:
   - packages with location meta → correct shipping split.
5. Taxes & coupons:
   - verify totals by location.
6. Stock:
   - reduced/restored only on children.
7. Emails:
   - no duplicate customer emails.
8. Refunds:
   - parent refund updates children correctly.

## Open Questions (Confirm)
1. Should parent appear in customer “My Orders”? (Recommended: yes, as payment record.)
2. Do you want parent admin emails suppressed when children exist? (Recommended: yes.)
3. For gateways that require line items, should we keep parent line items but suppress stock reduction? (Recommended: yes, behind a filter.)

