<?php

/**
 * Location Managers Admin Page
 * 
 * @package Multi Location Product & Inventory Management
 * @since 1.0.4
 */

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Location_Managers
{

    public function __construct()
    {
        add_action('wp_ajax_mulopimfwc_create_location_manager', [$this, 'create_location_manager']);
        add_action('wp_ajax_mulopimfwc_update_manager_permissions', [$this, 'update_manager_permissions']);
        add_action('wp_ajax_mulopimfwc_delete_location_manager', [$this, 'delete_location_manager']);
        add_action('wp_ajax_mulopimfwc_search_users', [$this, 'search_users']);

        // Create location manager role on plugin activation
        add_action('init', [$this, 'create_location_manager_role']);

        // Restrict admin access for location managers (FIXED)
        add_action('admin_init', [$this, 'restrict_location_manager_access']);

        add_action('admin_menu', [$this,  'restrict_location_manager_menus'], 999);

        // Add location manager meta box to user profile
        add_action('show_user_profile', [$this, 'add_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_user_profile_fields']);
        add_action('personal_options_update', [$this, 'save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);

        // Redirect location managers to admin dashboard instead of my-account
        add_action('wp_login', [$this, 'redirect_location_manager_after_login'], 10, 2);
    }

    public function redirect_location_manager_after_login($user_login, $user)
    {
        if (in_array('mulopimfwc_location_manager', $user->roles)) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }


    /**
     * Create location manager role
     */
    public function create_location_manager_role()
    {
        if (!get_role('mulopimfwc_location_manager')) {
            // Get shop manager capabilities as base
            $shop_manager = get_role('shop_manager');
            $shop_manager_caps = $shop_manager ? $shop_manager->capabilities : [];

            // Base capabilities for location manager (similar to shop manager)
            $base_caps = [
                // Core WordPress capabilities
                'read' => true,
                'level_0' => true,
                'level_1' => true,
                'level_2' => true,
                'level_3' => true,
                'level_4' => true,
                'level_5' => true,

                // Dashboard and admin access
                'view_admin_dashboard' => true,
                'access_dashboard' => true,

                // Admin bar visibility (IMPORTANT for wp admin bar)
                'show_admin_bar_front' => true,
                'read_private_pages' => true,
                'read_private_posts' => true,

                // Profile management
                'edit_profile' => true,
                'read_profile' => true,

                // WooCommerce capabilities (same as shop manager)
                'view_woocommerce_reports' => false,
                'read_shop_orders' => true,
                'edit_shop_orders' => true,
                'publish_shop_orders' => true,
                'read_private_shop_orders' => true,
                'edit_shop_order' => true,
                'edit_others_shop_orders' => true,
                'edit_published_shop_orders' => true,
                'delete_shop_orders' => true,
                'delete_published_shop_orders' => true,
                'delete_others_shop_orders' => true,
                'manage_woocommerce' => true,

                // Product capabilities
                'read_products' => true,
                'edit_products' => true,
                'publish_products' => true,
                'edit_published_products' => true,
                'edit_others_products' => true,
                'delete_products' => true,
                'delete_published_products' => true,
                'delete_others_products' => true,
                'manage_product_terms' => true,
                'edit_product_terms' => true,
                'delete_product_terms' => true,
                'assign_product_terms' => true,

                // Media capabilities
                'upload_files' => true,
                'edit_files' => true,

                // Customer/User management (limited)
                'list_users' => false,
                'edit_users' => false,
                'create_users' => false,

                // Coupon capabilities
                'read_shop_coupons' => true,
                'edit_shop_coupons' => true,
                'publish_shop_coupons' => true,
                'edit_published_shop_coupons' => true,
                'delete_shop_coupons' => true,

                // our plugin page access
                'manage_multi_location_inventory' => true,
                'manage_location_stock' => true,
                'access_location_management' => true,

                // Prevent dangerous capabilities
                'manage_options' => true,
                'activate_plugins' => false,
                'edit_theme_options' => false,
                'update_core' => false,
                'update_plugins' => false,
                'update_themes' => false,
                'install_plugins' => false,
                'install_themes' => false,
                'delete_themes' => false,
                'delete_plugins' => false,
                'edit_files' => false,
                'delete_users' => false,
                'remove_users' => false,
                'promote_users' => false,
                'edit_dashboard' => false,
            ];

            // Merge with shop manager capabilities if available
            if (!empty($shop_manager_caps)) {
                $capabilities = array_merge($shop_manager_caps, $base_caps);

                // Remove dangerous capabilities
                $dangerous_caps = [
                    'activate_plugins',
                    'edit_theme_options',
                    'update_core',
                    'update_plugins',
                    'update_themes',
                    'install_plugins',
                    'install_themes',
                    'delete_themes',
                    'delete_plugins',
                    'edit_files',
                    'delete_users',
                    'remove_users',
                    'promote_users',
                    'edit_dashboard'
                ];

                foreach ($dangerous_caps as $cap) {
                    $capabilities[$cap] = false;
                }
            } else {
                $capabilities = $base_caps;
            }

            add_role(
                'mulopimfwc_location_manager',
                __('Location Manager', 'multi-location-product-and-inventory-management'),
                $capabilities
            );
        }
    }

    /**
     * Get all available capabilities
     */
    private function get_available_capabilities()
    {
        return [
            'manage_inventory' => __('Manage Inventory', 'multi-location-product-and-inventory-management'),
            'view_orders' => __('View Orders', 'multi-location-product-and-inventory-management'),
            'manage_orders' => __('Manage Orders', 'multi-location-product-and-inventory-management'),
            'view_products' => __('View Products', 'multi-location-product-and-inventory-management'),
            'manage_products' => __('Manage Products', 'multi-location-product-and-inventory-management'),
            'run_reports' => __('Run Reports', 'multi-location-product-and-inventory-management'),
        ];
    }

    /**
     * Get location managers
     */
    private function get_location_managers()
    {
        return get_users([
            'role' => 'mulopimfwc_location_manager',
            'meta_query' => [
                [
                    'key' => 'mulopimfwc_assigned_locations',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
    }

    /**
     * Admin page content
     */
    public function admin_page()
    {
        global $mulopimfwc_locations;

        $managers = $this->get_location_managers();
        $locations = $mulopimfwc_locations;
        $capabilities = $this->get_available_capabilities();
        $options = get_option('mulopimfwc_display_options', []);
        $global_capabilities = isset($options['location_manager_capabilities']) ? $options['location_manager_capabilities'] : [];

?>
        <div class="wrap">
            <h1><?php echo esc_html__('Location Managers', 'multi-location-product-and-inventory-management'); ?></h1>

            <div class="mulopimfwc-location-managers">
                <!-- Add New Manager Button -->
                <div class="mulopimfwc-manager-header">
                    <button type="button" class="button button-primary" id="mulopimfwc-add-manager-btn">
                        <?php echo esc_html__('Add New Location Manager', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                </div>

                <!-- Managers List -->
                <div class="mulopimfwc-managers-list">
                    <h2><?php echo esc_html__('Current Location Managers', 'multi-location-product-and-inventory-management'); ?></h2>

                    <?php if (empty($managers)): ?>
                        <div class="mulopimfwc-no-managers">
                            <p><?php echo esc_html__('No location managers found. Create your first location manager to get started.', 'multi-location-product-and-inventory-management'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="mulopimfwc-managers-grid">
                            <?php foreach ($managers as $manager): ?>
                                <?php
                                $assigned_locations = get_user_meta($manager->ID, 'mulopimfwc_assigned_locations', true);
                                $manager_capabilities = get_user_meta($manager->ID, 'mulopimfwc_manager_capabilities', true);
                                if (!is_array($assigned_locations)) $assigned_locations = [];
                                if (!is_array($manager_capabilities)) $manager_capabilities = $global_capabilities;
                                ?>
                                <div class="mulopimfwc-manager-card" data-manager-id="<?php echo esc_attr($manager->ID); ?>">
                                    <div class="manager-header">
                                        <div class="manager-info">
                                            <?php echo get_avatar($manager->ID, 50); ?>
                                            <div class="manager-details">
                                                <h3><?php echo esc_html($manager->display_name); ?></h3>
                                                <p class="manager-email"><?php echo esc_html($manager->user_email); ?></p>
                                            </div>
                                        </div>
                                        <div class="manager-actions">
                                            <button type="button" class="button button-small mulopimfwc-edit-manager" data-manager-id="<?php echo esc_attr($manager->ID); ?>">
                                                <?php echo esc_html__('Edit', 'multi-location-product-and-inventory-management'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete mulopimfwc-delete-manager" data-manager-id="<?php echo esc_attr($manager->ID); ?>">
                                                <?php echo esc_html__('Delete', 'multi-location-product-and-inventory-management'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="manager-locations">
                                        <h4><?php echo esc_html__('Assigned Locations:', 'multi-location-product-and-inventory-management'); ?></h4>
                                        <?php if (empty($assigned_locations)): ?>
                                            <p class="no-locations"><?php echo esc_html__('No locations assigned', 'multi-location-product-and-inventory-management'); ?></p>
                                        <?php else: ?>
                                            <ul class="location-list">
                                                <?php foreach ($assigned_locations as $location_slug): ?>
                                                    <?php
                                                    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
                                                    if ($location):
                                                    ?>
                                                        <li><span class="location-tag"><?php echo esc_html($location->name); ?></span></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>

                                    <div class="manager-capabilities">
                                        <h4><?php echo esc_html__('Permissions:', 'multi-location-product-and-inventory-management'); ?></h4>
                                        <?php if (empty($manager_capabilities)): ?>
                                            <p class="no-capabilities"><?php echo esc_html__('No specific permissions set (using global defaults)', 'multi-location-product-and-inventory-management'); ?></p>
                                        <?php else: ?>
                                            <ul class="capability-list">
                                                <?php foreach ($manager_capabilities as $capability): ?>
                                                    <?php if (isset($capabilities[$capability])): ?>
                                                        <li><span class="capability-tag"><?php echo esc_html($capabilities[$capability]); ?></span></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Add/Edit Manager Modal -->
        <div id="mulopimfwc-manager-modal" class="mulopimfwc-modal" style="display: none;">
            <div class="mulopimfwc-modal-content">
                <div class="mulopimfwc-modal-header">
                    <h2 id="mulopimfwc-modal-title"><?php echo esc_html__('Add New Location Manager', 'multi-location-product-and-inventory-management'); ?></h2>
                    <button type="button" class="mulopimfwc-modal-close">&times;</button>
                </div>

                <form id="mulopimfwc-manager-form" method="post">
                    <input type="hidden" id="manager-id" name="manager_id" value="">
                    <input type="hidden" id="action-type" name="action_type" value="create">

                    <div class="mulopimfwc-form-row">
                        <label for="user-search"><?php echo esc_html__('Select User:', 'multi-location-product-and-inventory-management'); ?></label>
                        <div class="user-search-container">
                            <input type="text" id="user-search" placeholder="<?php echo esc_attr__('Search users by name or email...', 'multi-location-product-and-inventory-management'); ?>">
                            <input type="hidden" id="selected-user-id" name="user_id" value="">
                            <div id="user-search-results" class="search-results"></div>
                        </div>
                        <div id="create-new-user" style="display: none;">
                            <hr>
                            <h4><?php echo esc_html__('Or Create New User:', 'multi-location-product-and-inventory-management'); ?></h4>
                            <div class="mulopimfwc-form-row">
                                <label for="new-username"><?php echo esc_html__('Username:', 'multi-location-product-and-inventory-management'); ?></label>
                                <input type="text" id="new-username" name="new_username" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-email"><?php echo esc_html__('Email:', 'multi-location-product-and-inventory-management'); ?></label>
                                <input type="email" id="new-email" name="new_email" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-first-name"><?php echo esc_html__('First Name:', 'multi-location-product-and-inventory-management'); ?></label>
                                <input type="text" id="new-first-name" name="new_first_name" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-last-name"><?php echo esc_html__('Last Name:', 'multi-location-product-and-inventory-management'); ?></label>
                                <input type="text" id="new-last-name" name="new_last_name" value="">
                            </div>
                        </div>
                        <button type="button" id="toggle-create-user" class="button button-secondary">
                            <?php echo esc_html__('Create New User Instead', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                    </div>

                    <div class="mulopimfwc-form-row">
                        <label><?php echo esc_html__('Assign Locations:', 'multi-location-product-and-inventory-management'); ?></label>
                        <div class="location-checkboxes">
                            <?php if (!empty($locations)): ?>
                                <?php foreach ($locations as $location): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="assigned_locations[]" value="<?php echo esc_attr($location->slug); ?>">
                                        <?php echo esc_html($location->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p><?php echo esc_html__('No locations available. Please create locations first.', 'multi-location-product-and-inventory-management'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mulopimfwc-form-row">
                        <label><?php echo esc_html__('Individual Permissions:', 'multi-location-product-and-inventory-management'); ?></label>
                        <p class="description"><?php echo esc_html__('Leave unchecked to use global default permissions.', 'multi-location-product-and-inventory-management'); ?></p>
                        <div class="capability-checkboxes">
                            <?php foreach ($capabilities as $cap_key => $cap_label): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="manager_capabilities[]" value="<?php echo esc_attr($cap_key); ?>">
                                    <?php echo esc_html($cap_label); ?>
                                    <?php if (in_array($cap_key, $global_capabilities)): ?>
                                        <span class="global-default">(<?php echo esc_html__('Global Default', 'multi-location-product-and-inventory-management'); ?>)</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mulopimfwc-modal-footer">
                        <button type="button" class="button button-secondary mulopimfwc-modal-close">
                            <?php echo esc_html__('Cancel', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                        <button type="submit" class="button button-primary" id="mulopimfwc-save-manager">
                            <?php echo esc_html__('Save Manager', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php wp_nonce_field('mulopimfwc_location_managers_nonce', 'mulopimfwc_location_managers_nonce'); ?>

        <style>
            .mulopimfwc-location-managers {
                margin-top: 20px;
            }

            .mulopimfwc-manager-header {
                margin-bottom: 20px;
            }

            .mulopimfwc-managers-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
            }

            .mulopimfwc-manager-card {
                background: #fff;
                border: 1px solid #ddd;
                padding: 20px;
                border-radius: 8px;
            }

            .manager-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
            }

            .manager-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .manager-details h3 {
                margin: 0;
                font-size: 16px;
            }

            .manager-email {
                margin: 5px 0 0;
                color: #666;
                font-size: 14px;
            }

            .manager-actions {
                display: flex;
                gap: 5px;
            }

            .manager-locations,
            .manager-capabilities {
                margin-bottom: 15px;
            }

            .manager-locations h4,
            .manager-capabilities h4 {
                margin: 0 0 10px;
                font-size: 14px;
                color: #333;
            }

            .location-list,
            .capability-list {
                list-style: none;
                padding: 0;
                margin: 0;
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .location-tag,
            .capability-tag {
                background: #f0f0f1;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                color: #333;
            }

            .capability-tag {
                background: #e7f3ff;
                color: #0073aa;
            }

            .no-locations,
            .no-capabilities {
                color: #666;
                font-style: italic;
                margin: 0;
            }

            .mulopimfwc-no-managers {
                text-align: center;
                padding: 40px;
                background: #f9f9f9;
                border-radius: 8px;
            }

            /* Modal Styles */
            .mulopimfwc-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .mulopimfwc-modal-content {
                background-color: #fff;
                margin: 5% auto;
                padding: 0;
                border-radius: 8px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
            }

            .mulopimfwc-modal-header {
                padding: 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .mulopimfwc-modal-header h2 {
                margin: 0;
            }

            .mulopimfwc-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
            }

            .mulopimfwc-modal form {
                padding: 20px;
            }

            .mulopimfwc-form-row {
                margin-bottom: 20px;
            }

            .mulopimfwc-form-row label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .mulopimfwc-form-row input[type="text"],
            .mulopimfwc-form-row input[type="email"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .location-checkboxes,
            .capability-checkboxes {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 10px;
            }

            .checkbox-label {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
            }

            .checkbox-label:hover {
                background: #f9f9f9;
            }

            .global-default {
                font-size: 11px;
                color: #666;
                font-style: italic;
            }

            .mulopimfwc-modal-footer {
                padding: 20px;
                border-top: 1px solid #ddd;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }

            /* User Search */
            .user-search-container {
                position: relative;
            }

            .search-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #ddd;
                border-top: none;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
            }

            .search-result-item {
                padding: 10px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            }

            .search-result-item:hover {
                background: #f9f9f9;
            }

            .search-result-item:last-child {
                border-bottom: none;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                let searchTimeout;
                let isEditMode = false;

                // Add manager button
                $('#mulopimfwc-add-manager-btn').on('click', function() {
                    isEditMode = false;
                    resetForm();
                    $('#mulopimfwc-modal-title').text('<?php echo esc_js(__('Add New Location Manager', 'multi-location-product-and-inventory-management')); ?>');
                    $('#action-type').val('create');
                    $('#mulopimfwc-manager-modal').show();
                });

                // Edit manager button
                $(document).on('click', '.mulopimfwc-edit-manager', function() {
                    isEditMode = true;
                    const managerId = $(this).data('manager-id');
                    loadManagerData(managerId);
                });

                // Delete manager button
                $(document).on('click', '.mulopimfwc-delete-manager', function() {
                    if (confirm('<?php echo esc_js(__('Are you sure you want to delete this location manager?', 'multi-location-product-and-inventory-management')); ?>')) {
                        const managerId = $(this).data('manager-id');
                        deleteManager(managerId);
                    }
                });

                // Close modal
                $(document).on('click', '.mulopimfwc-modal-close', function() {
                    $('#mulopimfwc-manager-modal').hide();
                });

                // Toggle create new user
                $('#toggle-create-user').on('click', function() {
                    $('#create-new-user').toggle();
                    const isVisible = $('#create-new-user').is(':visible');
                    $(this).text(isVisible ? '<?php echo esc_js(__('Select Existing User Instead', 'multi-location-product-and-inventory-management')); ?>' : '<?php echo esc_js(__('Create New User Instead', 'multi-location-product-and-inventory-management')); ?>');
                });

                // User search
                $('#user-search').on('input', function() {
                    const query = $(this).val();
                    clearTimeout(searchTimeout);

                    if (query.length < 2) {
                        $('#user-search-results').empty().hide();
                        return;
                    }

                    searchTimeout = setTimeout(() => {
                        searchUsers(query);
                    }, 300);
                });

                // Form submission
                $('#mulopimfwc-manager-form').on('submit', function(e) {
                    e.preventDefault();
                    saveManager();
                });

                // Functions
                function resetForm() {
                    $('#mulopimfwc-manager-form')[0].reset();
                    $('#manager-id').val('');
                    $('#selected-user-id').val('');
                    $('#user-search-results').empty().hide();
                    $('#create-new-user').hide();
                    $('#toggle-create-user').text('<?php echo esc_js(__('Create New User Instead', 'multi-location-product-and-inventory-management')); ?>');
                }

                function loadManagerData(managerId) {
                    // This would typically load data via AJAX
                    // For now, we'll just show the modal
                    $('#mulopimfwc-modal-title').text('<?php echo esc_js(__('Edit Location Manager', 'multi-location-product-and-inventory-management')); ?>');
                    $('#manager-id').val(managerId);
                    $('#action-type').val('edit');
                    $('#mulopimfwc-manager-modal').show();
                }

                function searchUsers(query) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mulopimfwc_search_users',
                            query: query,
                            nonce: $('#mulopimfwc_location_managers_nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                displaySearchResults(response.data.users);
                            }
                        }
                    });
                }

                function displaySearchResults(users) {
                    const resultsContainer = $('#user-search-results');
                    resultsContainer.empty();

                    if (users.length === 0) {
                        resultsContainer.html('<div class="search-result-item"><?php echo esc_js(__('No users found', 'multi-location-product-and-inventory-management')); ?></div>');
                    } else {
                        users.forEach(user => {
                            const item = $(`<div class="search-result-item" data-user-id="${user.ID}">
                            <strong>${user.display_name}</strong> (${user.user_email})
                        </div>`);

                            item.on('click', function() {
                                $('#selected-user-id').val(user.ID);
                                $('#user-search').val(user.display_name);
                                resultsContainer.empty().hide();
                            });

                            resultsContainer.append(item);
                        });
                    }

                    resultsContainer.show();
                }

                function saveManager() {
                    const formData = new FormData($('#mulopimfwc-manager-form')[0]);
                    formData.append('action', 'mulopimfwc_create_location_manager');
                    formData.append('nonce', $('#mulopimfwc_location_managers_nonce').val());

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Error saving manager', 'multi-location-product-and-inventory-management')); ?>');
                            }
                        }
                    });
                }

                function deleteManager(managerId) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mulopimfwc_delete_location_manager',
                            manager_id: managerId,
                            nonce: $('#mulopimfwc_location_managers_nonce').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Error deleting manager', 'multi-location-product-and-inventory-management')); ?>');
                            }
                        }
                    });
                }
            });
        </script>
    <?php
    }

    /**
     * Search users AJAX handler
     */
    public function search_users()
    {
        check_ajax_referer('mulopimfwc_location_managers_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management')]);
        }

        $query = sanitize_text_field($_POST['query']);

        $users = get_users([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'user_nicename', 'display_name'],
            'exclude' => get_users(['role' => 'mulopimfwc_location_manager', 'fields' => 'ID']),
            'number' => 10
        ]);

        $user_data = [];
        foreach ($users as $user) {
            $user_data[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email
            ];
        }

        wp_send_json_success(['users' => $user_data]);
    }

    /**
     * Create/Update location manager AJAX handler
     */
    public function create_location_manager()
    {
        check_ajax_referer('mulopimfwc_location_managers_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management')]);
        }

        $action_type = sanitize_text_field($_POST['action_type']);
        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $assigned_locations = isset($_POST['assigned_locations']) ? array_map('sanitize_text_field', $_POST['assigned_locations']) : [];
        $manager_capabilities = isset($_POST['manager_capabilities']) ? array_map('sanitize_text_field', $_POST['manager_capabilities']) : [];

        try {
            if ($action_type === 'create') {
                // Check if creating new user
                if (empty($user_id) && !empty($_POST['new_username'])) {
                    $username = sanitize_user($_POST['new_username']);
                    $email = sanitize_email($_POST['new_email']);
                    $first_name = sanitize_text_field($_POST['new_first_name']);
                    $last_name = sanitize_text_field($_POST['new_last_name']);

                    // Check if username or email already exists
                    if (username_exists($username) || email_exists($email)) {
                        wp_send_json_error(['message' => __('Username or email already exists', 'multi-location-product-and-inventory-management')]);
                    }

                    // Create new user
                    $user_id = wp_create_user($username, wp_generate_password(), $email);

                    if (is_wp_error($user_id)) {
                        wp_send_json_error(['message' => $user_id->get_error_message()]);
                    }

                    // Update user meta
                    wp_update_user([
                        'ID' => $user_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'display_name' => trim($first_name . ' ' . $last_name)
                    ]);

                    // Send password reset email
                    wp_send_new_user_notifications($user_id, 'user');
                }

                if (empty($user_id)) {
                    wp_send_json_error(['message' => __('Please select or create a user', 'multi-location-product-and-inventory-management')]);
                }

                // Change user role to location manager
                $user = new WP_User($user_id);
                $user->set_role('mulopimfwc_location_manager');
            } else {
                // Edit mode
                if (empty($manager_id)) {
                    wp_send_json_error(['message' => __('Invalid manager ID', 'multi-location-product-and-inventory-management')]);
                }
                $user_id = $manager_id;
            }

            // Save assigned locations
            update_user_meta($user_id, 'mulopimfwc_assigned_locations', $assigned_locations);

            // Save manager capabilities
            update_user_meta($user_id, 'mulopimfwc_manager_capabilities', $manager_capabilities);

            wp_send_json_success(['message' => __('Location manager saved successfully', 'multi-location-product-and-inventory-management')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Delete location manager AJAX handler
     */
    public function delete_location_manager()
    {
        check_ajax_referer('mulopimfwc_location_managers_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management')]);
        }

        $manager_id = intval($_POST['manager_id']);

        if (empty($manager_id)) {
            wp_send_json_error(['message' => __('Invalid manager ID', 'multi-location-product-and-inventory-management')]);
        }

        // Remove location manager role and revert to subscriber
        $user = new WP_User($manager_id);
        $user->set_role('subscriber');

        // Remove location manager meta
        delete_user_meta($manager_id, 'mulopimfwc_assigned_locations');
        delete_user_meta($manager_id, 'mulopimfwc_manager_capabilities');

        wp_send_json_success(['message' => __('Location manager deleted successfully', 'multi-location-product-and-inventory-management')]);
    }

    /**
     * Update manager permissions AJAX handler
     */
    public function update_manager_permissions()
    {
        check_ajax_referer('mulopimfwc_location_managers_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management')]);
        }

        $manager_id = intval($_POST['manager_id']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : [];

        if (empty($manager_id)) {
            wp_send_json_error(['message' => __('Invalid manager ID', 'multi-location-product-and-inventory-management')]);
        }

        update_user_meta($manager_id, 'mulopimfwc_manager_capabilities', $capabilities);

        wp_send_json_success(['message' => __('Permissions updated successfully', 'multi-location-product-and-inventory-management')]);
    }

    /**
     * Restrict admin access for location managers (FIXED VERSION)
     */
    public function restrict_location_manager_access()
    {
        // Don't restrict administrators or shop managers
        if (current_user_can('administrator') || current_user_can('shop_manager')) {
            return;
        }

        // Only apply restrictions to location managers
        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', $user->roles)) {
            return;
        }

        $current_screen = get_current_screen();

        // If we're not in the admin, return
        if (!$current_screen) {
            return;
        }

        // Pages that location managers are NOT allowed to access
        $restricted_pages = [
            'themes',              // Themes
            'theme-editor',        // Theme editor
            'customize',           // Customizer
            'widgets',             // Widgets
            'nav-menus',           // Menus
            'plugins',             // Plugins
            'plugin-editor',       // Plugin editor
            'tools',               // Tools
            'import',              // Import
            'export',              // Export
            'options-general',     // Settings
            'options-writing',     // Writing settings
            'options-reading',     // Reading settings
            'options-discussion',  // Discussion settings
            'options-media',       // Media settings
            'options-permalink',   // Permalink settings
            'site-health',         // Site health
            'privacy',             // Privacy
            'update-core',         // Updates
        ];

        // Check if current screen is restricted
        $is_restricted = false;
        if ($current_screen) {
            if (
                in_array($current_screen->id, $restricted_pages) ||
                in_array($current_screen->base, $restricted_pages) ||
                strpos($current_screen->id, 'theme') !== false ||
                strpos($current_screen->id, 'plugin') !== false
            ) {
                $is_restricted = true;
            }
        }

        // If accessing restricted area, redirect to dashboard
        if ($is_restricted) {
            wp_redirect(admin_url('index.php?restricted=1'));
            exit;
        }
    }


    public function restrict_location_manager_menus()
    {
        if (!current_user_can('mulopimfwc_location_manager')) return;

        $manager = wp_get_current_user();
        $manager_capabilities = get_user_meta($manager->ID, 'mulopimfwc_manager_capabilities', true);

        // If no capabilities are set, deny access to everything except dashboard and profile
        if (empty($manager_capabilities) || !is_array($manager_capabilities)) {
            $manager_capabilities = [];
        }

        // Get all global menu items
        global $menu, $submenu;

        // Base allowed pages (always accessible)
        $base_allowed_pages = [
            'index.php', // Dashboard
            'profile.php', // User profile
        ];

        // Capability-based page mapping
        $capability_pages = [
            'manage_inventory' => [
                'multi-location-product-and-inventory-management',
                'location-stock-management',
                'upload.php', // Media library for product images
                'media-new.php',
                'Library',
                'upload_files',
            ],
            'view_products' => [
                'edit.php?post_type=product',
                'products',
                'edit_products',
            ],
            'manage_products' => [
                'edit.php?post_type=product',
                'post-new.php?post_type=product',
                'edit-tags.php?taxonomy=product_brand&amp;post_type=product',
                'edit-tags.php?taxonomy=product_cat&amp;post_type=product',
                'edit-tags.php?taxonomy=product_tag&amp;post_type=product',
                'product_attributes',
                'products',
                'edit_products',
                'edit.php',
                'post-new.php',
                'edit-tags.php',
                'manage_product_terms',
                'upload.php', // Media library for product management
                'media-new.php',
                'Library',
                'upload_files',
            ],
            'view_orders' => [
                'wc-orders',
                'edit.php?post_type=shop_order',
                'woocommerce',
            ],
            'manage_orders' => [
                'wc-orders',
                'edit.php?post_type=shop_order',
                'woocommerce',
                'coupons-moved', // If they can manage orders, they might need coupons
            ],
            'run_reports' => [
                'woocommerce', // WooCommerce main menu usually contains reports
                // Add specific report pages here if you have custom ones
            ],
        ];

        // Build allowed pages based on capabilities
        $allowed_pages = $base_allowed_pages;

        foreach ($manager_capabilities as $capability) {
            if (isset($capability_pages[$capability])) {
                $allowed_pages = array_merge($allowed_pages, $capability_pages[$capability]);
            }
        }

        // Remove duplicates
        $allowed_pages = array_unique($allowed_pages);

        // Remove all top-level menus except allowed ones
        foreach ($menu as $index => $item) {
            if (!in_array($item[2], $allowed_pages)) {
                remove_menu_page($item[2]);
            }
        }

        // Remove all submenus except for allowed pages
        foreach ($submenu as $parent => $items) {
            if (!in_array($parent, $allowed_pages)) {
                remove_menu_page($parent);
                continue;
            }

            // Clean up submenus for allowed pages
            foreach ($items as $index => $subitem) {
                if (!in_array($subitem[2], $allowed_pages)) {
                    unset($submenu[$parent][$index]);
                }
            }
        }
    }

    /**
     * Add user profile fields for location manager settings
     */
    public function add_user_profile_fields($user)
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (!in_array('mulopimfwc_location_manager', $user->roles)) {
            return;
        }

        global $mulopimfwc_locations;
        $assigned_locations = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);
        $manager_capabilities = get_user_meta($user->ID, 'mulopimfwc_manager_capabilities', true);
        $capabilities = $this->get_available_capabilities();

        if (!is_array($assigned_locations)) $assigned_locations = [];
        if (!is_array($manager_capabilities)) $manager_capabilities = [];
    ?>

        <h3><?php echo esc_html__('Location Manager Settings', 'multi-location-product-and-inventory-management'); ?></h3>

        <table class="form-table">
            <tr>
                <th><label><?php echo esc_html__('Assigned Locations', 'multi-location-product-and-inventory-management'); ?></label></th>
                <td>
                    <?php if (!empty($mulopimfwc_locations)): ?>
                        <?php foreach ($mulopimfwc_locations as $location): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="mulopimfwc_assigned_locations[]" value="<?php echo esc_attr($location->slug); ?>" <?php checked(in_array($location->slug, $assigned_locations)); ?>>
                                <?php echo esc_html($location->name); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?php echo esc_html__('No locations available', 'multi-location-product-and-inventory-management'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label><?php echo esc_html__('Manager Capabilities', 'multi-location-product-and-inventory-management'); ?></label></th>
                <td>
                    <?php foreach ($capabilities as $cap_key => $cap_label): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="mulopimfwc_manager_capabilities[]" value="<?php echo esc_attr($cap_key); ?>" <?php checked(in_array($cap_key, $manager_capabilities)); ?>>
                            <?php echo esc_html($cap_label); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description"><?php echo esc_html__('Individual permissions for this manager. Leave unchecked to use global defaults.', 'multi-location-product-and-inventory-management'); ?></p>
                </td>
            </tr>
        </table>

<?php
    }

    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id)
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $assigned_locations = isset($_POST['mulopimfwc_assigned_locations']) ? array_map('sanitize_text_field', $_POST['mulopimfwc_assigned_locations']) : [];
        $manager_capabilities = isset($_POST['mulopimfwc_manager_capabilities']) ? array_map('sanitize_text_field', $_POST['mulopimfwc_manager_capabilities']) : [];

        update_user_meta($user_id, 'mulopimfwc_assigned_locations', $assigned_locations);
        update_user_meta($user_id, 'mulopimfwc_manager_capabilities', $manager_capabilities);
    }

    /**
     * Check if current user is location manager for specific location
     */
    public static function is_location_manager_for($location_slug = '')
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', $user->roles)) {
            return false;
        }

        if (empty($location_slug)) {
            return true; // User is a location manager, location not specified
        }

        $assigned_locations = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);
        if (!is_array($assigned_locations)) {
            return false;
        }

        return in_array($location_slug, $assigned_locations);
    }

    /**
     * Check if current user has specific capability
     */
    public static function user_has_capability($capability)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', $user->roles)) {
            return current_user_can('manage_woocommerce'); // Admin check
        }

        // Get individual capabilities
        $manager_capabilities = get_user_meta($user->ID, 'mulopimfwc_manager_capabilities', true);
        if (!is_array($manager_capabilities) || empty($manager_capabilities)) {
            // Use global capabilities
            $options = get_option('mulopimfwc_display_options', []);
            $manager_capabilities = isset($options['location_manager_capabilities']) ? $options['location_manager_capabilities'] : [];
        }

        return in_array($capability, $manager_capabilities);
    }

    /**
     * Get assigned locations for current user
     */
    public static function get_user_assigned_locations()
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', $user->roles)) {
            return []; // Not a location manager
        }

        $assigned_locations = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);
        return is_array($assigned_locations) ? $assigned_locations : [];
    }
}

// Initialize the class
new MULOPIMFWC_Location_Managers();







/**
 * filter orders based on location manager's assigned locations
 */

class MULOPIMFWC_Order_Filter
{
    public function __construct()
    {
        // Filter orders in admin list table
        add_action('pre_get_posts', [$this, 'filter_orders_by_location_manager']);

        // Filter orders in WooCommerce HPOS (High Performance Order Storage) if enabled
        add_filter('woocommerce_order_query_args', [$this, 'filter_hpos_orders_by_location_manager']);

        // Add location filter dropdown in orders admin
        add_action('restrict_manage_posts', [$this, 'add_location_filter_dropdown']);

        // Handle location filter dropdown
        add_action('pre_get_posts', [$this, 'handle_location_filter_dropdown']);
    }

    /**
     * Filter orders for location managers (Traditional Posts)
     */
    public function filter_orders_by_location_manager($query)
    {
        // Only apply in admin area
        if (!is_admin()) {
            return;
        }

        // Only apply to main query
        if (!$query->is_main_query()) {
            return;
        }

        // Only apply to shop_order post type
        if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'shop_order') {
            return;
        }

        // Check if current user is a location manager
        if (!$this->is_current_user_location_manager()) {
            return;
        }

        // Get assigned locations for current user
        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            // If no locations assigned, show no orders
            $query->set('post__in', [0]);
            return;
        }

        // Add meta query to filter by store location
        $meta_query = $query->get('meta_query') ?: [];

        $meta_query[] = [
            'key' => '_store_location',
            'value' => $assigned_locations,
            'compare' => 'IN'
        ];

        $query->set('meta_query', $meta_query);
    }

    /**
     * Filter orders for location managers (HPOS - High Performance Order Storage)
     */
    public function filter_hpos_orders_by_location_manager($query_args)
    {
        // Only apply in admin area
        if (!is_admin()) {
            return $query_args;
        }

        // Check if current user is a location manager
        if (!$this->is_current_user_location_manager()) {
            return $query_args;
        }

        // Get assigned locations for current user
        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            // If no locations assigned, show no orders
            $query_args['post__in'] = [0];
            return $query_args;
        }

        // Add meta query for HPOS
        if (!isset($query_args['meta_query'])) {
            $query_args['meta_query'] = [];
        }

        $query_args['meta_query'][] = [
            'key' => '_store_location',
            'value' => $assigned_locations,
            'compare' => 'IN'
        ];

        return $query_args;
    }

    /**
     * Add location filter dropdown for admins (not location managers)
     */
    public function add_location_filter_dropdown()
    {
        global $typenow;

        // Only show on orders page
        if ($typenow !== 'shop_order') {
            return;
        }

        // Don't show for location managers (they already have filtered view)
        if ($this->is_current_user_location_manager()) {
            return;
        }

        // Only show for users who can manage woocommerce
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        global $mulopimfwc_locations;

        if (empty($mulopimfwc_locations)) {
            return;
        }

        $selected_location = isset($_GET['filter_by_location']) ? sanitize_text_field($_GET['filter_by_location']) : '';

        echo '<select name="filter_by_location" id="filter_by_location">';
        echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management') . '</option>';

        foreach ($mulopimfwc_locations as $location) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($location->slug),
                selected($selected_location, $location->slug, false),
                esc_html($location->name)
            );
        }

        echo '</select>';
    }

    /**
     * Handle location filter dropdown selection
     */
    public function handle_location_filter_dropdown($query)
    {
        // Only apply in admin area
        if (!is_admin()) {
            return;
        }

        // Only apply to main query
        if (!$query->is_main_query()) {
            return;
        }

        // Only apply to shop_order post type
        if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'shop_order') {
            return;
        }

        // Don't apply if user is location manager (they have their own filtering)
        if ($this->is_current_user_location_manager()) {
            return;
        }

        // Check if location filter is set
        if (empty($_GET['filter_by_location'])) {
            return;
        }

        $filter_location = sanitize_text_field($_GET['filter_by_location']);

        // Add meta query to filter by selected location
        $meta_query = $query->get('meta_query') ?: [];

        $meta_query[] = [
            'key' => '_store_location',
            'value' => $filter_location,
            'compare' => '='
        ];

        $query->set('meta_query', $meta_query);
    }

    /**
     * Check if current user is a location manager
     */
    private function is_current_user_location_manager()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('mulopimfwc_location_manager', $user->roles);
    }

    /**
     * Add notice to show current location filtering status
     */
    public function add_location_filter_notice()
    {
        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'edit-shop_order') {
            return;
        }

        if (!$this->is_current_user_location_manager()) {
            return;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            echo '<div class="notice notice-warning"><p>' .
                esc_html__('You are not assigned to any locations. No orders will be displayed.', 'multi-location-product-and-inventory-management') .
                '</p></div>';
            return;
        }

        global $mulopimfwc_locations;
        $location_names = [];

        foreach ($assigned_locations as $location_slug) {
            $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            if ($location) {
                $location_names[] = $location->name;
            }
        }

        if (!empty($location_names)) {
            echo '<div class="notice notice-info"><p>' .
                sprintf(
                    esc_html__('You are viewing orders for: %s', 'multi-location-product-and-inventory-management'),
                    '<strong>' . implode(', ', $location_names) . '</strong>'
                ) .
                '</p></div>';
        }
    }
}

// Initialize the order filter
add_action('init', function () {
    new MULOPIMFWC_Order_Filter();
});

// Also add the notice functionality
add_action('admin_notices', [new MULOPIMFWC_Order_Filter(), 'add_location_filter_notice']);



/**
 * Filter order count in admin menu for location managers
 * This handles both traditional posts and WooCommerce HPOS
 */

class MULOPIMFWC_Order_Count_Filter
{
    public function __construct()
    {
        // Filter post counts for traditional WordPress posts
        add_filter('wp_count_posts', [$this, 'filter_order_count'], 10, 3);

        // Filter WooCommerce HPOS order counts
        add_filter('woocommerce_order_query', [$this, 'filter_wc_order_count_query'], 10, 2);

        // Alternative approach for WooCommerce admin menu counts
        add_filter('woocommerce_menu_order_count', [$this, 'filter_wc_menu_order_count']);
    }

    /**
     * Filter order count for traditional WordPress posts
     */
    public function filter_order_count($counts, $type, $perm)
    {
        if ($type !== 'shop_order' || !is_admin()) {
            return $counts;
        }

        if (!$this->is_current_user_location_manager()) {
            return $counts;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            // Reset all counts to 0 if no locations assigned
            $empty_counts = new stdClass();
            foreach ($counts as $status => $count) {
                $empty_counts->$status = 0;
            }
            return $empty_counts;
        }

        // Get filtered counts for each status
        $filtered_counts = new stdClass();
        foreach ($counts as $status => $count) {
            $filtered_counts->$status = $this->get_filtered_order_count($status, $assigned_locations);
        }

        return $filtered_counts;
    }

    /**
     * Get filtered order count for specific status and locations
     */
    private function get_filtered_order_count($status, $assigned_locations)
    {
        global $wpdb;

        // Handle WooCommerce HPOS if enabled
        if ($this->is_hpos_enabled()) {

            // HPOS enabled - query orders table
            $orders_table = $wpdb->prefix . 'wc_orders';
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

            $placeholders = implode(',', array_fill(0, count($assigned_locations), '%s'));
            $query_params = array_merge([$status], $assigned_locations);

            $sql = $wpdb->prepare("
                SELECT COUNT(o.id)
                FROM {$orders_table} o
                INNER JOIN {$orders_meta_table} om ON o.id = om.order_id
                WHERE o.status = %s
                AND om.meta_key = '_store_location'
                AND om.meta_value IN ({$placeholders})
            ", $query_params);

            return (int) $wpdb->get_var($sql);
        } else {
            // Traditional posts table
            $placeholders = implode(',', array_fill(0, count($assigned_locations), '%s'));
            $query_params = array_merge([$status], $assigned_locations);

            $sql = $wpdb->prepare("
                SELECT COUNT(p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status = %s
                AND pm.meta_key = '_store_location'
                AND pm.meta_value IN ({$placeholders})
            ", $query_params);

            return (int) $wpdb->get_var($sql);
        }
    }

    /**
     * Filter WooCommerce HPOS order count query
     */
    public function filter_wc_order_count_query($query, $query_vars)
    {
        if (!is_admin() || !$this->is_current_user_location_manager()) {
            return $query;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            // Force no results
            $query_vars['post__in'] = [0];
            return $query;
        }

        // Add meta query for assigned locations
        if (!isset($query_vars['meta_query'])) {
            $query_vars['meta_query'] = [];
        }

        $query_vars['meta_query'][] = [
            'key' => '_store_location',
            'value' => $assigned_locations,
            'compare' => 'IN'
        ];

        return $query;
    }

    /**
     * Filter WooCommerce menu order count
     */
    public function filter_wc_menu_order_count($count)
    {
        if (!is_admin() || !$this->is_current_user_location_manager()) {
            return $count;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            return 0;
        }

        // Get count of processing orders for assigned locations
        return $this->get_filtered_order_count('wc-processing', $assigned_locations);
    }

    /**
     * Check if WooCommerce HPOS is enabled
     */
    private function is_hpos_enabled()
    {
        // Check if WooCommerce HPOS is available and enabled
        if (!function_exists('wc_get_container')) {
            return false;
        }

        // Multiple ways to check HPOS
        if (function_exists('wc_get_order_datastore')) {
            $datastore = wc_get_order_datastore();
            return is_a($datastore, 'Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore');
        }

        // Alternative check
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        // Fallback check
        if (function_exists('wc_get_container')) {
            try {
                $features_controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\Features\FeaturesController::class);
                return $features_controller->feature_is_enabled('custom_order_tables');
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check if current user is a location manager
     */
    private function is_current_user_location_manager()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('mulopimfwc_location_manager', $user->roles);
    }

    /**
     * Get total order count for all statuses (for dashboard widget)
     */
    public function get_location_manager_total_orders()
    {
        if (!$this->is_current_user_location_manager()) {
            return null;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            return 0;
        }

        global $wpdb;

        // Handle WooCommerce HPOS if enabled
        if ($this->is_hpos_enabled()) {

            $orders_table = $wpdb->prefix . 'wc_orders';
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

            $placeholders = implode(',', array_fill(0, count($assigned_locations), '%s'));

            $sql = $wpdb->prepare("
                SELECT COUNT(DISTINCT o.id)
                FROM {$orders_table} o
                INNER JOIN {$orders_meta_table} om ON o.id = om.order_id
                WHERE om.meta_key = '_store_location'
                AND om.meta_value IN ({$placeholders})
            ", $assigned_locations);

            return (int) $wpdb->get_var($sql);
        } else {
            $placeholders = implode(',', array_fill(0, count($assigned_locations), '%s'));

            $sql = $wpdb->prepare("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND pm.meta_key = '_store_location'
                AND pm.meta_value IN ({$placeholders})
            ", $assigned_locations);

            return (int) $wpdb->get_var($sql);
        }
    }
}

// Initialize the order count filter
new MULOPIMFWC_Order_Count_Filter();

/**
 * Alternative approach: Hook into WooCommerce reports and dashboard widgets
 */
add_filter('woocommerce_reports_order_statuses', function ($order_statuses) {
    $filter = new MULOPIMFWC_Order_Count_Filter();
    if (
        method_exists($filter, 'is_current_user_location_manager') &&
        $filter->is_current_user_location_manager()
    ) {
        // This ensures reports also respect location filtering
        add_filter('woocommerce_reports_get_order_report_query', function ($query) {
            $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
            if (!empty($assigned_locations)) {
                $query['meta_query'] = $query['meta_query'] ?? [];
                $query['meta_query'][] = [
                    'key' => '_store_location',
                    'value' => $assigned_locations,
                    'compare' => 'IN'
                ];
            }
            return $query;
        });
    }
    return $order_statuses;
});
?>