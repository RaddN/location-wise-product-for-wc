# Location-wise Currency: Logical Conflicts & Risk Audit

Date: 2026-03-02  
Plugin: `multi-location-product-and-inventory-management-pro`

## Scope

This audit focuses on logical conflicts introduced (or exposed) by `location_wise_currency`, especially around:

- Product/cart/checkout price display
- Cart/order total calculation paths
- Fees, shipping, coupon, tax interactions
- Order editing/reassignment
- Reports/analytics/import-export/API flows

---

## Findings

### 1) Currency switch is formatting-level; no conversion engine
**Severity:** Critical

The runtime only overrides currency code/position/format hooks, but does not convert numeric amounts.

**Evidence**
- [`multi-location-product-and-inventory-management-pro.php:2896`](../multi-location-product-and-inventory-management-pro.php#L2896)
- [`multi-location-product-and-inventory-management-pro.php:2898`](../multi-location-product-and-inventory-management-pro.php#L2898)
- [`multi-location-product-and-inventory-management-pro.php:2900`](../multi-location-product-and-inventory-management-pro.php#L2900)
- [`multi-location-product-and-inventory-management-pro.php:3340`](../multi-location-product-and-inventory-management-pro.php#L3340)
- [`multi-location-product-and-inventory-management-pro.php:3414`](../multi-location-product-and-inventory-management-pro.php#L3414)

**Conflict**
- If a location uses a different currency but prices/fees are not separately maintained in that currency, displayed totals become mislabeled monetary values.

---

### 2) Product price fallback can relabel base-currency amounts as location currency
**Severity:** Critical

When location-specific prices are missing, logic falls back to default product prices (no conversion).

**Evidence**
- [`includes/stock-price-backorder-manage.php:744`](../includes/stock-price-backorder-manage.php#L744)
- [`includes/stock-price-backorder-manage.php:767`](../includes/stock-price-backorder-manage.php#L767)
- [`includes/stock-price-backorder-manage.php:1287`](../includes/stock-price-backorder-manage.php#L1287)
- [`includes/stock-price-backorder-manage.php:1333`](../includes/stock-price-backorder-manage.php#L1333)

**Conflict**
- A store default USD amount can be shown/charged as EUR/BDT/etc if location currency changes and location price metadata is incomplete.

---

### 3) Non-product amounts are not currency-aware (fees/shipping/coupons/tax rates)
**Severity:** High

Amounts for transfer-fees, shipping method costs, coupon values, and tax rates are not converted per location currency.

**Evidence**
- Transfer fee uses raw float route costs and adds as cart fee:
  - [`multi-location-product-and-inventory-management-pro.php:3809`](../multi-location-product-and-inventory-management-pro.php#L3809)
  - [`multi-location-product-and-inventory-management-pro.php:3922`](../multi-location-product-and-inventory-management-pro.php#L3922)
  - [`multi-location-product-and-inventory-management-pro.php:3728`](../multi-location-product-and-inventory-management-pro.php#L3728)
- Shipping/tax/payment runtime filters only whitelist methods/classes, not amount conversion:
  - [`includes/location-wise-shipping-payment-tax.php:345`](../includes/location-wise-shipping-payment-tax.php#L345)
  - [`includes/location-wise-shipping-payment-tax.php:379`](../includes/location-wise-shipping-payment-tax.php#L379)
  - [`includes/location-wise-shipping-payment-tax.php:400`](../includes/location-wise-shipping-payment-tax.php#L400)
- Coupon module applies location eligibility filters only:
  - [`includes/location-wise-coupons.php:26`](../includes/location-wise-coupons.php#L26)
  - [`includes/location-wise-coupons.php:27`](../includes/location-wise-coupons.php#L27)
  - [`includes/location-wise-coupons.php:28`](../includes/location-wise-coupons.php#L28)

**Conflict**
- Final payable totals can be arithmetically wrong in cross-currency scenarios even when price labels look correct.

---

### 4) Location switch can keep old-location cart lines while global currency switches
**Severity:** Critical

With location-wise currency, `preserve_cart` is forced to `update_cart`, but switch cleanup removes only unavailable items; it does not normalize remaining line-item location metadata.

**Evidence**
- Force behavior change:
  - [`multi-location-product-and-inventory-management-pro.php:8866`](../multi-location-product-and-inventory-management-pro.php#L8866)
  - [`multi-location-product-and-inventory-management-pro.php:11127`](../multi-location-product-and-inventory-management-pro.php#L11127)
- Switch cleanup removes unavailable lines only:
  - [`multi-location-product-and-inventory-management-pro.php:11150`](../multi-location-product-and-inventory-management-pro.php#L11150)
  - [`multi-location-product-and-inventory-management-pro.php:11225`](../multi-location-product-and-inventory-management-pro.php#L11225)
- Frontend switch path relies on that response:
  - [`assets/js/location-selector.js:244`](../assets/js/location-selector.js#L244)
  - [`assets/js/location-selector.js:284`](../assets/js/location-selector.js#L284)
- Cart items retain explicit per-line location metadata:
  - [`multi-location-product-and-inventory-management-pro.php:4477`](../multi-location-product-and-inventory-management-pro.php#L4477)

**Conflict**
- Remaining cart lines may still be priced using previous location logic while the page currency is already switched to new location currency.

---

### 5) Inconsistent price source in cart/checkout (`regular/sale` vs `price`)
**Severity:** High

`regular_price` and `sale_price` filters use current selected location directly, while final `price` uses cart-item runtime location resolver.

**Evidence**
- Current-location based:
  - [`includes/stock-price-backorder-manage.php:744`](../includes/stock-price-backorder-manage.php#L744)
  - [`includes/stock-price-backorder-manage.php:767`](../includes/stock-price-backorder-manage.php#L767)
- Cart-item runtime location resolver:
  - [`includes/stock-price-backorder-manage.php:698`](../includes/stock-price-backorder-manage.php#L698)
  - [`includes/stock-price-backorder-manage.php:1287`](../includes/stock-price-backorder-manage.php#L1287)
  - [`includes/stock-price-backorder-manage.php:1333`](../includes/stock-price-backorder-manage.php#L1333)

**Conflict**
- Strikethrough/sale displays can disagree with charged line totals after location/currency transitions.

---

### 6) Admin order-item location change recalculates amounts but does not change order currency
**Severity:** High

Order item location change updates item subtotal/total and order totals, but currency is not updated.

**Evidence**
- Main order-item update flow:
  - [`multi-location-product-and-inventory-management-pro.php:4786`](../multi-location-product-and-inventory-management-pro.php#L4786)
  - [`multi-location-product-and-inventory-management-pro.php:4955`](../multi-location-product-and-inventory-management-pro.php#L4955)
  - [`multi-location-product-and-inventory-management-pro.php:5003`](../multi-location-product-and-inventory-management-pro.php#L5003)
- Bulk/admin flow has same pattern:
  - [`admin/admin.php:3524`](../admin/admin.php#L3524)
  - [`admin/admin.php:3546`](../admin/admin.php#L3546)
- No `set_currency()` in these admin update flows.

**Conflict**
- Order can end with location-B prices stored under location-A currency code.

---

### 7) Final order location assignment may diverge from currency source
**Severity:** High

Order assignment (manual/inventory/proximity) can set `_store_location` after checkout logic, while currency runtime is resolved from request/cookie/effective runtime candidates.

**Evidence**
- Assignment path:
  - [`multi-location-product-and-inventory-management-pro.php:7238`](../multi-location-product-and-inventory-management-pro.php#L7238)
  - [`multi-location-product-and-inventory-management-pro.php:7289`](../multi-location-product-and-inventory-management-pro.php#L7289)
  - [`multi-location-product-and-inventory-management-pro.php:7316`](../multi-location-product-and-inventory-management-pro.php#L7316)
- Currency runtime location candidates:
  - [`multi-location-product-and-inventory-management-pro.php:3193`](../multi-location-product-and-inventory-management-pro.php#L3193)
  - [`multi-location-product-and-inventory-management-pro.php:3227`](../multi-location-product-and-inventory-management-pro.php#L3227)
  - [`multi-location-product-and-inventory-management-pro.php:3257`](../multi-location-product-and-inventory-management-pro.php#L3257)

**Conflict**
- Assigned fulfillment location can differ from the currency context used during pricing/checkout.

---

### 8) Dashboard/report totals aggregate potentially mixed currencies as one
**Severity:** High

Revenue totals are summed numerically and displayed with a single currency symbol/code.

**Evidence**
- Summation:
  - [`admin/dashboard.php:2213`](../admin/dashboard.php#L2213)
  - [`admin/dashboard.php:2368`](../admin/dashboard.php#L2368)
  - [`admin/dashboard.php:1426`](../admin/dashboard.php#L1426)
- Single currency output:
  - [`admin/dashboard.php:534`](../admin/dashboard.php#L534)
  - [`admin/dashboard.php:664`](../admin/dashboard.php#L664)
  - [`admin/dashboard.php:213`](../admin/dashboard.php#L213)

**Conflict**
- Cross-currency revenue KPIs and exports become financially invalid.

---

### 9) Social digest/alerts also sum and format mixed-currency revenue as one currency
**Severity:** High

Daily digest builds per-location and global revenue summaries with `wc_price(...)` on accumulated floats.

**Evidence**
- Revenue accumulation and formatting:
  - [`multi-location-product-and-inventory-management-pro.php:12952`](../multi-location-product-and-inventory-management-pro.php#L12952)
  - [`multi-location-product-and-inventory-management-pro.php:13006`](../multi-location-product-and-inventory-management-pro.php#L13006)
  - [`multi-location-product-and-inventory-management-pro.php:13291`](../multi-location-product-and-inventory-management-pro.php#L13291)

**Conflict**
- Notifications can present materially wrong revenue values where multiple currencies are involved.

---

### 10) Profitability/investment math mixes purchase-price base values with location sale values
**Severity:** High

Profit calculations rely on `_purchase_price` and location sale/default prices without currency normalization.

**Evidence**
- Revenue/profit core:
  - [`multi-location-product-and-inventory-management-pro.php:12393`](../multi-location-product-and-inventory-management-pro.php#L12393)
  - [`multi-location-product-and-inventory-management-pro.php:12448`](../multi-location-product-and-inventory-management-pro.php#L12448)
- Dashboard profitability:
  - [`admin/dashboard.php:3239`](../admin/dashboard.php#L3239)
  - [`admin/dashboard.php:3300`](../admin/dashboard.php#L3300)
  - [`admin/dashboard.php:3325`](../admin/dashboard.php#L3325)
- Product table profit display:
  - [`includes/class-product-location-table.php:1797`](../includes/class-product-location-table.php#L1797)
  - [`includes/class-product-location-table.php:1801`](../includes/class-product-location-table.php#L1801)

**Conflict**
- Margin and profitability outputs can be meaningless when locations use different currencies.

---

### 11) Import/export and sync API do not carry currency context
**Severity:** Medium

Price columns are raw numbers without `currency_code` per location.

**Evidence**
- API export headers:
  - [`includes/api/inventory-sync-api.php:468`](../includes/api/inventory-sync-api.php#L468)
- API processing price fields:
  - [`includes/api/inventory-sync-api.php:291`](../includes/api/inventory-sync-api.php#L291)
  - [`includes/api/inventory-sync-api.php:297`](../includes/api/inventory-sync-api.php#L297)
- CSV import expected columns:
  - [`admin/import-export-settings.php:533`](../admin/import-export-settings.php#L533)
  - [`admin/import-export-settings.php:639`](../admin/import-export-settings.php#L639)
  - [`admin/import-export-settings.php:643`](../admin/import-export-settings.php#L643)

**Conflict**
- External systems cannot safely interpret imported/exported location prices in multi-currency setups.

---

### 12) Fixed 2-decimal assumptions conflict with zero/three-decimal currencies
**Severity:** Medium

Several reporting queries/formatters hardcode `DECIMAL(10,2)` or `number_format(..., 2)`.

**Evidence**
- SQL casts:
  - [`admin/dashboard.php:2719`](../admin/dashboard.php#L2719)
  - [`admin/dashboard.php:3407`](../admin/dashboard.php#L3407)
- Formatting:
  - [`admin/dashboard.php:534`](../admin/dashboard.php#L534)
  - [`admin/dashboard.php:664`](../admin/dashboard.php#L664)

**Conflict**
- JPY/KRW-like zero-decimal currencies and 3-decimal currencies lose precision or display wrong decimals.

---

### 13) Split-child order currency always inherited from parent (legacy risk)
**Severity:** Medium

Child split orders always get parent currency.

**Evidence**
- [`includes/order-split-by-location.php:779`](../includes/order-split-by-location.php#L779)

**Conflict**
- If split flows are enabled through customization/legacy data while location currencies differ, child order currency can mismatch child location.

---

### 14) Admin runtime currency side-effects for location managers
**Severity:** Medium

Admin non-AJAX requests can force runtime currency for single-assigned location managers, affecting option reads in admin UI/forms.

**Evidence**
- Admin runtime gate:
  - [`multi-location-product-and-inventory-management-pro.php:3166`](../multi-location-product-and-inventory-management-pro.php#L3166)
  - [`multi-location-product-and-inventory-management-pro.php:3347`](../multi-location-product-and-inventory-management-pro.php#L3347)
- Single-assigned manager detection:
  - [`multi-location-product-and-inventory-management-pro.php:3130`](../multi-location-product-and-inventory-management-pro.php#L3130)
- Admin input symbol derived from `get_option('woocommerce_currency')`:
  - [`includes/stock-price-backorder-manage.php:14`](../includes/stock-price-backorder-manage.php#L14)

**Conflict**
- Admin users may unintentionally enter/interpret global values in location currency context.

---

## Existing safeguards (important, but not sufficient)

- Mixed cart/location change/split order are programmatically disabled when location-wise currency is on:
  - [`multi-location-product-and-inventory-management-pro.php:432`](../multi-location-product-and-inventory-management-pro.php#L432)
  - [`multi-location-product-and-inventory-management-pro.php:460`](../multi-location-product-and-inventory-management-pro.php#L460)
  - [`multi-location-product-and-inventory-management-pro.php:520`](../multi-location-product-and-inventory-management-pro.php#L520)
  - [`admin/settings.php:4775`](../admin/settings.php#L4775)
  - [`admin/settings.php:4785`](../admin/settings.php#L4785)
  - [`admin/settings.php:4796`](../admin/settings.php#L4796)

These reduce some conflict surfaces, but they do not solve conversion, reporting, API, and reassignment-currency consistency problems above.

