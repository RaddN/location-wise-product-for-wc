# Classic Stock Central Audit

Date: 2026-02-19  
Scope: `admin/stock-central.php`, `includes/class-product-location-table.php`, `multi-location-product-and-inventory-management-pro.php`

## Executive Summary

- Total findings: 17
- Critical: 3
- High: 3
- Medium: 9
- Low: 2
- Top priorities: authorization hardening, server-side validation, and classic-mode UI consistency.

## Logical Errors And Risky Behaviors

### 1) [Critical] AJAX save allows out-of-scope product updates
- Where: `multi-location-product-and-inventory-management-pro.php:5490`, `multi-location-product-and-inventory-management-pro.php:5495`
- Issue: Endpoint checks only global capability (`manage_products`) and does not verify product-level permission (`edit_post`) or product scope for restricted managers.
- Impact: A restricted manager can submit another product ID and change default stock/price/purchase fields.
- Fix: Enforce per-product authorization and allowed-scope checks before any write.

### 2) [Critical][Fixed 2026-02-19] Variation ownership is not verified before save
- Where: `multi-location-product-and-inventory-management-pro.php:5511`, `multi-location-product-and-inventory-management-pro.php:5707`
- Issue: Posted variation IDs were saved without confirming they belong to the posted `product_id`.
- Impact: Cross-product variation tampering was possible via crafted requests.
- Fix implemented: Added pre-validation of the full `variations` payload before any writes; each variation must be a real child of the posted product, and mismatches now return an error. Added a defensive ownership re-check immediately before variation save.

### 3) [Critical] Bulk assign/remove can be request-tampered to affect unauthorized products/locations
- Where: `includes/class-product-location-table.php:1543`, `includes/class-product-location-table.php:1547`, `includes/class-product-location-table.php:1571`
- Issue: Bulk handlers trust posted product IDs and `bulk_location_id` with no per-product capability checks and no server-side allowed-location enforcement.
- Impact: Restricted managers can mutate products/locations outside intended scope.
- Fix: Validate each product with `current_user_can('edit_post', $product_id)` and enforce allowed location IDs server-side.

### 4) [High] Server-side business validation is missing
- Where: `admin/stock-central.php:1285` (client validation), `multi-location-product-and-inventory-management-pro.php:5519`, `multi-location-product-and-inventory-management-pro.php:5523`, `multi-location-product-and-inventory-management-pro.php:5680`, `multi-location-product-and-inventory-management-pro.php:5712`
- Issue: Price/stock/purchase rules are enforced mostly in JS only; backend writes sanitized values directly.
- Impact: Invalid states can be persisted by bypassing JS.
- Fix: Re-implement critical validation rules in PHP and return field-level errors.

### 5) [High] Bulk remove leaves stale location meta
- Where: `includes/class-product-location-table.php:1575`, compare with cleanup logic in `multi-location-product-and-inventory-management-pro.php:5638`
- Issue: Bulk remove detaches taxonomy terms but does not remove `_location_*` product/variation meta.
- Impact: Old stock/pricing data can silently reappear if location is re-assigned.
- Fix: On bulk remove, delete location meta keys for product and variations.

### 6) [High] External products can lose the regular-price column in classic mode
- Where: `admin/stock-central.php:1238`, `admin/stock-central.php:1239`, `admin/stock-central.php:1180`
- Issue: Fallback hidden indexes `[1,4]` are applied when field detection fails; in `price_only` tables, index `1` is Regular price.
- Impact: External product editing UI becomes partially broken.
- Fix: Use layout-aware indexes or only toggle columns when target fields actually exist.

### 7) [Medium] Backorders fields are editable when manage stock is off, but saves are ignored
- Where: `includes/class-product-location-table.php:693`, `includes/class-product-location-table.php:866`, `multi-location-product-and-inventory-management-pro.php:5528`, `multi-location-product-and-inventory-management-pro.php:5688`
- Issue: UI keeps default/variation backorders editable; backend applies them only when manage stock is enabled.
- Impact: Users can "save" values that are not persisted.
- Fix: Disable/hide these fields when manage stock is off, or persist them consistently.

### 8) [Medium] Variation location visibility toggles stock only, not backorders
- Where: `admin/stock-central.php:1249`
- Issue: Variation manage-stock logic hides `stock` column but leaves `backorders` active.
- Impact: Inconsistent behavior between product-level and variation-level controls.
- Fix: Toggle both `stock` and `backorders` for variation location tables.

### 9) [Medium] Bulk actions do not use Post/Redirect/Get safety
- Where: `includes/class-product-location-table.php:1512` to `includes/class-product-location-table.php:1611`
- Issue: Action processing happens on GET without redirecting to a clean URL.
- Impact: Refresh/back can re-trigger actions.
- Fix: Redirect after processing with status query args.

### 10) [Medium] Product type filter logic and UI are inconsistent
- Where: `includes/class-product-location-table.php:1703`, `includes/class-product-location-table.php:2030`
- Issue: Query allows `affiliate`, dropdown does not expose it.
- Impact: Incomplete filter functionality.
- Fix: Add Affiliate option or remove it from whitelist.

### 11) [Low] Stock Central query is publish-only
- Where: `includes/class-product-location-table.php:1638`
- Issue: Draft/private products are excluded from inventory management.
- Impact: Unpublished-catalog inventory workflows are blocked.
- Fix: Add status filter and include editable statuses for authorized users.

### 12) [Medium] Classic data loading is heavy for larger catalogs
- Where: `includes/class-product-location-table.php:1764` to `includes/class-product-location-table.php:1924`
- Issue: Per-product/per-variation loops perform repeated term and meta lookups.
- Impact: Slower load times and poor scalability.
- Fix: Batch-fetch meta/terms and lazy-load row editor payloads.

## Necessary But Missing For A User-Friendly, Professional Experience

### 13) [Medium] No unsaved-changes navigation warning
- Where: `admin/stock-central.php:835` onward (no `beforeunload` handler)
- Issue: Unsaved row edits can be lost on filter/pagination/navigation.
- Recommendation: Add `beforeunload` guard when dirty rows exist.

### 14) [Medium] No confirmation for destructive actions
- Where: `admin/stock-central.php:1901`, `admin/stock-central.php:2002`
- Issue: Remove-location and reset-all execute immediately.
- Recommendation: Add confirmation dialog and optional undo toast.

### 15) [Medium] Validation UX lacks a focused row-level summary
- Where: `admin/stock-central.php:1486`, `admin/stock-central.php:1747`
- Issue: Field highlights exist, but there is no compact summary and no auto-focus on first failing field.
- Recommendation: Add row summary + scroll/focus to first invalid control.

### 16) [Low] Accessibility labels are weak on icon-led controls
- Where: `includes/class-product-location-table.php:1453`, `includes/class-product-location-table.php:1454`, `includes/class-product-location-table.php:582`
- Issue: Controls rely on icon glyphs and `title`, without explicit `aria-label` and clearer visible labels.
- Recommendation: Add accessible names and readable button text.

### 17) [Medium] No concurrent-edit conflict detection
- Where: save flow in `multi-location-product-and-inventory-management-pro.php:5484` to `multi-location-product-and-inventory-management-pro.php:5728`
- Issue: Saves overwrite server state without revision/version checks.
- Recommendation: Include a row version/hash and reject stale updates with merge guidance.

## Suggested Fix Order

1. Authorization hardening (`#1`, `#2`, `#3`).
2. Data integrity (`#4`, `#5`, `#6`, `#7`, `#8`).
3. Workflow safety and UX (`#9`, `#13`, `#14`, `#15`, `#17`).
4. Completeness and scale (`#10`, `#11`, `#12`, `#16`).
