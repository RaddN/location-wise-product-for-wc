# Cache Compatibility Implementation Summary

## Overview
This implementation adds comprehensive cache compatibility for the Multi Location Product & Inventory Management plugin, ensuring proper cache varying by location cookie across all cache layers (plugin cache, server cache, CDN).

## Files Modified/Created

### 1. New File: `includes/class-mulopimfwc-cache-compat.php`
**Purpose**: Main cache compatibility handler class

**Key Features**:
- Sets `X-MuLoPIM-Location` response header for CDN/reverse proxy cache varying
- Adds `Vary` header (appends safely, doesn't overwrite)
- Integrates with popular cache plugins (LiteSpeed, WP Rocket, W3 Total Cache, Cache Enabler, WP Super Cache)
- Optional query string fallback mode (`?mulopim_loc=<slug>`)
- Admin-only debug mode (adds `X-MuLoPIM-Debug` header)
- Validates location slugs against existing locations
- Only varies cache on eligible requests (excludes admin, AJAX, REST, cart, checkout, account pages)

### 2. Modified: `multi-location-product-and-inventory-management-pro.php`

**Changes**:
- Added helper function `mulopimfwc_set_location_cookie()` with standardized, filterable cookie arguments
- Updated `set_store_location_cookie()` method to use new helper function
- Added `do_action('mulopimfwc_location_selected', $location, $location_obj)` hook
- Included cache compatibility class file
- Initialized cache compatibility class in `mulopimfwc_location_wise_products_init()`

**Helper Function Details**:
```php
mulopimfwc_set_location_cookie(string $location_slug, ?string $cookie_name = null): bool
```
- Sets cookie with standardized args: Path=/, SameSite=Lax, Secure=auto, HttpOnly=true (filterable)
- Sets `$_COOKIE[$name]` for same-request access
- Fully filterable via hooks

### 3. Modified: `includes/product-location-selector-single.php`

**Changes**:
- Updated `set_location_cookie()` method to use standardized helper function
- Added `do_action('mulopimfwc_location_selected', $location, $location_obj)` hook
- Ensures consistent cookie setting across all location selection methods

## Developer Hooks & Filters

### Filters

1. **`mulopimfwc_location_cookie_name`**
   - Default: `'mulopimfwc_store_location'`
   - Allows changing the cookie name

2. **`mulopimfwc_location_cookie_value`**
   - Parameters: `$slug`, `$location_obj`
   - Allows modifying the cookie value before setting

3. **`mulopimfwc_location_cookie_args`**
   - Parameters: `$args`, `$location_slug`
   - Allows full customization of cookie arguments (expires, path, domain, secure, httponly, samesite)

4. **`mulopimfwc_location_cookie_domain`**
   - Default: `COOKIE_DOMAIN`
   - Allows customizing cookie domain

5. **`mulopimfwc_location_cookie_httponly`**
   - Default: `true`
   - Allows changing HttpOnly setting

6. **`mulopimfwc_location_cookie_expiry`**
   - Parameters: `$expiry`, `$location_slug`
   - Allows customizing cookie expiry timestamp

7. **`mulopimfwc_cache_location_header_enabled`**
   - Default: `true`
   - Enable/disable the `X-MuLoPIM-Location` header

8. **`mulopimfwc_cache_location_header_name`**
   - Default: `'X-MuLoPIM-Location'`
   - Allows changing the header name

9. **`mulopimfwc_cache_vary_enabled_for_request`**
   - Parameters: `$bool`, `$context`
   - Allows customizing which requests should vary cache
   - Context includes: is_admin, is_ajax, is_rest, is_cart, is_checkout, is_account, method

10. **`mulopimfwc_cache_querystring_fallback_enabled`**
    - Default: `false`
    - Enable query string fallback mode (`?mulopim_loc=<slug>`)

11. **`mulopimfwc_cache_vary_for_logged_in`**
    - Default: `false`
    - Whether to vary cache for logged-in users (defaults to guests only)

12. **`mulopimfwc_cache_debug_enabled`**
    - Default: `false`
    - Enable admin-only debug mode (adds `X-MuLoPIM-Debug` header)

### Actions

1. **`mulopimfwc_location_selected`**
   - Parameters: `$slug`, `$location_obj`
   - Fires after location is selected and cookie is set
   - `$location_obj` is the term object (null for 'all-products')

## Cache Plugin Integrations

### Detected & Integrated

1. **LiteSpeed Cache**
   - Filter: `litespeed_vary`
   - Adds cookie to LiteSpeed's vary list

2. **WP Rocket**
   - Filter: `rocket_cache_reject_cookies`
   - Adds cookie to WP Rocket's vary list

3. **W3 Total Cache**
   - Filter: `w3tc_pagecache_set_cookies`
   - Adds cookie to W3TC's vary list

4. **Cache Enabler**
   - Filter: `cache_enabler_bypass_cache`
   - Integrates with Cache Enabler (relies on headers)

5. **WP Super Cache**
   - Action: `init` (priority 1)
   - Works with WP Super Cache (relies on cookies in request)

### Integration Approach

All integrations are:
- **Defensive**: Check for plugin existence before applying
- **Safe**: Never cause fatal errors if plugin is absent
- **Non-destructive**: Never purge cache or disable caching globally
- **Optional**: Gracefully skip if plugin not detected

## Cache Varying Strategy

### When Cache is Varied

Cache is varied when:
- ✅ Valid location cookie exists
- ✅ Request is eligible (not admin, AJAX, REST, cart, checkout, account)
- ✅ Not a POST request
- ✅ User type matches filter (default: guests only)

### When Cache is NOT Varied

Cache is NOT varied on:
- ❌ Admin pages (`is_admin()`)
- ❌ AJAX requests (`wp_doing_ajax()`)
- ❌ REST API requests (`REST_REQUEST`)
- ❌ WooCommerce cart page
- ❌ WooCommerce checkout page
- ❌ WooCommerce account page
- ❌ POST requests
- ❌ Invalid or empty location slugs

## Testing Checklist

### Basic Functionality

- [ ] **Cookie Setting**
  - [ ] Select location A in incognito/guest mode
  - [ ] Verify cookie `mulopimfwc_store_location` is set with correct value
  - [ ] Verify cookie has: Path=/, SameSite=Lax, Secure (if HTTPS), HttpOnly=true
  - [ ] Verify cookie domain is correct
  - [ ] Verify cookie expiry matches configured value

- [ ] **Cache Headers**
  - [ ] Visit frontend page as guest with location A selected
  - [ ] Check response headers for `X-MuLoPIM-Location: <location-slug-a>`
  - [ ] Check response headers for `Vary: X-MuLoPIM-Location` (or appended to existing Vary)
  - [ ] Verify headers are NOT present on cart/checkout/account pages
  - [ ] Verify headers are NOT present on admin pages
  - [ ] Verify headers are NOT present on AJAX requests

- [ ] **Location Switching**
  - [ ] Select location A, verify content shows location A products
  - [ ] Select location B, verify content shows location B products
  - [ ] Verify different cache variants are served
  - [ ] Verify header value changes: `X-MuLoPIM-Location: <location-slug-b>`

### Cache Plugin Integration

- [ ] **LiteSpeed Cache** (if installed)
  - [ ] Verify cookie is added to LiteSpeed vary list
  - [ ] Test cache varying works correctly

- [ ] **WP Rocket** (if installed)
  - [ ] Verify cookie is added to WP Rocket vary list
  - [ ] Test cache varying works correctly

- [ ] **W3 Total Cache** (if installed)
  - [ ] Verify cookie is added to W3TC vary list
  - [ ] Test cache varying works correctly

- [ ] **Cache Enabler** (if installed)
  - [ ] Verify integration doesn't break
  - [ ] Test cache varying works correctly

- [ ] **WP Super Cache** (if installed)
  - [ ] Verify integration doesn't break
  - [ ] Test cache varying works correctly

### Query String Fallback (Optional)

- [ ] **Enable Fallback Mode**
  - [ ] Add filter: `add_filter('mulopimfwc_cache_querystring_fallback_enabled', '__return_true');`
  - [ ] Select location A
  - [ ] Verify redirect to URL with `?mulopim_loc=<location-slug-a>`
  - [ ] Verify cache varies by query string parameter

### Debug Mode (Admin Only)

- [ ] **Enable Debug Mode**
  - [ ] Add filter: `add_filter('mulopimfwc_cache_debug_enabled', '__return_true');`
  - [ ] Visit frontend as admin
  - [ ] Check response headers for `X-MuLoPIM-Debug: cookie=set; location=<slug>; valid=yes`
  - [ ] Verify debug header is NOT present for non-admin users

### Edge Cases

- [ ] **Invalid Location**
  - [ ] Set invalid location slug in cookie
  - [ ] Verify no cache headers are set
  - [ ] Verify no errors/warnings

- [ ] **Empty Location**
  - [ ] Clear location cookie
  - [ ] Verify no cache headers are set
  - [ ] Verify no errors/warnings

- [ ] **Special Value: 'all-products'**
  - [ ] Select 'all-products' location
  - [ ] Verify cookie is set correctly
  - [ ] Verify cache headers are set (if valid)

- [ ] **Headers Already Sent**
  - [ ] Test on page with early output
  - [ ] Verify no "headers already sent" warnings
  - [ ] Verify graceful failure

- [ ] **PHP Notices/Warnings**
  - [ ] Enable WP_DEBUG
  - [ ] Test all location selection methods
  - [ ] Verify no PHP notices or warnings

### Hooks & Filters

- [ ] **Filter: mulopimfwc_location_cookie_name**
  - [ ] Change cookie name via filter
  - [ ] Verify new cookie name is used

- [ ] **Filter: mulopimfwc_location_cookie_args**
  - [ ] Modify cookie args via filter
  - [ ] Verify custom args are applied

- [ ] **Action: mulopimfwc_location_selected**
  - [ ] Add action hook listener
  - [ ] Verify hook fires after location selection
  - [ ] Verify correct parameters are passed

- [ ] **Filter: mulopimfwc_cache_vary_enabled_for_request**
  - [ ] Disable cache varying for specific requests
  - [ ] Verify filter works correctly

## Integration Detection Summary

The implementation checks for cache plugins using:

1. **LiteSpeed Cache**: `defined('LSCWP_V')`
2. **WP Rocket**: `function_exists('rocket_clean_domain')`
3. **W3 Total Cache**: `defined('W3TC')`
4. **Cache Enabler**: `class_exists('Cache_Enabler')`
5. **WP Super Cache**: `function_exists('wp_cache_is_enabled') && wp_cache_is_enabled()`

All integrations are defensive and will not cause errors if plugins are not present.

## Notes

- Cookie is set early enough to avoid "headers already sent" errors
- `$_COOKIE[$name]` is set for same-request access
- All cookie settings are filterable for customization
- Cache varying only occurs for valid, known location slugs
- Default behavior: vary cache for guests only (logged-in users can be excluded via filter)
- Query string fallback is optional and disabled by default
- Debug mode is admin-only and disabled by default

## Example Usage

### Enable Query String Fallback
```php
add_filter('mulopimfwc_cache_querystring_fallback_enabled', '__return_true');
```

### Enable Debug Mode
```php
add_filter('mulopimfwc_cache_debug_enabled', '__return_true');
```

### Customize Cookie Domain
```php
add_filter('mulopimfwc_location_cookie_domain', function() {
    return '.example.com';
});
```

### Listen for Location Selection
```php
add_action('mulopimfwc_location_selected', function($slug, $location_obj) {
    error_log("Location selected: {$slug}");
    // Custom logic here
}, 10, 2);
```

### Disable Cache Varying for Specific Pages
```php
add_filter('mulopimfwc_cache_vary_enabled_for_request', function($enabled, $context) {
    if (is_page('special-page')) {
        return false;
    }
    return $enabled;
}, 10, 2);
```

