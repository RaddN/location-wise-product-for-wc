# Frontend Text Management - Complete List

This document lists all frontend text strings that should be manageable through the Text Management settings tab.

## Table of Contents
1. [Location Selector Modal/Popup](#location-selector-modalpopup)
2. [Product Location Selector](#product-location-selector)
3. [Location Information Display](#location-information-display)
4. [Product Filter](#product-filter)
5. [Cart & Checkout](#cart--checkout)
6. [Location Reviews](#location-reviews)
7. [Business Hours & Status](#business-hours--status)
8. [Payment Gateway](#payment-gateway)
9. [Error & Success Messages](#error--success-messages)
10. [JavaScript Alert/Confirm Messages](#javascript-alertconfirm-messages)

---

## Location Selector Modal/Popup

### Modal Title & Subtitle
- **Popup Title** (Default: "Select Your Location")
  - Settings Key: `mulopimfwc_popup_title`
  - Used in: All modal templates
  - Files: `templates/location-info-modal.php`, `templates/modern-modal.php`, `templates/classic-modal.php`, `templates/modern-simple-modal.php`, `templates/modal.php`

- **Popup Subtitle** (Default: "Choose a store location to continue shopping with accurate availability.")
  - Used in: `templates/location-info-modal.php`
  - Alternative: "Find the closest store and start shopping with accurate local stock." (modern-modal.php)
  - Alternative: "Choose a nearby store to continue." (modern-simple-modal.php)
  - Alternative: "Browse available stores and select your preferred location." (classic-modal.php)

- **Select Location Button Text** (Default: "Select Location")
  - Settings Key: `mulopimfwc_popup_btn_txt`
  - Used in: All modal templates

- **Popup Placeholder** (Default: "-- Select a Store --")
  - Settings Key: `mulopimfwc_popup_placeholder`
  - Used in: `templates/modal.php`

### Modern Modal Specific
- **Your location** (Label)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Enter city, address, or postal code** (Placeholder)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Search** (Button)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Use my location** (Button)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Nearest store** (Heading)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **More locations** (Heading)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

### Classic Modal Specific
- **Search locations** (Label)
  - Used in: `templates/classic-modal.php`

- **Search by store name, city, or address** (Placeholder)
  - Used in: `templates/classic-modal.php`

- **Select** (Button for each location)
  - Used in: `templates/classic-modal.php`

### Modal JavaScript Messages (i18n)
- **Detecting your location...**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `templates/classic-modal.php`

- **Searching...**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Search failed. Try again.**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **We could not detect your location. Search for a place instead.**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`
  - Alternative: "We could not detect your location. Distances may be unavailable." (classic-modal.php)

- **No store locations found.**
  - Used in: Multiple templates

- **No matches found. Try a more specific address.**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **away** (Distance unit label)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `templates/classic-modal.php`

- **km** (Distance unit)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `templates/classic-modal.php`

- **Address unavailable**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Hours today**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Approximate location**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Near you**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Showing stores near your location.**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Select this store**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`

- **Distances from your location**
  - Used in: `templates/classic-modal.php`

- **Approximate distances**
  - Used in: `templates/classic-modal.php`

- **No locations match your search.**
  - Used in: `templates/classic-modal.php`

### Hierarchical Dropdowns
- **-- Select Area --** (Level 1 placeholder)
  - Used in: `templates/modal.php`

- **-- Select Sub-area --** (Level 2 placeholder)
  - Used in: `templates/modal.php`

---

## Product Location Selector

### Selector Label
- **Select Location:** (Default label)
  - Filter: `mulopimfwc_location_selector_label`
  - Used in: `includes/product-location-selector-single.php`
  - Alternative: "Select Location" (for shortcode without product context)

### Dropdown Placeholder
- **Choose a location...**
  - Used in: `includes/product-location-selector-single.php` (select layout)

### Location Change Messages
- **Location changed to: %s**
  - Used in: `includes/product-location-selector-single.php` (AJAX response)

- **All Products** (When location is 'all-products')
  - Used in: `includes/product-location-selector-single.php`

### Error Messages
- **Security check failed**
  - Used in: `includes/product-location-selector-single.php`

- **Invalid location**
  - Used in: `includes/product-location-selector-single.php`

- **Location not found**
  - Used in: `includes/product-location-selector-single.php`

---

## Location Information Display

### Tab Title
- **Location Information**
  - Used in: `includes/frontend-location-information.php` (Product tabs)

### Archive Page Headings
- **Contact Information**
  - Used in: `includes/frontend-location-information.php`

- **Address** (Label)
  - Used in: `includes/frontend-location-information.php`

- **Phone** (Label)
  - Used in: `includes/frontend-location-information.php`

- **Email** (Label)
  - Used in: `includes/frontend-location-information.php`

- **Get Directions** (Button)
  - Used in: `includes/frontend-location-information.php`

- **Location Map**
  - Used in: `includes/frontend-location-information.php`

- **Gallery**
  - Used in: `includes/frontend-location-information.php`

- **Business Hours**
  - Used in: `includes/frontend-location-information.php`

### Multiple Locations Display
- **Available at Multiple Locations**
  - Used in: `includes/frontend-location-information.php`

- **View Details** (Button)
  - Used in: `includes/frontend-location-information.php`

### Shortcode Headings
- **Our Locations (%d)** (With count)
  - Used in: `includes/frontend-location-information.php`

- **Search locations...** (Placeholder)
  - Used in: `includes/frontend-location-information.php`

- **No locations found matching your search.**
  - Used in: `includes/frontend-location-information.php`

- **No locations found.**
  - Used in: `includes/frontend-location-information.php`

---

## Product Filter

### Filter Labels
- **Location** (Filter label)
  - Used in: `includes/frontend-product-filter.php`

- **Stock Status** (Filter label)
  - Used in: `includes/frontend-product-filter.php`

### Filter Options
- **All Locations**
  - Used in: `includes/frontend-product-filter.php`

- **All Products**
  - Used in: `includes/frontend-product-filter.php`

- **In Stock**
  - Used in: `includes/frontend-product-filter.php`

- **Out of Stock**
  - Used in: `includes/frontend-product-filter.php`

### Filter Buttons
- **Filter** (Button)
  - Used in: `includes/frontend-product-filter.php`

- **Clear** (Button)
  - Used in: `includes/frontend-product-filter.php`

### JavaScript Messages (i18n)
- **Loading products...**
  - Used in: `includes/frontend-product-filter.php`

- **No products found.**
  - Used in: `includes/frontend-product-filter.php`

- **An error occurred. Please try again.**
  - Used in: `includes/frontend-product-filter.php`

- **Filter Products**
  - Used in: `includes/frontend-product-filter.php`

- **Clear Filters**
  - Used in: `includes/frontend-product-filter.php`

---

## Cart & Checkout

### Location Change Notification
- **Do you want to change the store location? Your cart will be updated.**
  - Settings Key: `location_notification_text`
  - Default: "Do you want to change the store location? Your cart will be updated."
  - Used in: `includes/product-location-selector-single.php`, `assets/js/location-selector.js`
  - Alternative: "Do you want to change the store location? Your cart will be emptied." (script.js)

### Cart Location Selector
- **Updating...** (Text shown while updating cart item location)
  - Used in: `assets/js/cart-location-change.js`

---

## Location Reviews

### Review Section Headings
- **Reviews from your neighbours**
  - Used in: `includes/location-wise-reviews.php`

- **Showing recent reviews from %s** (With location name)
  - Used in: `includes/location-wise-reviews.php`

### Review Location Label
- **Reviewed from: %s** (With location name)
  - Used in: `includes/location-wise-reviews.php`
  - Icon: 📍

---

## Business Hours & Status

### Status Labels
- **Open Now**
  - Used in: Multiple templates and includes

- **Open** (Simplified)
  - Used in: `templates/classic-modal.php`

- **Closed**
  - Used in: Multiple templates and includes

### Business Hours Labels
- **Closed today**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `includes/frontend-location-information.php`

- **Open 24 hours**
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `includes/frontend-location-information.php`

- **Closed** (For closed days)
  - Used in: `includes/frontend-location-information.php`

- **Open 24 Hours** (For all-day hours)
  - Used in: `includes/frontend-location-information.php`

### Time Labels
- **Closes at %s** (With time)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `includes/frontend-location-information.php`

- **Opens at %s** (With time)
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `includes/frontend-location-information.php`

### Day Labels
- **Monday**, **Tuesday**, **Wednesday**, **Thursday**, **Friday**, **Saturday**, **Sunday**
  - Used in: `includes/frontend-location-information.php`

- **Today** (Label for current day)
  - Used in: `includes/frontend-location-information.php`

### Time Format
- **%s - %s** (Time range format, e.g., "9:00 AM - 5:00 PM")
  - Used in: `templates/modern-modal.php`, `templates/modern-simple-modal.php`, `includes/frontend-location-information.php`

---

## Payment Gateway

### Cash on Pickup Gateway
- **Cash on Pickup** (Title)
  - Settings: WooCommerce Payment Gateway Settings
  - Used in: `includes/cash-on-pickup-payment-gateway.php`

- **Pay with cash when you pick up your order from the store location. Powered by Plugincy.**
  - Default description
  - Used in: `includes/cash-on-pickup-payment-gateway.php`

- **Please have exact cash ready when you pick up your order from the store location. Powered by Plugincy - Multi Location Product & Inventory Management.**
  - Default instructions
  - Used in: `includes/cash-on-pickup-payment-gateway.php`

- **Payment method powered by Plugincy**
  - Used in: `includes/cash-on-pickup-payment-gateway.php` (Thank you page)

- **Payment to be collected on pickup.**
  - Used in: `includes/cash-on-pickup-payment-gateway.php` (Order status)

---

## Error & Success Messages

### Product Display
- **Out of Stock** (Badge text)
  - Used in: `includes/product-display.php`

- **Location layouts are unavailable.**
  - Used in: `templates/location-info-modal.php`

---

## JavaScript Alert/Confirm Messages

### Location Selection Errors
- **Please select a store.**
  - Used in: `assets/js/script.js`

- **Please select a store location.**
  - Used in: `assets/js/script.js`, `assets/js/popup-layouts.js`

- **Failed to clear the cart. Please try again.**
  - Used in: `assets/js/script.js`

- **Unable to change location. Please try again.**
  - Used in: `assets/js/location-selector.js`

- **Unable to change location right now. Please try again.**
  - Used in: `assets/js/location-selector.js`

### Recommendations
- **Loading recommendations...**
  - Used in: `assets/js/recommendations.js`

- **No recommendations available yet for this location.**
  - Used in: `assets/js/recommendations.js`

- **An error occurred while loading recommendations. Please try again.**
  - Used in: `assets/js/recommendations.js`

- **Please select a location to see recommendations.**
  - Used in: `assets/js/recommendations.js`

---

## Additional Text Strings

### Shortcode Selector
- **Select Location** (Default label for shortcode without product context)
  - Used in: `includes/product-location-selector-single.php`

### Location Info Shortcode
- **No locations found.**
  - Used in: `includes/frontend-location-information.php`

---

## Summary of Settings Keys Needed

### Existing Settings Keys (Already in use)
- `mulopimfwc_popup_title` - Popup title
- `mulopimfwc_popup_btn_txt` - Button text
- `mulopimfwc_popup_placeholder` - Dropdown placeholder
- `location_notification_text` - Location change notification

### Recommended New Settings Keys
All text strings listed above should have corresponding settings keys in the format:
- `text_[category]_[key]` (e.g., `text_modal_subtitle`, `text_filter_location_label`)

### Categories for Settings Tab
1. **Modal/Popup** - All location selector modal text
2. **Product Selector** - Product page location selector text
3. **Location Info** - Location information display text
4. **Product Filter** - Filter interface text
5. **Cart & Checkout** - Cart and checkout related messages
6. **Reviews** - Location-based review text
7. **Business Hours** - Hours and status labels
8. **Payment** - Payment gateway text
9. **Messages** - Error and success messages
10. **JavaScript** - Client-side alert/confirm messages

---

## Implementation Notes

1. **Translation Support**: All text should support WordPress i18n functions (`__()`, `esc_html__()`, etc.)
2. **Default Values**: Each setting should have a sensible default value
3. **Context**: Some text may need context-aware variations (e.g., singular vs plural)
4. **Placeholders**: Text with dynamic content (like `%s` for location names) should be clearly documented
5. **HTML Support**: Some text fields may need to support HTML (e.g., descriptions with links)

---

## Files That Need Updates

### Settings Page
- `admin/settings.php` - Add new "Text Management" tab with all text fields

### Template Files
- `templates/location-info-modal.php`
- `templates/modern-modal.php`
- `templates/classic-modal.php`
- `templates/modern-simple-modal.php`
- `templates/modal.php`

### Include Files
- `includes/frontend-product-filter.php`
- `includes/frontend-location-information.php`
- `includes/product-location-selector-single.php`
- `includes/location-wise-reviews.php`
- `includes/cash-on-pickup-payment-gateway.php`
- `includes/product-display.php`

### JavaScript Files
- `assets/js/script.js`
- `assets/js/location-selector.js`
- `assets/js/popup-layouts.js`
- `assets/js/recommendations.js`
- `assets/js/cart-location-change.js`

---

**Last Updated**: Generated from codebase analysis
**Total Text Strings Identified**: 100+ unique text strings across all frontend components

