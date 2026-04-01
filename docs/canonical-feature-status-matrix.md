# Canonical Feature Status Matrix

Date: 2026-03-19

Product: `Multi Location Product & Inventory Management for WooCommerce`

## Purpose

Use this page as the single source of truth for:

- public feature-status documentation,
- sales/support alignment,
- Tawk.to / Apollo training,
- preventing AI guesses from landing-page wording alone.

## AI Usage Rules

Apollo should follow these rules when this page is published:

- If an item appears under `Supported`, it can be described as currently available.
- If an item appears under `Depends on Pro`, Apollo must explicitly say it requires Pro.
- If an item appears under `Not Supported`, Apollo must not describe it as available.
- If an item is not listed on this page, Apollo should answer: `I cannot confirm that from the current product documentation.`
- Do not let Apollo infer capability from marketing language alone.

## Status Definitions

- `Supported`: implemented in the current product build and safe to describe as available.
- `Depends on Pro`: available only in the Pro version or behind Pro-gated settings.
- `Not Supported`: should not be claimed as available in current public docs.
- `Planned`: future or roadmap item. Do not present as currently available.

---

## Supported

| Item | Notes |
| --- | --- |
| Multi-location store taxonomy | Supports store/location terms for products. |
| Location-wise stock management | Stock can be managed per location for products and variations. |
| Location-wise pricing | Product pricing can vary by location. |
| Product filtering by selected location | Products can be filtered based on the selected store/location. |
| Store/location selector UI | Popup and selector interfaces are available for customer location selection. |
| Location display on products | Current location can be shown on product titles and product pages. |
| Location information display | Location details can be displayed with address, map, and related metadata. |
| Product location selector shortcode | Shortcode support exists for product-level location selection. |
| Store location selector shortcode | Shortcode support exists for general store/location selection. |
| Location info shortcode | Shortcode support exists for showing location details. |
| Product filter shortcode | AJAX product filter shortcode exists. |
| Location status shortcode | Shortcode support exists for outputting a location status badge. |
| Stock Central screen | Admin stock-management screen exists for location-wise inventory work. |
| CSV inventory import/export | CSV-based inventory import/export is supported. |
| Full canonical CSV export/import workflow | Full product + location migration flow exists with canonical CSV handling. |
| ZIP package import workflow | Newer import flow supports ZIP package handling in addition to CSV. |
| REST API inventory endpoints | API endpoints exist for inventory sync, export, locations, and products. |
| Webhook inventory update endpoint | A webhook endpoint exists for external inventory update flows. |
| Location managers | Assigned managers, location assignment, and scoped permissions are supported. |
| Dashboard and analytics screens | Dashboard/reporting views exist for location-wise operational data. |
| Customer insights and recommendations | Location-based tracking and recommendations features exist. |
| Social notification channels | Slack/Teams/Discord/custom webhook and Telegram channel support exists. |
| Location metadata management | Locations support address, contact info, coordinates, logo, gallery, ordering, and active/inactive status. |
| Location aliases | Alias metadata exists for location resolution/search behavior. |
| Location archive / URL capability | Location URLs and archive behavior exist when enabled. |
| Location SEO support | SEO/title/meta/schema integration exists for location-aware pages. |

---

## Depends on Pro

| Item | Notes |
| --- | --- |
| Location-wise currency | Requires Pro-gated settings and depends on location pricing. |
| Mixed-location cart | Pro-gated. Also affected by assignment mode and currency rules. |
| Change location in cart | Pro behavior depends on cart/currency configuration. |
| Group cart by location | Pro-gated. |
| Split orders by location | Pro-gated and depends on mixed-location cart setup. |
| Location-based shipping methods | Pro-gated. |
| Location-based payment methods | Pro-gated. |
| Location-based tax behavior | Pro-gated. |
| Location-based pickup logic | Pro-gated. |
| Cash on pickup flow | Pro-gated behavior tied to pickup/location setup. |
| Location-based discounts/coupon behavior | Pro-gated. |
| Location-specific reviews | Pro-gated. |
| Business-hours purchase restriction | Pro-gated. |
| Social notifications settings | Channel/event configuration is Pro-gated. |
| API key and webhook secret management UI | API/webhook settings UI is Pro-gated. |
| Customer location tracking toggle | Customer tracking/recommendation settings are Pro-gated. |
| Location URL settings UI | URL settings are exposed as Pro-gated settings. |
| Location SEO settings UI | SEO settings are exposed as Pro-gated settings. |

---

## Not Supported

| Item | Notes |
| --- | --- |
| Google Maps integration | Current implementation uses browser geolocation plus OpenStreetMap Nominatim/Leaflet, not Google Maps. |
| MaxMind integration | No current implementation evidence found for MaxMind integration. |
| Native XLSX inventory import | Current import/export workflows are CSV-focused; do not claim XLSX import support. |
| Native XLS inventory import | Do not claim `.xls` inventory import support. |
| Product bundles | Do not claim as supported until there is real implementation and documentation. |
| Dedicated location-wise invoices feature | Do not claim until there is a dedicated implemented module and docs. |
| Unlimited bulk API sync in one request | Do not claim unlimited requests; current API uses request limits and failure rules. |
| Vendor-specific native POS connectors | Do not claim named POS integrations unless a specific connector is documented and maintained. |

---

## Planned

Only add items here after product, engineering, and documentation all agree they belong on the roadmap.

Do not let Apollo present `Planned` items as currently available.

| Item | Owner | Target Version | Notes |
| --- | --- | --- | --- |
| TODO | TODO | TODO | Add only confirmed roadmap items. |

---

## Publishing Notes

Before publishing this page publicly, review:

- every `Supported` item against the current release build,
- every `Depends on Pro` item against the Free vs Pro comparison,
- every `Not Supported` item against current landing-page claims,
- every `Planned` item against the real roadmap.

## Suggested Follow-Up

Once this page is published, update Apollo/Tawk training so that:

1. this page is preferred over the landing page for product capability questions,
2. unsupported items are answered with a direct refusal,
3. Pro-only items always include the Pro qualifier,
4. any unlisted item is treated as unconfirmed.
