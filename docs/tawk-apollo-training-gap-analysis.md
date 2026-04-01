# Tawk.to / Apollo Training Gap Analysis

Date: 2026-03-19

Plugin: `Multi Location Product & Inventory Management for WooCommerce Pro`

## Scope

This review compares:

- Landing page: https://plugincy.com/multi-location-product-and-inventory-management-for-woocommerce/
- Documentation hub: https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/
- Public API/docs pages found from the documentation hub
- Current plugin code in this repository

## Executive Summary

Your current public content is already broad, but it is not yet safe to use as the only knowledge source for Apollo if your goal is "0% incorrect".

The biggest risk is not only missing documentation. The bigger risk is a mix of:

- marketing claims that are broader than the current implementation,
- missing pages for important implemented features,
- missing limits / conditions / edge cases,
- inconsistent menu names and feature names across pages,
- docs that sometimes describe ideal behavior instead of exact current behavior.

Important: a practical "near 0% incorrect" AI setup requires Apollo to abstain when the answer is not explicitly documented. Without an "I cannot confirm that from current documentation" rule, the bot will still guess.

## Highest-Risk Claims To Fix Before Training

These should be corrected first because they can directly cause wrong answers.

| Risk | Public source | Current evidence in code | Recommended correction |
| --- | --- | --- | --- |
| Geolocation provider claim is inaccurate | Landing page says nearest location uses "Google Maps or MaxMind API integration" | Current code uses browser geolocation, OpenStreetMap Nominatim, and Leaflet. I did not find Google Maps or MaxMind integration. Evidence: `templates/shortcode-selector.php`, `assets/js/modern-popup.js`, `includes/frontend-location-information.php`, `admin/admin.php`, `multi-location-product-and-inventory-management-pro.php:8454-8493` | Replace with exact wording: "Uses browser geolocation plus OpenStreetMap Nominatim/Leaflet for nearest-location behavior." |
| Import/export file format claim is inaccurate | Landing page says import/export uses `XLS` or `XLSX` | Current import/export is CSV-focused, with CSV or ZIP package support for the newer flow. Dashboard has HTML-based `.xls` export, not XLSX import. Evidence: `admin/import-export-settings.php`, `includes/class-stock-central-import-export.php`, `includes/class-import-export-v2.php`, `admin/dashboard.php` | Replace with exact wording: "Inventory import/export uses CSV. Full import/export supports canonical CSV and ZIP package workflow. Dashboard also exports HTML-based XLS reports." |
| "CSV, API & POS Sync" feature block contains wrong text | Landing page repeats profit/aging copy under the "CSV, API & POS Sync" heading | API and webhook sync do exist, but the landing block text does not describe them. Evidence: landing page text vs `includes/api/inventory-sync-api.php` | Rewrite that block to describe actual REST endpoints, webhook sync, auth headers, and CSV export/import behavior. |
| Product bundles are claimed without implementation evidence | Landing page lists "Product Bundles" | I found only an empty settings subtab placeholder for bundles and no working bundle module. Evidence: `admin/settings.php:6055-6056` | Remove the claim until implemented, or add real implementation and docs. |
| Location-wise invoices are claimed without implementation evidence | Landing page free vs pro table lists "Location wise invoices" | I did not find a dedicated invoice feature/module. I only found an email-type reference to `customer_invoice` in split-order email filtering. Evidence: `includes/order-split-by-location.php:38` | Remove or qualify this claim unless there is another module not in this codebase. |
| API key docs are inaccurate | Public doc says keys are "displayed only once" | Admin UI shows stored keys again after generation. Evidence: `admin/api-settings.php` renders the saved key and secret whenever they exist | Update docs to say keys remain visible in settings until regenerated or removed. |
| Bulk API docs oversimplify failure handling | Public bulk sync doc says successful items are processed independently and one failure does not stop the sync | Code enforces a default max of 1000 items and rolls back the transaction if failures exceed 50 percent. Evidence: `includes/api/inventory-sync-api.php:143-177` | Document max item count, rollback threshold, and exact failure semantics. |
| Admin path naming is inconsistent | Public docs mix `Location Manage`, `Multi Location Products`, `Settings -> Notifications`, and `Multi Location Products -> API & Webhooks` | Actual admin menu root is `Location Manage`, with `Settings`, `Dashboard`, `Stock Central`, and `Location Managers`. Evidence: `admin/admin.php:4090-4174` | Standardize all docs to one exact menu path convention. |

## Missing Or Weakly Documented Topics

These are implemented or materially supported in code, but are missing, underspecified, or hard to confirm from the public docs hub.

### P0: Must Add

| Topic | Why Apollo needs it | Evidence in code |
| --- | --- | --- |
| Location-wise currency | This changes pricing, currency symbol, conversion rate, and also disables some cart behaviors. Without this page, the bot will answer mixed-cart and pricing questions incorrectly. | `multi-location-product-and-inventory-management-pro.php:313-569`, `admin/admin.php:1021-1099`, `includes/location-wise-shipping-payment-tax.php`, `includes/stock-price-backorder-manage.php` |
| Location URL settings | Public docs hub shows SEO pages, but I found no dedicated public page for location URLs, query parameter vs path prefix mode, or permalink flushing. | `admin/settings.php:1911-2039`, `admin/admin.php:6099-6154` |
| Import/export matrix | Public docs appear too thin for the actual complexity. Apollo needs one page that clearly separates legacy CSV inventory import, full canonical CSV export/import, ZIP package import, dry-run/apply flow, row limits, schema versioning, and failure reports. | `admin/import-export-settings.php`, `includes/class-stock-central-import-export.php`, `includes/class-import-export-v2.php`, `admin/stock-central.php` |
| API limits and exact contracts | The public API docs need stronger precision: max items, rollback behavior, CSV export caveats, auth methods, headers, required fields, and paginated export behavior. | `includes/api/inventory-sync-api.php`, `admin/api-settings.php` |
| Supported / unsupported feature matrix | Apollo needs one canonical page with "supported", "not supported", "planned", and "depends on Pro" items. This is the only reliable way to stop guesses about bundles, invoices, Google Maps, MaxMind, XLSX, etc. | Gap found across landing page, docs, and code |
| Exact admin menu map | Menu naming drift creates wrong step-by-step support answers. | `admin/admin.php:4090-4174` |

### P1: Strongly Recommended

| Topic | Why Apollo needs it | Evidence in code |
| --- | --- | --- |
| Location metadata schema | Locations carry more than name and slug: aliases, address, postal code, phone, email, business hours, map coordinates, logo, gallery, shipping methods, payment methods, pickup locations, currency, tax class, display order, active status. Support answers will be weak without one page covering these fields. | `admin/admin.php`, `includes/location-resolver.php`, `includes/frontend-location-information.php`, `includes/location-wise-seo.php` |
| Location aliases / resolver behavior | Alias matching affects location lookup and search accuracy, but I found no public doc for it. | `admin/admin.php:1266-1309`, `includes/location-resolver.php:753-770` |
| Manual assignment, mixed cart, split order constraints | These features depend on each other. For example, location-wise currency disables mixed-location cart, cart-level location change, and split order behavior. This needs an explicit decision table. | `multi-location-product-and-inventory-management-pro.php:313-569`, `includes/order-split-by-location.php` |
| Cash on pickup and pickup-location behavior | The landing page promotes cash on pickup, but the docs hub does not expose a dedicated page for this flow. | `includes/cash-on-pickup-payment-gateway.php`, `includes/location-wise-local-pickup.php`, `multi-location-product-and-inventory-management-pro.php:798-822` |
| Shortcodes catalog | Public docs cover some display shortcodes, but Apollo needs one complete shortcode reference including `mulopimfwc_store_location_selector`, `mulopimfwc_display_popup`, `mulopimfwc_location_info`, `mulopimfwc_location_recommendations`, `mulopimfwc_product_filter`, and `mulopimfwc_location_status`. | `multi-location-product-and-inventory-management-pro.php:3007-3008`, `includes/frontend-location-information.php:143`, `includes/customer-location-insights.php:96`, `includes/frontend-product-filter.php:45`, `admin/admin.php:93` |
| Customer insights and recommendations | If Apollo is asked how recommendations work, what is tracked, whether it uses sessions, and how reports are built, current public sources look too thin. | `includes/customer-location-insights.php` |
| Social notification payloads and channel behavior | Public docs list channels and setup steps, but one page should also define event names, thresholds, digest schedule, payload format, and what "custom webhook" receives. | `multi-location-product-and-inventory-management-pro.php:13148-14287`, `admin/settings.php:3394-3525` |
| External services and dependencies | Apollo should answer with exact providers. Today that includes Nominatim, Leaflet, Telegram API, Slack/Teams/Discord/custom webhooks, and WooCommerce auth. | `multi-location-product-and-inventory-management-pro.php`, `admin/admin.php`, `includes/frontend-location-information.php`, `multi-location-product-and-inventory-management-pro.php:13619-13622` |

### P2: Helpful For Support Accuracy

| Topic | Why Apollo needs it | Evidence in code |
| --- | --- | --- |
| Troubleshooting matrix | Good support bots need exact answers for common issues: location not changing, cart blocked, prices not switching, missing map, API auth errors, location archive not loading, permalinks needing flush, etc. | Cross-cutting |
| Compatibility notes | Theme compatibility is marketed, but there should be a support-style compatibility page for WooCommerce blocks, HPOS, local pickup, geolocation permissions, and external webhook/network dependencies. | Cross-cutting |
| Privacy / data tracking notes | Customer insights use session-based tracking and stored options. Apollo should not guess about privacy behavior. | `includes/customer-location-insights.php` |
| Changelog / versioned behavior page | Support answers should be version-aware. | Cross-cutting |

## Public Docs That Need Tighter Wording

These are not necessarily missing pages. They need more exact wording.

- The API key page should not say keys are shown only once.
- The bulk sync API page should document the 1000-item default cap and rollback rule.
- Integration pages should state exact menu paths as they exist now.
- Geolocation pages should name the real providers being used now.
- Import/export pages should say "CSV" unless they are specifically talking about dashboard HTML XLS export.
- Any page that describes a feature should also include "requirements", "limitations", and "what disables this feature".

## Recommended New Public Pages

Create these as standalone public docs pages. They will give Apollo much better grounding than the landing page.

1. `Location-wise currency and currency conversion`
2. `Location URL settings and permalink behavior`
3. `Import/export formats and workflow matrix`
4. `REST API limits, authentication, and payload reference`
5. `Shortcodes reference`
6. `Cash on pickup and pickup-location setup`
7. `Location metadata reference`
8. `Mixed cart, split order, and assignment-mode compatibility rules`
9. `Supported / unsupported features matrix`
10. `Troubleshooting and known limitations`

## Apollo / Tawk Training Recommendations

### 1. Change the source strategy

Do not treat the landing page as an authority for technical support until the inaccurate claims are fixed.

Best setup:

- Use docs pages and a reviewed FAQ as the primary source.
- Keep the landing page only for pricing, high-level features, and sales copy.
- Add one canonical "AI support facts" page and make Apollo prefer it over marketing pages.

### 2. Add a strict answer policy

For near-0% incorrect answers, Apollo should follow rules like:

- Answer only from reviewed docs / FAQ / support facts pages.
- If a feature is not explicitly documented, say: "I cannot confirm that from the current documentation."
- Do not infer from marketing adjectives like "smart", "advanced", "automatic", or "real-time".
- Always mention conditions and limits when a feature depends on another setting.
- For setup paths, use exact admin menu names only.
- For integrations, name the exact current provider or say it is not documented.

### 3. Build a canonical FAQ set

Create a public FAQ page with direct answers to the questions buyers actually ask:

- Does it support Google Maps?
- Does it support MaxMind?
- Does it support XLSX import?
- Does it support ZIP import?
- Does it support API sync?
- Does it support POS sync?
- Does it support mixed-location cart?
- What turns mixed-location cart off?
- Does location-wise currency work with split orders?
- Does it support product bundles?
- Does it support invoices?
- What is Free vs Pro?
- What are the API limits?
- Which geolocation service is used?
- Which webhook channels are supported?

### 4. Add negative examples for the AI

This matters a lot. Apollo should be trained on "what not to say", not only "what to say".

Examples:

- `Does it use Google Maps?` -> `Current implementation uses browser geolocation plus OpenStreetMap Nominatim/Leaflet.`
- `Can I import XLSX?` -> `Current import/export flows are CSV-focused; full import also supports ZIP package workflow.`
- `Does it support bundles?` -> `I cannot confirm a working product-bundles feature from the current documentation and code evidence.`
- `Can the bulk API sync unlimited items?` -> `No. The default limit is 1000 items per request.`

### 5. Normalize terminology

Pick one vocabulary set and use it everywhere:

- Admin menu root: `Location Manage`
- Settings page: `Location Manage -> Settings`
- Dashboard page: `Location Manage -> Dashboard`
- Stock screen: `Location Manage -> Stock Central`
- Manager screen: `Location Manage -> Location Managers`

Also standardize feature names:

- `Location-wise currency`
- `Location URL settings`
- `Mixed-location cart`
- `Split order by location`
- `Cash on pickup`
- `Location-specific reviews`
- `Social notifications`

### 6. Use a fixed doc template for every feature page

Each public doc page should follow the same structure:

1. What this feature does
2. Availability: Free or Pro
3. Requirements / dependencies
4. Exact admin path
5. Settings and field explanations
6. What this feature affects
7. What disables or overrides it
8. Example use cases
9. Limits / caveats
10. Troubleshooting

That template alone will reduce Apollo hallucination significantly.

### 7. Add a versioned "support facts" page

Publish one page with a title like:

`Multi Location Product & Inventory Management for WooCommerce - AI Support Facts`

It should contain:

- current plugin version,
- minimum WordPress / WooCommerce / PHP requirements,
- current supported file formats,
- current supported integrations,
- exact API routes,
- exact admin paths,
- supported shortcodes,
- feature compatibility rules,
- unsupported or not-yet-documented features.

This page should be short, factual, versioned, and manually reviewed whenever the plugin changes.

### 8. Run a weekly answer-quality loop

Operationally, this is what gets you closest to 0% incorrect:

- export Apollo conversations weekly,
- label wrong / incomplete / risky answers,
- group failures by topic,
- update docs and FAQ for the repeated gaps,
- add the failed question as a new eval test,
- re-check after each documentation update.

## Suggested Priority Order

1. Fix the landing page inaccuracies.
2. Publish the missing P0 docs pages.
3. Publish one canonical AI support facts page.
4. Add negative-answer examples and abstention rules to Apollo.
5. Review weekly chat logs and keep expanding the FAQ/eval set.

## Bottom Line

Right now, Apollo can easily answer many questions, but it will still produce avoidable mistakes because the public sources do not clearly separate:

- implemented vs marketed,
- supported vs unsupported,
- always-on vs conditional behavior,
- exact current behavior vs simplified descriptions.

If you want the bot to become extremely reliable, the next step is not only "more content". The next step is:

- cleaner factual sources,
- a canonical support facts page,
- explicit unsupported statements,
- and a refusal rule when documentation is missing.
