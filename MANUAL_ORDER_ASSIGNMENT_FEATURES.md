

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

