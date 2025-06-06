<?php
if (!defined('ABSPATH')) exit;
$options = $this->get_display_options();
if(isset($options["use_select2"]) && $options["use_select2"]==="yes"){
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<?php } ?>
<div id="lwp-store-selector-modal" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="lwp-store-selector-content">
    <?php if(isset($options["title_show_popup"]) && $options["title_show_popup"]==="yes"){?>
        <h2><?php esc_html_e($options["lwp_popup_title"] ?? 'Select Your Store', 'location-wise-products-for-woocommerce'); ?></h2><?php }?>
        <form id="lwp-store-selector-form-modal">
            <?php wp_nonce_field('plugincylwp_modal_selector', 'plugincylwp_modal_selector_nonce'); ?>

            <?php if (isset($options["herichical"]) && $options["herichical"] === "seperately"): ?>
                <?php
                // Organize locations into a hierarchical structure for separate selects
                $location_hierarchy = array();
                $parent_children_map = array();
                $child_counts = array();
                $depth_map = array();
                $max_depth = 0;
                $show_count = isset($options["show_count"]) && $options["show_count"] === "yes";
                
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
                foreach ($location_hierarchy[0] as $root_location) {
                    $calculate_depth($root_location->term_id);
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
                    $select_id = "lwp-store-selector-level-{$level}";
                    $placeholder = $level == 0 ? 
                        ($options["lwp_popup_placeholder"] ?? '-- Select a Store --') : 
                        sprintf(__('-- Select %s --', 'location-wise-products-for-woocommerce'), ($level == 1 ? 'Area' : 'Sub-area'));
                ?>
                    <div class="lwp-select-container level-<?php echo $level; ?>" <?php echo $level > 0 ? 'style="display:none;"' : ''; ?>>
                        <select id="<?php echo $select_id; ?>" class="lwp-store-selector-dropdown" data-level="<?php echo $level; ?>">
                            <option value=""><?php esc_html_e($placeholder, 'location-wise-products-for-woocommerce'); ?></option>
                            <?php if ($level == 0 && !empty($location_hierarchy[0])): ?>
                                <?php foreach ($location_hierarchy[0] as $location): ?>
                                    <?php 
                                    $child_count = isset($child_counts[$location->term_id]) ? $child_counts[$location->term_id] : 0; 
                                    $display_name = esc_html($location->name);
                                    if ($show_count && $child_count > 0) {
                                        $display_name .= ' (' . $child_count . ')';
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($location->slug); ?>" data-term-id="<?php echo esc_attr($location->term_id); ?>">
                                        <?php echo $display_name; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endfor; ?>
                
                <input type="hidden" id="lwp-selected-store" name="lwp_selected_store" value="">
                
            <?php else: ?>
                <!-- Original single dropdown implementation -->
                <select id="lwp-store-selector-modal-dropdown">
                    <option value=""><?php esc_html_e($options["lwp_popup_placeholder"] ?? '-- Select a Store --', 'location-wise-products-for-woocommerce'); ?></option>
                    <?php
                    if (!empty($locations) && !is_wp_error($locations)) {
                        if (isset($options["herichical"]) && $options["herichical"] === "yes") {
                            // Organize locations into a hierarchical structure
                            $parent_locations = array();
                            $child_locations = array();
                            $child_counts = array();
                            $show_count = isset($options["show_count"]) && $options["show_count"] === "yes";
                            
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
                                
                                echo '<option value="' . esc_attr($parent->slug) . '">' . $display_name . '</option>';
                                
                                // Check if this parent has children
                                if (isset($child_locations[$parent->term_id])) {
                                    foreach ($child_locations[$parent->term_id] as $child) {
                                        echo '<option value="' . esc_attr($child->slug) . '">&nbsp;&nbsp;— ' . esc_html($child->name) . '</option>';
                                    }
                                }
                            }
                        } else {
                            // Display locations in flat list
                            foreach ($locations as $location) {
                                echo '<option value="' . esc_attr($location->slug) . '">' . esc_html($location->name) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>
                
                <input type="hidden" id="lwp-selected-store" name="lwp_selected_store" value="">
                
            <?php endif; ?>
            
            <button type="button" id="lwp-store-selector-submit" class="button"><?php esc_html_e($options["lwp_popup_btn_txt"] ?? 'Confirm', 'location-wise-products-for-woocommerce'); ?></button>
        </form>
    </div>
</div>

<?php if(isset($options["herichical"]) && $options["herichical"] === "seperately"): ?>
<script>
    jQuery(document).ready(function($) {
        // Store location data
        var locationsData = <?php echo $locations_json; ?>;
        var parentChildrenMap = <?php echo $parent_children_json; ?>;
        var childCounts = <?php echo $child_counts_json; ?>;
        var showCount = <?php echo $show_count ? 'true' : 'false'; ?>;
        
        // Handle dropdown changes
        $('.lwp-store-selector-dropdown').on('change', function() {
            var selectedLevel = $(this).data('level');
            var selectedTermId = $(this).find(':selected').data('term-id');
            var selectedValue = $(this).val();
            
            // Store the final selected value
            if (selectedValue) {
                $('#lwp-selected-store').val(selectedValue);
            }
            
            // Hide all lower level dropdowns
            for (var i = selectedLevel + 1; i <= <?php echo $max_depth; ?>; i++) {
                $('.lwp-select-container.level-' + i).hide();
                $('#lwp-store-selector-level-' + i).empty().append('<option value=""><?php echo esc_js(__('-- Select --', 'location-wise-products-for-woocommerce')); ?></option>');
            }
            
            // If a valid option is selected and it has children, populate and show the next dropdown
            if (selectedValue && selectedTermId && parentChildrenMap[selectedTermId]) {
                var nextLevel = selectedLevel + 1;
                var nextDropdown = $('#lwp-store-selector-level-' + nextLevel);
                
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
            }
        });
        
        <?php if(isset($options["use_select2"]) &&  $options["use_select2"]==="yes"): ?>
        // Initialize Select2 on all dropdowns
        $('.lwp-store-selector-dropdown').select2();
        <?php endif; ?>
    });
</script>
<?php else: ?>
<script>
    jQuery(document).ready(function($) {
        <?php if(isset($options["use_select2"]) && $options["use_select2"]==="yes"): ?>
        // Initialize Select2 on dropdown
        $('#lwp-store-selector-modal-dropdown').select2();
        <?php endif; ?>
        
        // Update hidden field when selection changes
        $('#lwp-store-selector-modal-dropdown').on('change', function() {
            var selectedValue = $(this).val();
            if (selectedValue) {
                $('#lwp-selected-store').val(selectedValue);
            }
        });
    });
</script>

<style> 
<?php echo $options["lwp_popup_custom_css"]; ?>
</style>
<?php endif; ?>