# Inventory Sync Mode Implementation Plan

Date: 2026-02-05
Scope: Implement `mulopimfwc_display_options[inventory_sync_mode]` across stock read/write, UI, API, and reporting.

**Current State**
- Setting exists in `admin/settings.php` but the value is not used.
- Stock is stored per location in `_location_stock_{term_id}` and used by frontend overrides and order stock updates in `includes/stock-price-backorder-manage.php` and `multi-location-product-and-inventory-management-pro.php`.
- Global WooCommerce stock `_stock` is used as a fallback in several places and updated in the API for a `mulopimfwc_primary_location`.
- Defaults in `multi-location-product-and-inventory-management-pro.php` do not include `inventory_sync_mode`.
- `admin/settings.php` sanitization does not currently validate `inventory_sync_mode`.

**Mode Definitions (Required Behavior)**
- Independent: Each location has its own stock. No automatic propagation between locations. Orders reduce only the selected location stock.
- Centralized: Global stock is authoritative. Each location has an allocation or available pool drawn from the global total. Orders reduce both the location allocation and the global total. Sum of allocations must not exceed global stock.
- Synchronized: Single shared stock value for all locations. Any update to one location updates all other locations and the global stock value. Reports must not multiply totals by number of locations.

**Features and Changes Required**
1. Inventory sync mode core with a validated getter and a single read/write path for stock updates.
2. Mode-aware stock reads for frontend and backend displays, availability checks, and location selection.
3. Mode-aware stock writes for order reduce, restore, refund, manual edits, API updates, and bulk imports.
4. Centralized allocation management UI, including unallocated stock visibility and validation.
5. Synchronized mode UI and guardrails to prevent divergent per-location edits.
6. Migration and reconciliation tooling for switching modes safely.
7. API and webhook behavior updates aligned to the selected mode.
8. Reports and alerts that use the correct stock source per mode and avoid double counting.
9. Permissions and audit logging adjustments for multi-user workflows.

**Change Map (Files and Areas)**
- `admin/settings.php` add sanitization for `inventory_sync_mode`, update descriptions, and add warnings when switching modes.
- `multi-location-product-and-inventory-management-pro.php` add default option, add helpers, update inventory-based location selection and order item stock updates to respect mode.
- `includes/stock-price-backorder-manage.php` update stock quantity and stock status filters plus reduce, restore, and refund hooks to use mode-aware stock writes.
- `includes/api/inventory-sync-api.php` adjust `process_inventory_item` to update central stock or synchronized stock across locations, and support an optional `central_stock` field.
- `includes/class-product-location-table.php` modify Stock Central display and edit behavior for centralized or synchronized modes.
- `admin/stock-central.php` add a mode banner and explanatory UI, disable or change edit controls based on mode.
- `admin/admin.php` update available-location stock snapshots and bulk assignment validation to use mode-aware stock.
- `admin/dashboard.php` update stock totals, low stock lists, and analytics to avoid double counting in synchronized mode and to respect centralized totals.
- `assets/js/admin.js` add UI toggles and disabling logic for mode-specific fields.
- `readme.txt` and `FRONTEND_TEXT_MANAGEMENT.md` document behavior and any user-facing text changes.

**Data Model and Stock Source**
- Independent: `_location_stock_{term_id}` is authoritative per location. `_stock` is used only when location stock is not set.
- Centralized: `_stock` (or a dedicated meta key if preferred) becomes the global stock source. `_location_stock_{term_id}` represents allocation per location. Unallocated stock is `_stock` minus sum of allocations.
- Synchronized: `_stock` is canonical. `_location_stock_{term_id}` is either ignored on read or kept in sync by mirroring the global stock.

**Mode Switching and Migration**
- Add a conversion routine when switching modes that can run in batches.
- Independent to Synchronized: choose a source stock per product and propagate to all locations.
- Independent to Centralized: set `_stock` to sum of allocations or a chosen location, then compute allocations from current per-location values.
- Synchronized to Independent: copy global stock into each location or require manual allocation.
- Centralized to Independent: keep allocations as location stock and leave `_stock` unchanged or set to sum for reference.
- Provide a dry-run preview and a rollback or backup option.

**Order and Cart Behavior**
- Independent: unchanged.
- Centralized: use location allocation for availability checks; prevent allocations from going negative; reduce `_stock` and allocation on order placement; restore both on cancellation or refund.
- Synchronized: all availability checks use `_stock`; reduce `_stock` and update all location stocks on order placement; restore similarly.

**Alerts and Notifications**
- Independent: per-location thresholds as today.
- Centralized: alert on low unallocated stock and optionally on per-location allocations.
- Synchronized: alert once per product based on global stock and avoid duplicate alerts per location.

**API and Import and Export**
- Independent: keep current payloads.
- Centralized: allow payloads to update global stock and per-location allocations in a single request.
- Synchronized: treat location-specific stock updates as updates to global stock and mirror to all locations.
- Export should include global stock when in centralized or synchronized mode.

**Acceptance Criteria**
- Mode selection is saved, sanitized, and respected across all stock reads and writes.
- Stock totals and availability are accurate for each mode.
- No double counting in reports or dashboard totals.
- Order creation, refund, cancellation, and manual edits correctly update stock for each mode.
- API and bulk sync produce consistent results in each mode.
- Switching modes does not corrupt stock and provides a safe migration path.

**Open Questions**
- Should backorders and stock status be synchronized in synchronized mode, or remain per location?
- In centralized mode, should allocations be required for all locations or optional with on-demand allocation?
- Which field is the authoritative global stock source, `_stock` or a dedicated meta key?
