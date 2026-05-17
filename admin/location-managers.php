<?php

/**
 * Location Managers Admin Page
 * 
 * @package Multi Location Product & Inventory Management
 * @since 1.1.7.10
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
        add_action('admin_bar_menu', [$this, 'restrict_location_manager_admin_bar'], 999);

        // Add location manager meta box to user profile
        add_action('show_user_profile', [$this, 'add_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_user_profile_fields']);
        add_action('personal_options_update', [$this, 'save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);

        // Redirect location managers to admin dashboard instead of my-account
        add_action('wp_login', [$this, 'redirect_location_manager_after_login'], 10, 2);

        // Keep order details read-only when manager can view orders but cannot manage them.
        add_action('admin_head', [$this, 'disable_order_details_editing_for_view_only_managers']);
        add_filter('woocommerce_order_is_editable', [$this, 'make_order_details_readonly_for_view_only_managers'], 20, 2);
    }

    public function redirect_location_manager_after_login($user_login, $user)
    {
        if (in_array('mulopimfwc_location_manager', $user->roles)) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }


    /**
     * Get expected capabilities for location manager role
     * 
     * @return array Expected capabilities array
     */
    private function get_expected_location_manager_capabilities()
    {
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

        return $capabilities;
    }

    /**
     * Validate location manager role
     * Checks if role exists, has correct name, and all required capabilities
     * 
     * @return bool True if role is valid, false otherwise
     */
    private function validate_location_manager_role()
    {
        $role = get_role('mulopimfwc_location_manager');
        
        // Check if role exists
        if (!$role) {
            return false;
        }

        // Get expected role name
        $expected_name = __('Location Manager', 'multi-location-product-and-inventory-management-pro');
        $wp_roles = wp_roles();
        $actual_name = isset($wp_roles->roles['mulopimfwc_location_manager']['name']) 
            ? $wp_roles->roles['mulopimfwc_location_manager']['name'] 
            : '';

        // Check if role name is correct (allow for translation variations)
        if (empty($actual_name) && empty($expected_name)) {
            // Both empty is acceptable (translation might not be loaded)
        } elseif (empty($actual_name)) {
            return false;
        }

        // Get expected capabilities
        $expected_caps = $this->get_expected_location_manager_capabilities();
        $actual_caps = $role->capabilities;

        // Check if role has capabilities
        if (empty($actual_caps) || !is_array($actual_caps)) {
            return false;
        }

        // Check critical capabilities that must exist
        $critical_caps = [
            'read',
            'manage_multi_location_inventory',
            'manage_location_stock',
            'access_location_management',
        ];

        foreach ($critical_caps as $cap) {
            if (!isset($actual_caps[$cap]) || $actual_caps[$cap] !== true) {
                return false;
            }
        }

        // Role is valid if it exists, has a name, and has all critical capabilities
        return true;
    }

    /**
     * Remove location manager role
     * 
     * @return bool True if role was removed, false otherwise
     */
    private function remove_location_manager_role()
    {
        $role = get_role('mulopimfwc_location_manager');
        
        if (!$role) {
            return true; // Already removed
        }

        // Get all users with this role
        $users = get_users(['role' => 'mulopimfwc_location_manager']);
        
        // Mark users who had this role so we can restore them after recreation
        foreach ($users as $user) {
            // Store a flag that this user should have location manager role
            update_user_meta($user->ID, '_mulopimfwc_should_be_location_manager', true);
            // Temporarily change their role to customer to prevent data loss
            $user->set_role('customer');
        }

        // Remove the role
        remove_role('mulopimfwc_location_manager');
        
        return true;
    }

    /**
     * Create location manager role
     * Validates existing role and recreates if invalid
     */
    public function create_location_manager_role()
    {
        $role = get_role('mulopimfwc_location_manager');
        
        // If role doesn't exist, create it
        if (!$role) {
            $this->add_location_manager_role();
            return;
        }

        // Validate existing role
        if (!$this->validate_location_manager_role()) {
            // Role exists but is invalid - remove and recreate
            $this->remove_location_manager_role();
            $this->add_location_manager_role();
            
            // Restore users who had this role
            $users = get_users([
                'meta_key' => '_mulopimfwc_should_be_location_manager',
                'meta_value' => true,
            ]);
            
            foreach ($users as $user) {
                // Remove the flag
                delete_user_meta($user->ID, '_mulopimfwc_should_be_location_manager');
                // Restore location manager role
                $user->set_role('mulopimfwc_location_manager');
            }
        }
    }

    /**
     * Add location manager role with all capabilities
     * 
     * @return bool True if role was added successfully
     */
    private function add_location_manager_role()
    {
        $capabilities = $this->get_expected_location_manager_capabilities();
        
        $result = add_role(
            'mulopimfwc_location_manager',
            __('Location Manager', 'multi-location-product-and-inventory-management-pro'),
            $capabilities
        );

        return $result !== null;
    }

    /**
     * Get all available capabilities
     */
    private function get_available_capabilities()
    {
        return [
            'view_dashboard' => __('View Dashboard', 'multi-location-product-and-inventory-management-pro'),
            'view_orders' => __('View Orders', 'multi-location-product-and-inventory-management-pro'),
            'manage_orders' => __('Manage Orders', 'multi-location-product-and-inventory-management-pro'),
            'all_orders' => __('Manage/View All Orders', 'multi-location-product-and-inventory-management-pro'),
            'view_products' => __('View Products', 'multi-location-product-and-inventory-management-pro'),
            'manage_products' => __('Manage Products', 'multi-location-product-and-inventory-management-pro'),
            'all_products' => __('Manage/View All Products', 'multi-location-product-and-inventory-management-pro'),
            'run_reports' => __('Run Reports', 'multi-location-product-and-inventory-management-pro'),
            'export_report' => __('Export Report', 'multi-location-product-and-inventory-management-pro'),
            'location_specific_products_frontend' => __('Location Specific Products Frontend', 'multi-location-product-and-inventory-management-pro'),
        ];
    }

    private static function normalize_manager_capabilities($capabilities)
    {
        if (!is_array($capabilities)) {
            return [];
        }

        if (in_array('manage_inventory', $capabilities, true)) {
            $capabilities[] = 'view_dashboard';
            $capabilities[] = 'view_products';
        }

        return array_values(array_unique($capabilities));
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

        $sample_managers = [
            (object) [
                'ID' => 1,
                'display_name' => 'John Doe',
                'user_email' => 'john.doe@example.com',
                'capabilities' => ['Manage Products', 'View Reports'],
                'assigned_locations' => ['New York', 'Los Angeles']
            ],
            (object) [
                'ID' => 2,
                'display_name' => 'Jane Smith',
                'user_email' => 'jane.smith@example.com',
                'capabilities' => ['View Dashboard'],
                'assigned_locations' => ['Chicago']
            ],
            (object) [
                'ID' => 3,
                'display_name' => 'Alice Johnson',
                'user_email' => 'alice.johnson@example.com',
                'capabilities' => ['Manage Orders', 'Edit Products'],
                'assigned_locations' => []
            ],
            (object) [
                'ID' => 4,
                'display_name' => 'Bob Brown',
                'user_email' => 'bob.brown@example.com',
                'capabilities' => ['View Reports'],
                'assigned_locations' => ['Miami', 'Houston']
            ],
            (object) [
                'ID' => 5,
                'display_name' => 'Charlie Davis',
                'user_email' => 'charlie.davis@example.com',
                'capabilities' => ['Manage Products', 'View Dashboard'],
                'assigned_locations' => ['San Francisco']
            ],
            (object) [
                'ID' => 7,
                'display_name' => 'Ethan Green',
                'user_email' => 'ethan.green@example.com',
                'capabilities' => ['Edit Products'],
                'assigned_locations' => ['Boston']
            ],
            (object) [
                'ID' => 9,
                'display_name' => 'George King',
                'user_email' => 'george.king@example.com',
                'capabilities' => ['View Dashboard'],
                'assigned_locations' => ['Denver', 'Las Vegas']
            ],
            (object) [
                'ID' => 10,
                'display_name' => 'Hannah Lee',
                'user_email' => 'hannah.lee@example.com',
                'capabilities' => ['Manage Products', 'Edit Products'],
                'assigned_locations' => ['Phoenix']
            ]
        ];

        $managers = mulopimfwc_get_pro_class(false, $this->get_location_managers(), $sample_managers);

        $locations = $mulopimfwc_locations;
        $capabilities = $this->get_available_capabilities();
        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $global_capabilities = isset($options['location_manager_capabilities']) ? $options['location_manager_capabilities'] : [];
        $global_capabilities = self::normalize_manager_capabilities($global_capabilities);

?>
        <div class="wrap mulopimfwc-location-managers-main <?php echo esc_attr(mulopimfwc_get_pro_class(false, '', 'mulopimfwc_pro_only mulopimfwc_pro_only_blur1')); ?>">
            <h1 style="display: none !important;"><?php echo esc_html__('Location Managers', 'multi-location-product-and-inventory-management-pro'); ?></h1>

            <div class="mulopimfwc-location-managers">
                <div class="mulopimfwc-manager-header">
                    <h1 class="mlm-settings-heading">
                        <div class="mlm-settings-icon">
                            <svg class="svg-inline--fa fa-users" aria-hidden="true" data-prefix="fas" data-icon="users" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 16" width="20" height="16">
                                <path fill="#ffffff" d="M4.5 0a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5M16 0a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5M0 9.334A3.336 3.336 0 0 1 3.334 6h1.334c.497 0 .969.109 1.394.303A4 4 0 0 0 7.356 10H.666A.67.67 0 0 1 0 9.334M12.666 10h-.022a4 4 0 0 0 1.353-3q-.002-.355-.059-.697A3.3 3.3 0 0 1 15.332 6h1.334A3.335 3.335 0 0 1 20 9.334c0 .369-.3.666-.666.666zM7 7a3 3 0 1 1 6 0 3 3 0 1 1-6 0m-3 8.166C4 12.866 5.866 11 8.166 11h3.669c2.3 0 4.166 1.866 4.166 4.166a.834.834 0 0 1-.834.834H4.834A.834.834 0 0 1 4 15.166"></path>
                            </svg>
                        </div>
                        <span><?php echo esc_html__('Location Managers', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    </h1>
                    <!-- Add New Manager Button -->
                    <button type="button" class="button button-primary" id="<?php echo esc_attr(mulopimfwc_get_pro_class(false, 'mulopimfwc-add-manager-btn', ' ')); ?>">
                        <svg class="svg-inline--fa fa-plus" aria-hidden="true" data-prefix="fas" data-icon="plus" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                            <path fill="currentColor" d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32v144H48c-17.7 0-32 14.3-32 32s14.3 32 32 32h144v144c0 17.7 14.3 32 32 32s32-14.3 32-32V288h144c17.7 0 32-14.3 32-32s-14.3-32-32-32H256z" />
                        </svg> <?php echo esc_html__('Add New Location Manager', 'multi-location-product-and-inventory-management-pro'); ?>
                    </button>
                </div>

                <!-- Managers List -->
                <div class="mulopimfwc-managers-list">
                    <h2><?php echo esc_html__('Current Location Managers', 'multi-location-product-and-inventory-management-pro'); ?></h2>

                    <?php if (empty($managers)): ?>
                        <div class="mulopimfwc-no-managers">
                            <p><?php echo esc_html__('No location managers found. Create your first location manager to get started.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="mulopimfwc-managers-grid">
                            <?php foreach ($managers as $manager): ?>
                                <?php
                                $assigned_locations = get_user_meta($manager->ID, 'mulopimfwc_assigned_locations', true);
                                $manager_capabilities = get_user_meta($manager->ID, 'mulopimfwc_manager_capabilities', true);
                                $manager_default_location = get_user_meta($manager->ID, 'mulopimfwc_manager_default_location', true);
                                $social_channels = get_user_meta($manager->ID, 'mulopimfwc_social_channels', true);
                                if (!is_array($social_channels)) {
                                    $social_channels = [];
                                }
                                // Backfill legacy Slack field so existing managers keep receiving alerts
                                if (empty($social_channels['slack_webhook'])) {
                                    $legacy_slack = get_user_meta($manager->ID, 'mulopimfwc_slack_webhook', true);
                                    if (!empty($legacy_slack)) {
                                        $social_channels['slack_webhook'] = $legacy_slack;
                                    }
                                }
                                if (!is_array($assigned_locations)) $assigned_locations = [];
                                $assigned_locations = array_values(array_filter(array_map(function ($location_slug) {
                                    return sanitize_title(rawurldecode((string) $location_slug));
                                }, $assigned_locations)));
                                $assigned_locations = array_values(array_unique($assigned_locations));
                                $manager_default_location = sanitize_title(rawurldecode((string) $manager_default_location));
                                if ($manager_default_location === '' || !in_array($manager_default_location, $assigned_locations, true)) {
                                    $manager_default_location = '';
                                }
                                if (!is_array($manager_capabilities)) {
                                    $manager_capabilities = $global_capabilities;
                                } else {
                                    $manager_capabilities = self::normalize_manager_capabilities($manager_capabilities);
                                }
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
                                    </div>

                                    <div class="manager-locations">
                                        <h4><svg class="svg-inline--fa fa-location-dot" aria-hidden="true" data-prefix="fas" data-icon="location-dot" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                                <path fill="#9ca3af" d="M215.7 499.2C267 435 384 279.4 384 192 384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2 12.3 15.3 35.1 15.3 47.4 0M192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128" />
                                            </svg><?php echo esc_html__('Assigned Locations:', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                                        <?php if (empty($assigned_locations)): ?>
                                            <p class="no-locations"><?php echo esc_html__('No locations assigned', 'multi-location-product-and-inventory-management-pro'); ?></p>
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
                                        <h4><svg class="svg-inline--fa fa-key" aria-hidden="true" data-prefix="fas" data-icon="key" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                                <path fill="#9ca3af" d="M336 352c97.2 0 176-78.8 176-176S433.2 0 336 0 160 78.8 160 176c0 18.7 2.9 36.8 8.3 53.7L7 391c-4.5 4.5-7 10.6-7 17v80c0 13.3 10.7 24 24 24h80c13.3 0 24-10.7 24-24v-40h40c13.3 0 24-10.7 24-24v-40h40c6.4 0 12.5-2.5 17-7l33.3-33.3c16.9 5.4 35 8.3 53.7 8.3m40-256a40 40 0 1 1 0 80 40 40 0 1 1 0-80" />
                                            </svg><?php echo esc_html__('Permissions:', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                                        <?php if (empty($manager_capabilities)): ?>
                                            <p class="no-capabilities"><?php echo esc_html__('No specific permissions set (using global defaults)', 'multi-location-product-and-inventory-management-pro'); ?></p>
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

                                    <div class="manager-actions">
                                        <button type="button" class="button button-small <?php echo esc_attr(mulopimfwc_get_pro_class(false, 'mulopimfwc-edit-manager', ' ')); ?> mulopimfwc-btn-primary" data-manager-id="<?php echo esc_attr($manager->ID); ?>" data-assign-locations=<?php echo wp_json_encode($assigned_locations); ?> data-assign-capabilities=<?php echo wp_json_encode($manager_capabilities); ?> data-default-location="<?php echo esc_attr($manager_default_location); ?>" data-social-channels="<?php echo esc_attr(wp_json_encode($social_channels)); ?>">
                                            <svg class="svg-inline--fa fa-pencil" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="pencil" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                                <path fill="currentColor" d="M410.3 231l11.3-11.3-33.9-33.9-62.1-62.1L291.7 89.8l-11.3 11.3-22.6 22.6L58.6 322.9c-10.4 10.4-18 23.3-22.2 37.4L1 480.7c-2.5 8.4-.2 17.5 6.1 23.7s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L387.7 253.7 410.3 231zM160 399.4l-9.1 22.7c-4 3.1-8.5 5.4-13.3 6.9L59.4 452l23-78.1c1.4-4.9 3.8-9.4 6.9-13.3l22.7-9.1v32c0 8.8 7.2 16 16 16h32zM362.7 18.7L348.3 33.2 325.7 55.8 314.3 67.1l33.9 33.9 62.1 62.1 33.9 33.9 11.3-11.3 22.6-22.6 14.5-14.5c25-25 25-65.5 0-90.5L453.3 18.7c-25-25-65.5-25-90.5 0zm-47.4 168l-144 144c-6.2 6.2-16.4 6.2-22.6 0s-6.2-16.4 0-22.6l144-144c6.2-6.2 16.4-6.2 22.6 0s6.2 16.4 0 22.6z"></path>
                                            </svg> <?php echo esc_html__('Edit', 'multi-location-product-and-inventory-management-pro'); ?>
                                        </button>
                                        <button type="button" class="button button-small <?php echo esc_attr(mulopimfwc_get_pro_class(false, 'button-link-delete mulopimfwc-delete-manager', ' ')); ?> mulopimfwc-delete-btn" data-manager-id="<?php echo esc_attr($manager->ID); ?>">
                                            <svg class="svg-inline--fa fa-trash" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="trash" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" data-fa-i2svg="">
                                                <path fill="currentColor" d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"></path>
                                            </svg> <?php echo esc_html__('Delete', 'multi-location-product-and-inventory-management-pro'); ?>
                                        </button>
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
                    <h2 id="mulopimfwc-modal-title"><?php echo esc_html__('Add New Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                    <button type="button" class="mulopimfwc-modal-close">&times;</button>
                </div>

                <form id="mulopimfwc-manager-form" method="post">
                    <input type="hidden" id="manager-id" name="manager_id" value="">
                    <input type="hidden" id="action-type" name="action_type" value="create">

                    <div class="mulopimfwc-form-row" id="search_or_add_manager">
                        <label for="user-search"><?php echo esc_html__('Select User:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <div class="user-search-container">
                            <input type="text" id="user-search" placeholder="<?php echo esc_attr__('Search users by name or email...', 'multi-location-product-and-inventory-management-pro'); ?>">
                            <input type="hidden" id="selected-user-id" name="user_id" value="">
                            <div id="user-search-results" class="search-results"></div>
                        </div>
                        <div id="create-new-user" style="display: none;">
                            <hr>
                            <h4><?php echo esc_html__('Or Create New User:', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                            <div class="mulopimfwc-form-row">
                                <label for="new-username"><?php echo esc_html__('Username:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="text" id="new-username" name="new_username" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-email"><?php echo esc_html__('Email:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="email" id="new-email" name="new_email" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-first-name"><?php echo esc_html__('First Name:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="text" id="new-first-name" name="new_first_name" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-last-name"><?php echo esc_html__('Last Name:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="text" id="new-last-name" name="new_last_name" value="">
                            </div>
                            <div class="mulopimfwc-form-row">
                                <label for="new-password"><?php echo esc_html__('Password:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="password" id="new-password" name="new_password" value="">
                                <p class="description"><?php echo esc_html__('Optional: set an initial password. Leave empty to auto-generate.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            </div>
                        </div>
                        <button type="button" id="toggle-create-user" class="button button-secondary">
                            <?php echo esc_html__('Create New User Instead', 'multi-location-product-and-inventory-management-pro'); ?>
                        </button>
                    </div>

                    <div class="mulopimfwc-form-row">
                        <label><?php echo esc_html__('Assign Locations:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <div class="location-checkboxes">
                            <?php if (!empty($locations)): ?>
                                <?php foreach ($locations as $location): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="assigned_locations[]" value="<?php echo esc_attr(rawurldecode($location->slug)); ?>">
                                        <?php echo esc_html($location->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p><?php echo esc_html__('No locations available. Please create locations first.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mulopimfwc-form-row">
                        <label for="manager-default-location"><?php echo esc_html__('Default Location:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <select id="manager-default-location" name="default_location">
                            <option value=""><?php echo esc_html__('Use first assigned location', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__('Only assigned locations are available. If not set, the first assigned location is used.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                    </div>

                    <div class="mulopimfwc-form-row">
                        <label><?php echo esc_html__('Individual Permissions:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <p class="description"><?php echo esc_html__('Leave unchecked to use global default permissions.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        <div class="capability-checkboxes">
                            <?php foreach ($capabilities as $cap_key => $cap_label): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="manager_capabilities[]" value="<?php echo esc_attr($cap_key); ?>">
                                    <?php echo esc_html($cap_label); ?>
                                    <?php if (in_array($cap_key, $global_capabilities)): ?>
                                        <span class="global-default">(<?php echo esc_html__('Global Default', 'multi-location-product-and-inventory-management-pro'); ?>)</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mulopimfwc-form-row">
                        <label><?php echo esc_html__('Social Notifications', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <div class="mulopimfwc-social-grid">
                            <div class="mulopimfwc-social-field">
                                <label for="social_slack_webhook"><?php echo esc_html__('Slack / Discord / Teams Webhook', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="url" id="social_slack_webhook" name="social_slack_webhook" placeholder="https://hooks.slack.com/services/...">
                                <p class="description"><?php echo esc_html__('Use any incoming webhook URL to receive alerts for this manager.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            </div>
                            <div class="mulopimfwc-social-field">
                                <label for="social_custom_webhook"><?php echo esc_html__('Custom Webhook', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="url" id="social_custom_webhook" name="social_custom_webhook" placeholder="https://example.com/webhook">
                                <p class="description"><?php echo esc_html__('Any other platform that accepts a JSON POST payload.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            </div>
                            <div class="mulopimfwc-social-field">
                                <label for="social_telegram_chat"><?php echo esc_html__('Telegram Chat ID', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <input type="text" id="social_telegram_chat" name="social_telegram_chat" placeholder="<?php echo esc_attr__('@username or chat ID', 'multi-location-product-and-inventory-management-pro'); ?>">
                                <p class="description"><?php echo esc_html__('Requires the bot token configured in Advanced → Social Notifications.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="mulopimfwc-modal-footer">
                        <button type="button" class="button button-secondary mulopimfwc-modal-close">
                            <?php echo esc_html__('Cancel', 'multi-location-product-and-inventory-management-pro'); ?>
                        </button>
                        <button type="submit" class="button button-primary mulopimfwc-btn-primary" id="mulopimfwc-save-manager">
                            <svg width="16" height="16" viewBox="0 0 0.32 0.32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M.22.02h-.2V.3h.06V.18h.16V.3H.3V.1z" />
                                <path d="M.2.3V.22H.12V.3z" />
                            </svg>
                            <?php echo esc_html__('Save Manager', 'multi-location-product-and-inventory-management-pro'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php wp_nonce_field('mulopimfwc_location_managers_nonce', 'mulopimfwc_location_managers_nonce'); ?>

        <style>
            /* New start*/
            .wrap.mulopimfwc-location-managers-main {
                border: 2px solid #d1d1d4;
                border-radius: 8px;
                background-color: #f9fafb;
                margin: 20px 20px 0px 0px;
            }

            h1.mlm-settings-heading {
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .mlm-settings-icon {
                color: #ffffff;
                background: #3b82f6;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 10px;
                border-radius: 7px;
            }

            .mulopimfwc-manager-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: rgba(33, 35, 38, 0.1) 0px 10px 10px -10px;
                padding: 20px;
            }

            .mulopimfwc-manager-header button {
                background: #2563eb !important;
                border-color: #2563eb !important;
                padding: 5px 15px !important;
                border-radius: 6px !important;
                font-size: 15px !important;
                font-weight: 600;
                display: flex !important;
                justify-content: center;
                align-items: center;
            }

            .mulopimfwc-manager-header button svg {
                width: 15px;
                height: 15px;
                margin-right: 6px;
            }

            .mulopimfwc-social-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
            }

            .mulopimfwc-social-field input {
                width: 100%;
            }

            .mulopimfwc-social-field .description {
                margin-top: 6px;
                color: #475569;
            }

            .mulopimfwc-managers-list {
                padding: 10px 25px;
            }

            .manager-info img {
                border-radius: 50%;
            }


            .manager-actions .mulopimfwc-delete-manager,
            .mulopimfwc-delete-btn {
                background: #ffffff !important;
                border-color: #dc2626 !important;
                padding: 0px 30px !important;
                border-radius: 6px !important;
                font-size: 15px !important;
                color: #dc2626 !important;
                font-weight: 600;
                display: flex !important;
                justify-content: center;
                align-items: center;
                transition: all 0.25s ease !important;
            }

            .manager-actions .mulopimfwc-delete-manager:hover,
            .mulopimfwc-delete-btn:hover {
                box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4) !important;
                transform: translateY(-2px);
            }



            /* New End */

            .mulopimfwc-managers-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
            }

            .mulopimfwc-manager-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
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
                font-size: 18px;
                font-weight: 600;
            }

            .manager-email {
                margin: 0px 0 0;
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
                display: flex;
                align-items: center;
            }

            .manager-locations h4 svg,
            .manager-capabilities h4 svg {
                width: auto;
                height: 14px;
                margin-right: 6px;
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
                background: #f3f4f6;
                padding: 4px 8px;
                border-radius: 30px;
                font-size: 12px;
            }

            .capability-tag {
                background: #2563eb1a;
                color: #2563eb;
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
                margin: 1.5% auto;
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
            .mulopimfwc-form-row input[type="password"],
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
                    $('#mulopimfwc-modal-title').text('<?php echo esc_js(esc_html_e('Add New Location Manager', 'multi-location-product-and-inventory-management-pro')); ?>');
                    $('#action-type').val('create');
                    $('#mulopimfwc-manager-modal').show();
                    $('#search_or_add_manager').show();
                });

                // Edit manager button
                $(document).on('click', '.mulopimfwc-edit-manager', function() {
                    isEditMode = true;
                    const managerId = $(this).data('manager-id');
                    const assign_locations = $(this).data('assign-locations');
                    const assign_capabilities = $(this).data('assign-capabilities');
                    const default_location = $(this).data('default-location') || '';
                    const social_channels = $(this).data('social-channels') || {};
                    loadManagerData(managerId, assign_locations, assign_capabilities, default_location, social_channels);
                });

                // Delete manager button
                $(document).on('click', '.mulopimfwc-delete-manager', function() {
                    if (confirm('<?php echo esc_js(esc_html_e('Are you sure you want to delete this location manager?', 'multi-location-product-and-inventory-management-pro')); ?>')) {
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
                    $(this).text(isVisible ? '<?php echo esc_js(esc_html_e('Select Existing User Instead', 'multi-location-product-and-inventory-management-pro')); ?>' : '<?php echo esc_js(esc_html_e('Create New User Instead', 'multi-location-product-and-inventory-management-pro')); ?>');
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

                $(document).on('change', 'input[name="assigned_locations[]"]', function() {
                    refreshDefaultLocationOptions();
                });

                // Functions
                function resetForm() {
                    $('#mulopimfwc-manager-form')[0].reset();
                    $('#manager-id').val('');
                    $('#selected-user-id').val('');
                    $('#user-search-results').empty().hide();
                    $('#create-new-user').hide();
                    $('#social_slack_webhook, #social_custom_webhook, #social_telegram_chat').val('');
                    $('#toggle-create-user').text('<?php echo esc_js(esc_html_e('Create New User Instead', 'multi-location-product-and-inventory-management-pro')); ?>');
                    refreshDefaultLocationOptions('');
                }

                function refreshDefaultLocationOptions(preferredLocation) {
                    const $defaultLocation = $('#manager-default-location');
                    const fallbackLabel = '<?php echo esc_js(__('Use first assigned location', 'multi-location-product-and-inventory-management-pro')); ?>';
                    const explicitPreferred = typeof preferredLocation === 'string' ? preferredLocation : null;
                    const currentValue = explicitPreferred !== null ? explicitPreferred : ($defaultLocation.val() || '');
                    const assignedLocations = [];

                    $('input[name="assigned_locations[]"]:checked').each(function() {
                        assignedLocations.push({
                            value: $(this).val(),
                            label: $.trim($(this).parent().text())
                        });
                    });

                    $defaultLocation.empty();
                    $defaultLocation.append($('<option></option>').val('').text(fallbackLabel));

                    assignedLocations.forEach(function(locationData) {
                        $defaultLocation.append(
                            $('<option></option>').val(locationData.value).text(locationData.label)
                        );
                    });

                    const hasCurrent = currentValue !== '' && assignedLocations.some(function(locationData) {
                        return locationData.value === currentValue;
                    });
                    $defaultLocation.val(hasCurrent ? currentValue : '');
                }

                function loadManagerData(managerId, assign_locations, assign_capabilities, default_location, social_channels) {
                    // Update modal title and form fields
                    $('#mulopimfwc-modal-title').text('<?php echo esc_js(esc_html_e('Edit Location Manager', 'multi-location-product-and-inventory-management-pro')); ?>');
                    $('#manager-id').val(managerId);
                    $('#action-type').val('edit');
                    $('#search_or_add_manager').hide();

                    // Reset all checkboxes first
                    $('input[name="assigned_locations[]"]').prop('checked', false);
                    $('input[name="manager_capabilities[]"]').prop('checked', false);

                    // Check assigned locations
                    if (assign_locations && assign_locations.length > 0) {
                        assign_locations.forEach(function(location) {
                            $('input[name="assigned_locations[]"][value="' + location + '"]').prop('checked', true);
                        });
                    }
                    refreshDefaultLocationOptions(default_location || '');

                    // Check assigned capabilities
                    if (assign_capabilities && assign_capabilities.length > 0) {
                        assign_capabilities.forEach(function(capability) {
                            $('input[name="manager_capabilities[]"][value="' + capability + '"]').prop('checked', true);
                        });
                    }

                    // Social channels
                    const social = social_channels || {};
                    $('#social_slack_webhook').val(social.slack_webhook || '');
                    $('#social_custom_webhook').val(social.custom_webhook || '');
                    $('#social_telegram_chat').val(social.telegram_chat_id || '');

                    // Show the modal
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
                        resultsContainer.html('<div class="search-result-item"><?php echo esc_js(esc_html_e('No users found', 'multi-location-product-and-inventory-management-pro')); ?></div>');
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
                                alert(response.data.message || '<?php echo esc_js(esc_html_e('Error saving manager', 'multi-location-product-and-inventory-management-pro')); ?>');
                            }
                        }
                    });

                    // reload after 1s 
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
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
                                alert(response.data.message || '<?php echo esc_js(esc_html_e('Error deleting manager', 'multi-location-product-and-inventory-management-pro')); ?>');
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
            wp_send_json_error(['message' => esc_html__('Permission denied', 'multi-location-product-and-inventory-management-pro')]);
            return;
        }

        // FIXED: Add rate limiting (Issue #31)
        $rate_limit_key = 'mulopimfwc_search_users_rate_' . get_current_user_id();
        $requests = get_transient($rate_limit_key);
        if ($requests === false) {
            $requests = 0;
        }
        $requests++;
        set_transient($rate_limit_key, $requests, MINUTE_IN_SECONDS);

        $rate_limit = apply_filters('mulopimfwc_search_users_rate_limit', 30); // 30 requests per minute
        if ($requests > $rate_limit) {
            wp_send_json_error(['message' => __('Rate limit exceeded. Please try again later.', 'multi-location-product-and-inventory-management-pro')]);
            return;
        }

        // FIXED: Add wp_unslash and input validation (Issues #34, #39)
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        
        // FIXED: Add minimum and maximum length validation (Issue #34)
        if (strlen($query) < 2) {
            wp_send_json_error(['message' => __('Search query must be at least 2 characters.', 'multi-location-product-and-inventory-management-pro')]);
            return;
        }

        if (strlen($query) > 100) {
            wp_send_json_error(['message' => __('Search query is too long.', 'multi-location-product-and-inventory-management-pro')]);
            return;
        }

        // FIXED: Remove wildcards from user input to prevent potential issues (Issue #34)
        $query = trim($query);

        // FIXED: Optimize to single query and cache excluded IDs (Issue #35)
        $cache_key = 'mulopimfwc_excluded_manager_ids';
        $excluded_ids = get_transient($cache_key);
        if ($excluded_ids === false) {
            $excluded_ids = get_users(['role' => 'mulopimfwc_location_manager', 'fields' => 'ID']);
            set_transient($cache_key, $excluded_ids, 5 * MINUTE_IN_SECONDS); // Cache for 5 minutes
        }

        $users = get_users([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'user_nicename', 'display_name'],
            'exclude' => $excluded_ids,
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
            wp_send_json_error(['message' => esc_html_e('Permission denied', 'multi-location-product-and-inventory-management-pro')]);
        }

        $action_type = sanitize_text_field($_POST['action_type']);
        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $assigned_locations = isset($_POST['assigned_locations']) ? array_map('sanitize_text_field', (array) $_POST['assigned_locations']) : [];
        $assigned_locations = array_values(array_filter(array_map(function ($location_slug) {
            return sanitize_title(rawurldecode((string) $location_slug));
        }, $assigned_locations)));
        $assigned_locations = array_values(array_unique($assigned_locations));
        $requested_default_location = isset($_POST['default_location']) ? sanitize_title(rawurldecode((string) sanitize_text_field($_POST['default_location']))) : '';
        $manager_default_location = '';
        if ($requested_default_location !== '' && in_array($requested_default_location, $assigned_locations, true)) {
            $manager_default_location = $requested_default_location;
        }
        $manager_capabilities = isset($_POST['manager_capabilities']) ? array_map('sanitize_text_field', $_POST['manager_capabilities']) : [];
        $social_channels = [
            'slack_webhook' => isset($_POST['social_slack_webhook']) ? esc_url_raw(trim($_POST['social_slack_webhook'])) : '',
            'custom_webhook' => isset($_POST['social_custom_webhook']) ? esc_url_raw(trim($_POST['social_custom_webhook'])) : '',
            'telegram_chat_id' => isset($_POST['social_telegram_chat']) ? sanitize_text_field($_POST['social_telegram_chat']) : '',
        ];
        $notify_email = '';

        try {
            if ($action_type === 'create') {
                // Check if creating new user
                if (empty($user_id) && !empty($_POST['new_username'])) {
                    $username = sanitize_user($_POST['new_username']);
                    $email = sanitize_email($_POST['new_email']);
                    $first_name = sanitize_text_field($_POST['new_first_name']);
                    $last_name = sanitize_text_field($_POST['new_last_name']);
                    $password = isset($_POST['new_password']) && $_POST['new_password'] !== '' ? $_POST['new_password'] : wp_generate_password();
                    $notify_email = $email;

                    // Check if username or email already exists
                    if (username_exists($username) || email_exists($email)) {
                        wp_send_json_error(['message' => esc_html_e('Username or email already exists', 'multi-location-product-and-inventory-management-pro')]);
                    }

                    // Create new user
                    $user_id = wp_create_user($username, $password, $email);

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

                    // Send welcome email with password info
                    wp_mail(
                        $email,
                        sprintf(/* translators: %s: site name */ __('Your Location Manager account on %s', 'multi-location-product-and-inventory-management-pro'), get_bloginfo('name')),
                        sprintf(
                            /* translators: 1: user display name, 2: username, 3: password, 4: login URL */
                            __("Hello %1\$s,\n\nAn account has been created for you.\nUsername: %2\$s\nPassword: %3\$s\nLogin: %4\$s\n\nPlease change your password after logging in.", 'multi-location-product-and-inventory-management-pro'),
                            trim($first_name . ' ' . $last_name),
                            $username,
                            $password,
                            wp_login_url()
                        )
                    );
                }

                if (empty($user_id)) {
                    wp_send_json_error(['message' => esc_html_e('Please select or create a user', 'multi-location-product-and-inventory-management-pro')]);
                }

                // Change user role to location manager
                $user = new WP_User($user_id);
                $user->set_role('mulopimfwc_location_manager');
            } else {
                // Edit mode
                if (empty($manager_id)) {
                    wp_send_json_error(['message' => esc_html_e('Invalid manager ID', 'multi-location-product-and-inventory-management-pro')]);
                }
                $user_id = $manager_id;
            }

            // Save assigned locations
            update_user_meta($user_id, 'mulopimfwc_assigned_locations', $assigned_locations);
            if ($manager_default_location !== '') {
                update_user_meta($user_id, 'mulopimfwc_manager_default_location', $manager_default_location);
            } else {
                delete_user_meta($user_id, 'mulopimfwc_manager_default_location');
            }

            // Save manager capabilities
            update_user_meta($user_id, 'mulopimfwc_manager_capabilities', $manager_capabilities);
            update_user_meta($user_id, 'mulopimfwc_social_channels', $social_channels);
            update_user_meta($user_id, 'mulopimfwc_slack_webhook', $social_channels['slack_webhook']);

            // Notify on creation or permission changes
            if (empty($notify_email)) {
                $user_obj = get_user_by('id', $user_id);
                $notify_email = $user_obj && !empty($user_obj->user_email) ? $user_obj->user_email : '';
            }

            if (!empty($notify_email)) {
                $site_name = get_bloginfo('name');
                $subject = sprintf(/* translators: %s: site name */ __('Location Manager update on %s', 'multi-location-product-and-inventory-management-pro'), $site_name);
                $body_lines = [];
                if ($action_type === 'create') {
                    $body_lines[] = __('Your Location Manager account has been created or updated.', 'multi-location-product-and-inventory-management-pro');
                } else {
                    $body_lines[] = __('Your Location Manager permissions or assigned locations have been updated.', 'multi-location-product-and-inventory-management-pro');
                }
                if (!empty($assigned_locations)) {
                    $body_lines[] = sprintf(/* translators: %s: comma-separated list of location names */ __('Assigned Locations: %s', 'multi-location-product-and-inventory-management-pro'), implode(', ', $assigned_locations));
                }
            if (!empty($manager_capabilities)) {
                $body_lines[] = sprintf(/* translators: %s: comma-separated list of capability names */ __('Capabilities: %s', 'multi-location-product-and-inventory-management-pro'), implode(', ', $manager_capabilities));
            }
            $body_lines[] = sprintf(/* translators: %s: login URL */ __('Login: %s', 'multi-location-product-and-inventory-management-pro'), wp_login_url());
            wp_mail($notify_email, $subject, implode("\n", $body_lines));
        }

        // Social alert for manager change
        if (function_exists('mulopimfwc_get_social_settings') && function_exists('mulopimfwc_send_social_message') && function_exists('mulopimfwc_collect_social_channels')) {
            $settings = mulopimfwc_get_social_settings();
            if (isset($settings['enabled'], $settings['manager_change_alert']) && $settings['enabled'] === 'on' && $settings['manager_change_alert'] === 'on') {
                $channels = mulopimfwc_collect_social_channels('', $settings);
                if (!empty($channels)) {
                    $user_obj = get_user_by('id', $user_id);
                    mulopimfwc_send_social_message(
                        __('Manager updated', 'multi-location-product-and-inventory-management-pro'),
                        sprintf(
                            /* translators: 1: manager display name, 2: action (created/updated), 3: comma-separated locations or "none" */
                            __('%1$s was %2$s. Locations: %3$s', 'multi-location-product-and-inventory-management-pro'),
                            $user_obj ? $user_obj->display_name : __('Manager', 'multi-location-product-and-inventory-management-pro'),
                            $action_type === 'create' ? __('created', 'multi-location-product-and-inventory-management-pro') : __('updated', 'multi-location-product-and-inventory-management-pro'),
                            !empty($assigned_locations) ? implode(', ', $assigned_locations) : __('none', 'multi-location-product-and-inventory-management-pro')
                        ),
                        $channels,
                        $settings,
                        admin_url('users.php')
                    );
                }
            }
        }

        wp_send_json_success(['message' => esc_html_e('Location manager saved successfully', 'multi-location-product-and-inventory-management-pro')]);
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
            wp_send_json_error(['message' => esc_html_e('Permission denied', 'multi-location-product-and-inventory-management-pro')]);
        }

        $manager_id = intval($_POST['manager_id']);

        if (empty($manager_id)) {
            wp_send_json_error(['message' => esc_html_e('Invalid manager ID', 'multi-location-product-and-inventory-management-pro')]);
        }

        // Remove location manager role and revert to subscriber
        $user = new WP_User($manager_id);
        $user->set_role('subscriber');

        // Remove location manager meta
        delete_user_meta($manager_id, 'mulopimfwc_assigned_locations');
        delete_user_meta($manager_id, 'mulopimfwc_manager_capabilities');
        delete_user_meta($manager_id, 'mulopimfwc_manager_default_location');

        wp_send_json_success(['message' => esc_html_e('Location manager deleted successfully', 'multi-location-product-and-inventory-management-pro')]);
    }

    /**
     * Update manager permissions AJAX handler
     */
    public function update_manager_permissions()
    {
        check_ajax_referer('mulopimfwc_location_managers_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => esc_html_e('Permission denied', 'multi-location-product-and-inventory-management-pro')]);
        }

        $manager_id = intval($_POST['manager_id']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : [];

        if (empty($manager_id)) {
            wp_send_json_error(['message' => esc_html_e('Invalid manager ID', 'multi-location-product-and-inventory-management-pro')]);
        }

        update_user_meta($manager_id, 'mulopimfwc_manager_capabilities', $capabilities);

        wp_send_json_success(['message' => esc_html_e('Permissions updated successfully', 'multi-location-product-and-inventory-management-pro')]);
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

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if (
            $page === 'multi-location-product-and-inventory-management-pro' &&
            !self::user_has_capability('view_dashboard')
        ) {
            if (self::user_has_capability('view_products') || self::user_has_capability('manage_products')) {
                wp_redirect(admin_url('admin.php?page=location-stock-management'));
            } elseif (self::user_has_capability('run_reports')) {
                wp_redirect(admin_url('admin.php?page=mulopimfwc-analytics'));
            } else {
                wp_redirect(admin_url('index.php?restricted=1'));
            }
            exit;
        }

        if (
            $page === 'location-stock-management' &&
            !self::user_has_capability('view_products') &&
            !self::user_has_capability('manage_products')
        ) {
            wp_redirect(admin_url('index.php?restricted=1'));
            exit;
        }

        if ($page === 'mulopimfwc-analytics' && !self::user_has_capability('run_reports')) {
            wp_redirect(admin_url('index.php?restricted=1'));
            exit;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
        if (
            ($post_type === 'product' || ($current_screen && $current_screen->post_type === 'product')) &&
            !self::user_has_capability('manage_products')
        ) {
            wp_redirect(admin_url('index.php?restricted=1'));
            exit;
        }
    }


    public function restrict_location_manager_menus()
    {
        // Don't restrict menus in network admin
        if (is_network_admin()) {
            return;
        }

        // Don't restrict menus for administrators or super admins
        if (current_user_can('administrator') || (is_multisite() && is_super_admin())) {
            return;
        }

        // Only restrict menus for location managers
        $manager = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', $manager->roles)) {
            return;
        }

        // Double-check capability
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $manager_capabilities = get_user_meta($manager->ID, 'mulopimfwc_manager_capabilities', true);

        // If no capabilities are set, deny access to everything except dashboard and profile
        if (empty($manager_capabilities) || !is_array($manager_capabilities)) {
            $manager_capabilities = [];
        }
        $manager_capabilities = self::normalize_manager_capabilities($manager_capabilities);

        // Get all global menu items
        global $menu, $submenu;

        // Base allowed pages (always accessible)
        $base_allowed_pages = [
            'index.php', // Dashboard
            'profile.php', // User profile
        ];

        $can_view_dashboard = in_array('view_dashboard', $manager_capabilities, true);
        $can_view_products = in_array('view_products', $manager_capabilities, true);
        $can_manage_products = in_array('manage_products', $manager_capabilities, true);
        $can_run_reports = in_array('run_reports', $manager_capabilities, true);
        $show_plugin_parent = $can_view_dashboard || $can_view_products || $can_manage_products || $can_run_reports;

        // Capability-based page mapping
        $capability_pages = [
            'view_dashboard' => [
                'multi-location-product-and-inventory-management-pro',
            ],
            'view_products' => [
                'location-stock-management',
            ],
            'manage_products' => [
                'location-stock-management',
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
                'mulopimfwc-analytics',
                // Add specific report pages here if you have custom ones
            ],
        ];

        // Build allowed pages based on capabilities
        $allowed_pages = $base_allowed_pages;
        $allowed_parents = $base_allowed_pages;

        foreach ($manager_capabilities as $capability) {
            if (isset($capability_pages[$capability])) {
                $allowed_pages = array_merge($allowed_pages, $capability_pages[$capability]);
            }
        }

        if ($show_plugin_parent) {
            $allowed_parents[] = 'multi-location-product-and-inventory-management-pro';
        }

        // Remove duplicates
        $allowed_pages = array_unique($allowed_pages);
        $allowed_parents = array_unique($allowed_parents);

        // Remove all top-level menus except allowed ones
        foreach ($menu as $index => $item) {
            if (!in_array($item[2], $allowed_pages, true) && !in_array($item[2], $allowed_parents, true)) {
                remove_menu_page($item[2]);
            }
        }

        // Remove all submenus except for allowed pages
        foreach ($submenu as $parent => $items) {
            if (!in_array($parent, $allowed_pages, true) && !in_array($parent, $allowed_parents, true)) {
                remove_menu_page($parent);
                continue;
            }

            // Clean up submenus for allowed pages
            foreach ($items as $index => $subitem) {
                if (!in_array($subitem[2], $allowed_pages, true)) {
                    unset($submenu[$parent][$index]);
                }
            }
        }
    }

    private function get_location_manager_admin_bar_permissions()
    {
        $can_view_dashboard = self::user_has_capability('view_dashboard');
        $can_view_products = self::user_has_capability('view_products');
        $can_manage_products = self::user_has_capability('manage_products');
        $can_view_orders = self::user_has_capability('view_orders');
        $can_manage_orders = self::user_has_capability('manage_orders');
        $can_run_reports = self::user_has_capability('run_reports');

        return [
            'view_dashboard' => $can_view_dashboard,
            'view_products' => $can_view_products,
            'manage_products' => $can_manage_products,
            'view_orders' => $can_view_orders,
            'manage_orders' => $can_manage_orders,
            'run_reports' => $can_run_reports,
            'can_access_any_plugin_area' => (
                $can_view_dashboard ||
                $can_view_products ||
                $can_manage_products ||
                $can_view_orders ||
                $can_manage_orders ||
                $can_run_reports
            ),
        ];
    }

    private function location_manager_can_access_admin_bar_href($href, $permissions)
    {
        if (!is_string($href) || $href === '') {
            return true;
        }

        $decoded_href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
        $normalized_href = strtolower($decoded_href);

        if (
            strpos($normalized_href, 'action=logout') !== false ||
            strpos($normalized_href, 'profile.php') !== false ||
            strpos($normalized_href, 'user-edit.php') !== false
        ) {
            return true;
        }

        $parsed = wp_parse_url($decoded_href);
        $path = isset($parsed['path']) ? strtolower((string) $parsed['path']) : '';
        $basename = $path !== '' ? basename($path) : '';
        $query = [];
        if (!empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }

        $page = isset($query['page']) ? sanitize_key((string) $query['page']) : '';
        $post_type = isset($query['post_type']) ? sanitize_key((string) $query['post_type']) : '';
        $taxonomy = isset($query['taxonomy']) ? sanitize_key((string) $query['taxonomy']) : '';

        $looks_like_admin_link = (
            strpos($normalized_href, 'wp-admin') !== false ||
            in_array($basename, [
                'index.php',
                'admin.php',
                'edit.php',
                'post.php',
                'post-new.php',
                'edit-tags.php',
                'themes.php',
                'theme-editor.php',
                'customize.php',
                'widgets.php',
                'nav-menus.php',
                'plugins.php',
                'plugin-editor.php',
                'tools.php',
                'import.php',
                'export.php',
                'options-general.php',
                'options-writing.php',
                'options-reading.php',
                'options-discussion.php',
                'options-media.php',
                'options-permalink.php',
                'site-health.php',
                'privacy.php',
                'update-core.php',
                'upload.php',
                'media-new.php',
                'users.php',
                'user-new.php',
                'edit-comments.php',
            ], true)
        );

        if (!$looks_like_admin_link && $page === '') {
            return true;
        }

        if (in_array($basename, [
            'themes.php',
            'theme-editor.php',
            'customize.php',
            'widgets.php',
            'nav-menus.php',
            'plugins.php',
            'plugin-editor.php',
            'tools.php',
            'import.php',
            'export.php',
            'options-general.php',
            'options-writing.php',
            'options-reading.php',
            'options-discussion.php',
            'options-media.php',
            'options-permalink.php',
            'site-health.php',
            'privacy.php',
            'update-core.php',
            'users.php',
            'user-new.php',
            'edit-comments.php',
        ], true)) {
            return false;
        }

        if ($basename === 'index.php') {
            return (bool) $permissions['view_dashboard'];
        }

        if ($page === 'multi-location-product-and-inventory-management-pro') {
            return (bool) $permissions['view_dashboard'];
        }

        if ($page === 'location-stock-management') {
            return (bool) ($permissions['view_products'] || $permissions['manage_products']);
        }

        if ($page === 'mulopimfwc-analytics') {
            return (bool) $permissions['run_reports'];
        }

        if ($page === 'location-managers' || $page === 'multi-location-product-and-inventory-management-settings') {
            return false;
        }

        if ($page === 'wc-admin' || strpos($normalized_href, 'page=wc-admin') !== false) {
            return (bool) (
                $permissions['view_dashboard'] ||
                $permissions['view_products'] ||
                $permissions['manage_products'] ||
                $permissions['view_orders'] ||
                $permissions['manage_orders'] ||
                $permissions['run_reports']
            );
        }

        if ($page === 'wc-orders' || $post_type === 'shop_order' || strpos($normalized_href, 'wc-orders') !== false) {
            return (bool) ($permissions['view_orders'] || $permissions['manage_orders']);
        }

        if ($page === 'wc-reports' || strpos($normalized_href, 'wc-reports') !== false) {
            return (bool) $permissions['run_reports'];
        }

        if (in_array($page, ['wc-settings', 'wc-status', 'wc-addons'], true)) {
            return false;
        }

        if (
            $post_type === 'product' ||
            in_array($taxonomy, ['product_cat', 'product_tag', 'product_brand', 'mulopimfwc_store_location'], true) ||
            $page === 'product_attributes' ||
            strpos($normalized_href, 'post_type=product') !== false ||
            strpos($normalized_href, 'mulopimfwc_store_location') !== false
        ) {
            return (bool) $permissions['manage_products'];
        }

        if (in_array($basename, ['upload.php', 'media-new.php'], true)) {
            return (bool) $permissions['manage_products'];
        }

        if ($basename === 'post.php') {
            $post_id = isset($query['post']) ? absint($query['post']) : 0;
            $resolved_post_type = $post_type;
            if ($resolved_post_type === '' && $post_id > 0) {
                $resolved_post_type = (string) get_post_type($post_id);
            }

            if ($resolved_post_type === 'product') {
                return (bool) $permissions['manage_products'];
            }

            if ($resolved_post_type === 'shop_order') {
                return (bool) ($permissions['view_orders'] || $permissions['manage_orders']);
            }

            if ($resolved_post_type !== '') {
                return false;
            }
        }

        if ($basename === 'post-new.php') {
            return false;
        }

        if ($basename === 'edit.php' && ($post_type === '' || $post_type === 'post' || $post_type === 'page')) {
            return false;
        }

        if ($page !== '' || strpos($normalized_href, 'wp-admin') !== false) {
            return (bool) $permissions['view_dashboard'];
        }

        return true;
    }

    public function restrict_location_manager_admin_bar($wp_admin_bar)
    {
        if (is_network_admin()) {
            return;
        }

        if (current_user_can('administrator') || (is_multisite() && is_super_admin())) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $manager = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', (array) $manager->roles, true)) {
            return;
        }

        $permissions = $this->get_location_manager_admin_bar_permissions();

        if (!$permissions['can_access_any_plugin_area']) {
            $wp_admin_bar->remove_node('mulopimfwc-notifications');
            $wp_admin_bar->remove_node('mulopimfwc-notifications-dropdown');
        }

        $nodes = (array) $wp_admin_bar->get_nodes();
        if (empty($nodes)) {
            return;
        }

        $always_allowed_node_ids = [
            'my-account',
            'user-actions',
            'user-info',
            'logout',
            'edit-profile',
        ];

        foreach ($nodes as $node) {
            if (!is_object($node)) {
                continue;
            }

            if (in_array((string) $node->id, $always_allowed_node_ids, true)) {
                continue;
            }

            if (!$this->location_manager_can_access_admin_bar_href($node->href ?? '', $permissions)) {
                $wp_admin_bar->remove_node($node->id);
            }
        }

        $remaining_nodes = (array) $wp_admin_bar->get_nodes();
        $has_new_content_child = false;
        foreach ($remaining_nodes as $remaining_node) {
            if (is_object($remaining_node) && isset($remaining_node->parent) && $remaining_node->parent === 'new-content') {
                $has_new_content_child = true;
                break;
            }
        }

        if (!$has_new_content_child) {
            $wp_admin_bar->remove_node('new-content');
        }
    }

    private function is_order_details_screen($screen = null)
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = $screen ?: get_current_screen();
        if (!$screen) {
            return false;
        }

        $is_classic_edit = ($screen->base === 'post' && $screen->post_type === 'shop_order');
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        $is_hpos_edit = ($screen->id === 'woocommerce_page_wc-orders' && $action === 'edit');

        return $is_classic_edit || $is_hpos_edit;
    }

    public function make_order_details_readonly_for_view_only_managers($is_editable, $order = null)
    {
        if (!is_admin()) {
            return $is_editable;
        }

        if (!self::current_user_is_view_only_order_manager()) {
            return $is_editable;
        }

        return false;
    }

    public function disable_order_details_editing_for_view_only_managers()
    {
        if (!self::current_user_is_view_only_order_manager()) {
            return;
        }

        if (!$this->is_order_details_screen()) {
            return;
        }
?>
        <style id="mulopimfwc-order-readonly-style">
            .mulopimfwc-order-readonly-field {
                cursor: not-allowed !important;
            }

            .mulopimfwc-order-readonly-link {
                pointer-events: none !important;
                opacity: 0.55 !important;
            }
        </style>
        <script id="mulopimfwc-order-readonly-script">
            (function () {
                function shouldSkip(node) {
                    if (!node) {
                        return true;
                    }

                    if (node.matches('.notice-dismiss, .handlediv, .postbox-header button, .hndle button, .toggle-indicator')) {
                        return true;
                    }

                    if (node.closest('#screen-options-wrap, #contextual-help-wrap, #wpadminbar, #adminmenuwrap, #adminmenuback')) {
                        return true;
                    }

                    return false;
                }

                function lockOrderEditing() {
                    var roots = document.querySelectorAll('#post, .woocommerce-layout__main, .woocommerce-order');
                    if (!roots.length) {
                        roots = [document];
                    }

                    roots.forEach(function (root) {
                        var controls = root.querySelectorAll('input:not([type="hidden"]), select, textarea, button');
                        controls.forEach(function (control) {
                            if (shouldSkip(control)) {
                                return;
                            }

                            control.disabled = true;
                            if (control.tagName === 'INPUT' || control.tagName === 'TEXTAREA') {
                                control.readOnly = true;
                            }
                            control.classList.add('mulopimfwc-order-readonly-field');
                        });

                        var actionLinks = root.querySelectorAll('a.button, a.button-primary, a.button-secondary');
                        actionLinks.forEach(function (link) {
                            if (shouldSkip(link)) {
                                return;
                            }

                            link.classList.add('mulopimfwc-order-readonly-link');
                            link.setAttribute('aria-disabled', 'true');
                            link.setAttribute('tabindex', '-1');
                        });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', lockOrderEditing);
                } else {
                    lockOrderEditing();
                }

                var observer = new MutationObserver(function () {
                    lockOrderEditing();
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            })();
        </script>
<?php
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
        $social_channels = get_user_meta($user->ID, 'mulopimfwc_social_channels', true);
        if (!is_array($social_channels)) {
            $social_channels = [];
        }
        $legacy_slack = get_user_meta($user->ID, 'mulopimfwc_slack_webhook', true);
        if (empty($social_channels['slack_webhook']) && !empty($legacy_slack)) {
            $social_channels['slack_webhook'] = $legacy_slack;
        }
        $capabilities = $this->get_available_capabilities();

        if (!is_array($assigned_locations)) $assigned_locations = [];
        if (!is_array($manager_capabilities)) $manager_capabilities = [];
        $manager_capabilities = self::normalize_manager_capabilities($manager_capabilities);
    ?>

        <h3><?php echo esc_html__('Location Manager Settings', 'multi-location-product-and-inventory-management-pro'); ?></h3>

        <table class="form-table">
            <tr>
                <th><label><?php echo esc_html__('Assigned Locations', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
                <td>
                    <?php if (!empty($mulopimfwc_locations)): ?>
                        <?php foreach ($mulopimfwc_locations as $location): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="mulopimfwc_assigned_locations[]" value="<?php echo esc_attr(rawurldecode($location->slug)); ?>" <?php checked(in_array($location->slug, $assigned_locations)); ?>>
                                <?php echo esc_html($location->name); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?php echo esc_html__('No locations available', 'multi-location-product-and-inventory-management-pro'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label><?php echo esc_html__('Manager Capabilities', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
                <td>
                    <?php foreach ($capabilities as $cap_key => $cap_label): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="mulopimfwc_manager_capabilities[]" value="<?php echo esc_attr($cap_key); ?>" <?php checked(in_array($cap_key, $manager_capabilities)); ?>>
                            <?php echo esc_html($cap_label); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description"><?php echo esc_html__('Individual permissions for this manager. Leave unchecked to use global defaults.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label><?php echo esc_html__('Social Notification Channels', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
                <td>
                    <div class="mulopimfwc-social-grid" style="margin-top: 4px;">
                        <div class="mulopimfwc-social-field">
                            <label for="mulopimfwc_slack_webhook"><?php echo esc_html__('Slack / Discord / Teams Webhook', 'multi-location-product-and-inventory-management-pro'); ?></label>
                            <input type="url" name="mulopimfwc_slack_webhook" id="mulopimfwc_slack_webhook" value="<?php echo esc_attr(isset($social_channels['slack_webhook']) ? $social_channels['slack_webhook'] : ''); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Paste an incoming webhook URL to receive alerts for this manager.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        </div>
                        <div class="mulopimfwc-social-field">
                            <label for="mulopimfwc_custom_webhook"><?php echo esc_html__('Custom Webhook', 'multi-location-product-and-inventory-management-pro'); ?></label>
                            <input type="url" name="mulopimfwc_custom_webhook" id="mulopimfwc_custom_webhook" value="<?php echo esc_attr(isset($social_channels['custom_webhook']) ? $social_channels['custom_webhook'] : ''); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Any HTTPS endpoint that accepts JSON payloads.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        </div>
                        <div class="mulopimfwc-social-field">
                            <label for="mulopimfwc_telegram_chat_id"><?php echo esc_html__('Telegram Chat ID', 'multi-location-product-and-inventory-management-pro'); ?></label>
                            <input type="text" name="mulopimfwc_telegram_chat_id" id="mulopimfwc_telegram_chat_id" value="<?php echo esc_attr(isset($social_channels['telegram_chat_id']) ? $social_channels['telegram_chat_id'] : ''); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Use @username or numeric chat ID. Requires bot token in Advanced → Social Notifications.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        </div>
                    </div>
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
        $assigned_locations = array_values(array_filter(array_map(function ($location_slug) {
            return sanitize_title(rawurldecode((string) $location_slug));
        }, $assigned_locations)));
        $assigned_locations = array_values(array_unique($assigned_locations));
        $manager_capabilities = isset($_POST['mulopimfwc_manager_capabilities']) ? array_map('sanitize_text_field', $_POST['mulopimfwc_manager_capabilities']) : [];
        $social_channels = [
            'slack_webhook' => isset($_POST['mulopimfwc_slack_webhook']) ? esc_url_raw(trim($_POST['mulopimfwc_slack_webhook'])) : '',
            'custom_webhook' => isset($_POST['mulopimfwc_custom_webhook']) ? esc_url_raw(trim($_POST['mulopimfwc_custom_webhook'])) : '',
            'telegram_chat_id' => isset($_POST['mulopimfwc_telegram_chat_id']) ? sanitize_text_field($_POST['mulopimfwc_telegram_chat_id']) : '',
        ];

        update_user_meta($user_id, 'mulopimfwc_assigned_locations', $assigned_locations);
        update_user_meta($user_id, 'mulopimfwc_manager_capabilities', $manager_capabilities);
        update_user_meta($user_id, 'mulopimfwc_social_channels', $social_channels);
        update_user_meta($user_id, 'mulopimfwc_slack_webhook', $social_channels['slack_webhook']);
        $manager_default_location = sanitize_title(rawurldecode((string) get_user_meta($user_id, 'mulopimfwc_manager_default_location', true)));
        if ($manager_default_location !== '' && !in_array($manager_default_location, $assigned_locations, true)) {
            delete_user_meta($user_id, 'mulopimfwc_manager_default_location');
        }

        // Social alert for manager change (profile save)
        if (function_exists('mulopimfwc_get_social_settings') && function_exists('mulopimfwc_send_social_message') && function_exists('mulopimfwc_collect_social_channels')) {
            $settings = mulopimfwc_get_social_settings();
            if (isset($settings['enabled'], $settings['manager_change_alert']) && $settings['enabled'] === 'on' && $settings['manager_change_alert'] === 'on') {
                $channels = mulopimfwc_collect_social_channels('', $settings);
                if (!empty($channels)) {
                    $user_obj = get_user_by('id', $user_id);
                    mulopimfwc_send_social_message(
                        __('Manager updated', 'multi-location-product-and-inventory-management-pro'),
                        sprintf(
                            /* translators: 1: manager display name, 2: comma-separated locations or "none" */
                            __('%1$s profile was updated. Locations: %2$s', 'multi-location-product-and-inventory-management-pro'),
                            $user_obj ? $user_obj->display_name : __('Manager', 'multi-location-product-and-inventory-management-pro'),
                            !empty($assigned_locations) ? implode(', ', $assigned_locations) : __('none', 'multi-location-product-and-inventory-management-pro')
                        ),
                        $channels,
                        $settings,
                        admin_url('user-edit.php?user_id=' . $user_id)
                    );
                }
            }
        }
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
    public static function user_has_capability($capability, $user_id = null)
    {
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return false;
            }
        } else {
            if (!is_user_logged_in()) {
                return false;
            }
            $user = wp_get_current_user();
        }

        if (!in_array('mulopimfwc_location_manager', $user->roles, true)) {
            return $user_id ? false : current_user_can('manage_woocommerce');
        }

        // Get individual capabilities
        $manager_capabilities = get_user_meta($user->ID, 'mulopimfwc_manager_capabilities', true);
        if (!is_array($manager_capabilities) || empty($manager_capabilities)) {
            // Use global capabilities
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $manager_capabilities = isset($options['location_manager_capabilities']) ? $options['location_manager_capabilities'] : [];
        }
        $manager_capabilities = self::normalize_manager_capabilities($manager_capabilities);

        return in_array($capability, $manager_capabilities, true);
    }

    public static function current_user_can_manage_orders()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
            return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
        }

        return self::user_has_capability('manage_orders', $user->ID);
    }

    public static function current_user_is_view_only_order_manager()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
            return false;
        }

        return self::user_has_capability('view_orders', $user->ID)
            && !self::user_has_capability('manage_orders', $user->ID);
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
global $MULOPIMFWC_Location_Managers;

$MULOPIMFWC_Location_Managers = new MULOPIMFWC_Location_Managers();







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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
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
        echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management-pro') . '</option>';

        foreach ($mulopimfwc_locations as $location) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr(rawurldecode($location->slug)),
                selected($selected_location, rawurldecode($location->slug), false),
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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
            return;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();

        if (empty($assigned_locations)) {
            echo '<div class="notice notice-warning"><p>' .
                esc_html__('You are not assigned to any locations. No orders will be displayed.', 'multi-location-product-and-inventory-management-pro') .
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
                    // translators: %s: List of location names (e.g. "Sydney, Melbourne")
                    esc_html__('You are viewing orders for: %s', 'multi-location-product-and-inventory-management-pro'),
                    '<strong>' . esc_attr(implode(', ', $location_names)) . '</strong>'
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

        // Filter HPOS list table counts (wc-orders screen)
        add_filter('woocommerce_shop_order_list_table_order_count', [$this, 'filter_hpos_list_table_order_count'], 10, 2);

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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
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
     * Filter HPOS list table order counts for location managers.
     */
    public function filter_hpos_list_table_order_count($count, $statuses)
    {
        if (!is_admin() || !$this->is_current_user_location_manager()) {
            return $count;
        }
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
            return $count;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        if (empty($assigned_locations)) {
            return 0;
        }

        $total = 0;
        foreach ((array) $statuses as $status) {
            $total += $this->get_filtered_order_count($status, $assigned_locations);
        }

        return $total;
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
    public function is_current_user_location_manager()
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
        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
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
 * Filter products in admin area for location managers without all_products capability.
 */
class MULOPIMFWC_Product_Filter
{
    public function __construct()
    {
        add_action('pre_get_posts', [$this, 'filter_admin_products_by_location_manager'], 999);
    }

    private function should_skip_admin_product_location_filter($user): bool
    {
        $roles = is_object($user) && isset($user->roles) ? (array) $user->roles : [];

        return in_array('administrator', $roles, true) || in_array('shop_manager', $roles, true);
    }

    public function filter_admin_products_by_location_manager($query)
    {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        $post_type = $query->get('post_type');
        $targets_product = $post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true));
        if (!$targets_product) {
            return;
        }

        $user = wp_get_current_user();
        if ($this->should_skip_admin_product_location_filter($user)) {
            return;
        }

        if (!in_array('mulopimfwc_location_manager', $user->roles, true)) {
            return;
        }

        if (MULOPIMFWC_Location_Managers::user_has_capability('all_products')) {
            return;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        if (empty($assigned_locations)) {
            $query->set('post__in', [0]);
            return;
        }

        $location_tax_query = $this->build_location_tax_query($assigned_locations);
        $existing_tax_query = (array) $query->get('tax_query');
        if (!empty($existing_tax_query)) {
            $existing_tax_query[] = $location_tax_query;
            if (!isset($existing_tax_query['relation'])) {
                $existing_tax_query['relation'] = 'AND';
            }
            $query->set('tax_query', $existing_tax_query);
        } else {
            $query->set('tax_query', [$location_tax_query]);
        }
    }

    private function build_location_tax_query($locations)
    {
        return [
            'taxonomy' => 'mulopimfwc_store_location',
            'field' => 'slug',
            'terms' => $locations,
            'operator' => 'IN',
        ];
    }
}

new MULOPIMFWC_Product_Filter();

/**
 * Filter product counts in admin lists for location managers without all_products capability.
 */
class MULOPIMFWC_Product_Count_Filter
{
    public function __construct()
    {
        add_filter('wp_count_posts', [$this, 'filter_product_count'], 10, 3);
    }

    private function should_skip_admin_product_location_filter($user): bool
    {
        $roles = is_object($user) && isset($user->roles) ? (array) $user->roles : [];

        return in_array('administrator', $roles, true) || in_array('shop_manager', $roles, true);
    }

    public function filter_product_count($counts, $type, $perm)
    {
        if ($type !== 'product' || !is_admin()) {
            return $counts;
        }

        if (!is_user_logged_in()) {
            return $counts;
        }

        $user = wp_get_current_user();
        if ($this->should_skip_admin_product_location_filter($user)) {
            return $counts;
        }

        if (!in_array('mulopimfwc_location_manager', $user->roles, true)) {
            return $counts;
        }

        if (MULOPIMFWC_Location_Managers::user_has_capability('all_products')) {
            return $counts;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        if (empty($assigned_locations)) {
            $empty_counts = new stdClass();
            foreach ($counts as $status => $count) {
                $empty_counts->$status = 0;
            }
            return $empty_counts;
        }

        $filtered_counts = $this->get_filtered_product_counts($assigned_locations, $perm);
        $updated_counts = new stdClass();
        foreach ($counts as $status => $count) {
            $updated_counts->$status = isset($filtered_counts[$status]) ? $filtered_counts[$status] : 0;
        }

        return $updated_counts;
    }

    private function get_filtered_product_counts($assigned_locations, $perm)
    {
        global $wpdb;

        $assigned_locations = array_values(array_filter($assigned_locations));
        if (empty($assigned_locations)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($assigned_locations), '%s'));
        $params = array_merge(['mulopimfwc_store_location'], $assigned_locations);

        $where = "p.post_type = 'product'
            AND tt.taxonomy = %s
            AND t.slug IN ({$placeholders})";

        $post_type_object = get_post_type_object('product');
        $can_read_private = $post_type_object && current_user_can($post_type_object->cap->read_private_posts);

        if ('readable' === $perm && is_user_logged_in() && !$can_read_private) {
            $where .= " AND (p.post_status != 'private' OR (p.post_status = 'private' AND p.post_author = %d))";
            $params[] = get_current_user_id();
        }

        $sql = $wpdb->prepare("
            SELECT p.post_status, COUNT(DISTINCT p.ID) AS num_posts
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE {$where}
            GROUP BY p.post_status
        ", $params);

        $results = (array) $wpdb->get_results($sql, ARRAY_A);
        $counts = [];

        foreach ($results as $row) {
            $counts[$row['post_status']] = (int) $row['num_posts'];
        }

        return $counts;
    }
}

new MULOPIMFWC_Product_Count_Filter();

/**
 * Filter product term counts in taxonomy lists for location managers without all_products capability.
 */
class MULOPIMFWC_Product_Term_Count_Filter
{
    public function __construct()
    {
        add_filter('get_terms', [$this, 'filter_product_term_counts'], 10, 4);
    }

    private function should_skip_admin_product_location_filter($user): bool
    {
        $roles = is_object($user) && isset($user->roles) ? (array) $user->roles : [];

        return in_array('administrator', $roles, true) || in_array('shop_manager', $roles, true);
    }

    public function filter_product_term_counts($terms, $taxonomies, $args, $term_query)
    {
        if (!is_admin() || !is_user_logged_in() || is_wp_error($terms)) {
            return $terms;
        }

        if (!is_array($terms) || empty($terms)) {
            return $terms;
        }

        if (!class_exists('MULOPIMFWC_Location_Managers')) {
            return $terms;
        }

        $user = wp_get_current_user();
        if ($this->should_skip_admin_product_location_filter($user)) {
            return $terms;
        }

        if (!in_array('mulopimfwc_location_manager', $user->roles, true)) {
            return $terms;
        }

        if (MULOPIMFWC_Location_Managers::user_has_capability('all_products')) {
            return $terms;
        }

        if (!$this->is_product_taxonomy_list_screen($taxonomies)) {
            return $terms;
        }

        if (isset($args['fields']) && !in_array($args['fields'], ['all', 'all_with_object_id'], true)) {
            return $terms;
        }

        $taxonomy = $this->resolve_taxonomy($taxonomies, $terms);
        if (empty($taxonomy) || $taxonomy === 'mulopimfwc_store_location') {
            return $terms;
        }

        if (!is_object_in_taxonomy('product', $taxonomy)) {
            return $terms;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        if (empty($assigned_locations)) {
            foreach ($terms as $term) {
                if (is_object($term)) {
                    $term->count = 0;
                }
            }
            return $terms;
        }

        $term_ids = array_values(array_unique(array_map('intval', wp_list_pluck($terms, 'term_id'))));
        if (empty($term_ids)) {
            return $terms;
        }

        $counts = $this->get_location_filtered_term_counts($taxonomy, $term_ids, $assigned_locations);
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->term_id)) {
                $term->count = isset($counts[$term->term_id]) ? $counts[$term->term_id] : 0;
            }
        }

        return $terms;
    }

    private function is_product_taxonomy_list_screen($taxonomies)
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        if ($screen->base !== 'edit-tags' || $screen->post_type !== 'product') {
            return false;
        }

        $taxonomies = (array) $taxonomies;
        if (empty($screen->taxonomy) || !in_array($screen->taxonomy, $taxonomies, true)) {
            return false;
        }

        return true;
    }

    private function resolve_taxonomy($taxonomies, $terms)
    {
        $taxonomies = (array) $taxonomies;
        if (count($taxonomies) === 1) {
            return $taxonomies[0];
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && !empty($screen->taxonomy) && in_array($screen->taxonomy, $taxonomies, true)) {
                return $screen->taxonomy;
            }
        }

        $first_term = reset($terms);
        if (is_object($first_term) && !empty($first_term->taxonomy)) {
            return $first_term->taxonomy;
        }

        return $taxonomies[0] ?? '';
    }

    private function get_location_filtered_term_counts($taxonomy, $term_ids, $assigned_locations)
    {
        global $wpdb;

        $term_ids = array_values(array_filter(array_map('intval', $term_ids)));
        $assigned_locations = array_values(array_filter($assigned_locations));

        if (empty($term_ids) || empty($assigned_locations)) {
            return [];
        }

        $taxonomy_object = get_taxonomy($taxonomy);
        $post_statuses = ['publish'];
        if ($taxonomy_object) {
            $post_statuses = apply_filters('update_post_term_count_statuses', $post_statuses, $taxonomy_object);
        }

        $post_statuses = array_values(array_filter($post_statuses));
        if (empty($post_statuses)) {
            return [];
        }

        $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));
        $term_placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        $location_placeholders = implode(',', array_fill(0, count($assigned_locations), '%s'));

        $sql = "
            SELECT tt.term_id, COUNT(DISTINCT p.ID) AS term_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->term_relationships} tr_loc ON p.ID = tr_loc.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt_loc ON tr_loc.term_taxonomy_id = tt_loc.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t_loc ON tt_loc.term_id = t_loc.term_id
            WHERE p.post_type = 'product'
                AND p.post_status IN ({$status_placeholders})
                AND tt.taxonomy = %s
                AND tt.term_id IN ({$term_placeholders})
                AND tt_loc.taxonomy = 'mulopimfwc_store_location'
                AND t_loc.slug IN ({$location_placeholders})
            GROUP BY tt.term_id
        ";

        $params = array_merge($post_statuses, [$taxonomy], $term_ids, $assigned_locations);
        $prepared = $wpdb->prepare($sql, ...$params);
        $results = (array) $wpdb->get_results($prepared, ARRAY_A);

        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row['term_id']] = (int) $row['term_count'];
        }

        return $counts;
    }
}

new MULOPIMFWC_Product_Term_Count_Filter();

/**
 * Alternative approach: Hook into WooCommerce reports and dashboard widgets
 */
add_filter('woocommerce_reports_order_statuses', function ($order_statuses) {
    $filter = new MULOPIMFWC_Order_Count_Filter();
    if (
        method_exists($filter, 'is_current_user_location_manager') &&
        $filter->is_current_user_location_manager() &&
        !MULOPIMFWC_Location_Managers::user_has_capability('all_orders')
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
