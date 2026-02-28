# Location-wise Currency Feature Plan

## 1. Goal
Add **location-wise currency** so when customer location changes, WooCommerce currency changes automatically and consistently across:

- Product price display
- Cart/checkout totals
- Order currency at checkout time
- Location-specific pricing logic already in this plugin

---

## 2. What Established Plugins/Common Implementations Do

### 2.1 WooCommerce core hooks used by currency plugins
Most currency plugins rely on WooCommerce filters/functions instead of hard-coding template replacements:

- `get_woocommerce_currency()` is filter-driven via `woocommerce_currency`.
- `wc_price()` supports argument-level filtering via `wc_price_args`.
- Price formatting pipeline also exposes filters like `raw_woocommerce_price` and `formatted_woocommerce_price`.

This is the correct foundation for this feature (hook-based, not template-only).

### 2.2 WooCommerce Multi-Currency (official extension pattern)
Common behavior:

- Auto-switch currency by customer geolocation/country.
- Persist preference using cookie for guests.
- Persist preference in user profile for logged-in users.

### 2.3 WooPayments Multi-Currency (official)
Common behavior:

- Define country-to-currency mapping.
- Choose auto-switch by customer location or manual switch only.

### 2.4 Currency Switcher for WooCommerce (community plugin pattern)
Common behavior:

- Currency state storage in session/cookie.
- Checkout control options (example: revert/lock behavior on checkout).

### 2.5 WOOCS implementation pattern (code-level)
Common behavior:

- Hooks into `woocommerce_currency`.
- Hooks into price formatting filters.
- Stores selected currency in session/cookie.
- Updates checkout currency explicitly.

## Key takeaway for our plugin
Professional solutions use:

1. A single server-side currency resolver.
2. Cookie/session persistence for deterministic frontend behavior.
3. Checkout/order currency locking rules.
4. Guardrails for cart/checkout state changes.

---

## 3. Current Plugin Reality (Gap Analysis)

### 3.1 Good existing foundation

- Central location cookie helpers already exist:
  - `mulopimfwc_get_store_location_cookie()`
  - `mulopimfwc_set_location_cookie()`
  - `mulopimfwc_clear_location_cookie()`
- Location switch actions already exist:
  - `mulopimfwc_switch_location` (main AJAX switch)
  - `mulopimfwc_change_product_location` (single selector endpoint)
- Action hook already exists after selection:
  - `do_action('mulopimfwc_location_selected', $location, $location_obj);`
- Location-specific pricing already exists via product price filters in `includes/stock-price-backorder-manage.php`.

### 3.2 Important constraints we must design for

- WooCommerce cart supports **one active currency** at a time.
- This plugin supports mixed-location cart (`allow_mixed_location_cart`) where items can have different locations.
- Split orders currently inherit parent order currency (`includes/order-split-by-location.php`).

That means location-wise currency and mixed-location cart can conflict unless rules are explicit.

---

## 4. Recommended Solution (Professional Architecture)

### 4.1 Data model
Add per-location term meta:

- `currency_code` (string, ISO 4217, e.g. `USD`, `EUR`, `BDT`)

Optional later:

- `currency_source` (`manual`/`inherited`)

### 4.2 Resolver
Add centralized resolver helpers (new include file recommended: `includes/location-wise-currency.php`):

- `mulopimfwc_get_location_currency(string $location_slug): string`
- `mulopimfwc_get_effective_currency_for_request(): string`
- `mulopimfwc_validate_currency_code(string $code): bool`

Fallback order:

1. Current selected location currency.
2. Parent location currency (if taxonomy hierarchy and child unset).
3. Store default WooCommerce currency (`get_option('woocommerce_currency')`).

### 4.3 WooCommerce currency hook
Register:

- `add_filter('woocommerce_currency', 'mulopimfwc_filter_woocommerce_currency', <priority>)`

Behavior:

- On frontend and AJAX/store contexts, return resolved location currency.
- In admin non-AJAX contexts, keep default unless explicitly needed.
- Never accept raw user-submitted currency; derive from trusted location meta only.

### 4.4 Location switch behavior
When location changes:

1. Resolve new location currency.
2. Force cart totals recalculation in the same request when possible.
3. Return currency info in AJAX response for frontend consistency.

Extend switch responses with:

- `currency_code`
- `currency_symbol`
- `currency_changed` (boolean)

### 4.5 Checkout/order locking rule
Set order currency at checkout from resolved currency (explicitly, not only implicitly):

- Hook `woocommerce_checkout_create_order` and call `$order->set_currency(...)`.

Also save audit meta:

- `_mulopimfwc_location_currency`
- `_mulopimfwc_location_currency_source`

---

## 5. Required Product Decisions (Must Finalize Before Coding)

### 5.1 Mixed cart policy (critical)
Because cart currency is single, choose one policy:

1. **Recommended:** Block/disable mixed-location cart when location-wise currency is enabled.
2. Allow mixed cart but force one currency from currently selected location (can be confusing).

Recommendation: policy 1 for correctness and predictable accounting.

### 5.2 Missing location price for non-default currency
If a location currency is different but product has no location-specific price:

1. **Recommended:** use strict mode and require location price.
2. Optional advanced mode: auto-convert base price using exchange rate provider.
3. Legacy fallback: use base price as-is (not recommended).

Recommendation: strict mode default.

### 5.3 Split order behavior
Current split-child orders copy parent currency.
Keep this behavior in Phase 1 for accounting consistency.

---

## 6. Exact Changes Needed (File-by-File)

### 6.1 New file
- `includes/location-wise-currency.php`
  - Resolver functions.
  - Currency validation.
  - WooCommerce currency filter registration.
  - Checkout currency lock + order meta audit.

### 6.2 Bootstrap/loading
- `multi-location-product-and-inventory-management-pro.php`
  - `require_once` new currency include.
  - Initialize currency feature with plugin init.
  - Extend localized frontend config (`mulopimfwc_locationWiseProducts`) with:
    - `currentCurrency`
    - `locationCurrencyEnabled`
    - mixed-cart/currency policy flags

### 6.3 Location term admin UI
- `admin/admin.php`
  - `add_location_fields()` add "Currency" select.
  - `edit_location_fields()` show existing selected currency.
  - `save_location_fields()` validate and save `currency_code`.
  - Optional: add taxonomy column "Currency".

### 6.4 Settings and sanitization
- `admin/settings.php`
  - Add settings:
    - `enable_location_currency`
    - `location_currency_mixed_cart_policy`
    - `location_currency_missing_price_policy`
    - optional `lock_currency_on_checkout`
  - Update `sanitize_settings()` so these values persist.

### 6.5 Location switch endpoints
- `multi-location-product-and-inventory-management-pro.php` (`ajax_switch_location`)
  - Resolve and include currency fields in response.
  - Recalculate totals when currency changed.
- `includes/product-location-selector-single.php` (`handle_location_change`)
  - Return the same currency payload shape.

### 6.6 Cart and frontend refresh paths
- `assets/js/location-selector.js`
- `assets/js/script.js`
- `assets/js/classic-popup.js`
- `assets/js/modern-popup.js`
- `assets/js/popup-layouts.js`
  - On successful switch, consume response `currency_code`.
  - Trigger refresh events/fragments where no hard reload.
  - Keep reload fallback.

### 6.7 API/export compatibility
- `includes/api/inventory-sync-api.php`
  - Extend `/locations` payload with `currency_code`.
- `admin/import-export-settings.php`
  - Include location currency in export payload for locations.
  - Support import validation for location currency mappings.

### 6.8 Cache compatibility
- `includes/class-mulopimfwc-cache-compat.php`
  - If currency is always derived from location cookie, current vary strategy is mostly sufficient.
  - Add optional debug header including resolved currency for diagnostics.

---

## 7. Validation Rules

- Currency code must exist in `get_woocommerce_currencies()`.
- Reject invalid currency code save/import.
- If location deleted or currency removed, fallback safely to store default.
- Never switch currency from client-provided currency param.

---

## 8. QA Checklist

- Location change updates currency on:
  - Shop/archive
  - Single product
  - Cart
  - Checkout
- Order created with expected currency and audit meta.
- Changing location when cart has items follows selected policy.
- Location-specific prices + new currency display correctly.
- Split order child currencies remain consistent with parent (Phase 1).
- No regression in:
  - location-based stock
  - shipping/payment/tax location rules
  - cache vary behavior

---

## 9. Rollout Plan

### Phase 1 (safe MVP)

- Add location currency term meta + resolver + `woocommerce_currency` filter.
- Checkout order currency lock.
- Enforce mixed-cart guardrail.
- Response payload updates for switch endpoints.

### Phase 2

- Extend import/export + API payloads.
- Add admin column and enhanced diagnostics.
- Improve no-reload JS currency refresh behavior.

### Phase 3 (optional advanced)

- Automatic FX conversion mode for missing location prices.
- Third-party currency plugin interoperability adapters (WOOCS/WPML/Aelia/etc.).

---

## 10. External References Used

- WooCommerce core `get_woocommerce_currency()` and currency filter:
  - https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/plugins/woocommerce/includes/wc-core-functions.php
- WooCommerce core `wc_price()` filter points:
  - https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/plugins/woocommerce/includes/wc-formatting-functions.php
- WooCommerce Multi-Currency geolocation behavior:
  - https://woocommerce.com/document/multi-currency-geolocation/
- WooPayments Multi-Currency setup (country-based/auto behavior):
  - https://woocommerce.com/document/woocommerce-payments-multi-currency-setup/
- Currency Switcher for WooCommerce readme (checkout/storage behavior):
  - https://plugins.trac.wordpress.org/browser/currency-switcher-woocommerce/trunk/readme.txt?format=txt
- WOOCS source pattern (`woocommerce_currency`, storage, checkout handling):
  - https://raw.githubusercontent.com/wp-plugins/woocommerce-currency-switcher/master/index.php
