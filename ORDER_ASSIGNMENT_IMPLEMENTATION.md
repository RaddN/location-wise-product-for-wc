# Order Assignment Methods Implementation Guide

## Overview
This document outlines all changes and features required to implement the following order assignment methods:
1. **inventory_based** - Inventory Based (Location with highest stock)
2. **proximity_based** - Proximity Based (Nearest location to shipping address)
3. **priority_based** - Location as per priority in stock
4. **shipping_based** - Location as per Shipping Zones

---

## 1. INVENTORY_BASED Assignment Method

### Features Required:
- Calculate total stock quantity per location for all products in the order
- Select location with highest combined stock for all order items
- Handle cases where multiple locations have same stock level
- Consider product availability per location
- Handle variations and simple products

### Changes Required:

#### A. Core Logic Implementation
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()` (around line 5416)

**Changes:**
1. Add new method `assign_location_by_inventory()` to calculate best location based on stock
2. Modify `save_location_to_order_meta()` to call inventory-based assignment when method is `inventory_based`
3. Handle edge cases:
   - Products not assigned to any location
   - Multiple locations with same stock totals
   - Products with zero stock at all locations

**Logic Flow:**
```
1. Get all order items (products + variations)
2. For each location:
   - Calculate total stock available for all order items
   - Consider only locations where products are assigned
   - Sum stock quantities: Σ(stock_qty for each product at location)
3. Select location with highest total stock
4. If tie, use display_order as tiebreaker (lower number = higher priority)
5. Save selected location slug to order meta
```

#### B. Helper Functions Needed:
```php
/**
 * Get total stock quantity for all order items at a specific location
 * @param WC_Order $order
 * @param int $location_id
 * @return int Total stock quantity
 */
private function get_total_stock_for_location($order, $location_id)

/**
 * Get all locations that have at least one product from the order
 * @param WC_Order $order
 * @return array Array of location term objects
 */
private function get_locations_with_order_products($order)

/**
 * Assign location based on inventory levels
 * @param WC_Order $order
 * @return string|null Location slug or null
 */
private function assign_location_by_inventory($order)
```

#### C. Settings Update
**File:** `admin/settings.php` (around line 3948)
- Remove `disabled` attribute from `inventory_based` option
- Enable the option in the dropdown

---

## 2. PROXIMITY_BASED Assignment Method

### Features Required:
- Geocoding service integration (Google Maps API, OpenStreetMap Nominatim, etc.)
- Calculate distance between shipping address and location coordinates
- Select nearest location to shipping address
- Handle missing coordinates gracefully
- Cache geocoding results to reduce API calls

### Changes Required:

#### A. Core Logic Implementation
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()` (around line 5416)

**Changes:**
1. Add new method `assign_location_by_proximity()` to calculate nearest location
2. Modify `save_location_to_order_meta()` to call proximity-based assignment when method is `proximity_based`
3. Implement geocoding for shipping address
4. Calculate distances using Haversine formula or geocoding API

**Logic Flow:**
```
1. Get shipping address from order (shipping_address_1, shipping_city, shipping_state, shipping_postcode, shipping_country)
2. Geocode shipping address to get lat/lng coordinates
   - Use cached result if available
   - Call geocoding API if not cached
3. Get all locations with valid coordinates (latitude/longitude)
4. For each location:
   - Calculate distance from shipping address coordinates
   - Use Haversine formula: distance = 2 * R * asin(sqrt(sin²(Δlat/2) + cos(lat1) * cos(lat2) * sin²(Δlng/2)))
5. Select location with shortest distance
6. Save selected location slug to order meta
```

#### B. Helper Functions Needed:
```php
/**
 * Geocode an address to get coordinates
 * @param string $address Full address string
 * @return array|false ['lat' => float, 'lng' => float] or false on failure
 */
private function geocode_address($address)

/**
 * Calculate distance between two coordinates using Haversine formula
 * @param float $lat1 Latitude of first point
 * @param float $lng1 Longitude of first point
 * @param float $lat2 Latitude of second point
 * @param float $lng2 Longitude of second point
 * @return float Distance in kilometers
 */
private function calculate_distance($lat1, $lng1, $lat2, $lng2)

/**
 * Get shipping address coordinates from order
 * @param WC_Order $order
 * @return array|false ['lat' => float, 'lng' => float] or false
 */
private function get_shipping_address_coordinates($order)

/**
 * Assign location based on proximity to shipping address
 * @param WC_Order $order
 * @return string|null Location slug or null
 */
private function assign_location_by_proximity($order)
```

#### C. Settings/Configuration Needed:
**File:** `admin/settings.php`
- Add geocoding API settings:
  - Geocoding provider selection (Google Maps, OpenStreetMap, etc.)
  - API key field (if required)
  - Enable/disable geocoding caching
  - Cache expiration time

**New Settings Fields:**
```php
'geocoding_provider' => 'google' | 'openstreetmap' | 'none'
'geocoding_api_key' => string
'enable_geocoding_cache' => 'on' | 'off'
'geocoding_cache_expiry' => int (hours)
```

#### D. Database/Cache:
- Store geocoded addresses in transient cache or custom table
- Key format: `mulopimfwc_geocode_{md5(address)}`
- Expiry based on settings

#### E. Settings Update
**File:** `admin/settings.php` (around line 3949)
- Remove `disabled` attribute from `proximity_based` option
- Enable the option in the dropdown

---

## 3. PRIORITY_BASED Assignment Method

### Features Required:
- Use existing `display_order` term meta for location priority
- Select location with lowest display_order number (higher priority)
- Consider only locations where products are available
- Handle products assigned to multiple locations

### Changes Required:

#### A. Core Logic Implementation
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()` (around line 5416)

**Changes:**
1. Add new method `assign_location_by_priority()` to select location by priority
2. Modify `save_location_to_order_meta()` to call priority-based assignment when method is `priority_based`
3. Use `display_order` term meta (already exists in codebase)

**Logic Flow:**
```
1. Get all locations that have at least one product from the order
2. For each location:
   - Get display_order meta value (default to 999 if not set)
   - Lower number = higher priority
3. Sort locations by display_order (ascending)
4. Select location with lowest display_order
5. If multiple locations have same priority, use first one or apply secondary criteria (e.g., inventory)
6. Save selected location slug to order meta
```

#### B. Helper Functions Needed:
```php
/**
 * Get display order (priority) for a location
 * @param int $location_id
 * @return int Display order (default: 999)
 */
private function get_location_priority($location_id)

/**
 * Assign location based on priority (display_order)
 * @param WC_Order $order
 * @return string|null Location slug or null
 */
private function assign_location_by_priority($order)
```

#### C. Settings Update
**File:** `admin/settings.php`
- Add new option `priority_based` to the dropdown
- Add description explaining priority is based on display_order

**New Option:**
```php
<option value="priority_based" <?php selected($value, 'priority_based'); ?>>
    <?php echo esc_html_e('Priority Based (Location as per priority in stock)', 'multi-location-product-and-inventory-management'); ?>
</option>
```

---

## 4. SHIPPING_BASED Assignment Method

### Features Required:
- Map WooCommerce shipping zones to locations
- Determine which shipping zone applies to the order's shipping address
- Select location associated with that shipping zone
- Handle multiple locations per shipping zone
- Fallback logic if no zone match

### Changes Required:

#### A. Core Logic Implementation
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()` (around line 5416)

**Changes:**
1. Add new method `assign_location_by_shipping_zone()` to select location based on shipping zone
2. Modify `save_location_to_order_meta()` to call shipping-based assignment when method is `shipping_based`
3. Use WooCommerce shipping zone matching logic

**Logic Flow:**
```
1. Get shipping address from order
2. Determine which WooCommerce shipping zone matches the shipping address
   - Use WC_Shipping_Zone::get_zone_matching_package() or similar
   - Match by country, state, postcode
3. Get locations associated with that shipping zone
   - Check location term meta 'shipping_zones' (already exists)
   - Match zone ID
4. If multiple locations match:
   - Use priority (display_order) as tiebreaker
   - Or use inventory levels
5. If no zone match:
   - Fallback to default location or customer selection
6. Save selected location slug to order meta
```

#### B. Helper Functions Needed:
```php
/**
 * Get shipping zone ID for an order's shipping address
 * @param WC_Order $order
 * @return int|false Shipping zone ID or false
 */
private function get_shipping_zone_for_order($order)

/**
 * Get locations associated with a shipping zone
 * @param int $zone_id Shipping zone ID
 * @return array Array of location term objects
 */
private function get_locations_for_shipping_zone($zone_id)

/**
 * Assign location based on shipping zone
 * @param WC_Order $order
 * @return string|null Location slug or null
 */
private function assign_location_by_shipping_zone($order)
```

#### C. Location-Shipping Zone Mapping:
**File:** `admin/admin.php` (around line 828)
- Location term meta `shipping_zones` already exists
- Ensure it stores array of zone IDs: `[zone_id1, zone_id2, ...]`
- Verify mapping is saved correctly

#### D. Settings Update
**File:** `admin/settings.php`
- Add new option `shipping_based` to the dropdown
- Add description explaining assignment is based on shipping zones

**New Option:**
```php
<option value="shipping_based" <?php selected($value, 'shipping_based'); ?>>
    <?php echo esc_html_e('Shipping Based (Location as per Shipping Zones)', 'multi-location-product-and-inventory-management'); ?>
</option>
```

---

## 5. COMMON CHANGES ACROSS ALL METHODS

### A. Update Settings Dropdown
**File:** `admin/settings.php` (around line 3946-3951)

**Current:**
```php
<select name="mulopimfwc_display_options[order_assignment_method]">
    <option value="customer_selection" <?php selected($value, 'customer_selection'); ?>><?php echo esc_html_e('Customer Selection (Based on selected location)', 'multi-location-product-and-inventory-management'); ?></option>
    <option disabled value="inventory_based" <?php selected($value, 'inventory_based'); ?>><?php echo esc_html_e('Inventory Based (Location with highest stock)', 'multi-location-product-and-inventory-management'); ?></option>
    <option disabled value="proximity_based" <?php selected($value, 'proximity_based'); ?>><?php echo esc_html_e('Proximity Based (Nearest location to shipping address)', 'multi-location-product-and-inventory-management'); ?></option>
    <option value="manual" <?php selected($value, 'manual'); ?>><?php echo esc_html_e('Manual Assignment (Admin assigns after order)', 'multi-location-product-and-inventory-management'); ?></option>
</select>
```

**Updated:**
```php
<select name="mulopimfwc_display_options[order_assignment_method]">
    <option value="customer_selection" <?php selected($value, 'customer_selection'); ?>><?php echo esc_html_e('Customer Selection (Based on selected location)', 'multi-location-product-and-inventory-management'); ?></option>
    <option value="inventory_based" <?php selected($value, 'inventory_based'); ?>><?php echo esc_html_e('Inventory Based (Location with highest stock)', 'multi-location-product-and-inventory-management'); ?></option>
    <option value="proximity_based" <?php selected($value, 'proximity_based'); ?>><?php echo esc_html_e('Proximity Based (Nearest location to shipping address)', 'multi-location-product-and-inventory-management'); ?></option>
    <option value="priority_based" <?php selected($value, 'priority_based'); ?>><?php echo esc_html_e('Priority Based (Location as per priority in stock)', 'multi-location-product-and-inventory-management'); ?></option>
    <option value="shipping_based" <?php selected($value, 'shipping_based'); ?>><?php echo esc_html_e('Shipping Based (Location as per Shipping Zones)', 'multi-location-product-and-inventory-management'); ?></option>
    <option value="manual" <?php selected($value, 'manual'); ?>><?php echo esc_html_e('Manual Assignment (Admin assigns after order)', 'multi-location-product-and-inventory-management'); ?></option>
</select>
```

### B. Update Core Assignment Logic
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()` (around line 5416)

**Current Logic:**
```php
if ($assignment_method === 'manual') {
    // Manual assignment logic
    return;
}

// Use get_current_location method which includes default location logic
$location = $this->get_current_location();
```

**Updated Logic:**
```php
if ($assignment_method === 'manual') {
    // Manual assignment logic
    return;
}

// Handle different assignment methods
$location = null;

switch ($assignment_method) {
    case 'customer_selection':
        $location = $this->get_current_location();
        break;
    
    case 'inventory_based':
        $order = wc_get_order($order_id);
        if ($order) {
            $location = $this->assign_location_by_inventory($order);
        }
        break;
    
    case 'proximity_based':
        $order = wc_get_order($order_id);
        if ($order) {
            $location = $this->assign_location_by_proximity($order);
        }
        break;
    
    case 'priority_based':
        $order = wc_get_order($order_id);
        if ($order) {
            $location = $this->assign_location_by_priority($order);
        }
        break;
    
    case 'shipping_based':
        $order = wc_get_order($order_id);
        if ($order) {
            $location = $this->assign_location_by_shipping_zone($order);
        }
        break;
    
    default:
        $location = $this->get_current_location();
        break;
}

// Save location if found
if (!empty($location)) {
    $order = wc_get_order($order_id);
    if ($order) {
        $order->update_meta_data('_store_location', $location);
        $order->save();
    }
}
```

### C. Error Handling & Fallbacks
- If assignment method fails to find a location:
  - Log error for debugging
  - Fallback to customer selection or default location
  - Add order note explaining assignment failure
  - Optionally set order to 'on-hold' status

### D. Order Notes
Add order notes when automatic assignment occurs:
```php
$order->add_order_note(
    sprintf(
        __('Location automatically assigned: %s (Method: %s)', 'multi-location-product-and-inventory-management'),
        $location_name,
        $assignment_method_label
    )
);
```

### E. Testing Considerations
- Test with orders containing:
  - Single product
  - Multiple products
  - Products with variations
  - Products assigned to multiple locations
  - Products not assigned to any location
  - Orders with missing shipping address (for proximity)
  - Orders with missing coordinates (for locations)

---

## 6. ADDITIONAL FEATURES TO CONSIDER

### A. Multi-Location Orders
- Consider splitting orders across multiple locations if beneficial
- Add setting to enable/disable multi-location fulfillment
- Track which items come from which location

### B. Stock Availability Check
- Before assigning, verify location has sufficient stock
- Consider backorder settings per location
- Handle partial fulfillment scenarios

### C. Admin Override
- Allow admin to manually override automatic assignment
- Add UI in order edit screen to change assigned location
- Log manual overrides in order notes

### D. Assignment Rules/Preferences
- Allow setting minimum stock threshold for inventory-based assignment
- Set maximum distance for proximity-based assignment
- Configure fallback behavior when primary method fails

### E. Performance Optimization
- Cache location data queries
- Batch database queries where possible
- Use transients for geocoding results
- Optimize stock calculation queries

---

## 7. FILES TO MODIFY

### Primary Files:
1. **multi-location-product-and-inventory-management-pro.php**
   - `save_location_to_order_meta()` method (line ~5416)
   - Add new assignment methods
   - Add helper functions

2. **admin/settings.php**
   - Update order assignment method dropdown (line ~3946)
   - Add geocoding settings (for proximity method)
   - Add descriptions for each method

### Secondary Files (if needed):
3. **admin/admin.php**
   - Verify shipping zone mapping is correct
   - Ensure display_order is properly saved

4. **includes/location-based-shipping.php**
   - May need updates for shipping zone matching logic

---

## 8. DATABASE CONSIDERATIONS

### Existing Meta Fields (Already Available):
- `_store_location` - Order meta (stores location slug)
- `display_order` - Location term meta (for priority)
- `latitude` / `longitude` - Location term meta (for proximity)
- `shipping_zones` - Location term meta (for shipping-based)
- `_location_stock_{location_id}` - Product meta (for inventory)

### New Meta Fields (May Need):
- Geocoding cache (transients or custom table)
- Assignment method used (order meta for tracking)

---

## 9. IMPLEMENTATION PRIORITY

### Phase 1 (Easiest):
1. ✅ Priority Based (uses existing display_order)
2. ✅ Inventory Based (uses existing stock tracking)

### Phase 2 (Medium):
3. ✅ Shipping Based (uses existing shipping zone mapping)

### Phase 3 (Most Complex):
4. ✅ Proximity Based (requires geocoding API integration)

---

## 10. TESTING CHECKLIST

### For Each Method:
- [ ] Single product order
- [ ] Multiple product order
- [ ] Order with variations
- [ ] Order with products not assigned to any location
- [ ] Order with products assigned to multiple locations
- [ ] Edge cases (no locations, no stock, etc.)
- [ ] Fallback behavior when method fails
- [ ] Order notes are created correctly
- [ ] Location is saved to order meta
- [ ] Stock is deducted from correct location

### Specific Tests:
- **Inventory Based:** Test with equal stock levels, zero stock scenarios
- **Proximity Based:** Test with missing coordinates, invalid addresses, API failures
- **Priority Based:** Test with missing display_order, equal priorities
- **Shipping Based:** Test with no matching zone, multiple locations per zone

---

## 11. DOCUMENTATION NEEDED

- Admin documentation explaining each assignment method
- Configuration guide for geocoding API (proximity method)
- Troubleshooting guide for common issues
- Examples of when to use each method

---

## Summary

**Total Methods to Implement:** 4
**Core Files to Modify:** 2 (main plugin file + settings)
**New Helper Functions:** ~10-12
**New Settings:** 4-5 (geocoding related)
**Complexity:** Medium to High (especially proximity-based)

The implementation requires careful consideration of edge cases, fallback logic, and performance optimization. Start with simpler methods (priority, inventory) before tackling proximity-based assignment.

