# Location-wise Currency Conflict & Logical Error Audit

Date: 2026-03-04  
Scope: `multi-location-product-and-inventory-management-pro` plugin (full price-related flow review)

## Goal
Identify conflicts and logical errors that can occur while implementing/using location-wise currency, especially where prices are read, calculated, converted, formatted, saved, or reported.

## Coverage (price touchpoints reviewed)
- Product price runtime hooks (simple, variation, variable ranges)
- Cart/checkout price behavior
- Location switch behavior (global selector + shortcode + product selector)
- Coupon fixed-amount conversion
- Shipping/tax runtime conversion and filtering
- Order split totals display
- Admin order item location-change pricing
- Dashboard revenue/amount aggregation
- Stock Central / product location table display formatting

## Risk Summary
- Critical: 2
- High: 9
- Medium: 7

---

## Findings

### C-01: `wc_price_args` forcibly overrides explicitly provided currency/format
Severity: **Critical**

Scenario:
- Any code calls `wc_price($amount, ['currency' => $order_currency])`.
- Plugin runtime location currency is active.

What happens:
- Filter overwrites caller-provided `currency` and `price_format`.
- Explicit order currency can be replaced by selected location currency.

Evidence:
- `multi-location-product-and-inventory-management-pro.php:3491-3513`
- Explicit currency caller: `includes/order-split-by-location.php:489-507`

Impact:
- Wrong currency display in split order totals, order history, admin screens, reports, emails.

Fix direction:
- Only set `currency` / `price_format` if caller did not provide them.
- Add guard to skip override in order-context rendering (or when explicit args exist).

---

### C-02: `pre_option_woocommerce_currency*` global option override can leak into unrelated flows
Severity: **Critical**

Scenario:
- Runtime currency filters are active.
- Any code/plugin reads `get_option('woocommerce_currency')` or `woocommerce_currency_pos`.

What happens:
- Low-level option reads return runtime location values, not configured store base values.

Evidence:
- Hook registration: `multi-location-product-and-inventory-management-pro.php:2900-2901`
- Override implementations: `multi-location-product-and-inventory-management-pro.php:3548-3596`

Impact:
- Broad compatibility risk with third-party plugins and internal logic expecting stable store defaults.

Fix direction:
- Remove/limit `pre_option_*` overrides; prefer higher-level Woo filters only.
- If kept, scope strictly to frontend display contexts and exclude admin/order/report internals.

---

### C-03: Valid `0` location prices are treated as empty/missing *fixed*
Severity: **High**

Scenario:
- Location sale/regular price intentionally set to `0` (free item/offer).

What happens:
- `!empty(...)` and positive-only checks treat `0` as absent.
- Logic falls back to base/converted/default prices or hides intended free pricing.

Evidence:
- Product price hooks: `includes/stock-price-backorder-manage.php:1418-1427`, `1465-1474`, `1504-1524`
- Admin/location display: `includes/stock-price-backorder-manage.php:1745-1751`, `1897-1903`, `1994-2000`
- Admin order item change: `multi-location-product-and-inventory-management-pro.php:4943-4946`
- Product table positive-only checks: `includes/class-product-location-table.php:1414-1423`, `1463-1472`, `1519-1522`, `1568-1575`, `1781-1791`

Impact:
- Free location pricing cannot be represented reliably.
- Price inconsistency between intended configuration and runtime.

Fix direction:
- Treat `''` / `null` as missing, but keep numeric `0` as valid.
- Replace `!empty($price)` with explicit numeric/null checks.

---

### C-04: Admin order item location-change fallback price can use wrong location context *fixed*
Severity: **High**

Scenario:
- Changing an order item location in admin where new location has no location-specific price.

What happens:
- Fallback uses `$product_obj->get_sale_price()/get_regular_price()`, which are runtime-filtered by current selected location context.
- Can pull price from unrelated location context.

Evidence:
- `multi-location-product-and-inventory-management-pro.php:4948-4955`

Impact:
- Wrong line subtotal/total after location change in order admin.

Fix direction:
- Read raw product meta (`_sale_price`, `_regular_price`) or use base-unfiltered read for fallback.
- Avoid context-sensitive getters in this admin mutation path.

---

### C-05: `mulopimfwc_get_currency_settings_for_location()` default cache can be tainted by runtime override
Severity: **High**

Scenario:
- First call initializes defaults while runtime `pre_option` currency override is active.

What happens:
- Static defaults cache stores runtime currency as "default".

Evidence:
- Default initialization via `get_option`: `includes/stock-price-backorder-manage.php:2081-2088`
- Runtime pre-option hooks: `multi-location-product-and-inventory-management-pro.php:3548-3596`

Impact:
- Incorrect defaults for downstream fallbacks in same request.

Fix direction:
- Build defaults from raw DB source (same strategy as `mulopimfwc_get_store_base_currency_code_raw`).
- Keep cached defaults isolated from runtime display filters.

---

### C-06: Missing/invalid rate silently falls back to `1.0` and may still mark conversion active
Severity: **High**

Scenario:
- Location currency differs from base, but no valid `location_currency_rate` stored.

What happens:
- `rate` defaults to `1.0`; `should_convert` can still become `true` for mismatched currencies.
- Amount appears converted (actually unchanged 1:1).

Evidence:
- Conversion context logic: `includes/stock-price-backorder-manage.php:2244-2255`

Impact:
- Hidden pricing inaccuracies with no explicit warning.

Fix direction:
- Require explicit positive configured rate when currencies differ.
- If missing rate, disable conversion and log/admin-notice configuration error.

---

### C-07: Location-wise currency can be enabled while location pricing is disabled *fixed*
Severity: **High**

Scenario:
- `location_wise_currency = on` and `enable_location_price = off`.

What happens:
- Currency symbol/code switches runtime.
- Core product price filters do not convert product numbers.

Evidence:
- Product price conversion gated by `enable_location_price`: `includes/stock-price-backorder-manage.php:840-843`, `866-869`, `1389-1393`, `1483-1486`
- Currency hooks independent of `enable_location_price`: `multi-location-product-and-inventory-management-pro.php:2896-2901`
- Settings sanitizer does not force `enable_location_price` when currency enabled: `admin/settings.php:4800-4810`

Impact:
- Symbol/format in location currency with numeric values still effectively base-currency numbers.

Fix direction:
- Enforce/auto-enable location price when location-wise currency is enabled.
- Or block saving incompatible combination and show admin warning.

---

### C-08: `all-products` runtime currency empty state can desync symbol/format vs line-item conversion contexts
Severity: **High**

Scenario:
- Selected location is `all-products`.
- Cart still contains location-bound items.

What happens:
- Runtime currency settings intentionally return empty for `all-products`.
- Item-level location resolution in cart/checkout still uses cart item location.

Evidence:
- Runtime currency returns empty on `all-products`: `multi-location-product-and-inventory-management-pro.php:3383-3390`
- Item runtime location from cart items: `includes/stock-price-backorder-manage.php:794-803`
- Price hooks using runtime item location: `includes/stock-price-backorder-manage.php:1406-1411`, `1453-1458`, `1490-1495`

Impact:
- Inconsistent display/amount contexts in edge flows (especially legacy carts/session states).

Fix direction:
- Define strict behavior for `all-products` when currency mode is on (e.g., disallow for price-bearing contexts or normalize to one currency context).

---

### C-09: Product selector script nonce mismatch can break location switch AJAX
Severity: **High**

Scenario:
- Product page selector uses `location-selector.js` switch endpoint.

What happens:
- Product-page enqueue sets nonce for `mulopimfwc_change_location_nonce`.
- JS calls `mulopimfwc_switch_location`, server verifies different nonce action (`multi-location-product-and-inventory-management`).

Evidence:
- Product selector nonce (old action): `includes/product-location-selector-single.php:175`, `204`, `69`, `802`
- JS AJAX action: `assets/js/location-selector.js:275-277`
- Server nonce expectation: `multi-location-product-and-inventory-management-pro.php:11140`
- Shortcode enqueue already switched to new nonce: `includes/product-location-selector-single.php:1044-1049`, `1077`

Impact:
- Location switch can fail on product-page selector path.

Fix direction:
- Unify nonce action for all selectors using `mulopimfwc_switch_location`.
- Remove legacy nonce in product selector enqueue path.

---

### C-10: Product selector config does not coerce `preserve_cart` to `update_cart` in currency mode
Severity: **Medium**

Scenario:
- `location_wise_currency = on`, saved `location_switching_behavior = preserve_cart`.
- Product selector uses localized config directly.

What happens:
- Main script localizer coerces preserve -> update in currency mode.
- Product selector localizer keeps raw saved behavior.

Evidence:
- Coercion in main localizer: `multi-location-product-and-inventory-management-pro.php:8895-8900`
- Product selector uses raw option: `includes/product-location-selector-single.php:184`, `1057`

Impact:
- Frontend behavior inconsistency depending on selector entry point.

Fix direction:
- Apply same coercion helper in all script-localization paths.

---

### C-11: Shortcode switch flow still clears full cart instead of removing unavailable items
Severity: **Medium**

Scenario:
- Non-mixed cart with cart update enabled.

What happens:
- Shortcode script path uses `clear_cart` action and reloads.
- Main switch endpoint supports targeted removal (`remove_unavailable_cart_items`).

Evidence:
- Full clear in shortcode script: `assets/js/script.js:264-285`, `432-458`
- Targeted removal exists server-side: `multi-location-product-and-inventory-management-pro.php:11181-11183`, `11257`

Impact:
- Over-destructive cart behavior; possible loss of valid items.

Fix direction:
- Route shortcode flow to `mulopimfwc_switch_location` and use removed-items response.

---

### C-12: Cart item location resolver returns first match and can be ambiguous in legacy mixed states
Severity: **Medium**

Scenario:
- Same product/variation appears with multiple location entries in cart (legacy/session edge cases).

What happens:
- Resolver loops cart and returns first matching item location.

Evidence:
- `includes/stock-price-backorder-manage.php:703-734`

Impact:
- Nondeterministic stock/price context resolution for affected carts.

Fix direction:
- Resolve by cart item key where possible; avoid product/variation-only matching.

---

### C-13: Coupon conversion covers fixed discount amount but not spend-threshold normalization *fixed*
Severity: **Medium**

Scenario:
- Fixed coupon amount converted to runtime currency.
- Coupon min/max spend remains base semantics (Woo fields), with no plugin normalization.

What happens:
- Discount value can be in location currency while threshold logic remains unconverted.

Evidence:
- Coupon amount conversion hook: `includes/location-wise-coupons.php:16-17`, `46-67`
- No plugin-side min/max spend conversion logic found in coupon module.

Impact:
- Coupon eligibility mismatch by currency context.

Fix direction:
- Add min/max spend conversion filters in same runtime context as coupon amount conversion.

---

### C-14: Dashboard revenue totals aggregate cross-currency orders without normalization
Severity: **High**

Scenario:
- Orders exist in multiple currencies.

What happens:
- Revenue sums are aggregated directly into one number and formatted once.

Evidence:
- Aggregation logic: `admin/dashboard.php:2250-2254`, `2367-2371`
- Revenue display: `admin/dashboard.php:1426`
- Revenue calc function has no currency normalization: `multi-location-product-and-inventory-management-pro.php:12403-12493`

Impact:
- Financial metrics become mathematically invalid across currencies.

Fix direction:
- Normalize to reporting base currency before aggregation, or group/label by currency.

---

### C-15: Order/split totals formatted with order currency can still be overridden by runtime price args
Severity: **High**

Scenario:
- Split order UI correctly passes `['currency' => $order->get_currency()]`.

What happens:
- Global `wc_price_args` override can replace provided currency.

Evidence:
- Split totals caller: `includes/order-split-by-location.php:489-507`
- Override behavior: `multi-location-product-and-inventory-management-pro.php:3491-3513`

Impact:
- Customer sees totals with runtime location currency instead of actual order currency.

Fix direction:
- Same as C-01; never override explicit caller currency.

---

### C-16: Admin runtime currency activation is broad (`location`, `location_filter`, etc.)
Severity: **Medium**

Scenario:
- Admin non-AJAX requests include generic keys in `$_REQUEST`.

What happens:
- Currency runtime turns on for admin request even when context may not be pricing-specific.

Evidence:
- Key checks: `multi-location-product-and-inventory-management-pro.php:3166-3187`
- Admin skip gate: `multi-location-product-and-inventory-management-pro.php:3347-3352`

Impact:
- Unexpected admin-side currency-scoped display in unrelated screens/queries.

Fix direction:
- Limit activation to explicit known admin pages/actions instead of generic request key presence.

---

### C-17: Two different shipping-rate filters can conflict (different data sources + priorities)
Severity: **Medium**

Scenario:
- Location shipping enabled with per-location mode.

What happens:
- `includes/location-wise-shipping-payment-tax.php` filters by term meta `shipping_methods` at priority 50.
- `includes/location-based-shipping.php` filters again by option `mulopimfwc_shipping_method_locations_*` at priority 100.

Evidence:
- Runtime filter: `includes/location-wise-shipping-payment-tax.php:473-500`, `386-403`, `305-310`
- Legacy filter: `includes/location-based-shipping.php:306-347`, `288-290`, `36`

Impact:
- Divergent allowlists can remove rates unexpectedly.
- Hard-to-debug shipping availability differences by admin configuration path.

Fix direction:
- Consolidate to one shipping filtering source-of-truth and one hook path.

---

### C-18: Shipping amounts are blindly converted; currency-aware shipping calculators may get double-converted
Severity: **Medium** (compatibility risk)

Scenario:
- Third-party shipping method already computes cost in active runtime currency.
- Plugin conversion still runs on all package rates when `should_convert = true`.

What happens:
- Cost/tax may be multiplied again.

Evidence:
- Unconditional conversion loop: `includes/location-wise-shipping-payment-tax.php:511-577`
- Currency hooks globally active: `multi-location-product-and-inventory-management-pro.php:2896-2901`

Impact:
- Overstated shipping totals in some integrations.

Fix direction:
- Add integration guard/flag per rate or method; convert only when source amount is base currency.

---

## Option-Combination Scenario Matrix (price-related)

### S-01
Options:
- `location_wise_currency = on`
- `enable_location_price = on`
- specific location selected

Expected:
- Consistent converted amounts + symbols.

Observed risks:
- C-01, C-02, C-03, C-06, C-09, C-15

### S-02
Options:
- `location_wise_currency = on`
- `enable_location_price = off`

Expected:
- Either blocked config, or no currency switch.

Observed risks:
- C-07 (symbol changes while product numeric price path not aligned)

### S-03
Options:
- `location_wise_currency = on`
- selected location = `all-products`

Expected:
- Defined stable currency behavior.

Observed risks:
- C-08 (runtime empty currency settings vs item-level location contexts)

### S-04
Options:
- Currency mode on + product page selector usage

Expected:
- Seamless switch + cart update behavior.

Observed risks:
- C-09, C-10

### S-05
Options:
- Admin order item location change
- New location has no location-specific price

Expected:
- Fallback price from correct deterministic source.

Observed risks:
- C-04

### S-06
Options:
- Location price set to `0`

Expected:
- Treated as valid price.

Observed risks:
- C-03

### S-07
Options:
- Split order/customer order totals rendering

Expected:
- Use actual order currency.

Observed risks:
- C-01, C-15

### S-08
Options:
- Multi-currency historical orders + dashboard reports

Expected:
- Normalized or currency-separated reporting.

Observed risks:
- C-14

---

## Priority Fix Order
1. C-01 + C-15: stop overriding explicit `wc_price` args.
2. C-02 + C-05: reduce global option override side effects.
3. C-03: fix zero-price semantics.
4. C-07 + C-08: enforce valid currency/price option combinations and `all-products` rules.
5. C-09 + C-10 + C-11: unify location switch frontend behavior and nonce usage.
6. C-14: normalize reporting currency before aggregation.
7. C-17 + C-18: unify shipping filter pipeline and add conversion guards.

---

## Notes
- Mixed cart and cart-item location-change are correctly disabled when currency mode is enabled via helper/sanitizer logic (`multi-location-product-and-inventory-management-pro.php:432-437`, `460-465`, `520-524`; `admin/settings.php:4813-4832`).
- `ajax_switch_location` already coerces `preserve_cart` to `update_cart` server-side (`multi-location-product-and-inventory-management-pro.php:11159-11163`), but frontend config paths are not fully aligned (C-10).
