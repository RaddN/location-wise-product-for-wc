# AI Coding Instructions for Multi Location Product & Inventory Management Pro

## Overview
This is a WordPress plugin that extends WooCommerce to manage products and inventory across multiple store locations. It allows location-specific pricing, stock, shipping, and customer filtering.

## Architecture
- **Main Class**: `mulopimfwc_Location_Wise_Products` in main plugin file - handles core filtering and location logic.
- **Admin Classes**: Located in `admin/` - e.g., `MULOPIMFWC_Admin`, `MULOPIMFWC_Location_Managers` for backend management.
- **Includes**: Feature-specific modules in `includes/` like `location-based-shipping.php`, `stock-price-backorder-manage.php`.
- **Data Storage**: Uses WordPress taxonomies (`mulopimfwc_store_location`), post meta for product location data, user meta for manager assignments.
- **Frontend**: Assets in `assets/`, templates in `templates/`, shortcodes for location selectors.

## Key Patterns
- **Prefix**: All functions, classes, hooks use `mulopimfwc_` prefix.
- **AJAX**: Actions prefixed `wp_ajax_mulopimfwc_*`, with nonces for security (e.g., `mulopimfwc_location_managers_nonce`).
- **Hooks**: Extensive use of WooCommerce filters like `woocommerce_product_query` to filter products by location.
- **Pro Features**: Use `mulopimfwc_get_pro_class()` to wrap pro-only elements (e.g., `mulopimfwc_get_pro_class(false, 'pro-feature', 'free-class')`).
- **Location Logic**: Check user location via cookies (`mulopimfwc_user_location`), filter queries accordingly.
- **Manager Roles**: Custom role `mulopimfwc_location_manager` with capabilities restricted to assigned locations.

## Workflows
- **Development**: Edit PHP files directly; no build process. Test in WordPress environment with WooCommerce.
- **Debugging**: Use `error_log()` or `var_dump()`; check WP debug logs. AJAX responses logged via `wp_send_json_error/success`.
- **Testing**: Manual testing in WP admin/frontend. No automated tests evident; use WP testing framework if needed.
- **Activation**: Plugin checks for WooCommerce; deactivates free version if present.

## Conventions
- **Security**: Always use `check_ajax_referer()` for AJAX, `wp_verify_nonce()` for forms.
- **Internationalization**: Use `__()` and `_e()` with text domain `'multi-location-product-and-inventory-management-pro'`.
- **Options**: Store settings in `mulopimfwc_display_options` option array.
- **User Meta**: Manager locations in `mulopimfwc_assigned_locations`, capabilities in `mulopimfwc_manager_capabilities`.
- **Product Meta**: Location stock in `_mulopimfwc_stock_{location_slug}`, pricing in location-specific meta.

## Integration Points
- **WooCommerce**: Hooks into product queries, cart, checkout, orders.
- **WordPress**: Uses core functions for users, taxonomies, meta.
- **External APIs**: Inventory sync via `api/inventory-sync-api.php` (REST endpoints).

Reference files: `multi-location-product-and-inventory-management-pro.php` for main class, `admin/location-managers.php` for manager logic, `includes/stock-price-backorder-manage.php` for product meta handling.