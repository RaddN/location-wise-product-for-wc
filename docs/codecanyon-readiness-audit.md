# CodeCanyon Readiness Audit

Research date: March 14, 2026

Plugin reviewed: `Multi Location Product & Inventory Management for WooCommerce Pro`

Goal: sell this plugin on CodeCanyon while continuing to sell it directly through EDD on your own site.

Status: not ready for CodeCanyon submission as-is.

## Executive Summary

The main blockers are not feature quality. They are channel-specific compliance issues:

- The current Pro build is hardwired to EDD licensing, EDD renewal, and EDD update flows.
- Premium functionality is gated by an EDD license state, which is not compatible with a CodeCanyon buyer experience.
- The plugin performs automatic remote calls to `plugincy.com` and exposes a frontend `nopriv` background license check flow.
- Telemetry is effectively enabled by default and sends site information, active plugin data, theme data, and the stored license key.
- The buyer/admin UI still assumes Plugincy checkout, Plugincy account pages, and WordPress.org community support.
- The package is not marketplace-clean yet; it still contains repo folders, a nested ZIP, backup translation files, and no buyer-ready license/third-party notice package.

If you submit this exact build to CodeCanyon, the likely review problems are licensing/update flow mismatch, privacy/remote-call concerns, packaging quality, and reviewer confusion about how CodeCanyon buyers are expected to activate and update the product.

## 1. Business Decisions You Must Make First

These are not code changes, but they directly determine what code changes are required.

### 1.1 Decide exclusive vs non-exclusive for this item

- If you want to keep selling the same plugin on your own EDD site, this item cannot be treated as exclusive on Envato.
- If your author account is currently exclusive for the items sold through it, resolve that first before listing the same plugin outside Envato.
- Envato's Exclusivity Policy makes this a hard commercial rule, not a code preference.

### 1.2 Decide the Envato licensing model for this item

- Choose between Envato's default split license and 100% GPL.
- Because this is a WordPress plugin, the item must be GPL-compliant either way.
- If you want to opt into 100% GPL, first verify that every bundled third-party component is GPL-compatible.

### 1.3 Decide the CodeCanyon buyer flow

Pick one clear model before editing code:

- Simplest model: no in-plugin activation at all; buyers manually update by downloading new versions from Envato.
- Better UX model: optional purchase-code registration for updates/support access.
- Advanced model: Envato-aware automatic updater using purchase code or Envato token.
- If you use purchase-code registration, document clearly that buyers obtain it from Envato Downloads via `License Certificate and Purchase Code`.

What cannot stay:

- EDD license key entry as the only activation method.
- EDD renewal messaging.
- EDD-specific update/package URLs.

### 1.4 Decide the official support channel for this item

Envato allows supported items to use:

- an external support URL,
- item comments,
- or email.

You should decide one support method and one response-time promise, then align the plugin UI with that exact method.

## 2. Mandatory Product Changes Before CodeCanyon Submission

### 2.1 Replace the EDD-only license and update system

This is the biggest required change.

Current evidence:

- `admin/license-page.php:7-8` hardcodes `https://plugincy.com/` and EDD `item_id = 10817`.
- `admin/license-page.php:378`, `413`, `445`, `494` call EDD actions `activate_license`, `deactivate_license`, `check_license`, and `get_version`.
- `admin/license-page.php:264` and `1083` gate premium features by license validity.
- `multi-location-product-and-inventory-management-pro.php:14938-15125` hooks the plugin into the EDD-backed update/API flow.

Required change:

- Build a CodeCanyon variant that does not depend on an EDD license key.
- Remove or replace the current license screen for the CodeCanyon build.
- Remove or replace EDD updater hooks for the CodeCanyon build.
- Do not require CodeCanyon buyers to visit Plugincy checkout or Plugincy account pages to keep using the plugin.
- If you keep a registration screen, it must accept CodeCanyon purchase verification logic instead of EDD keys.

Important nuance:

- Purchase-code registration is optional.
- EDD registration is not optional if this build is sold on CodeCanyon; it must be removed or replaced.

### 2.2 Stop gating premium functionality behind EDD license status

Current evidence:

- `admin/license-page.php:264` returns premium access only when `mulopimfwc_license_status === 'valid'`.
- `admin/license-page.php:1083` drives `mulopimfwc_premium_feature()`.
- Many premium features across `admin/settings.php`, `includes/`, and the main plugin file depend on `mulopimfwc_premium_feature()`.

Required change:

- For the CodeCanyon build, premium features must work for legitimate CodeCanyon buyers without requiring an EDD license.
- If you add CodeCanyon verification, wire `mulopimfwc_premium_feature()` to that verification state instead.
- Do not disable core paid functionality when Envato support expires; support period and software usage are separate concepts on Envato.

### 2.3 Remove EDD renewal and Plugincy account flows from the buyer UI

Current evidence:

- `admin/license-page.php:651`, `658`, `778`, `886` link to Plugincy EDD renewal URLs.
- `admin/license-page.php:659`, `999` link to Plugincy account pages.
- `admin/license-page.php:798` shows `Activate License & Install Pro`.
- `admin/license-page.php:790` shows `Check for Updates` in an EDD context.

Required change:

- Remove all renewal/account messaging that assumes the buyer purchased on Plugincy.
- Replace all update/account/support text with CodeCanyon-appropriate wording.
- If you keep an external support site, point only to the official support URL chosen in Envato support settings.
- Do not use the CodeCanyon build, documentation, or live preview to steer buyers toward buying the same item on your EDD site.

### 2.4 Remove automatic remote license checks from public page loads

Current evidence:

- `admin/license-page.php:31-69` injects a background license-check script and registers both logged-in and `nopriv` AJAX handlers.
- `admin/license-page.php:97` runs `background_license_check()`.
- `admin/license-page.php:185` runs `background_update_check()`.

Why this matters:

- A CodeCanyon reviewer should not see a paid plugin making hidden EDD license/update requests from frontend visitors.
- This is also a privacy and performance issue.

Required change:

- Remove the frontend background license/update check from the CodeCanyon build.
- If you keep verification, run it only in admin, only for authorized users, and only on explicit action or scheduled admin-side checks.
- Cache verification aggressively and never run purchase validation for anonymous visitors.

### 2.5 Disable telemetry by default, or remove it entirely in the CodeCanyon build

Current evidence:

- `multi-location-product-and-inventory-management-pro.php:1837` defaults `allow_data_share` to `on`.
- `multi-location-product-and-inventory-management-pro.php:14271-14318` initializes analytics and may send tracking automatically.
- `includes/analytics.php:54` sends tracking on activation.
- `includes/analytics.php:86-119` sends tracking data remotely.
- `includes/analytics.php:123-153` sends deactivation data remotely.
- `includes/analytics.php:176-179` includes `other_plugins`, `active_theme`, and `license_key` in the payload.

Required change:

- For CodeCanyon, either remove this telemetry entirely or switch it to explicit opt-in with default `off`.
- If any telemetry remains, document exactly what is sent, when it is sent, where it is sent, and how the admin opts out.
- Never send a stored license key or purchase identifier to your server without clear disclosure and strong justification.

Recommended channel policy:

- EDD build can keep an opt-in telemetry module if you want.
- CodeCanyon build should ship with telemetry disabled by default or absent.

### 2.6 Vendor critical runtime dependencies locally

Current evidence:

- `multi-location-product-and-inventory-management-pro.php:9051-9052` loads Leaflet from `unpkg.com` version `1.7.1`.
- `admin/admin.php:205-206` loads Leaflet from `unpkg.com` version `1.9.4`.
- `includes/frontend-location-information.php:219-220` loads Leaflet from `unpkg.com` version `1.9.4`.

Required change:

- Bundle Leaflet inside the plugin package and load it locally.
- Use one approved version consistently across admin and frontend.
- Do not make the core install depend on a public CDN.

### 2.7 Audit and document all optional third-party services

Current evidence:

- `multi-location-product-and-inventory-management-pro.php:8412` uses Nominatim.
- `admin/admin.php:234` exposes the Nominatim URL to admin JS.
- `admin/admin.php:1131` calls `open.er-api.com`.
- `admin/admin.php:1145` calls `floatrates.com`.
- `multi-location-product-and-inventory-management-pro.php:13541` calls Telegram.
- `multi-location-product-and-inventory-management-pro.php:14273` calls your analytics endpoint.

Required change:

- Treat these as optional integrations, not hidden infrastructure.
- Add buyer documentation that names every external service, what feature depends on it, and whether the service is free/third-party.
- Make sure all such features fail gracefully if the service is unavailable.
- Avoid automatic external requests unless the buyer explicitly enables the related feature.

### 2.8 Add a real uninstall path and a data-retention policy

Current evidence:

- There is no `uninstall.php`.
- No `register_uninstall_hook` was found.
- `multi-location-product-and-inventory-management-pro.php:14718-14731` only handles deactivation cleanup, and only conditionally.

Required change:

- Add an actual uninstall routine.
- Decide whether uninstall deletes all plugin data or respects a "keep data on uninstall" setting.
- Clean up options, transients, cron events, custom tables, and any stored analytics/tracking data only on uninstall, not on ordinary deactivation.

### 2.9 Clean up buyer-facing support and marketing language inside the paid build

Current evidence:

- `multi-location-product-and-inventory-management-pro.php:8810-8844` adds Plugincy support/docs links and a WordPress.org community support link.
- `admin/settings.php:5478` contains `Premium Support`.
- `admin/settings.php:7102` contains `Priority Email Support`.

Required change:

- Remove or neutralize wording that feels like upsell/upgrade messaging inside a paid marketplace build.
- Align all support/help links with the support method you publish in Envato.
- Remove the WordPress.org community support link from the paid build unless you truly intend to support paid buyers there.

### 2.10 Fix text-domain and marketplace metadata inconsistencies

Current evidence:

- Main text domain is `multi-location-product-and-inventory-management`.
- `multi-location-product-and-inventory-management-pro.php:1670` and `admin/license-page.php:77` use `multi-location-product-and-inventory-management-pro`.

Required change:

- Standardize the text domain for all translatable strings.
- Review plugin name, readme, docs, and listing copy so the marketplace version is consistent.

Important CodeCanyon metadata note:

- Envato's Item Information rules blacklist words like `Pro` and `Premium` in item titles/tags.
- The CodeCanyon listing title should therefore be neutral, for example `Multi Location Product & Inventory Management for WooCommerce`.

### 2.11 Separate JS/CSS/HTML more cleanly before review

Current evidence:

- Inline `<script>` blocks exist in many files, including `admin/license-page.php`, `admin/settings.php`, `admin/admin.php`, `includes/analytics.php`, `templates/shortcode-selector.php`, and others.
- Inline event handlers exist in `admin/license-page.php:780`, `786` and multiple places in `admin/settings.php`.

Required change:

- Move as much JS as possible into enqueued asset files.
- Remove inline event handlers and bind them in JS.
- Keep inline JSON-LD only where needed for structured data.

This is not the single largest blocker, but Envato's WordPress Plugin Requirements explicitly say JavaScript, CSS, and HTML should be separated as much as possible.

## 3. Mandatory Packaging Changes

### 3.1 Clean the upload package

Do not ship the following in the CodeCanyon buyer package:

- `.git`
- `.github`
- `.vscode`
- `.cursor`
- `multi-location-product-and-inventory-management-pro.zip` inside the plugin folder
- backup translation artifacts such as `languages/*backup*.po~` and `languages/*backup*.pot~`
- internal engineering notes that are not useful to buyers

Current evidence:

- Root folders `.git`, `.github`, `.vscode`, and `.cursor` exist in the repo.
- A nested plugin ZIP exists at `multi-location-product-and-inventory-management-pro.zip`.
- Backup translation files exist under `languages/`.

### 3.2 Add buyer-facing license files and third-party notices

Current evidence:

- `readme.txt:65` says "See the LICENSE file for more details."
- No root `LICENSE` file exists in this plugin directory.
- Bundled third-party libraries such as Select2 and Chart.js include their own license headers, but there is no root third-party notices file.

Required change:

- Add a root `LICENSE` or `COPYING` file that matches your chosen distribution approach.
- Add a `THIRD-PARTY-LICENSES.md` or equivalent notice file listing all bundled libraries and external services.
- If you choose 100% GPL, verify every shipped third-party asset is compatible.

### 3.3 Add buyer-ready documentation

The current `docs/` folder contains internal engineering notes, not customer documentation.

Required change:

- Add real buyer documentation in HTML, PDF, or a stable hosted documentation URL.
- Include installation, first setup, location creation, pricing/stock setup, import/export, map setup, optional integrations, troubleshooting, update instructions, and uninstall/data retention behavior.
- Include a section specifically for CodeCanyon buyers explaining purchase-code handling if you implement it.

## 4. Listing and Submission Assets You Must Prepare

These are required or practically required at submission time.

### 4.1 Item page metadata

- A title under 100 characters.
- Accurate description of what the plugin does.
- Clear dependency disclosure: WooCommerce is required.
- Supported WordPress, WooCommerce, and PHP versions that you have actually tested.
- Honest list of optional external services and what features rely on them.
- Tags that are accurate and not stuffed with blacklisted marketing words.

### 4.2 Visual assets

- Cover image. Envato recommends `2340 x 1560`; minimum accepted size is `1170 x 780`.
- At least 3 preview images. Envato requires each to be at least `570px` wide.
- Live preview is strongly recommended for WordPress items.

### 4.3 Upload package layout

Prepare the CodeCanyon "Main File(s)" package in a buyer-friendly way:

- installable plugin ZIP,
- documentation,
- license/readme files,
- changelog.

### 4.4 Support configuration in Envato

- Set whether the item is supported.
- If supported, set the support channel exactly once: external URL, comments, or email.
- Set the response-time expectation.

## 5. Documentation and Readme Fixes Inside This Repo

Current `readme.txt` issues:

- `readme.txt:20` has a folder-name typo: `multi-location-product-and-inventory-managements`.
- `readme.txt:55-61` still says the current version is the initial release.
- `readme.txt:65` references a missing `LICENSE` file.
- The readme is much too thin for the actual feature set of this plugin.

Required change:

- Rewrite the readme and buyer docs so they describe the actual current plugin.
- Keep the changelog current.
- Document CodeCanyon-specific update/support behavior.

## 6. Recommended Build Strategy for Selling on EDD and CodeCanyon Together

Recommended approach: one codebase, two distribution builds.

Suggested build targets:

- `edd` build: existing EDD license/update flow, if you still want it.
- `envato` build: no EDD UI, no EDD renewal links, no EDD license gating, no default telemetry, no frontend background validation.

What should become build-time or runtime switches:

- license screen,
- update mechanism,
- support links,
- telemetry module,
- purchase verification logic,
- admin copy that mentions renewal/account/Pro installation.

This is cleaner than trying to make every customer use the same activation flow.

## 7. Final Pre-Submission QA Checklist

Run all of these before uploading:

- Fresh install on a clean WordPress + WooCommerce site.
- Activation with no external account setup.
- All premium features usable in the CodeCanyon build.
- No hidden calls to EDD endpoints.
- No hidden telemetry unless the admin explicitly opted in.
- Update flow works, or manual-update documentation is complete.
- Support links go to the official CodeCanyon support path you selected.
- External integrations are optional and degrade gracefully.
- Uninstall behavior matches documentation.
- Buyer ZIP is clean and contains no repo/dev artifacts.

## 8. Current Blockers Found in This Codebase

Treat these as the first implementation queue:

1. Remove/replace EDD licensing and update hooks.
2. Remove frontend anonymous background license/update checks.
3. Disable or remove default telemetry and stop sending `license_key`, `other_plugins`, and `active_theme`.
4. Replace Plugincy renewal/account messaging in admin UI.
5. Vendor Leaflet locally and standardize the version.
6. Add uninstall flow plus license and third-party notice files.
7. Clean the buyer package.
8. Rewrite buyer docs and marketplace metadata.

## 9. Official Source Notes

Official sources reviewed on March 14, 2026:

- Envato Author Support, `WordPress Plugin Requirements`, updated October 20, 2025.
- Envato Author Support, `Item Presentation Requirements`, updated October 17, 2025.
- Envato Author Support, `Item Information and Metadata Requirements`, updated January 9, 2026.
- Envato Author Support, `How to Upload Your Items to Envato`, updated September 25, 2025.
- Envato Author Support, `Item Support - Best Practices`, updated October 10, 2025.
- Envato Author Support, `Item Support Settings For Authors`, updated October 10, 2025.
- Envato Author Support, `Theme & Plugin Licensing Options`, updated October 16, 2025.
- Envato Author Support, `What is Split Licensing and the GPL?`, updated October 16, 2025.
- Envato Author Support, `Exclusivity Policy`, updated March 28, 2025.
- Envato Author Support, `Item Quality FAQs`, updated October 17, 2025.
- Envato Market Support, `What is Item Support?`, updated March 4, 2026.
- Envato Market Support, `How to download your items`, updated March 4, 2026.
- Envato Market Support, `Do I need a Regular License or an Extended License?`, updated September 10, 2025.
- Envato Build, `Envato API Documentation`, accessed March 14, 2026.

Reference URLs:

- https://help.author.envato.com/hc/en-us/articles/360000510603-WordPress-Plugin-Requirements
- https://help.author.envato.com/hc/en-us/articles/360000424863-Item-Presentation-Requirements
- https://help.author.envato.com/hc/en-us/articles/360000471066-Item-Information-and-Metadata-Requirements
- https://help.author.envato.com/hc/en-us/articles/360000471943-How-to-Upload-Your-Items-to-Envato
- https://help.author.envato.com/hc/en-us/articles/360000471703-Item-Support-Best-Practices
- https://help.author.envato.com/hc/en-us/articles/360000471706-Item-Support-Settings-For-Authors
- https://help.author.envato.com/hc/en-us/articles/360000534626-Theme-Plugin-Licensing-Options
- https://help.author.envato.com/hc/en-us/articles/360000534646-What-is-Split-Licensing-and-the-GPL
- https://help.author.envato.com/hc/en-us/articles/360000471226-Exclusivity-Policy
- https://help.author.envato.com/hc/en-us/articles/360000471663-Item-Quality-FAQs
- https://help.market.envato.com/hc/en-us/articles/208191263
- https://help.market.envato.com/hc/en-us/articles/202501014-How-to-download-your-items
- https://help.market.envato.com/hc/en-us/articles/115005593363-Do-I-need-a-Regular-License-or-an-Extended-License
- https://build.envato.com/api/
