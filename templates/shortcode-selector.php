<?php 

if (!defined('ABSPATH')) exit;
// Template for the store location selector shortcode
?>

<?php if($atts["use_select2"]==="yes"){ ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php } ?>

<div class="lwp-shortcode-store-selector <?php echo esc_attr($atts['class']); ?>">
    <?php if ($atts['show_title'] === 'yes'): ?>
        <h3 class="lwp-shortcode-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>
    
    <form id="lwp-shortcode-selector-form" class="lwp-selector-form">
        <?php wp_nonce_field('plugincylwp_shortcode_selector', 'plugincylwp_shortcode_selector_nonce'); ?>
        
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
            $calculate_depth = function($location_id, $current_depth = 0) use (&$calculate_depth, &$depth_map, &$parent_children_map, &$max_depth) {
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
            $locations_json = json_encode($locations);
            $parent_children_json = json_encode($parent_children_map);
            $child_counts_json = json_encode($child_counts);

            // Generate separate dropdowns for each level
            for ($level = 0; $level <= $max_depth; $level++):
                $select_id = "lwp-shortcode-selector-level-{$level}";
                $placeholder = $level == 0 ? ($atts['placeholder'] ?? '-- Select a Store --') : sprintf(__('-- Select %s --', 'location-wise-products-for-woocommerce'), ($level == 1 ? 'Area' : 'Sub-area'));
            ?>
                <div class="lwp-select-container level-<?php echo $level; ?>" <?php echo $level > 0 ? 'style="display:none;"' : ''; ?>>
                    <select id="<?php echo $select_id; ?>" class="lwp-shortcode-selector-dropdown" data-level="<?php echo $level; ?>">
                        <option value=""><?php esc_html_e($placeholder, 'location-wise-products-for-woocommerce'); ?></option>
                        
                        <?php if ($level == 0 && $is_admin_or_manager): ?>
                            <option value="all-products" <?php echo ($selected_location === 'all-products') ? 'selected' : ''; ?>>
                                <?php esc_html_e('All Products', 'location-wise-products-for-woocommerce'); ?>
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
                                        <?php echo $selected; ?>>
                                    <?php echo $display_name; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            <?php endfor; ?>
            <input type="hidden" id="lwp-selected-store-shortcode" name="lwp_selected_store" value="<?php echo esc_attr($selected_location); ?>">
        
        <?php else: ?>
            <!-- Single dropdown implementation -->
            <select id="lwp-shortcode-selector" class="lwp-location-dropdown">
                <option value=""><?php esc_html_e($atts['placeholder'] ?? '-- Select a Store --', 'location-wise-products-for-woocommerce'); ?></option>
                
                <?php if ($is_admin_or_manager): ?>
                    <?php $selected = ($selected_location === 'all-products') ? 'selected' : ''; ?>
                    <option value="all-products" <?php echo esc_attr($selected); ?>><?php esc_html_e('All Products', 'location-wise-products-for-woocommerce'); ?></option>
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
                            echo '<option value="' . esc_attr($parent->slug) . '" ' . $selected . '>' . $display_name . '</option>';

                            // Check if this parent has children
                            if (isset($child_locations[$parent->term_id])) {
                                foreach ($child_locations[$parent->term_id] as $child) {
                                    $selected = ($child->slug === $selected_location) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($child->slug) . '" ' . $selected . '>&nbsp;&nbsp;— ' . esc_html($child->name) . '</option>';
                                }
                            }
                        }
                    } else {
                        // Display locations in flat list
                        foreach ($locations as $location) {
                            $selected = ($location->slug === $selected_location) ? 'selected' : '';
                            echo '<option value="' . esc_attr($location->slug) . '" ' . $selected . '>' . esc_html($location->name) . '</option>';
                        }
                    }
                    ?>
                <?php endif; ?>
            </select>
            <input type="hidden" id="lwp-selected-store-shortcode" name="lwp_selected_store" value="<?php echo esc_attr($selected_location); ?>">
        <?php endif; ?>
        
        <?php if ($atts['show_button'] === 'yes'): ?>
            <button type="button" class="button lwp-shortcode-submit">
                <?php esc_html_e($atts['button_text'] ?? 'Change Location', 'location-wise-products-for-woocommerce'); ?>
            </button>
        <?php endif; ?>
    </form>
</div>

<?php if($atts["herichical"] === "seperately"): ?>
<script>
jQuery(document).ready(function($) {
    // Store location data
    var locationsData = <?php echo $locations_json; ?>;
    var parentChildrenMap = <?php echo $parent_children_json; ?>;
    var childCounts = <?php echo $child_counts_json; ?>;
    var showCount = <?php echo $show_count ? 'true' : 'false'; ?>;
    
    // Auto-submit on change if no button is shown
    var autoSubmit = <?php echo $atts['show_button'] === 'no' ? 'true' : 'false'; ?>;
    
    // Handle dropdown changes
    $('.lwp-shortcode-selector-dropdown').on('change', function() {
        var selectedLevel = $(this).data('level');
        var selectedTermId = $(this).find(':selected').data('term-id');
        var selectedValue = $(this).val();
        
        // Store the final selected value
        if (selectedValue) {
            $('#lwp-selected-store-shortcode').val(selectedValue);
            
            // Auto-submit if enabled
            if (autoSubmit) {
                $('#lwp-shortcode-selector-form').submit();
            }
        }
        
        // Hide all lower level dropdowns
        for (var i = selectedLevel + 1; i <= <?php echo $max_depth; ?>; i++) {
            $('.lwp-select-container.level-' + i).hide();
            $('#lwp-shortcode-selector-level-' + i).empty().append('<option value=""><?php echo esc_js(__('-- Select --', 'location-wise-products-for-woocommerce')); ?></option>');
        }
        
        // If a valid option is selected and it has children, populate and show the next dropdown
        if (selectedValue && selectedTermId && parentChildrenMap[selectedTermId]) {
            var nextLevel = selectedLevel + 1;
            var nextDropdown = $('#lwp-shortcode-selector-level-' + nextLevel);
            
            // Clear and add default option
            nextDropdown.empty().append('<option value=""><?php echo esc_js(__('-- Select --', 'location-wise-products-for-woocommerce')); ?></option>');
            
            // Add child options
            $.each(parentChildrenMap[selectedTermId], function(index, location) {
                var childCount = childCounts[location.term_id] || 0;
                var displayText = location.name;
                if (showCount && childCount > 0) {
                    displayText += ' (' + childCount + ')';
                }
                nextDropdown.append('<option value="' + location.slug + '" data-term-id="' + location.term_id + '">' + displayText + '</option>');
            });
            
            // Show the container
            $('.lwp-select-container.level-' + nextLevel).show();
        } else if (autoSubmit && selectedValue) {
            // If no children and auto-submit is enabled, submit the form
            $('#lwp-shortcode-selector-form').submit();
        }
    });
    
    <?php if($atts["use_select2"]==="yes"): ?>
    // Initialize Select2 on all dropdowns
    $('.lwp-shortcode-selector-dropdown').select2();
    <?php endif; ?>
    
    // Submit handler for the button if present
    $('.lwp-shortcode-submit').on('click', function() {
        $('#lwp-shortcode-selector-form').submit();
    });
});
</script>

<?php else: ?>

<script>
jQuery(document).ready(function($) {
    <?php if($atts["use_select2"]==="yes"): ?>
    // Initialize Select2 on dropdown
    $('#lwp-shortcode-selector').select2();
    <?php endif; ?>
    
    // Update hidden field when selection changes
    $('#lwp-shortcode-selector').on('change', function() {
        var selectedValue = $(this).val();
        if (selectedValue) {
            $('#lwp-selected-store-shortcode').val(selectedValue);
            
            // Auto-submit if no button is shown
            <?php if ($atts['show_button'] === 'no'): ?>
            $('#lwp-shortcode-selector-form').submit();
            <?php endif; ?>
        }
    });
    
    // Submit handler for the button if present
    $('.lwp-shortcode-submit').on('click', function() {
        $('#lwp-shortcode-selector-form').submit();
    });
});
</script>
<?php endif; ?>