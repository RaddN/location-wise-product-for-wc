# Manual Order Assignment - Features & Changes Required

## Overview
This document outlines all features and code changes required to implement the **Manual Assignment** method (`order_assignment_method = 'manual'`) where admins assign locations to orders after they are placed.

---

## 1. Core Functionality Changes

### 1.1 Modify Order Location Saving Logic **(Fixed)**
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()`

**Changes Required:**
- Check if `order_assignment_method` is set to `'manual'`
- If manual mode, **DO NOT** automatically save location to order meta during checkout
- Orders should be created without `_store_location` meta when in manual mode
- Only save location when admin explicitly assigns it via metabox or bulk actions

**Implementation:**
```php
public function save_location_to_order_meta($order_id, $data = null)
{
    // Get order assignment method setting
    global $mulopimfwc_options;
    $options = is_array($mulopimfwc_options ?? null)
        ? $mulopimfwc_options
        : get_option('mulopimfwc_display_options', []);
    
    $assignment_method = isset($options['order_assignment_method']) 
        ? $options['order_assignment_method'] 
        : 'customer_selection';
    
    // Skip automatic assignment if manual mode is enabled
    if ($assignment_method === 'manual') {
        return; // Don't save location automatically
    }
    
    // Existing logic for other assignment methods...
    $location = $this->get_current_location();
    if (!empty($location)) {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_meta_data('_store_location', $location);
            $order->save();
        }
    }
}
```

---

## 2. Admin Interface Enhancements

### 2.1 Enhanced Order List Table **(Fixed)**
**File:** `admin/admin.php`
**Function:** `display_location_column_content()`

**Features Required:**
- **Visual Indicator for Unassigned Orders:**
  - Highlight orders without location assignment (red/yellow badge)
  - Show "⚠️ Unassigned" or "🔴 Needs Assignment" in location column
  - Add CSS styling for unassigned orders row (e.g., light red background)

- **Quick Assignment Dropdown:**
  - Add inline dropdown selector in location column for unassigned orders
  - Allow quick assignment without opening order details
  - AJAX-powered for instant updates

**Changes:**
```php
public function display_location_column_content($column, $order)
{
    // ... existing code ...
    
    $location_slug = (string) $order_obj->get_meta('_store_location');
    
    // Check if manual assignment mode is active
    $options = get_option('mulopimfwc_display_options', []);
    $is_manual_mode = isset($options['order_assignment_method']) 
        && $options['order_assignment_method'] === 'manual';
    
    if (empty($location_slug) && $is_manual_mode) {
        // Show quick assignment dropdown for unassigned orders
        echo $this->render_quick_assignment_dropdown($order_obj);
    } else {
        $location_label = $this->get_location_label($location_slug);
        echo esc_html($location_label !== '' ? $location_label : '—');
    }
}
```

### 2.2 Enhanced Location Metabox **(Fixed)**
**File:** `admin/admin.php`
**Function:** `render_location_metabox()`

**Features Required:**
- **Prominent Unassigned Status:**
  - Show large warning/alert when order is unassigned
  - Display "⚠️ This order needs location assignment" message
  - Highlight the dropdown with red border when unassigned

- **Assignment Status Indicator:**
  - Show "Assigned" or "Unassigned" badge
  - Display assignment timestamp if available
  - Show who assigned the location (if tracking enabled)

- **Smart Location Suggestions:**
  - Suggest locations based on:
    - Shipping address proximity
    - Product availability at locations
    - Customer's previous order locations
    - Location manager assignments

- **Stock Availability Display:**
  - Show stock levels for each location when selecting
  - Highlight locations with sufficient stock
  - Warn if selected location has insufficient stock

**Changes:**
```php
public function render_location_metabox($object)
{
    // ... existing code ...
    
    $location_slug = (string) $order->get_meta('_store_location');
    $is_unassigned = empty($location_slug);
    
    // Check if manual assignment mode
    $options = get_option('mulopimfwc_display_options', []);
    $is_manual_mode = isset($options['order_assignment_method']) 
        && $options['order_assignment_method'] === 'manual';
    
    if ($is_unassigned && $is_manual_mode) {
        echo '<div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">';
        echo '<p><strong>⚠️ ' . esc_html__('Location Assignment Required', 'multi-location-product-and-inventory-management') . '</strong></p>';
        echo '<p>' . esc_html__('This order is waiting for location assignment. Please select a location below.', 'multi-location-product-and-inventory-management') . '</p>';
        echo '</div>';
    }
    
    // Add suggested locations
    if ($is_unassigned && $is_manual_mode) {
        $suggestions = $this->get_suggested_locations($order);
        if (!empty($suggestions)) {
            echo '<div class="mulopimfwc-location-suggestions">';
            echo '<p><strong>' . esc_html__('Suggested Locations:', 'multi-location-product-and-inventory-management') . '</strong></p>';
            foreach ($suggestions as $suggestion) {
                echo '<button type="button" class="button mulopimfwc-suggest-location" data-location="' . esc_attr($suggestion['slug']) . '">';
                echo esc_html($suggestion['name']) . ' (' . esc_html($suggestion['reason']) . ')';
                echo '</button>';
            }
            echo '</div>';
        }
    }
    
    // ... rest of existing code ...
}
```

### 2.3 Bulk Assignment Feature
**File:** `admin/admin.php` (new function) **(Fixed)**

**Features Required:**
- **Bulk Actions Dropdown:**
  - Add "Assign Location" to WooCommerce bulk actions
  - Allow selecting multiple orders and assigning same location
  - Show confirmation dialog before bulk assignment

- **Bulk Assignment Modal:**
  - Popup modal for selecting location
  - Show count of selected orders
  - Preview which orders will be affected
  - Option to assign based on rules (proximity, stock, etc.)

**Implementation:**
```php
// Add bulk action
add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_location_assignment']);
add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_bulk_location_assignment']);
add_action('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_location_assignment'], 10, 3);
add_action('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_location_assignment'], 10, 3);

public function add_bulk_location_assignment($actions)
{
    $options = get_option('mulopimfwc_display_options', []);
    $is_manual_mode = isset($options['order_assignment_method']) 
        && $options['order_assignment_method'] === 'manual';
    
    if ($is_manual_mode) {
        $actions['mulopimfwc_assign_location'] = __('Assign Location', 'multi-location-product-and-inventory-management');
    }
    
    return $actions;
}

public function handle_bulk_location_assignment($redirect_to, $action, $post_ids)
{
    if ($action !== 'mulopimfwc_assign_location') {
        return $redirect_to;
    }
    
    // Handle bulk assignment via AJAX modal or redirect to assignment page
    // ...
}
```

---

## 3. Order Filtering & Search

### 3.1 Unassigned Orders Filter **(Fixed)**
**File:** `admin/admin.php`
**Function:** `add_store_location_filter()`

**Features Required:**
- Add "Unassigned" option to location filter dropdown
- Quick filter button for unassigned orders
- Show count of unassigned orders in filter dropdown
- Add URL parameter support: `?store_location_filter=unassigned`

**Changes:**
```php
public function add_store_location_filter()
{
    $locations = $this->get_all_store_locations();
    $options = get_option('mulopimfwc_display_options', []);
    $is_manual_mode = isset($options['order_assignment_method']) 
        && $options['order_assignment_method'] === 'manual';
    
    if (empty($locations) && !$is_manual_mode) {
        return;
    }
    
    $current_filter = isset($_GET['store_location_filter']) 
        ? sanitize_text_field($_GET['store_location_filter']) 
        : '';
    
    echo '<select name="store_location_filter" id="store_location_filter">';
    echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management') . '</option>';
    
    // Add unassigned option if manual mode
    if ($is_manual_mode) {
        $unassigned_count = $this->get_unassigned_orders_count();
        $selected = ($current_filter === 'unassigned') ? 'selected' : '';
        echo '<option value="unassigned" ' . $selected . '>';
        echo esc_html__('Unassigned', 'multi-location-product-and-inventory-management');
        if ($unassigned_count > 0) {
            echo ' (' . $unassigned_count . ')';
        }
        echo '</option>';
    }
    
    // ... existing location options ...
}
```

### 3.2 Unassigned Orders Count Badge **(Fixed)**
**File:** `admin/admin.php` (new function)

**Features Required:**
- Show badge/notification in admin menu with count of unassigned orders
- Update count in real-time via AJAX
- Link badge to filtered view of unassigned orders

---

## 9. Frontend Considerations

### 9.1 Checkout Process
**File:** `multi-location-product-and-inventory-management-pro.php`

**Changes Required:**
- When manual mode is active, **do not require** location selection at checkout **(Fixed)**
- Hide location selector entirely if manual mode **(Fixed)**

### 9.2 Order Confirmation/Thank You Page
**File:** `multi-location-product-and-inventory-management-pro.php`
**Hook:** `woocommerce_thankyou`

**Changes Required:**
- Check if order has `_store_location` meta before displaying
- If location is empty/unassigned in manual mode:
  - Do not display location information
  - Or show: "Your order location will be confirmed shortly"
  - Or show: "Location assignment pending"
- Only show location if it's been assigned by admin

### 9.3 Cart Page
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** Cart location display/change functionality

**Changes Required:**
- When manual mode is active, location selector in cart should be optional
- Add informational message: "Location selection is optional. Your order location will be assigned after checkout."
- If location is changed in cart during manual mode, it should not be enforced
- **Note:** Cart location is still used for product availability/stock display, but won't be saved to order in manual mode

### 9.4 My Account - Order Details
**File:** `multi-location-product-and-inventory-management-pro.php`
**Hook:** `woocommerce_order_details_after_order_table` or similar

**Changes Required:**
- Check if order has `_store_location` meta before displaying location to customer
- If location is empty/unassigned:
  - Do not display location section
  - Or show: "Location assignment pending" or "Your order location will be confirmed shortly"
- Only display location if it has been assigned by admin
- **Function:** Any function that displays order location to customers needs to check for empty location in manual mode

### 9.5 Order Emails (Customer-Facing)
**File:** `includes/location-wise-email.php`
**Function:** `replace_placeholders()`

**Changes Required:**
- When `{order_store_location}` placeholder is used in email templates:
  - Check if order has `_store_location` meta
  - If empty in manual mode:
    - Replace with empty string, OR
    - Replace with: "Location assignment pending" or "To be confirmed"
  - Only show actual location name if it's been assigned
- When `{store_location_logo}` placeholder is used:
  - If location is unassigned, return empty string (no logo)
  - Only show logo if location is assigned
- **Affected emails:**
  - Order confirmation email
  - Order status change emails
  - Order completed email
  - Any custom email templates using location placeholders

### 9.6 Product Pages & Location Selection
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** Location selector on product pages

**Changes Required:**
- Location selector on product pages can remain functional (for stock/price display)
- Add note on product pages if manual mode: "Location selection helps show accurate availability. Your order location will be assigned after checkout."
- Location selection on product pages is informational only in manual mode

### 9.7 Mini Cart / Cart Widget
**File:** `multi-location-product-and-inventory-management-pro.php`

**Changes Required:**
- Location display in mini cart should be optional/informational only
- No enforcement of location selection in mini cart when manual mode is active

### 9.8 REST API / Frontend API Calls
**File:** `multi-location-product-and-inventory-management-pro.php`

**Changes Required:**
- Any AJAX endpoints that save location to order should check for manual mode
- Frontend JavaScript should not enforce location selection if manual mode is active
- Location selection in frontend should be treated as optional/preference only

---

## 10. Admin Interface Changes

### 10.1 Order List Table - Location Column
**File:** `admin/admin.php`
**Function:** `display_location_column_content()`

**Changes Required:**
- When `order_assignment_method === 'manual'`:
  - Show "⚠️ Unassigned" or "🔴 Needs Assignment" for orders without location
  - Highlight unassigned orders with visual indicator (red/yellow badge)
  - Add CSS styling for unassigned orders row (e.g., light red background)
  - Show quick assignment dropdown for unassigned orders (already documented in section 2.1)
- Display assigned location normally if location exists

### 10.2 Order Edit Page - Location Metabox
**File:** `admin/admin.php`
**Function:** `render_location_metabox()`

**Changes Required:**
- When order is unassigned in manual mode:
  - Show prominent warning: "⚠️ This order needs location assignment"
  - Highlight dropdown with red border when unassigned
  - Show "Unassigned" badge prominently
  - Display assignment status indicator
  - Show suggested locations (already documented in section 2.2)
- When location is assigned:
  - Show "Assigned" badge
  - Display assignment timestamp if available
  - Show who assigned the location (if tracking enabled)

### 10.3 Order Edit Page - Order Items Location Display
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `display_location_in_order_items()`

**Changes Required:**
- When order location (`_store_location`) is empty in manual mode:
  - Show "⚠️ Unassigned" for order-level location
  - Order item locations can still be displayed/edited individually
  - Add note: "Order location must be assigned before fulfillment"
- If order location is assigned, display normally

### 10.4 Admin Order Emails
**File:** `includes/location-wise-email.php`
**Function:** `replace_placeholders()` and `maybe_location_recipient()`

**Changes Required:**
- When `{order_store_location}` placeholder is used in admin email templates:
  - If location is unassigned: Replace with "Unassigned - Manual Assignment Required"
  - Only show actual location if assigned
- When location-specific email recipients are enabled:
  - If order location is unassigned, send to default admin email only
  - Do not send to location-specific emails if location is not assigned
  - **Function:** `maybe_location_recipient()` should check if location exists before adding location emails

### 10.5 Social Notifications
**File:** `multi-location-product-and-inventory-management-pro.php`
**Functions:** Social notification handlers

**Changes Required:**
- When sending social notifications for new orders:
  - If location is unassigned in manual mode:
    - Use "Unassigned" or "Location TBD" in notification message
    - Still send notification but indicate location needs assignment
  - **Lines 8466-8468:** Already handles unassigned location with fallback text
  - **Lines 8702-8704:** Similar handling needed for status change notifications

### 10.6 Order Status Management
**File:** `multi-location-product-and-inventory-management-pro.php`
**Function:** `save_location_to_order_meta()` and `maybe_hold_manual_unassigned_order_status()`

**Changes Required:**
- When order is created in manual mode without location:
  - Automatically set order status to "on-hold" (already implemented in lines 5002-5008)
  - Add order note: "Awaiting manual location assignment"
- When location is assigned:
  - Allow order status to be changed from "on-hold" to processing/other statuses
  - Add order note: "Location assigned: [Location Name]"
- **Function:** `maybe_hold_manual_unassigned_order_status()` already handles this (lines 5026-5052)

### 10.7 Dashboard Widgets / Reports
**File:** `admin/dashboard.php` (if exists) or `admin/admin.php`

**Changes Required:**
- Any dashboard widgets showing order statistics should:
  - Include unassigned orders count when manual mode is active
  - Show separate metrics for assigned vs unassigned orders
  - Highlight unassigned orders that need attention

### 10.8 Bulk Actions
**File:** `admin/admin.php`
**Function:** `add_bulk_location_assignment()` and `handle_bulk_location_assignment()`

**Changes Required:**
- Bulk location assignment should only be available when manual mode is active
- Show count of unassigned orders in bulk actions dropdown
- Allow bulk assignment of multiple unassigned orders at once
- (Already documented in section 2.3)

### 10.9 Order Filters
**File:** `admin/admin.php`
**Function:** `add_store_location_filter()`

**Changes Required:**
- Add "Unassigned" filter option when manual mode is active
- Show count of unassigned orders in filter dropdown
- (Already documented in section 3.1)

### 10.10 Order Export / Reports
**File:** Any export/report functionality

**Changes Required:**
- When exporting orders, include location assignment status column
- Mark orders as "Unassigned" if location is empty in manual mode
- Reports should distinguish between assigned and unassigned orders

---

## 11. API & Webhooks

### 10.1 REST API Support
**File:** `multi-location-product-and-inventory-management-pro.php` (new functions)

**Features Required:**
- Add REST API endpoint for assigning location to order
- Support bulk assignment via API
- Webhook trigger when order is assigned
- Webhook trigger when order remains unassigned after threshold

---

## 12. Reporting & Analytics

### 11.1 Assignment Metrics
**File:** `admin/dashboard.php`

**Features Required:**
- Average time to assign location
- Percentage of orders assigned within X hours
- Most assigned locations
- Assignment performance by admin user
- Unassigned orders trend over time

---

## 13. JavaScript Enhancements

### 12.1 AJAX Assignment
**File:** `assets/js/admin.js` (new functions)

**Features Required:**
- AJAX handler for quick assignment from order list
- Real-time update of assignment status
- Bulk assignment modal with progress indicator
- Auto-refresh unassigned orders count
- Keyboard shortcuts for quick assignment

### 12.2 UI Improvements
**File:** `assets/css/admin-style.css`

**CSS Required:**
- Styles for unassigned order indicators
- Highlighting for orders needing attention
- Quick assignment dropdown styling
- Assignment suggestions UI
- Dashboard widget styling

---

## 14. Database & Performance

### 13.1 Meta Keys
**New Order Meta Keys:**
- `_store_location` (existing, but behavior changes)
- `_location_assignment_log` (new - array of assignment history)
- `_location_assigned_at` (new - timestamp)
- `_location_assigned_by` (new - user ID)
- `_assignment_reminder_sent` (new - timestamp of last reminder)

### 13.2 Query Optimization
- Index orders by `_store_location` meta for faster filtering
- Cache unassigned orders count
- Optimize queries for bulk operations

---

## 15. Testing Checklist

### 14.1 Functional Testing
- [ ] Orders created without location in manual mode
- [ ] Location can be assigned via metabox
- [ ] Location can be assigned via bulk actions
- [ ] Location can be assigned via quick dropdown
- [ ] Assignment history is logged
- [ ] Email notifications are sent
- [ ] Reminder emails work correctly
- [ ] Unassigned filter works
- [ ] Dashboard widget displays correctly
- [ ] Suggestions engine provides accurate recommendations

### 14.2 Edge Cases
- [ ] Orders with multiple locations for different items
- [ ] Orders with no shipping address
- [ ] Orders with deleted locations
- [ ] Concurrent assignment attempts
- [ ] Location changed after assignment
- [ ] Order cancelled after assignment

---

## 16. User Documentation

### 15.1 Admin Guide
- How to enable manual assignment mode
- How to assign locations to orders
- How to use bulk assignment
- How to interpret suggestions
- How to configure notifications
- How to view assignment history

---

## Summary of Files to Modify/Create

### Files to Modify:
1. `multi-location-product-and-inventory-management-pro.php`
   - `save_location_to_order_meta()` - Skip auto-assignment in manual mode
   - `enqueue_order_location_scripts()` - Add scripts for manual assignment
   - New: `get_suggested_locations()` - Location recommendation engine
   - New: `log_location_assignment()` - Assignment logging

2. `admin/admin.php`
   - `display_location_column_content()` - Add quick assignment UI
   - `render_location_metabox()` - Enhanced metabox with suggestions
   - `add_store_location_filter()` - Add unassigned filter
   - `save_location_metabox()` - Add assignment logging
   - New: `add_bulk_location_assignment()` - Bulk actions
   - New: `handle_bulk_location_assignment()` - Bulk assignment handler
   - New: `get_unassigned_orders()` - Query unassigned orders
   - New: `get_unassigned_orders_count()` - Count unassigned orders
   - New: `render_quick_assignment_dropdown()` - Quick assignment UI

3. `admin/settings.php`
   - Add new settings section for manual assignment configuration
   - Add reminder settings
   - Add notification settings

4. `admin/dashboard.php`
   - New: `add_unassigned_orders_dashboard_widget()` - Dashboard widget
   - New: `render_unassigned_orders_widget()` - Widget content

5. `assets/js/admin.js`
   - New: AJAX handlers for quick assignment
   - New: Bulk assignment modal
   - New: Auto-refresh functionality

6. `assets/css/admin-style.css`
   - New: Styles for unassigned orders
   - New: Quick assignment UI styles
   - New: Dashboard widget styles

### Files to Create:
1. `includes/manual-order-assignment.php` (optional - if code gets too large)
   - All manual assignment logic in separate class

---

## Priority Implementation Order

1. **Phase 1 - Core Functionality:**
   - Modify `save_location_to_order_meta()` to skip auto-assignment
   - Enhance location metabox with unassigned status
   - Add assignment logging

2. **Phase 2 - UI Enhancements:**
   - Add unassigned filter to order list
   - Add quick assignment dropdown
   - Enhance location column display

3. **Phase 3 - Bulk Operations:**
   - Implement bulk assignment
   - Add bulk actions menu

4. **Phase 4 - Notifications:**
   - Email notifications for unassigned orders
   - Reminder system
   - Admin notices

5. **Phase 5 - Advanced Features:**
   - Dashboard widget
   - Smart suggestions engine
   - Assignment analytics

---

## Estimated Development Time

- **Phase 1:** 4-6 hours
- **Phase 2:** 6-8 hours
- **Phase 3:** 4-6 hours
- **Phase 4:** 6-8 hours
- **Phase 5:** 8-10 hours

**Total:** 28-38 hours

---

## Notes

- Ensure backward compatibility with existing `customer_selection` mode
- All new strings must be translatable
- Follow WordPress and WooCommerce coding standards
- Add proper nonce verification for all AJAX requests
- Consider performance impact of queries for unassigned orders
- Test with HPOS (High-Performance Order Storage) enabled and disabled

