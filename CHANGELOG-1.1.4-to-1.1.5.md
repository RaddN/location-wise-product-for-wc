# Version 1.1.4 to 1.1.5 Changes

## Scope Note

This repository does not contain a plain `1.1.4` tag or version-bump commit. The closest available history is `1.1.4.16`, `1.1.4.17`, and `1.1.4.18` before `1.1.5`, so the notes below summarize the cumulative changes from the available `1.1.4.x` series up to `1.1.5`.

`1.1.5` is mainly a stability and refinement release on top of late `1.1.4.x` feature work.

## New Features and Major Improvements

- Added a new large-scale import/export workflow for products and location data.
- Added support for importing/exporting very large product sets instead of small limited batches.
- Introduced the `Import/Export V2` flow with background jobs, dry-run validation, apply confirmation, event logs, and downloadable reports.
- Added chunked upload handling for large import files.
- Added job controls for pause, resume, and cancel during import/export processing.
- Added import snapshot restore support for safer rollback after imports.
- Added WPML/multilingual support groundwork, including updated translation assets and language packs.
- Added automatic WooCommerce customer address syncing from the selected store location when that option is enabled, so shipping can recalculate against the active store location.
- Improved backorder handling so location-wise stock can go below zero when backorders are allowed instead of always being clamped to `0`.

## Fixes and Enhancements

- Fixed `Stock Alerts by Location` so low-stock and out-of-stock states are identified more accurately.
- Improved stock alert thresholds for product, variation, and location-level cases.
- Fixed stock alert dashboard rows, status badges, export output, and edit links.
- Improved restock detection by tracking alert snapshots more reliably.
- Fixed shipping-related issues in admin and runtime product filtering.
- Prevented backend/admin product searches from incorrectly inheriting the frontend selected-location cookie for administrators and shop managers.
- Kept location-manager product restrictions tied to their assigned locations in backend searches and filters.
- Fixed pickup filtering so a store with no assigned pickup points does not expose unrelated pickup options.
- Fixed `Cash on Pickup` availability so it only appears when the checkout is actually using pickup methods and the selected shipping methods match the gateway rules.
- Fixed location-wise shipping, payment, tax, and pickup setting handling.
- Improved settings sanitization so important toggles and enum-style options save more reliably.
- Centralized pickup enable-check logic to reduce inconsistent behavior across modules.
- Fixed gateway filtering so payment methods are not blocked when location-based payment filtering is disabled.
- Fixed console/admin UI issues, including malformed markup and hidden-field mirroring logic in settings screens.
- Fixed multiple language and translation issues across admin pages and helper texts.
- Made more labels, placeholders, status texts, and helper messages translatable.
- Updated license-page status labels and placeholders to use translation-ready strings.
- Improved translated labels for purchase price, quantity, API/webhook guidance, and shortcode help text.

## User-Facing Summary

From the available git history, the biggest additions between `1.1.4.x` and `1.1.5` are:

- more capable import/export support for large catalogs,
- multilingual/WPML-related support improvements,
- better location-aware shipping/pickup/payment behavior,
- stronger stock alert handling,
- and safer backorder-aware stock updates.

Most remaining work in `1.1.5` is bug fixing, data handling cleanup, and admin/dashboard reliability improvements rather than brand-new standalone modules.

## Source Commits Reviewed

- `8735e55` - possible to import export as much as product you want.
- `d80b9b9` - supported wpml
- `0fbeac5` - Location-wise stock now goes negative on backorder-enabled reductions instead of being clamped to 0.
- `960c26d` - Stock Alerts by Location related issues fixed
- `ba3fefe` - language related issues fixed
- `1de1a98` - shipping related issues fixed
- `6fe508e` - console related issues fixed
- `dd392bc` - shipping & pickup & cash on pickup related issues fixed.
- `fe19f4a` - location wise shipping, payment, tax, pickup related issues fixed

## Notes

- Generated backup translation files and packaged zip updates were excluded from the summary because they are not meaningful end-user feature notes.
