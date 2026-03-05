# Stock Central Full Product + Location Import Export Decision Checklist

Date: 2026-03-05  
Status: Draft (pre-implementation)  
Scope: Add `Import Export` in Stock Central header (before `Modern/Classic`) and support safe cross-site migration of full WooCommerce product data plus location-wise data.

## 1) Current System Facts (from existing code)

1. Stock Central view switch exists in `admin/stock-central.php` (`Modern` / `Classic`).
2. Product export currently exists on settings page and is inventory-oriented.
3. Existing export and import formats are not the same contract.
4. Existing import path mainly updates inventory fields and location meta per row.
5. Location data uses taxonomy `mulopimfwc_store_location` plus term-ID-based post meta keys (`_location_*_{term_id}`).
6. Import currently resolves location by ID or slug, but does not auto-create missing locations.
7. Import currently does not guarantee taxonomy assignment sync when writing location meta.
8. Plugin has many additional location term meta fields (geo, hours, tax/currency, status, address, etc.).

## 2) Why "Full Product Export/Import" Is Different

When we include all product info, migration is no longer only stock/price sync. We must safely migrate:

1. Product core identity and content.
2. Product type-specific structures (simple/variable/grouped/external/downloadable).
3. Taxonomies and attributes.
4. Media and file references.
5. Product relationships (linked, parent-child, variation mapping).
6. Plugin-specific location data and assignments.
7. Optional third-party custom meta fields.

This needs deterministic mapping and staged import passes, not a single flat row updater.

## 3) Decisions Required Before Coding

### A. CSV Contract

- [ ] A1. Export file format.
  - Options: single canonical CSV, multiple CSV files.
  - Recommended baseline: single canonical CSV file (no ZIP).

- [ ] A2. Schema versioning.
  - Required key: `schema_version`.
  - Recommended baseline: hard fail on unsupported major version.

- [ ] A3. Canonical CSV model structure.
  - Decide whether one row schema can represent all entities.
  - Recommended baseline: single CSV with `row_type` and `row_key`, for example:
    - `product`
    - `variation`
    - `location`
    - `taxonomy_term`
    - `relationship`
    - `location_inventory`
    - `media_ref`.

- [ ] A4. Null/empty/set semantics.
  - Must distinguish: skip field, clear field, set field.
  - Recommended baseline: explicit field operation mode in import options.

### B. Product Identity and Collision Rules

- [ ] B1. Product matching key priority.
  - Recommended baseline: `SKU` primary, then `slug` fallback in configurable strict mode.

- [ ] B2. Variation matching key priority.
  - Recommended baseline: variation SKU first, then parent SKU + normalized attribute combination.

- [ ] B3. Duplicate SKU behavior.
  - Recommended baseline: fail row and require manual mapping; never guess.

- [ ] B4. Missing products behavior.
  - Decide between update-only vs create-missing.
  - Recommended baseline v1: configurable; default `update + create`.

- [ ] B5. Slug collision behavior.
  - Recommended baseline: do not overwrite unrelated products; require explicit mapping.

### C. Full Product Data Scope (Must Decide Field-by-Field)

- [ ] C1. Core fields.
  - `name`, `slug`, `status`, `catalog_visibility`, `featured`, `menu_order`, `description`, `short_description`.

- [ ] C2. Pricing and inventory defaults.
  - `regular_price`, `sale_price`, `_price`, `manage_stock`, `_stock`, `_stock_status`, `backorders`, `sold_individually`.

- [ ] C3. Shipping and tax fields.
  - `virtual`, `downloadable`, `weight`, `length`, `width`, `height`, `shipping_class`, `tax_status`, `tax_class`.

- [ ] C4. Purchase/profit fields used by this plugin.
  - `_purchase_price`, `_purchase_quantity`.

- [ ] C5. Type-specific data.
  - External product URL/button text.
  - Grouped product children.
  - Downloadable files, limits, expiry.

- [ ] C6. Variation model.
  - Attributes, default attributes, variation-level prices, stock, backorders, image, enabled state, menu order.

- [ ] C7. Linked products.
  - Upsells, cross-sells, grouped/parent-child references with remapping.

- [ ] C8. SEO/custom fields.
  - Decide: include known SEO meta and selected custom meta or not.

- [ ] C9. Reviews and ratings.
  - Decide if review migration is in scope for this feature.

### D. Taxonomies and Attributes

- [ ] D1. Taxonomies in scope.
  - `product_cat`, `product_tag`, `product_brand` (if present), custom taxonomies, and `mulopimfwc_store_location`.

- [ ] D2. Attribute model.
  - Global attributes (`pa_*`) and custom product-level attributes.

- [ ] D3. Term identity strategy.
  - Recommended baseline: taxonomy + slug as primary identity, not term ID.

- [ ] D4. Missing term policy.
  - Recommended baseline: auto-create terms with optional strict mode.

- [ ] D5. Hierarchy reconstruction.
  - Categories and locations may be hierarchical; parent mapping must be deterministic.

### E. Location Model and Mapping

- [ ] E1. Primary identity for locations.
  - Recommended baseline: location slug.

- [ ] E2. Missing location behavior.
  - Recommended baseline: create missing location with minimal required term + configurable term meta import.

- [ ] E3. Location term meta scope.
  - Decide between:
    - Inventory-only location sync.
    - Full location profile sync (address/hours/currency/tax/geo/status/etc.).

- [ ] E4. Location assignment sync.
  - Always decide whether importer also updates product taxonomy assignment.
  - Recommended baseline: always sync assignment when location records are imported.

- [ ] E5. Location ID rekeying for `_location_*_{id}` meta.
  - Required mapping step: source location slug -> target term ID.

### F. Media and Files

- [ ] F1. Media strategy.
  - URL-reference only, or optional media sideload from URL list in CSV.
  - Recommended baseline: URL-reference + optional sideload mode.

- [ ] F2. Featured/gallery image mapping.
  - Must map source references to target attachment IDs safely.

- [ ] F3. Downloadable file links.
  - Validate URL accessibility and file integrity before attach.

- [ ] F4. Duplicate media dedupe policy.
  - Recommended baseline: hash/filename based dedupe in import job.

### G. Custom Meta / Third-Party Compatibility

- [ ] G1. Meta inclusion policy.
  - Whitelist-only vs include-all-except-blacklist.
  - Recommended baseline: allow explicit whitelist profiles.

- [ ] G2. Sensitive meta exclusion.
  - Must exclude volatile or unsafe meta (`_edit_lock`, transient/runtime cache/meta).

- [ ] G3. Plugin profile support.
  - Define import/export profiles per integration (SEO plugins, bundles, subscriptions, etc.).

### H. Safe Import Execution Model

- [ ] H1. Import mode.
  - `dry-run`, `update-only`, `create+update`, `replace-scope`.

- [ ] H2. Multi-pass pipeline.
  - Recommended baseline passes:
    - Pass 1: taxonomy/location/media pre-map and create.
    - Pass 2: parent/simple/grouped/external products.
    - Pass 3: variations.
    - Pass 4: relationships and linked references.
    - Pass 5: location-wise inventory/meta finalize and cache cleanup.

- [ ] H3. Mapping tables.
  - Persist source->target mapping for products, variations, terms, media.

- [ ] H4. Transaction and rollback model.
  - Recommended baseline: chunk-level transaction + job snapshot + restore job.

- [ ] H5. Idempotency.
  - Re-importing the same CSV should not create duplicates.

- [ ] H6. Concurrency safety.
  - Decide lock behavior during checkout/stock mutations.

### I. UI/UX in Stock Central

- [ ] I1. Header placement.
  - `Import Export` control before `Modern/Classic` toggle.

- [ ] I2. Control design.
  - Recommended baseline: split-button/dropdown.

- [ ] I3. Wizard steps.
  - Upload -> Validate -> Field Scope -> Mapping -> Dry Run -> Confirm -> Execute -> Report.

- [ ] I4. Error outputs.
  - Downloadable failed rows + human-readable report + retry failed.

- [ ] I5. Job history.
  - Persist import/export jobs with actor, file hash, options, result.

### J. Security and Governance

- [ ] J1. Capability model.
  - Separate caps for export/import.

- [ ] J2. File validation.
  - Extension, MIME, size, row limits, encoding, schema checksum.

- [ ] J3. Integrity and authenticity.
  - Optional CSV signature/checksum verification.

- [ ] J4. Audit log and compliance.
  - Who imported what, when, and with which mode.

### K. Performance and Scale

- [ ] K1. Execution style.
  - Recommended baseline: background queue, not blocking synchronous request.

- [ ] K2. Chunking strategy.
  - Configurable batch size per entity type.

- [ ] K3. Large catalog support.
  - Memory/time guards, resumable jobs, retry policy.

- [ ] K4. Cache invalidation.
  - Targeted product transient clears + final cache version bump.

### L. Testing and Rollout

- [ ] L1. Test matrix.
  - Simple, variable, grouped, external, downloadable, missing terms, missing media, duplicate SKU, duplicate slugs.

- [ ] L2. Cross-site scenarios.
  - Different location IDs, different product IDs, partial taxonomy overlap.

- [ ] L3. Safety tests.
  - Dry-run correctness, rollback success, idempotency.

- [ ] L4. Release plan.
  - Feature flag + pilot stores + staged rollout.

## 4) Recommended Baseline (for sign-off)

If no objections, use this baseline:

1. Build a versioned single CSV export model for full product + location data.
2. Use SKU/slug-based mapping (never trust source IDs across sites).
3. Support auto-create for missing terms/locations with strict mode option.
4. Run import as background multi-pass job with mandatory dry-run preview.
5. Synchronize taxonomy assignments and location meta together.
6. Include full product scope by profile:
   - `Core + Catalog`
   - `Pricing + Inventory`
   - `Taxonomies + Attributes`
   - `Variations + Relations`
   - `Location Inventory`
   - `Optional Media`
   - `Optional Custom Meta`
7. Provide detailed import report + failed rows export + retry capability.
8. Keep complete audit trail and rollback snapshot per job.

## 5) Sign-off Checklist

- [ ] Full-product scope confirmed (not only inventory).
- [ ] Create/update/replace behavior confirmed.
- [ ] Media strategy confirmed.
- [ ] Custom meta policy confirmed.
- [ ] Permission model confirmed.
- [ ] Baseline approved for implementation.
