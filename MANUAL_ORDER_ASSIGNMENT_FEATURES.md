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

### 2.2 Enhanced Location Metabox
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
**File:** `admin/admin.php` (new function)

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

## 4. Dashboard Widget

### 4.1 Unassigned Orders Widget
**File:** `admin/dashboard.php` (new widget)

**Features Required:**
- **Dashboard Widget:**
  - Display count of unassigned orders
  - Show list of recent unassigned orders
  - Quick links to assign locations
  - Time since order placed
  - Order value summary

- **Widget Content:**
  - List of orders needing assignment (last 10-20)
  - Quick assignment buttons for each order
  - Filter by date range
  - Sort by order value, date, urgency

**Implementation:**
```php
add_action('wp_dashboard_setup', [$this, 'add_unassigned_orders_dashboard_widget']);

public function add_unassigned_orders_dashboard_widget()
{
    $options = get_option('mulopimfwc_display_options', []);
    $is_manual_mode = isset($options['order_assignment_method']) 
        && $options['order_assignment_method'] === 'manual';
    
    if ($is_manual_mode) {
        wp_add_dashboard_widget(
            'mulopimfwc_unassigned_orders',
            __('Unassigned Orders', 'multi-location-product-and-inventory-management'),
            [$this, 'render_unassigned_orders_widget']
        );
    }
}

public function render_unassigned_orders_widget()
{
    $unassigned_orders = $this->get_unassigned_orders(['limit' => 10]);
    
    if (empty($unassigned_orders)) {
        echo '<p>' . esc_html__('No unassigned orders. Great job!', 'multi-location-product-and-inventory-management') . '</p>';
        return;
    }
    
    echo '<div class="mulopimfwc-unassigned-orders-widget">';
    foreach ($unassigned_orders as $order) {
        // Render order row with quick assignment
    }
    echo '</div>';
}
```

---

## 5. Notifications & Alerts

### 5.1 Email Notifications
**File:** `includes/location-wise-email.php` (new functions)

**Features Required:**
- **New Order Notification (Manual Mode):**
  - Send email to admin when order is placed in manual mode
  - Include "Action Required: Assign Location" in subject
  - Link directly to order edit page
  - Include order summary and customer details

- **Reminder Notifications:**
  - Send reminder emails for orders unassigned after X hours (configurable)
  - Daily summary of unassigned orders
  - Escalation emails if orders remain unassigned for extended period

- **Assignment Confirmation:**
  - Email to location manager when order is assigned to their location
  - Email to admin confirming assignment

**Settings Required:**
- Enable/disable email notifications
- Reminder interval (hours)
- Escalation threshold (hours/days)
- Recipient email addresses

### 5.2 Admin Notices
**File:** `admin/admin.php` (new function)

**Features Required:**
- Show persistent admin notice if unassigned orders exist
- Dismissible notice with link to unassigned orders
- Show count and urgency level
- Auto-dismiss when all orders are assigned

---

## 6. Assignment History & Logging

### 6.1 Assignment Log
**File:** `admin/admin.php` (new functions)

**Features Required:**
- Track when location was assigned
- Record who assigned the location (user ID)
- Log assignment changes (if location is reassigned)
- Display assignment history in order notes or custom meta

**Implementation:**
```php
public function log_location_assignment($order_id, $location_slug, $previous_location = '')
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $current_user = wp_get_current_user();
    $location_name = $this->get_location_label($location_slug);
    $previous_name = $previous_location ? $this->get_location_label($previous_location) : __('Unassigned', 'multi-location-product-and-inventory-management');
    
    $note = sprintf(
        __('Location assigned: %s (previously: %s) by %s', 'multi-location-product-and-inventory-management'),
        $location_name,
        $previous_name,
        $current_user->display_name
    );
    
    $order->add_order_note($note);
    
    // Also save to meta for structured data
    $assignment_log = $order->get_meta('_location_assignment_log', true);
    if (!is_array($assignment_log)) {
        $assignment_log = [];
    }
    
    $assignment_log[] = [
        'timestamp' => current_time('mysql'),
        'user_id' => $current_user->ID,
        'user_name' => $current_user->display_name,
        'location_slug' => $location_slug,
        'location_name' => $location_name,
        'previous_location' => $previous_location,
    ];
    
    $order->update_meta_data('_location_assignment_log', $assignment_log);
    $order->save();
}
```

---

## 7. Smart Assignment Suggestions

### 7.1 Location Recommendation Engine
**File:** `admin/admin.php` (new function)

**Features Required:**
- **Proximity-Based Suggestions:**
  - Calculate distance from shipping address to each location
  - Suggest nearest location(s)

- **Stock-Based Suggestions:**
  - Check product availability at each location
  - Prioritize locations with sufficient stock
  - Warn if no location has sufficient stock

- **Historical Suggestions:**
  - Suggest location based on customer's previous orders
  - Suggest based on product's most common location

- **Manager-Based Suggestions:**
  - If location managers exist, suggest based on their assigned locations

**Implementation:**
```php
public function get_suggested_locations($order)
{
    $suggestions = [];
    
    // Get order items
    $items = $order->get_items();
    $shipping_address = $order->get_shipping_address_1();
    $shipping_city = $order->get_shipping_city();
    $shipping_postcode = $order->get_shipping_postcode();
    
    $locations = get_terms([
        'taxonomy' => 'mulopimfwc_store_location',
        'hide_empty' => false,
    ]);
    
    foreach ($locations as $location) {
        $score = 0;
        $reasons = [];
        
        // Check proximity
        $location_address = get_term_meta($location->term_id, 'address', true);
        if ($location_address && $shipping_address) {
            $distance = $this->calculate_distance($shipping_address, $location_address);
            if ($distance < 50) { // Within 50km
                $score += 10;
                $reasons[] = __('Near shipping address', 'multi-location-product-and-inventory-management');
            }
        }
        
        // Check stock availability
        $has_stock = true;
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            $location_stock = get_post_meta($product_id, '_location_stock_' . $location->term_id, true);
            if ((int)$location_stock < $quantity) {
                $has_stock = false;
                break;
            }
        }
        if ($has_stock) {
            $score += 20;
            $reasons[] = __('Sufficient stock available', 'multi-location-product-and-inventory-management');
        }
        
        if ($score > 0) {
            $suggestions[] = [
                'slug' => $location->slug,
                'name' => $location->name,
                'score' => $score,
                'reason' => implode(', ', $reasons),
            ];
        }
    }
    
    // Sort by score
    usort($suggestions, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($suggestions, 0, 3); // Top 3 suggestions
}
```

---

## 8. Settings & Configuration

### 8.1 Additional Settings
**File:** `admin/settings.php`

**New Settings Required:**
- **Assignment Reminder Settings:**
  - Enable/disable reminders
  - Reminder interval (hours)
  - Maximum reminders before escalation

- **Notification Settings:**
  - Email recipients for unassigned order alerts
  - Notification frequency (immediate, hourly, daily)
  - Include order details in notifications

- **Auto-Assignment Rules (Future):**
  - Rules for automatic assignment after X hours if still unassigned
  - Fallback location if no assignment made

**Location in Settings:**
Add new section in "Order Fulfillment Settings" after `order_assignment_method` field.

---

## 9. Frontend Considerations

### 9.1 Checkout Process
**File:** `multi-location-product-and-inventory-management-pro.php`

**Changes Required:**
- When manual mode is active, **do not require** location selection at checkout
- Location selector can still be shown but marked as optional
- Or hide location selector entirely if manual mode
- Add note: "Your order location will be assigned after checkout"

### 9.2 Order Confirmation/Thank You Page
**File:** `multi-location-product-and-inventory-management-pro.php`

**Changes Required:**
- Do not display location on thank you page if unassigned
- Or show: "Your order location will be confirmed shortly"

---

## 10. API & Webhooks

### 10.1 REST API Support
**File:** `multi-location-product-and-inventory-management-pro.php` (new functions)

**Features Required:**
- Add REST API endpoint for assigning location to order
- Support bulk assignment via API
- Webhook trigger when order is assigned
- Webhook trigger when order remains unassigned after threshold

---

## 11. Reporting & Analytics

### 11.1 Assignment Metrics
**File:** `admin/dashboard.php`

**Features Required:**
- Average time to assign location
- Percentage of orders assigned within X hours
- Most assigned locations
- Assignment performance by admin user
- Unassigned orders trend over time

---

## 12. JavaScript Enhancements

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

## 13. Database & Performance

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

## 14. Testing Checklist

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

## 15. User Documentation

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

