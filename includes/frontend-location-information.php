<?php

/**
 * Frontend Location Information Display - Enhanced with Professional UI
 * Handles location archives and single product location information with maps and business hours
 *
 * @package Multi_Location_Product_Inventory_Management
 */

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Frontend_Location_Information
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        global $mulopimfwc_options;
        // mulopimfwc_display_options[store_locator_archive_position]
        $store_locator_archive_position = isset($mulopimfwc_options['store_locator_archive_position']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['store_locator_archive_position']
            : 'before_shop_loop';
        // Archive page hook based on settings
        switch ($store_locator_archive_position) {
            case 'before_breadcrumbs':
                add_action('mulopimfwc_before_location_products', [$this, 'display_location_archive_info'], 5);
                break;
            case 'after_breadcrumbs':
                add_action('mulopimfwc_before_location_products', [$this, 'display_location_archive_info'], 15);
                break;
            case 'after_shop_loop':
                add_action('mulopimfwc_after_location_products', [$this, 'display_location_archive_info'], 5);
                break;
            default:
                add_action('mulopimfwc_store_location_description_before_loop', [$this, 'display_location_archive_info'], 5);
                break;
        }

        // mulopimfwc_display_options[store_locator_single_product_position]
        $store_locator_single_product_position = isset($mulopimfwc_options['store_locator_single_product_position']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['store_locator_single_product_position']
            : 'in_tabs';
        // Single product page hook based on settings
        switch ($store_locator_single_product_position) {
            case 'before_product':
                add_action('woocommerce_before_single_product', [$this, 'display_single_product_location_info'], 15);
                break;
            case 'before_related':
                add_action('woocommerce_after_single_product_summary', [$this, 'display_single_product_location_info'], 19);
                break;
            case 'after_related':
                add_action('woocommerce_after_single_product_summary', [$this, 'display_single_product_location_info'], 25);
                break;
            case 'after_summary':
                add_action('woocommerce_after_single_product_summary', [$this, 'display_single_product_location_info'], 15);
                break;
            default:
                add_action('woocommerce_product_tabs', [$this, 'add_location_info_tab'], 30);
                break;
        }

        // Shortcode
        add_shortcode('mulopimfwc_location_info', [$this, 'location_info_shortcode']);

        // Template override
        add_filter('template_include', [$this, 'location_archive_template'], 99);
    }

    /**
     * Display location information tab content
     */
    public function add_location_info_tab($tabs)
    {

        global $mulopimfwc_options, $product;

        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['enable_store_locator']
            : 'off';

        if ($enable_locator !== 'on' || !is_singular('product')) {
            return $tabs;
        }

        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }

        if (!$product) {
            return $tabs;
        }

        $locations = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location');

        if (empty($locations) || is_wp_error($locations)) {
            return $tabs;
        }


        $tabs['location_info'] = [
            'title' => __('Location Information', 'multi-location-product-inventory-management'),
            'priority' => 30,
            'callback' => [$this, 'display_single_product_location_info'],
        ];
        return $tabs;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets()
    {
        global $mulopimfwc_options;

        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['enable_store_locator']
            : 'off';

        // mulopimfwc_display_options[default_map_zoom]
        $default_map_zoom = isset($mulopimfwc_options['default_map_zoom']) && mulopimfwc_premium_feature()
            ? intval($mulopimfwc_options['default_map_zoom'])
            : 15;

        if ($enable_locator !== 'on') {
            return;
        }

        // Check if we're on a location archive or single product with location
        // if (!is_tax('mulopimfwc_store_location') && !is_singular('product')) {
        //     return;
        // }

        // Leaflet CSS & JS
        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', ['jquery'], '1.9.4', true);

        // Custom styles
        wp_enqueue_style(
            'mulopimfwc-location-info',
            MULTI_LOCATION_PLUGIN_URL . 'assets/css/location-info.css',
            [],
            mulopimfwc_VERSION
        );

        // Custom scripts
        wp_enqueue_script(
            'mulopimfwc-location-info',
            MULTI_LOCATION_PLUGIN_URL . 'assets/js/location-info.js',
            ['jquery', 'leaflet'],
            mulopimfwc_VERSION,
            true
        );

        // Localize script
        wp_localize_script('mulopimfwc-location-info', 'mulopimfwcLocationInfo', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_location_info'),
            'mapTileUrl' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'mapAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'defaultMapZoom' => $default_map_zoom,
        ]);
    }

    /**
     * Display location information on archive page - COMPREHENSIVE VERSION
     */
    public function display_location_archive_info()
    {
        global $mulopimfwc_options;

        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['enable_store_locator']
            : 'off';

        if ($enable_locator !== 'on' || !is_tax('mulopimfwc_store_location')) {
            return;
        }

        $term = get_queried_object();
        if (!$term || is_wp_error($term)) {
            return;
        }

        $this->render_full_location_details($term->term_id);
    }

    /**
     * Display location information on single product page - TABBED VERSION
     */
    public function display_single_product_location_info()
    {
        global $mulopimfwc_options, $product;

        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['enable_store_locator']
            : 'off';

        if ($enable_locator !== 'on' || !is_singular('product')) {
            return;
        }

        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }

        if (!$product) {
            return;
        }

        $locations = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location');

        if (empty($locations) || is_wp_error($locations)) {
            return;
        }

        // Display tabbed interface for multiple locations
        if (count($locations) > 1) {
            $this->render_tabbed_locations($locations);
        } else {
            // Single location - compact display
            $this->render_location_details($locations[0]->term_id, 'product', true);
        }
    }

    /**
     * Render full location details for archive page
     */
    private function render_full_location_details($term_id)
    {
        $location = get_term($term_id, 'mulopimfwc_store_location');

        if (!$location || is_wp_error($location)) {
            return;
        }

        // Get all location meta
        $street_address = get_term_meta($term_id, 'street_address', true);
        $city = get_term_meta($term_id, 'city', true);
        $state = get_term_meta($term_id, 'state', true);
        $postal_code = get_term_meta($term_id, 'postal_code', true);
        $country = get_term_meta($term_id, 'country', true);
        $email = get_term_meta($term_id, 'email', true);
        $phone = get_term_meta($term_id, 'phone', true);
        $latitude = get_term_meta($term_id, 'latitude', true);
        $longitude = get_term_meta($term_id, 'longitude', true);
        $logo_id = get_term_meta($term_id, 'logo_id', true);
        $gallery_ids = get_term_meta($term_id, 'gallery_ids', true);

        global $MULOPIMFWC_Admin;
        $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);

?>
        <div class="mulopimfwc-location-full-wrapper" data-location-id="<?php echo esc_attr($term_id); ?>">

            <!-- Hero Section -->
            <div class="mulopimfwc-location-hero">
                <div class="mulopimfwc-hero-content">
                    <?php if ($logo_id): ?>
                        <div class="mulopimfwc-hero-logo">
                            <?php echo wp_get_attachment_image($logo_id, 'large', false, ['alt' => $location->name]); ?>
                        </div>
                    <?php endif; ?>

                    <div class="mulopimfwc-hero-info">
                        <div class="mulopimfwc-hero-header">
                            <h1 class="mulopimfwc-location-title">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                                </svg>
                                <?php echo esc_html($location->name); ?>
                            </h1>
                            <?php echo $this->render_status_badge($status); ?>
                        </div>

                        <?php if ($location->description): ?>
                            <div class="mulopimfwc-hero-description">
                                <?php echo wp_kses_post(wpautop($location->description)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="mulopimfwc-location-grid">

                <!-- Left Column -->
                <div class="mulopimfwc-location-sidebar">

                    <!-- Contact Card -->
                    <div class="mulopimfwc-info-card">
                        <h3 class="mulopimfwc-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor" />
                            </svg>
                            <?php esc_html_e('Contact Information', 'multi-location-product-and-inventory-management'); ?>
                        </h3>

                        <div class="mulopimfwc-contact-list">
                            <?php if ($street_address || $city): ?>
                                <div class="mulopimfwc-contact-item">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor" />
                                    </svg>
                                    <div>
                                        <div class="mulopimfwc-contact-label"><?php esc_html_e('Address', 'multi-location-product-and-inventory-management'); ?></div>
                                        <div class="mulopimfwc-contact-value">
                                            <?php if ($street_address): ?>
                                                <div><?php echo esc_html($street_address); ?></div>
                                            <?php endif; ?>
                                            <?php if ($city || $state || $postal_code): ?>
                                                <div>
                                                    <?php
                                                    $address_line = array_filter([$city, $state, $postal_code]);
                                                    echo esc_html(implode(', ', $address_line));
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($country): ?>
                                                <div><?php echo esc_html($country); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($phone): ?>
                                <div class="mulopimfwc-contact-item">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor" />
                                    </svg>
                                    <div>
                                        <div class="mulopimfwc-contact-label"><?php esc_html_e('Phone', 'multi-location-product-and-inventory-management'); ?></div>
                                        <a href="tel:<?php echo esc_attr($phone); ?>" class="mulopimfwc-contact-value mulopimfwc-contact-link">
                                            <?php echo esc_html($phone); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($email): ?>
                                <div class="mulopimfwc-contact-item">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor" />
                                    </svg>
                                    <div>
                                        <div class="mulopimfwc-contact-label"><?php esc_html_e('Email', 'multi-location-product-and-inventory-management'); ?></div>
                                        <a href="mailto:<?php echo esc_attr($email); ?>" class="mulopimfwc-contact-value mulopimfwc-contact-link">
                                            <?php echo esc_html($email); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($latitude && $longitude): ?>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mulopimfwc-btn mulopimfwc-btn-primary mulopimfwc-btn-block">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor" />
                                </svg>
                                <?php esc_html_e('Get Directions', 'multi-location-product-and-inventory-management'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Business Hours Card -->
                    <?php $this->render_business_hours_card($term_id, $status); ?>

                </div>

                <!-- Right Column -->
                <div class="mulopimfwc-location-main">

                    <!-- Map Section -->
                    <?php if ($latitude && $longitude): ?>
                        <div class="mulopimfwc-map-card">
                            <h3 class="mulopimfwc-card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z" fill="currentColor" />
                                </svg>
                                <?php esc_html_e('Location Map', 'multi-location-product-and-inventory-management'); ?>
                            </h3>
                            <div class="mulopimfwc-map-container">
                                <div id="mulopimfwc-map-<?php echo esc_attr($term_id); ?>"
                                    class="mulopimfwc-location-map mulopimfwc-map-large"
                                    data-lat="<?php echo esc_attr($latitude); ?>"
                                    data-lng="<?php echo esc_attr($longitude); ?>"
                                    data-name="<?php echo esc_attr($location->name); ?>"
                                    data-address="<?php echo esc_attr($street_address . ', ' . $city); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Gallery Section -->
                    <?php if ($gallery_ids && is_array($gallery_ids) && !empty($gallery_ids)): ?>
                        <div class="mulopimfwc-gallery-card">
                            <h3 class="mulopimfwc-card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" fill="currentColor" />
                                </svg>
                                <?php esc_html_e('Gallery', 'multi-location-product-and-inventory-management'); ?>
                            </h3>
                            <div class="mulopimfwc-gallery-grid">
                                <?php foreach ($gallery_ids as $image_id): ?>
                                    <?php
                                    $image = wp_get_attachment_image_src($image_id, 'medium');
                                    $full_image = wp_get_attachment_image_src($image_id, 'full');
                                    if ($image):
                                    ?>
                                        <a href="<?php echo esc_url($full_image[0]); ?>"
                                            class="mulopimfwc-gallery-item"
                                            data-lightbox="location-<?php echo esc_attr($term_id); ?>">
                                            <img src="<?php echo esc_url($image[0]); ?>"
                                                alt="<?php echo esc_attr($location->name); ?>"
                                                loading="lazy">
                                            <div class="mulopimfwc-gallery-overlay">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor" />
                                                </svg>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

            </div>

        </div>
    <?php
    }

    /**
     * Render tabbed locations for single product page
     */
    private function render_tabbed_locations($locations)
    {
        global $MULOPIMFWC_Admin;

    ?>
        <div class="mulopimfwc-tabbed-locations-wrapper">
            <h3 class="mulopimfwc-locations-heading">
                <?php esc_html_e('Available at Multiple Locations', 'multi-location-product-and-inventory-management'); ?>
            </h3>

            <div class="mulopimfwc-locations-tabs-container">

                <!-- Tabs Sidebar -->
                <div class="mulopimfwc-tabs-sidebar">
                    <div class="mulopimfwc-tabs-list">
                        <?php foreach ($locations as $index => $location):
                            $term_id = $location->term_id;
                            $logo_id = get_term_meta($term_id, 'logo_id', true);
                            $street_address = get_term_meta($term_id, 'street_address', true);
                            $city = get_term_meta($term_id, 'city', true);
                            $phone = get_term_meta($term_id, 'phone', true);
                            $email = get_term_meta($term_id, 'email', true);
                            $latitude = get_term_meta($term_id, 'latitude', true);
                            $longitude = get_term_meta($term_id, 'longitude', true);
                            $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);
                            $is_active = $index === 0 ? 'active' : '';
                        ?>
                            <div class="mulopimfwc-tab-item <?php echo esc_attr($is_active); ?>"
                                data-tab="location-<?php echo esc_attr($term_id); ?>"
                                data-lat="<?php echo esc_attr($latitude); ?>"
                                data-lng="<?php echo esc_attr($longitude); ?>"
                                data-name="<?php echo esc_attr($location->name); ?>"
                                data-address="<?php echo esc_attr($street_address . ', ' . $city); ?>">

                                <?php if ($logo_id): ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="mulopimfwc-tab-logo">
                                            <?php echo wp_get_attachment_image($logo_id, 'thumbnail', false, ['alt' => $location->name]); ?>
                                        </div>
                                        <div class="mulopimfwc-tab-header" style="width: calc(100% - 80px);">
                                            <h4 class="mulopimfwc-tab-title">
                                                <a href="<?php echo esc_url(get_term_link($location)); ?>" target="_blank">
                                                    <?php echo esc_html($location->name); ?>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                        <path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z" fill="currentColor" />
                                                    </svg>
                                                </a>
                                            </h4>
                                            <?php echo $this->render_status_badge($status, true); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mulopimfwc-tab-info">
                                    <div class="mulopimfwc-tab-details">
                                        <?php if ($street_address || $city): ?>
                                            <div class="mulopimfwc-tab-detail">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor" />
                                                </svg>
                                                <span><?php echo esc_html($street_address . ', ' . $city); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($phone): ?>
                                            <div class="mulopimfwc-tab-detail">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor" />
                                                </svg>
                                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($email): ?>
                                            <div class="mulopimfwc-tab-detail">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor" />
                                                </svg>
                                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($latitude && $longitude): ?>
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-directions">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor" />
                                            </svg>
                                            <?php esc_html_e('Get Directions', 'multi-location-product-and-inventory-management'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Map Panel -->
                <div class="mulopimfwc-tabs-content">
                    <div id="mulopimfwc-tabbed-map" class="mulopimfwc-location-map mulopimfwc-map-interactive"></div>

                    <div class="mulopimfwc-map-info-overlay">
                        <?php
                        // Render initial content for first location
                        $first_location = $locations[0];
                        $this->render_map_overlay_content($first_location->term_id);
                        ?>
                    </div>
                </div>

            </div>
        </div>
    <?php
    }

    /**
     * Render map overlay content
     */
    private function render_map_overlay_content($term_id)
    {
        global $MULOPIMFWC_Admin;

        $location = get_term($term_id, 'mulopimfwc_store_location');
        $logo_id = get_term_meta($term_id, 'logo_id', true);
        $street_address = get_term_meta($term_id, 'street_address', true);
        $city = get_term_meta($term_id, 'city', true);
        $state = get_term_meta($term_id, 'state', true);
        $postal_code = get_term_meta($term_id, 'postal_code', true);
        $phone = get_term_meta($term_id, 'phone', true);
        $email = get_term_meta($term_id, 'email', true);
        $latitude = get_term_meta($term_id, 'latitude', true);
        $longitude = get_term_meta($term_id, 'longitude', true);
        $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);

    ?>
        <div class="mulopimfwc-overlay-content" data-location="<?php echo esc_attr($term_id); ?>">
            <?php if ($logo_id): ?>
                <div class="mulopimfwc-overlay-logo">
                    <?php echo wp_get_attachment_image($logo_id, 'thumbnail', false, ['alt' => $location->name]); ?>
                </div>
            <?php endif; ?>

            <div class="mulopimfwc-overlay-header">
                <h4><?php echo esc_html($location->name); ?></h4>
                <?php echo $this->render_status_badge($status, true); ?>
            </div>

            <?php if ($location->description): ?>
                <div class="mulopimfwc-overlay-description">
                    <?php echo wp_kses_post(wp_trim_words($location->description, 20)); ?>
                </div>
            <?php endif; ?>

            <div class="mulopimfwc-overlay-details">
                <?php if ($street_address || $city): ?>
                    <div class="mulopimfwc-overlay-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor" />
                        </svg>
                        <div>
                            <?php if ($street_address): ?>
                                <div><?php echo esc_html($street_address); ?></div>
                            <?php endif; ?>
                            <?php if ($city || $state || $postal_code): ?>
                                <div>
                                    <?php
                                    $address_line = array_filter([$city, $state, $postal_code]);
                                    echo esc_html(implode(', ', $address_line));
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($phone): ?>
                    <div class="mulopimfwc-overlay-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor" />
                        </svg>
                        <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($email): ?>
                    <div class="mulopimfwc-overlay-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor" />
                        </svg>
                        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mulopimfwc-overlay-actions">
                <?php if ($latitude && $longitude): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor" />
                        </svg>
                        <?php esc_html_e('Get Directions', 'multi-location-product-and-inventory-management'); ?>
                    </a>
                <?php endif; ?>

                <a href="<?php echo esc_url(get_term_link($location)); ?>"
                    class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-secondary">
                    <?php esc_html_e('View Details', 'multi-location-product-and-inventory-management'); ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor" />
                    </svg>
                </a>
            </div>
        </div>
    <?php
    }

    /**
     * Render business hours card
     */
    private function render_business_hours_card($term_id, $status)
    {
        global $mulopimfwc_options;
        // mulopimfwc_display_options[enable_business_hours]
        if ((isset($mulopimfwc_options['enable_business_hours']) && $mulopimfwc_options['enable_business_hours'] != 'on') || !mulopimfwc_premium_feature() || (isset($mulopimfwc_options['display_hours_archive_page']) && $mulopimfwc_options['display_hours_archive_page'] != 'on')) {
            return;
        }
        $bh = get_term_meta($term_id, 'business_hours', true);

        if (empty($bh) || empty($bh['days'])) {
            return;
        }

        $days_labels = [
            'mon' => __('Monday', 'multi-location-product-and-inventory-management'),
            'tue' => __('Tuesday', 'multi-location-product-and-inventory-management'),
            'wed' => __('Wednesday', 'multi-location-product-and-inventory-management'),
            'thu' => __('Thursday', 'multi-location-product-and-inventory-management'),
            'fri' => __('Friday', 'multi-location-product-and-inventory-management'),
            'sat' => __('Saturday', 'multi-location-product-and-inventory-management'),
            'sun' => __('Sunday', 'multi-location-product-and-inventory-management'),
        ];

        $tz = new DateTimeZone($bh['timezone'] ?? wp_timezone_string());
        $now = new DateTimeImmutable('now', $tz);
        $current_day_index = (int)$now->format('N') - 1;
        $day_keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $current_day_key = $day_keys[$current_day_index];

    ?>
        <div class="mulopimfwc-info-card mulopimfwc-hours-card">
            <h3 class="mulopimfwc-card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z" fill="currentColor" />
                </svg>
                <?php esc_html_e('Business Hours', 'multi-location-product-and-inventory-management'); ?>
            </h3>

            <div class="mulopimfwc-hours-status">
                <?php echo $this->render_status_badge($status, true); ?>

                <?php if ($status['open'] && $status['next_change']): ?>
                    <div class="mulopimfwc-next-change">
                        <?php
                        printf(
                            esc_html__('Closes at %s', 'multi-location-product-and-inventory-management'),
                            '<strong>' . esc_html($status['next_change']->format('g:i A')) . '</strong>'
                        );
                        ?>
                    </div>
                <?php elseif (!$status['open'] && $status['next_change']): ?>
                    <div class="mulopimfwc-next-change">
                        <?php
                        printf(
                            esc_html__('Opens at %s', 'multi-location-product-and-inventory-management'),
                            '<strong>' . esc_html($status['next_change']->format('g:i A')) . '</strong>'
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mulopimfwc-hours-table">
                <?php foreach ($bh['days'] as $day_key => $hours): ?>
                    <?php
                    $is_current = ($day_key === $current_day_key);
                    $row_class = 'mulopimfwc-hours-row';
                    if ($is_current) {
                        $row_class .= ' mulopimfwc-current-day';
                    }
                    ?>
                    <div class="<?php echo esc_attr($row_class); ?>">
                        <span class="mulopimfwc-day-name">
                            <?php echo esc_html($days_labels[$day_key]); ?>
                            <?php if ($is_current): ?>
                                <span class="mulopimfwc-today-label"><?php esc_html_e('Today', 'multi-location-product-and-inventory-management'); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="mulopimfwc-day-hours">
                            <?php if (!empty($hours['closed'])): ?>
                                <span class="mulopimfwc-closed"><?php esc_html_e('Closed', 'multi-location-product-and-inventory-management'); ?></span>
                            <?php elseif (!empty($hours['all_day'])): ?>
                                <span class="mulopimfwc-all-day"><?php esc_html_e('Open 24 Hours', 'multi-location-product-and-inventory-management'); ?></span>
                            <?php else: ?>
                                <?php
                                $open = DateTime::createFromFormat('H:i', $hours['open']);
                                $close = DateTime::createFromFormat('H:i', $hours['close']);
                                echo esc_html($open->format('g:i A') . ' - ' . $close->format('g:i A'));
                                ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($bh['timezone'])): ?>
                <div class="mulopimfwc-timezone-info">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor" />
                    </svg>
                    <?php echo esc_html($bh['timezone']); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render location details (for compact/shortcode use)
     */
    public function render_location_details($term_id, $context = 'archive', $compact = false)
    {
        $location = get_term($term_id, 'mulopimfwc_store_location');

        if (!$location || is_wp_error($location)) {
            return;
        }

        // Get location meta
        $street_address = get_term_meta($term_id, 'street_address', true);
        $city = get_term_meta($term_id, 'city', true);
        $state = get_term_meta($term_id, 'state', true);
        $postal_code = get_term_meta($term_id, 'postal_code', true);
        $country = get_term_meta($term_id, 'country', true);
        $email = get_term_meta($term_id, 'email', true);
        $phone = get_term_meta($term_id, 'phone', true);
        $latitude = get_term_meta($term_id, 'latitude', true);
        $longitude = get_term_meta($term_id, 'longitude', true);
        $logo_id = get_term_meta($term_id, 'logo_id', true);

        global $MULOPIMFWC_Admin;
        $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);

        $wrapper_class = 'mulopimfwc-location-info-wrapper';
        $wrapper_class .= ' mulopimfwc-location-' . esc_attr($context);
        $wrapper_class .= $compact ? ' mulopimfwc-location-compact' : '';

    ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" data-location-id="<?php echo esc_attr($term_id); ?>">
            <div>
                <div class="mulopimfwc-compact-header">
                    <?php if ($logo_id): ?>
                        <div class="mulopimfwc-compact-logo">
                            <?php echo wp_get_attachment_image($logo_id, 'thumbnail', false, ['alt' => $location->name]); ?>
                        </div>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                        </svg>
                    <?php endif; ?>
                    <div class="mulopimfwc-compact-info">
                        <h4 class="mulopimfwc-compact-title">
                            <?php echo esc_html($location->name); ?>
                        </h4>
                        <?php echo $this->render_status_badge($status, true); ?>
                    </div>
                </div>

                <div class="mulopimfwc-compact-details">
                    <?php echo wp_kses_post($location->description, 20); ?>
                    <?php if ($street_address || $city): ?>
                        <div class="mulopimfwc-compact-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor" />
                            </svg>
                            <span><?php echo esc_html($street_address . ', ' . $city); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($phone): ?>
                        <div class="mulopimfwc-compact-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor" />
                            </svg>
                            <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                        </div>
                    <?php endif; ?>

                    <?php if ($email): ?>
                        <div class="mulopimfwc-compact-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor" />
                            </svg>
                            <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($latitude && $longitude): ?>
                    <div class="mulopimfwc-compact-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor" />
                            </svg>
                            <?php esc_html_e('Get Directions', 'multi-location-product-and-inventory-management'); ?>
                        </a>
                    </div>
            </div>
            <div class="mulopimfwc-map-wrapper">
                <div id="mulopimfwc-map-<?php echo esc_attr($term_id); ?>"
                    class="mulopimfwc-location-map mulopimfwc-map-small"
                    data-lat="<?php echo esc_attr($latitude); ?>"
                    data-lng="<?php echo esc_attr($longitude); ?>"
                    data-name="<?php echo esc_attr($location->name); ?>"
                    data-address="<?php echo esc_attr($street_address . ', ' . $city); ?>" style="height: 100%;">
                </div>
            </div>
        <?php else: ?>
        </div>
    <?php endif; ?>

    </div>
<?php
    }

    /**
     * Render status badge
     */
    private function render_status_badge($status, $with_icon = false)
    {
        $label = $status['open']
            ? __('Open Now', 'multi-location-product-and-inventory-management')
            : __('Closed', 'multi-location-product-and-inventory-management');

        $class = 'mulopimfwc-status-badge mulopimfwc-status-' . ($status['open'] ? 'open' : 'closed');

        ob_start();
?>
    <span class="<?php echo esc_attr($class); ?>">
        <?php if ($with_icon): ?>
            <svg class="mulopimfwc-status-icon" width="8" height="8" viewBox="0 0 8 8" fill="none">
                <circle cx="4" cy="4" r="4" fill="currentColor" />
            </svg>
        <?php endif; ?>
        <span class="mulopimfwc-status-text"><?php echo esc_html($label); ?></span>
    </span>
<?php
        return ob_get_clean();
    }

    /**
     * Location info shortcode - Enhanced version
     * 
     * Usage:
     * [mulopimfwc_location_info] - Show all locations in tabs
     * [mulopimfwc_location_info id="123"] - Single location
     * [mulopimfwc_location_info id="123,456,789"] - Multiple locations in tabs
     * [mulopimfwc_location_info slug="location-1,location-2"] - Multiple by slug
     * [mulopimfwc_location_info layout="tabs"] - Force tab layout
     * [mulopimfwc_location_info search="yes"] - Enable search
     * [mulopimfwc_location_info compact="yes"] - Compact single location view
     */
    public function location_info_shortcode($atts)
    {
        global $mulopimfwc_options;

        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['enable_store_locator']
            : 'off';

        if ($enable_locator !== 'on') {
            return '';
        }

        $atts = shortcode_atts([
            'id' => '',
            'slug' => '',
            'layout' => 'auto', // auto, tabs, compact, grid
            'search' => 'no',
            'compact' => 'no',
            'limit' => '', // limit number of locations
            'orderby' => 'name', // name, id, count
            'order' => 'ASC'
        ], $atts);

        $locations = [];

        // Get locations based on provided parameters
        if (!empty($atts['id'])) {
            // Multiple IDs separated by comma
            $ids = array_map('trim', explode(',', $atts['id']));
            foreach ($ids as $id) {
                $term = get_term($id, 'mulopimfwc_store_location');
                if ($term && !is_wp_error($term)) {
                    $locations[] = $term;
                }
            }
        } elseif (!empty($atts['slug'])) {
            // Multiple slugs separated by comma
            $slugs = array_map('trim', explode(',', $atts['slug']));
            foreach ($slugs as $slug) {
                $term = get_term_by('slug', $slug, 'mulopimfwc_store_location');
                if ($term && !is_wp_error($term)) {
                    $locations[] = $term;
                }
            }
        } else {
            // Get all locations
            $args = [
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
                'orderby' => $atts['orderby'],
                'order' => $atts['order']
            ];

            if (!empty($atts['limit'])) {
                $args['number'] = intval($atts['limit']);
            }

            $locations = get_terms($args);
        }

        if (empty($locations) || is_wp_error($locations)) {
            return '<p class="mulopimfwc-no-locations">' .
                esc_html__('No locations found.', 'multi-location-product-and-inventory-management') .
                '</p>';
        }

        ob_start();

        // Determine layout
        $layout = $atts['layout'];
        if ($layout === 'auto') {
            $layout = count($locations) > 1 ? 'tabs' : 'compact';
        }

        // Render based on layout
        switch ($layout) {
            case 'tabs':
                $this->render_shortcode_tabbed_locations($locations, $atts['search'] === 'yes');
                break;

            case 'grid':
                $this->render_shortcode_grid_locations($locations, $atts['search'] === 'yes');
                break;

            case 'compact':
            default:
                if (count($locations) === 1) {
                    $this->render_location_details($locations[0]->term_id, 'shortcode', $atts['compact'] === 'yes');
                } else {
                    $this->render_shortcode_tabbed_locations($locations, $atts['search'] === 'yes');
                }
                break;
        }

        return ob_get_clean();
    }

    /**
     * Render tabbed locations for shortcode with search
     */
    private function render_shortcode_tabbed_locations($locations, $enable_search = false)
    {
        global $MULOPIMFWC_Admin;
        $unique_id = 'shortcode-' . uniqid();

?>
    <div class="mulopimfwc-shortcode-locations-wrapper mulopimfwc-tabbed-locations-wrapper">
        <div class="mulopimfwc-shortcode-header">
            <h3 class="mulopimfwc-locations-heading">
                <?php
                printf(
                    esc_html__('Our Locations (%d)', 'multi-location-product-and-inventory-management'),
                    count($locations)
                );
                ?>
            </h3>
        </div>

        <div class="mulopimfwc-locations-tabs-container" id="<?php echo esc_attr($unique_id); ?>">

            <!-- Tabs Sidebar -->
            <div class="mulopimfwc-tabs-sidebar">
                <?php if ($enable_search): ?>
                    <div class="mulopimfwc-search-wrapper">
                        <input type="text"
                            class="mulopimfwc-location-search"
                            placeholder="<?php esc_attr_e('Search locations...', 'multi-location-product-and-inventory-management'); ?>"
                            data-target="<?php echo esc_attr($unique_id); ?>">
                        <svg class="mulopimfwc-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor" />
                        </svg>
                    </div>
                <?php endif; ?>
                <div class="mulopimfwc-tabs-list">
                    <?php foreach ($locations as $index => $location):
                        $term_id = $location->term_id;
                        $logo_id = get_term_meta($term_id, 'logo_id', true);
                        $street_address = get_term_meta($term_id, 'street_address', true);
                        $city = get_term_meta($term_id, 'city', true);
                        $state = get_term_meta($term_id, 'state', true);
                        $phone = get_term_meta($term_id, 'phone', true);
                        $email = get_term_meta($term_id, 'email', true);
                        $latitude = get_term_meta($term_id, 'latitude', true);
                        $longitude = get_term_meta($term_id, 'longitude', true);
                        $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);
                        $is_active = $index === 0 ? 'active' : '';
                        $search_data = strtolower($location->name . ' ' . $street_address . ' ' . $city . ' ' . $state);
                    ?>
                        <div class="mulopimfwc-tab-item <?php echo esc_attr($is_active); ?>"
                            data-tab="location-<?php echo esc_attr($term_id); ?>"
                            data-lat="<?php echo esc_attr($latitude); ?>"
                            data-lng="<?php echo esc_attr($longitude); ?>"
                            data-name="<?php echo esc_attr($location->name); ?>"
                            data-address="<?php echo esc_attr($street_address . ', ' . $city); ?>"
                            data-search="<?php echo esc_attr($search_data); ?>">

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($logo_id): ?>
                                    <div class="mulopimfwc-tab-logo">
                                        <?php echo wp_get_attachment_image($logo_id, 'thumbnail', false, ['alt' => $location->name]); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mulopimfwc-tab-header" style="width: calc(100% - <?php echo $logo_id ? '80px' : '0px'; ?>);">
                                    <h4 class="mulopimfwc-tab-title">
                                        <a href="<?php echo esc_url(get_term_link($location)); ?>" target="_blank">
                                            <?php echo esc_html($location->name); ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z" fill="currentColor" />
                                            </svg>
                                        </a>
                                    </h4>
                                    <?php echo $this->render_status_badge($status, true); ?>
                                </div>
                            </div>

                            <div class="mulopimfwc-tab-info">
                                <div class="mulopimfwc-tab-details">
                                    <?php if ($street_address || $city): ?>
                                        <div class="mulopimfwc-tab-detail">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor" />
                                            </svg>
                                            <span><?php echo esc_html($street_address . ', ' . $city); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($phone): ?>
                                        <div class="mulopimfwc-tab-detail">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor" />
                                            </svg>
                                            <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($email): ?>
                                        <div class="mulopimfwc-tab-detail">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor" />
                                            </svg>
                                            <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($latitude && $longitude): ?>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-directions">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor" />
                                        </svg>
                                        <?php esc_html_e('Get Directions', 'multi-location-product-and-inventory-management'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mulopimfwc-no-results" style="display: none;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor" />
                    </svg>
                    <p><?php esc_html_e('No locations found matching your search.', 'multi-location-product-and-inventory-management'); ?></p>
                </div>
            </div>

            <!-- Map Panel -->
            <div class="mulopimfwc-tabs-content">
                <div id="mulopimfwc-tabbed-map-<?php echo esc_attr($unique_id); ?>"
                    class="mulopimfwc-location-map mulopimfwc-map-interactive"></div>

                <div class="mulopimfwc-map-info-overlay">
                    <?php
                    // Render initial content for first location
                    $first_location = $locations[0];
                    $this->render_map_overlay_content($first_location->term_id);
                    ?>
                </div>
            </div>

        </div>
    </div>
<?php
    }

    /**
     * Render grid layout for locations
     */
    private function render_shortcode_grid_locations($locations, $enable_search = false)
    {
        $unique_id = 'grid-' . uniqid();
?>
    <div class="mulopimfwc-shortcode-locations-wrapper mulopimfwc-grid-locations-wrapper" id="<?php echo esc_attr($unique_id); ?>">
        <div class="mulopimfwc-shortcode-header">
            <h3 class="mulopimfwc-locations-heading">
                <?php
                printf(
                    esc_html__('Our Locations (%d)', 'multi-location-product-and-inventory-management'),
                    count($locations)
                );
                ?>
            </h3>

            <?php if ($enable_search): ?>
                <div class="mulopimfwc-search-wrapper">
                    <input type="text"
                        class="mulopimfwc-location-search"
                        placeholder="<?php esc_attr_e('Search locations...', 'multi-location-product-and-inventory-management'); ?>"
                        data-target="<?php echo esc_attr($unique_id); ?>">
                    <svg class="mulopimfwc-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor" />
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <div class="mulopimfwc-locations-grid">
            <?php foreach ($locations as $location):
                $term_id = $location->term_id;
                $search_data = strtolower($location->name . ' ' .
                    get_term_meta($term_id, 'street_address', true) . ' ' .
                    get_term_meta($term_id, 'city', true) . ' ' .
                    get_term_meta($term_id, 'state', true));
            ?>
                <div class="mulopimfwc-grid-location-item" data-search="<?php echo esc_attr($search_data); ?>">
                    <?php $this->render_location_details($term_id, 'grid', true); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mulopimfwc-no-results" style="display: none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor" />
            </svg>
            <p><?php esc_html_e('No locations found matching your search.', 'multi-location-product-and-inventory-management'); ?></p>
        </div>
    </div>
<?php
    }


    /**
     * Override location archive template
     */
    public function location_archive_template($template)
    {
        if (is_tax('mulopimfwc_store_location')) {
            $custom_template = locate_template('taxonomy-mulopimfwc_store_location.php');

            if ($custom_template) {
                return $custom_template;
            }

            $plugin_template = plugin_dir_path(__FILE__) . '../templates/taxonomy-mulopimfwc_store_location.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }
}

// Initialize later on the 'init' hook
add_action('init', function () {
    if (class_exists('MULOPIMFWC_Frontend_Location_Information')) {
        new MULOPIMFWC_Frontend_Location_Information();
    }
}, 100);
