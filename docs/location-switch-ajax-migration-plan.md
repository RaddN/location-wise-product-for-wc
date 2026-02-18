# AJAX Location Switching Migration Plan

## 1. Objective
Move location switching from full page reload to AJAX-first content refresh while keeping a safe fallback to current reload behavior.

This must work across:
- WooCommerce classic templates
- WooCommerce blocks (cart/checkout/mini-cart)
- Theme builders (Elementor, custom templates)
- All existing selector entry points in this plugin (popup layouts, shortcode selector, single-product selector, cart selector)

## 2. Current blockers (where full reload is hardcoded)
Full reload is currently triggered in multiple places:
- `assets/js/script.js`
- `assets/js/location-selector.js`
- `assets/js/classic-popup.js`
- `assets/js/modern-popup.js`
- `assets/js/popup-layouts.js`
- `assets/js/cart-location-change.js`
- `assets/js/recommendations.js`

Also, location switching logic is duplicated across endpoints:
- `multi-location-product-and-inventory-management-pro.php` (`mulopimfwc_switch_location`)
- `includes/product-location-selector-single.php` (`mulopimfwc_change_product_location`)

## 3. Recommended architecture (theme-safe)
Use a centralized two-step flow:
1. AJAX switch call to update server state and cookie.
2. AJAX content refresh for the current page context (without full reload).

If any refresh step fails, fallback to current full reload for reliability.

### 3.1 Single switch orchestrator (new JS module)
Add a shared module, e.g. `assets/js/location-switch-manager.js`, and route all UI entry points through it.

Required behavior:
- Accept `location`, `source`, `context`.
- Prevent duplicate/concurrent switches.
- Cancel previous in-flight refresh request if user switches again quickly.
- Emit events:
  - `mulopimfwc:location-switch:start`
  - `mulopimfwc:location-switch:success`
  - `mulopimfwc:location-switch:error`
  - `mulopimfwc:location-changed`

### 3.2 Page refresh adapters (AJAX mode)
Add a refresh layer, e.g. `assets/js/location-page-refresh.js`, with adapters by context:

- Shop/archive/search/tag:
  - Refresh `.woocommerce-result-count`, product loop wrapper (`ul.products` or equivalent), pagination.
  - Re-init plugin/theme scripts after replacement.

- Single product:
  - Refresh summary region (price, stock, add-to-cart form, variation block, location selector area).
  - Re-init Woo variation scripts.

- Cart/checkout (classic):
  - Trigger Woo events: `update_checkout`, `wc_fragment_refresh`, `updated_wc_div`.
  - If totals/items are still stale, fetch and replace cart/checkout region.

- Cart/checkout (blocks):
  - Invalidate Store API data via `wp.data.dispatch('wc/store/cart')` when available.
  - Re-run grouping/injection scripts after state update.

- Mini cart/widgets/recommendations:
  - Trigger refresh events first.
  - Replace specific wrapper only if events are insufficient.

- Unknown theme structure:
  - Use fallback target discovery (`main`, `#primary`, `.site-main`, `.content-area`).
  - If target discovery fails, fallback to full reload.

### 3.3 URL and cache safety
For cache-heavy sites, AJAX refresh must request location-aware HTML.

Required:
- Build refresh URL with `mulopim_loc=<slug>` in AJAX mode.
- Update browser URL with `history.replaceState` in AJAX mode (no reload).
- Keep current `includes/class-mulopimfwc-cache-compat.php` behavior compatible with partial refresh.

## 4. New setting (switch between AJAX and current mode)
Add a new setting in User Experience section:
- Key: `location_change_render_mode`
- Values:
  - `reload` (default, current behavior)
  - `ajax`

Required files:
- `admin/settings.php`
  - Add field UI under Location Customer Experience section.
  - Add sanitization and allowed values.
- `assets/js/admin.js`
  - Include new field in manual-mode disable/hidden handling only if needed.
- `multi-location-product-and-inventory-management-pro.php`
  - Include setting in localized frontend config.
- Default options fallback locations:
  - `multi-location-product-and-inventory-management-pro.php`
  - Any helper that builds default settings arrays.

## 5. Backend changes required
### 5.1 Consolidate location-switch server contract
Primary endpoint should be `mulopimfwc_switch_location`.

Required response contract additions:
- `location`
- `removed_items`
- `removed_count`
- `behavior`
- `allow_mixed`
- `needs_cart_refresh`
- `needs_checkout_refresh`
- `location_url` (current URL with `mulopim_loc`)

### 5.2 Align nonce usage
Unify frontend nonce usage for switch calls.

Required review points:
- `multi-location-product-and-inventory-management-pro.php` localized nonces.
- `includes/product-location-selector-single.php` uses a different nonce/action set currently.
- Validate location endpoint nonce mismatch (`mulopimfwc_validate_location`) should be corrected or removed from client flow.

### 5.3 Keep no-JS fallback intact
Current full-reload path must remain available:
- explicit setting `reload`
- automatic fallback after AJAX failure

## 6. Frontend refactor required
Update these files to call shared switch orchestrator instead of setting cookie + reload directly:
- `assets/js/script.js`
- `assets/js/location-selector.js`
- `assets/js/classic-popup.js`
- `assets/js/modern-popup.js`
- `assets/js/popup-layouts.js`
- `assets/js/cart-location-change.js`
- `assets/js/recommendations.js`

Key requirements per file:
- Remove direct `location.reload()` and `window.location.href = ...` for location change paths.
- Use unified events so dependent modules (recommendations/filter/widgets) update after switch.
- Keep per-file fallback to full reload if adapter signals failure.

## 7. Loading UX (beautiful + accessible)
Add a unified loading system:
- Global transition overlay for location switching.
- Optional section skeletons where DOM targets are known (product cards, summary blocks, cart rows).
- Disable interaction during transition.
- Accessibility:
  - `aria-busy=true` on refresh region
  - live status text (`role=status`, `aria-live=polite`)

Required files:
- New: `assets/js/location-loading-ui.js` (or merge into switch manager)
- `assets/css/style.css` for overlay/skeleton animation
- Localized text keys for loading/error states

## 8. Theme compatibility guardrails
For all-theme support, do not depend on a single selector.

Required strategy:
- Selector priority list: plugin selectors -> WooCommerce standard selectors -> generic main-content selectors.
- Extensibility filters/hooks:
  - PHP filter for default selector map.
  - JS extension point/event after DOM replace for third-party re-init.
- Hard fallback to full reload when:
  - no safe target found
  - replacement result is empty
  - critical Woo hooks are missing

## 9. Situations that must be handled
- User selects same location again (no-op, no refresh).
- User rapidly changes location multiple times.
- Cart has unavailable items and behavior is `update_cart` or `prompt_user`.
- Mixed cart enabled (`allow_mixed_location_cart=on`) and no cart cleanup needed.
- `all-products` pseudo location.
- Popup opened from shortcode instance vs global modal.
- Single product with variation pricing/stock changes.
- Shop/archive with pagination and sorting active.
- Cart/checkout blocks and classic templates.
- Cached pages/CDN returning stale HTML unless query/cookie vary is respected.
- Network failure, nonce expiry, invalid location slug.

## 10. File-level change list
### Core PHP
- `multi-location-product-and-inventory-management-pro.php`
  - Extend localized config.
  - Extend switch endpoint payload.
  - Keep reload fallback route.
- `includes/product-location-selector-single.php`
  - Route to unified switch behavior and nonce contract.
- `includes/class-mulopimfwc-cache-compat.php`
  - Ensure AJAX-mode URL strategy remains cache-safe.

### Settings/Admin
- `admin/settings.php`
  - Add `location_change_render_mode` field.
  - Add sanitize handling.
- `assets/js/admin.js`
  - Add dependency handling for the new setting only where necessary.

### Frontend JS
- New: `assets/js/location-switch-manager.js`
- New: `assets/js/location-page-refresh.js`
- New: `assets/js/location-loading-ui.js`
- Refactor callers:
  - `assets/js/script.js`
  - `assets/js/location-selector.js`
  - `assets/js/classic-popup.js`
  - `assets/js/modern-popup.js`
  - `assets/js/popup-layouts.js`
  - `assets/js/cart-location-change.js`
  - `assets/js/recommendations.js`

### Frontend CSS
- `assets/css/style.css`
  - Overlay, spinner, skeleton styles
  - Reduced-motion safe animations

## 11. QA checklist (must pass before release)
- Switch mode = `reload`: behavior matches current plugin exactly.
- Switch mode = `ajax`: no full page reload in normal cases.
- Works on:
  - shop/category/tag/search
  - single product (simple + variable)
  - cart + checkout (classic + blocks)
  - mini-cart
  - pages using shortcode selector
  - all popup layouts
- Cache plugin smoke tests:
  - LiteSpeed
  - WP Rocket
  - W3TC
- Failure tests:
  - nonce failure
  - network timeout
  - missing target selectors
  - stale cached response
- Accessibility checks:
  - keyboard lock/unlock during loading
  - screen-reader status updates

## 12. Delivery approach (safe rollout)
- Phase 1: add setting and shared switch manager with reload fallback always enabled.
- Phase 2: enable AJAX adapters for shop/single/cart/checkout.
- Phase 3: optimize loaders and third-party re-init hooks, then widen compatibility matrix.

This phased approach reduces regression risk while delivering the requested AJAX UX.
