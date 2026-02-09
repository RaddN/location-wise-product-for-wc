# Split Orders by Location - Requirements and Changes

## Overview
This document lists the features and code changes required to add a new setting that splits orders by location when mixed-location carts are enabled. The goal is to create one order per selected location at checkout while preserving existing stock, shipping, tax, and notification behaviors.

## New Setting (Admin UI)
- Add a new checkbox setting labeled `Split Order by Location` in Checkout Settings.
- Place the new setting immediately before `Auto-Populate Customer Addresses`.
- Only enable the setting when `Allow Mixed-Location Cart` is enabled and premium access is active.
- Disable the setting when Manual/Inventory/Proximity assignment strict mode is active, consistent with other mixed-location settings.
- Persist the setting under `mulopimfwc_display_options[split_order_by_location]` with default `off`.

## Split Behavior (Functional Requirements)
- Condition: `Allow Mixed-Location Cart` is enabled.
- Condition: `Split Order by Location` is enabled.
- Condition: The cart contains items from at least 2 distinct locations.
- Group items by cart item location `mulopimfwc_location` or order item meta `_mulopimfwc_location`.
- Create one child order per location and move the matching line items into that child order.
- The parent order must not retain line items when children exist to avoid double stock reduction.
- Decision required for unknown or invalid locations. add settings after Split Order by Location so that admin can decide Options: block checkout, create an `unassigned` child order, or keep those items in the parent order.

## Order Data and Meta
- Set `_store_location` on each child order to the corresponding location slug.
- Add parent meta `_mulopimfwc_split_parent` = `yes`.
- Add parent meta `_mulopimfwc_split_children` = array of child order IDs.
- Add child meta `_mulopimfwc_split_child` = `yes`.
- Add child meta `_mulopimfwc_split_parent_id` = parent order ID.
- Add a visible order note on the parent and each child linking the related order IDs.
- Ensure `_mulopimfwc_location` stays on each order item in child orders.

## Totals, Fees, Shipping, and Taxes
- Recalculate child order totals based on their line items.
- Allocate shipping per location using existing `split_shipping_packages_by_location` package data when available.
- Recalculate taxes per child order based on its location and current tax settings.
- Define how inter-location transfer fees are handled when orders are split.
- Define how coupon discounts are apportioned across child orders using existing mixed-cart coupon behavior.

## Payment and Status Handling
- Ensure only the parent order receives payment capture from the gateway.
- Child orders should be marked paid or processing without re-charging the customer.
- Decision required for gateways that require per-order capture. Options: create child orders first and charge once, or keep parent as the payment record and treat children as fulfillment-only orders.

## Inventory and Stock Movements
- Stock reductions and restorations should occur only on child orders that contain items.
- Prevent duplicate stock changes if the parent retains items for any reason.
- Ensure existing location-based stock logic continues to use `_mulopimfwc_location` from order items.

## Notifications and Emails
- Location-specific emails should use each child order location meta.
- Avoid duplicate customer emails when parent and children exist. Decide whether the parent, the children, or both should trigger emails.
- Ensure admin or location manager notifications are routed based on child order location.

## Admin UI and Reporting
- Display parent-child relationships in the order admin screen with links between orders.
- Ensure existing location filters and reports work with child orders by using `_store_location` meta.
- Decide how parent orders appear in location reports.

## Compatibility and Edge Cases
- Must work for both Classic Checkout and WooCommerce Blocks.
- Must work with HPOS order tables.
- Support refunds and cancellations by applying them to the correct child order and updating stock accordingly.
- Ensure splitting is idempotent and does not run twice for the same checkout.

## Settings and Sanitization
- Update the settings sanitizer to include `split_order_by_location` as a checkbox field.
- Add a helper like `mulopimfwc_is_split_order_enabled($options)` to centralize the check and respect manual assignment strict mode.

## Tests and Validation
- Mixed cart with two locations creates two child orders and correct totals.
- Single-location cart does not split.
- Unknown location items follow the chosen rule.
- Shipping rates per location are assigned correctly.
- Taxes and coupons are correctly distributed.
- Stock reduces and restores correctly across child orders.
- Emails and notifications do not duplicate unexpectedly.
- Refunds and cancellations affect the correct child order.
