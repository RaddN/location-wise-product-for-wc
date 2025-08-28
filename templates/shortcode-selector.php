<?php
// templates/shortcode-selector.php
if (!defined('ABSPATH')) exit;
// Template for the store location selector shortcode
?>
<div class="lwp-shortcode-store-selector <?php echo esc_attr($atts['class']); ?>">
    <?php if ($atts['show_title'] === 'yes'): ?>
        <h3 class="lwp-shortcode-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>

   <?php if ($atts['enable_user_locations'] === 'yes'): ?>
    <div class="lwp-user-location-features">
        <!-- Address Content Trigger -->
        <div class="address-content" id="address-trigger">
            <span class="address-label-icon">📍</span>
            <span class="address-text">
                <?php 
                $current_location = '';
                $location_set = false;
                
                // Check if user has a selected location
                if ($is_user_logged_in) {
                    $user_locations = get_user_meta($current_user->ID, 'mulopimfwc_user_locations', true);
                    $selected_location_id = isset($_COOKIE['mulopimfwc_user_location']) ? $_COOKIE['mulopimfwc_user_location'] : '';
                    
                    if (!empty($user_locations) && is_array($user_locations) && $selected_location_id) {
                        foreach ($user_locations as $location) {
                            if ($location['id'] === $selected_location_id) {
                                $current_location = $location['label'] . ' - ' . $location['address'];
                                $location_set = true;
                                break;
                            }
                        }
                    }
                } elseif (isset($_COOKIE['mulopimfwc_user_location'])) {
                    // For non-logged in users, check if location is set in cookie
                    $location_set = true;
                    $current_location = __('Current Location', 'multi-location-product-and-inventory-management');
                }
                
                echo $location_set ? esc_html($current_location) : esc_html__('Set Your Location', 'multi-location-product-and-inventory-management');
                ?>
            </span>
        </div>

        <!-- Tooltip Popup -->
        <div class="tooltip_popup" id="location-tooltip" style="display: none;">
            <div class="search-input-container">
                <input type="text" id="address-search-input" placeholder="<?php esc_attr_e('Street, Postal Code', 'multi-location-product-and-inventory-management'); ?>" aria-label="<?php esc_attr_e('Enter your address', 'multi-location-product-and-inventory-management'); ?>" value="">
                <label><?php _e('Enter your address', 'multi-location-product-and-inventory-management'); ?></label>
                <div>
                    <button type="button" aria-label="<?php esc_attr_e('Clear your address', 'multi-location-product-and-inventory-management'); ?>" data-testid="input-clear-icon" id="clear-address-btn">&times;</button>
                </div>
            </div>

            <!-- For logged in user -->
            <?php if ($is_user_logged_in): ?>
                <div class="saved_locations">
                    <h3 class="title"><?php _e('Saved Locations', 'multi-location-product-and-inventory-management'); ?></h3>
                    <div class="saved-locations-list">
                        <?php
                        $user_locations = get_user_meta($current_user->ID, 'mulopimfwc_user_locations', true);
                        if (!empty($user_locations) && is_array($user_locations)) {
                            foreach ($user_locations as $location) {
                                $selected = (isset($_COOKIE['mulopimfwc_user_location']) && $_COOKIE['mulopimfwc_user_location'] === $location['id']) ? 'selected' : '';
                                ?>
                                <div class="saved-location-item <?php echo esc_attr($selected); ?>" data-location-id="<?php echo esc_attr($location['id']); ?>">
                                    <div class="location-info">
                                        <span class="location-label"><?php echo esc_html($location['label']); ?></span>
                                        <span class="location-address"><?php echo esc_html($location['address']); ?></span>
                                    </div>
                                    <div class="location-actions">
                                        <button type="button" class="edit-location-btn" data-location-id="<?php echo esc_attr($location['id']); ?>" aria-label="<?php esc_attr_e('Edit location', 'multi-location-product-and-inventory-management'); ?>">✏️</button>
                                        <button type="button" class="delete-location-btn" data-location-id="<?php echo esc_attr($location['id']); ?>" aria-label="<?php esc_attr_e('Delete location', 'multi-location-product-and-inventory-management'); ?>">🗑️</button>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <p class="no-saved-locations"><?php _e('No saved locations yet', 'multi-location-product-and-inventory-management'); ?></p>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="location-actions-container">
                <button type="button" class="button button-primary lwp-use-current-location-btn">
                    <?php _e('Use Current Location', 'multi-location-product-and-inventory-management'); ?>
                </button>
                <?php if ($is_user_logged_in): ?>
                    <button type="button" class="button lwp-add-location-btn">
                        <?php _e('Add New Location', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CSS Styles -->
    <style>
        .lwp-user-location-features {
            position: relative;
            margin-bottom: 20px;
        }

        .address-content {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .address-content:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }

        .address-label-icon {
            margin-right: 8px;
            font-size: 16px;
        }

        .address-text {
            color: #495057;
            font-weight: 500;
        }

        .tooltip_popup {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            margin-top: 4px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .search-input-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input-container input {
            width: 100%;
            padding: 12px 40px 12px 16px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        .search-input-container input:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .search-input-container label {
            position: absolute;
            left: 16px;
            top: -8px;
            background: white;
            padding: 0 4px;
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
        }

        .search-input-container button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 18px;
            color: #6c757d;
            cursor: pointer;
            padding: 2px;
        }

        .search-input-container button:hover {
            color: #495057;
        }

        .saved_locations {
            margin-bottom: 20px;
        }

        .saved_locations .title {
            margin: 0 0 12px 0;
            font-size: 16px;
            font-weight: 600;
            color: #495057;
        }

        .saved-locations-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .saved-location-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .saved-location-item:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
        }

        .saved-location-item.selected {
            background: #e3f2fd;
            border-color: #2196f3;
        }

        .location-info {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .location-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .location-address {
            color: #6c757d;
            font-size: 13px;
            margin-top: 2px;
        }

        .location-actions {
            display: flex;
            gap: 8px;
        }

        .edit-location-btn,
        .delete-location-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .edit-location-btn:hover {
            background: #e3f2fd;
        }

        .delete-location-btn:hover {
            background: #ffebee;
        }

        .no-saved-locations {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            margin: 20px 0;
        }

        .location-actions-container {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .location-actions-container .button {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .tooltip_popup {
                left: -10px;
                right: -10px;
            }
            
            .location-actions-container {
                flex-direction: column;
            }
            
            .location-actions-container .button {
                flex: none;
            }
        }
    </style>
<?php endif; ?>

    <form id="lwp-shortcode-selector-form" class="lwp-selector-form">
        <?php wp_nonce_field('mulopimfwc_shortcode_selector', 'mulopimfwc_shortcode_selector_nonce'); ?>

        <?php if ($atts["herichical"] === "seperately"): ?>
            <?php
            // Organize locations into a hierarchical structure for separate selects
            $location_hierarchy = array();
            $parent_children_map = array();
            $child_counts = array();
            $depth_map = array();
            $max_depth = 0;
            $show_count = isset($atts["show_count"]) && $atts["show_count"] === "yes";

            // First pass: identify all parent-child relationships and depths
            foreach ($locations as $location) {
                if ($location->parent == 0) {
                    $depth_map[$location->term_id] = 0;
                    $location_hierarchy[0][] = $location;
                    $child_counts[$location->term_id] = 0; // Initialize child count
                } else {
                    $parent_children_map[$location->parent][] = $location;
                    // Increment child count for parent
                    if (!isset($child_counts[$location->parent])) {
                        $child_counts[$location->parent] = 1;
                    } else {
                        $child_counts[$location->parent]++;
                    }
                }
            }

            // Calculate depth for each location
            $calculate_depth = function ($location_id, $current_depth = 0) use (&$calculate_depth, &$depth_map, &$parent_children_map, &$max_depth) {
                $depth_map[$location_id] = $current_depth;
                if ($current_depth > $max_depth) {
                    $max_depth = $current_depth;
                }
                if (isset($parent_children_map[$location_id])) {
                    foreach ($parent_children_map[$location_id] as $child) {
                        $calculate_depth($child->term_id, $current_depth + 1);
                    }
                }
            };

            // Calculate depths starting from root locations
            if (!empty($location_hierarchy[0])) {
                foreach ($location_hierarchy[0] as $root_location) {
                    $calculate_depth($root_location->term_id);
                }
            }

            // Build full hierarchy
            for ($i = 1; $i <= $max_depth; $i++) {
                $location_hierarchy[$i] = array();
                foreach ($parent_children_map as $parent_id => $children) {
                    foreach ($children as $child) {
                        if ($depth_map[$parent_id] == $i - 1) {
                            $location_hierarchy[$i][] = $child;
                        }
                    }
                }
            }

            // Convert locations to JSON for JavaScript
            $locations_json = wp_json_encode($locations);
            $parent_children_json = wp_json_encode($parent_children_map);
            $child_counts_json = wp_json_encode($child_counts);

            // Generate separate dropdowns for each level
            for ($level = 0; $level <= $max_depth; $level++):
                $select_id = "lwp-shortcode-selector-level-{$level}";
                // translators: %s: The name of the location level (e.g., Area, Sub-area)
                $placeholder = $level == 0 ? ($atts['placeholder'] ?? '-- Select a Store --') : sprintf(__('-- Select %s --', 'multi-location-product-and-inventory-management'), ($level == 1 ? 'Area' : 'Sub-area'));
            ?>
                <div class="lwp-select-container level-<?php echo esc_html($level); ?>" <?php echo $level > 0 ? 'style="display:none;"' : ''; ?>>
                    <select id="<?php echo esc_html($select_id); ?>" class="lwp-shortcode-selector-dropdown" data-level="<?php echo esc_html($level); ?>">
                        <option value=""><?php echo esc_html($placeholder); ?></option>

                        <?php if ($level == 0 && $is_admin_or_manager && $show_all_products_admin === 'yes'): ?>
                            <option value="all-products" <?php echo ($selected_location === 'all-products') ? 'selected' : ''; ?>>
                                <?php echo esc_html_e('All Products', 'multi-location-product-and-inventory-management'); ?>
                            </option>
                        <?php endif; ?>

                        <?php if ($level == 0 && !empty($location_hierarchy[0])): ?>
                            <?php foreach ($location_hierarchy[0] as $location): ?>
                                <?php
                                $child_count = isset($child_counts[$location->term_id]) ? $child_counts[$location->term_id] : 0;
                                $display_name = esc_html($location->name);
                                if ($show_count && $child_count > 0) {
                                    $display_name .= ' (' . $child_count . ')';
                                }
                                $selected = ($location->slug === $selected_location) ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($location->slug); ?>"
                                    data-term-id="<?php echo esc_attr($location->term_id); ?>"
                                    <?php echo esc_html($selected); ?>>
                                    <?php echo esc_html($display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            <?php endfor; ?>
            <input type="hidden" id="lwp-selected-store-shortcode" name="mulopimfwc_selected_store" value="<?php echo esc_attr($selected_location); ?>">

        <?php else: ?>
            <!-- Single dropdown implementation -->
            <select id="lwp-shortcode-selector" class="lwp-location-dropdown">
                <option value=""><?php echo esc_html($atts['placeholder'] ?? '-- Select a Store --'); ?></option>

                <?php if ($is_admin_or_manager && $show_all_products_admin === 'yes'): ?>
                    <?php $selected = ($selected_location === 'all-products') ? 'selected' : ''; ?>
                    <option value="all-products" <?php echo esc_attr($selected); ?>><?php echo esc_html_e('All Products', 'multi-location-product-and-inventory-management'); ?></option>
                <?php endif; ?>

                <?php if (!empty($locations) && !is_wp_error($locations)): ?>
                    <?php
                    if ($atts["herichical"] === "yes") {
                        // Organize locations into a hierarchical structure
                        $parent_locations = array();
                        $child_locations = array();
                        $child_counts = array();
                        $show_count = isset($atts["show_count"]) && $atts["show_count"] === "yes";

                        foreach ($locations as $location) {
                            if ($location->parent == 0) {
                                $parent_locations[] = $location;
                                $child_counts[$location->term_id] = 0; // Initialize child count
                            } else {
                                $child_locations[$location->parent][] = $location;
                                // Increment child count for parent
                                if (!isset($child_counts[$location->parent])) {
                                    $child_counts[$location->parent] = 1;
                                } else {
                                    $child_counts[$location->parent]++;
                                }
                            }
                        }

                        // Display parent locations and their children
                        foreach ($parent_locations as $parent) {
                            $child_count = isset($child_counts[$parent->term_id]) ? $child_counts[$parent->term_id] : 0;
                            $display_name = esc_html($parent->name);
                            if ($show_count && $child_count > 0) {
                                $display_name .= ' (' . $child_count . ')';
                            }
                            $selected = ($parent->slug === $selected_location) ? 'selected' : '';
                            echo '<option value="' . esc_attr($parent->slug) . '" ' . esc_html($selected) . '>' . $display_name . '</option>';

                            // Check if this parent has children
                            if (isset($child_locations[$parent->term_id])) {
                                foreach ($child_locations[$parent->term_id] as $child) {
                                    $selected = ($child->slug === $selected_location) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($child->slug) . '" ' . esc_html($selected) . '>&nbsp;&nbsp;— ' . esc_html($child->name) . '</option>';
                                }
                            }
                        }
                    } else {
                        // Display locations in flat list
                        foreach ($locations as $location) {
                            $selected = ($location->slug === $selected_location) ? 'selected' : '';
                            echo '<option value="' . esc_attr($location->slug) . '" ' . esc_html($selected) . '>' . esc_html($location->name) . '</option>';
                        }
                    }
                    ?>
                <?php endif; ?>
            </select>
            <input type="hidden" id="lwp-selected-store-shortcode" name="mulopimfwc_selected_store" value="<?php echo esc_attr($selected_location); ?>">
        <?php endif; ?>

        <?php if ($atts['show_button'] === 'yes'): ?>
            <button type="button" class="button lwp-shortcode-submit">
                <?php echo esc_html($atts['button_text'] ?? 'Change Location'); ?>
            </button>
        <?php endif; ?>
    </form>
</div>


<!-- Location Popup -->
<div id="lwp-location-popup" class="lwp-location-popup" style="display:none;">
    <div class="lwp-popup-content">
        <div class="lwp-popup-header">
            <h3><?php _e('Set Your Location', 'multi-location-product-and-inventory-management'); ?></h3>
            <button type="button" class="lwp-close-popup">&times;</button>
        </div>

        <!-- Step 1: Map Selection -->
        <div id="lwp-location-step1" class="lwp-location-step">
            <div class="lwp-map-container">
                <div id="lwp-location-map" class="lwp-location-map"></div>
                <div class="lwp-map-controls">
                    <button type="button" class="button button-primary lwp-continue-btn"><?php _e('Continue', 'multi-location-product-and-inventory-management'); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 2: Details Form -->
        <div id="lwp-location-step2" class="lwp-location-step" style="display:none;">
            <div class="lwp-location-details">
                <div class="lwp-details-header">
                    <h4><?php _e('Location Details', 'multi-location-product-and-inventory-management'); ?></h4>
                    <p class="lwp-address-preview"></p>
                </div>

                <form id="lwp-location-form">
                    <?php wp_nonce_field('mulopimfwc_save_user_location', 'mulopimfwc_save_user_location_nonce'); ?>
                    <div class="lwp-form-group">
                        <label for="lwp-location-label"><?php _e('Label', 'multi-location-product-and-inventory-management'); ?></label>
                        <select id="lwp-location-label" name="label" required>
                            <option value="Home"><?php _e('Home', 'multi-location-product-and-inventory-management'); ?></option>
                            <option value="Work"><?php _e('Work', 'multi-location-product-and-inventory-management'); ?></option>
                            <option value="Partner"><?php _e('Partner', 'multi-location-product-and-inventory-management'); ?></option>
                            <option value="Other"><?php _e('Other', 'multi-location-product-and-inventory-management'); ?></option>
                        </select>
                    </div>

                    <div class="lwp-form-group">
                        <label for="lwp-location-street"><?php _e('Street Address', 'multi-location-product-and-inventory-management'); ?></label>
                        <input type="text" id="lwp-location-street" name="street" required>
                    </div>

                    <div class="lwp-form-group">
                        <label for="lwp-location-apartment"><?php _e('Apartment, suite, etc.', 'multi-location-product-and-inventory-management'); ?></label>
                        <input type="text" id="lwp-location-apartment" name="apartment">
                    </div>

                    <div class="lwp-form-group">
                        <label for="lwp-location-note"><?php _e('Note', 'multi-location-product-and-inventory-management'); ?></label>
                        <textarea id="lwp-location-note" name="note"></textarea>
                    </div>

                    <input type="hidden" id="lwp-location-lat" name="lat">
                    <input type="hidden" id="lwp-location-lng" name="lng">
                    <input type="hidden" id="lwp-location-city" name="city">
                    <input type="hidden" id="lwp-location-state" name="state">
                    <input type="hidden" id="lwp-location-postal" name="postal">
                    <input type="hidden" id="lwp-location-country" name="country">

                    <div class="lwp-form-actions">
                        <button type="button" class="button lwp-back-btn"><?php _e('Back', 'multi-location-product-and-inventory-management'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Save Location', 'multi-location-product-and-inventory-management'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<?php if ($atts["herichical"] === "seperately"):
    // Prepare JS variables for inline script
    $locations_json = wp_json_encode($locations);
    $parent_children_json = wp_json_encode($parent_children_map);
    $child_counts_json = wp_json_encode($child_counts);
    $show_count_js = $show_count ? 'true' : 'false';
    $auto_submit_js = $atts['show_button'] === 'no' ? 'true' : 'false';
    $max_depth_js = (int)$max_depth;
    $use_select2 = $atts["use_select2"] === "yes";
    $select2_init = $use_select2 ? "$('.lwp-shortcode-selector-dropdown').select2();" : "";

    $inline_js = <<<JS
jQuery(document).ready(function($) {
    var locationsData = $locations_json;
    var parentChildrenMap = $parent_children_json;
    var childCounts = $child_counts_json;
    var showCount = $show_count_js;
    var autoSubmit = $auto_submit_js;

    $('.lwp-shortcode-selector-dropdown').on('change', function() {
        var selectedLevel = $(this).data('level');
        var selectedTermId = $(this).find(':selected').data('term-id');
        var selectedValue = $(this).val();

        if (selectedValue) {
            $('#lwp-selected-store-shortcode').val(selectedValue);
            if (autoSubmit) {
                $('#lwp-shortcode-selector-form').submit();
            }
        }

        for (var i = selectedLevel + 1; i <= $max_depth_js; i++) {
            $('.lwp-select-container.level-' + i).hide();
            $('#lwp-shortcode-selector-level-' + i).empty().append('<option value="">' + mulopimfwc_selector_i18n.select + '</option>');
        }

        if (selectedValue && selectedTermId && parentChildrenMap[selectedTermId]) {
            var nextLevel = selectedLevel + 1;
            var nextDropdown = $('#lwp-shortcode-selector-level-' + nextLevel);

            nextDropdown.empty().append('<option value="">' + mulopimfwc_selector_i18n.select + '</option>');

            $.each(parentChildrenMap[selectedTermId], function(index, location) {
                var childCount = childCounts[location.term_id] || 0;
                var displayText = location.name;
                if (showCount && childCount > 0) {
                    displayText += ' (' + childCount + ')';
                }
                nextDropdown.append('<option value="' + location.slug + '" data-term-id="' + location.term_id + '">' + displayText + '</option>');
            });

            $('.lwp-select-container.level-' + nextLevel).show();
        } else if (autoSubmit && selectedValue) {
            $('#lwp-shortcode-selector-form').submit();
        }
    });

    $select2_init

    $('.lwp-shortcode-submit').on('click', function() {
        $('#lwp-shortcode-selector-form').submit();
    });
});
JS;

    // Pass translation string for '-- Select --'
    wp_localize_script('mulopimfwc_script', 'mulopimfwc_selector_i18n', array(
        'select' => esc_html__('-- Select --', 'multi-location-product-and-inventory-management'),
    ));

    // Output inline script
    wp_add_inline_script('mulopimfwc_script', $inline_js);

else:

    $use_select2 = $atts["use_select2"] === "yes";
    $show_button = $atts['show_button'] === 'yes';
    $auto_submit_js = $atts['show_button'] === 'no' ? 'true' : 'false';

    $inline_js = <<<JS
jQuery(document).ready(function($) {
    if ($use_select2) {
        $('#lwp-shortcode-selector').select2();
    }

    $('#lwp-shortcode-selector').on('change', function() {
        var selectedValue = $(this).val();
        if (selectedValue) {
            $('#lwp-selected-store-shortcode').val(selectedValue);
            if ($auto_submit_js) {
                $('#lwp-shortcode-selector-form').submit();
            }
        }
    });

    $('.lwp-shortcode-submit').on('click', function() {
        $('#lwp-shortcode-selector-form').submit();
    });
});
JS;

    wp_add_inline_script('mulopimfwc_script', $inline_js);

endif;