<?php
/**
 * Frontend Location Information Display - Enhanced with Business Hours
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

        // Location archive page
        add_action('mulopimfwc_store_location_description', [$this, 'display_location_archive_info'], 10);
        
        // Single product page
        add_action('woocommerce_before_single_product', [$this, 'display_single_product_location_info'], 15);
        
        // Shortcode
        add_shortcode('mulopimfwc_location_info', [$this, 'location_info_shortcode']);
        
        // Template override
        add_filter('template_include', [$this, 'location_archive_template'], 99);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets()
    {
        global $mulopimfwc_options;
        
        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) 
            ? $mulopimfwc_options['enable_store_locator'] 
            : 'off';

        if ($enable_locator !== 'on') {
            return;
        }

        // Check if we're on a location archive or single product with location
        if (!is_tax('mulopimfwc_store_location') && !is_singular('product')) {
            return;
        }

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
            'mapAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        ]);
    }

    /**
     * Display location information on archive page
     */
    public function display_location_archive_info()
    {
        global $mulopimfwc_options;
        
        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) 
            ? $mulopimfwc_options['enable_store_locator'] 
            : 'off';

        if ($enable_locator !== 'on' || !is_tax('mulopimfwc_store_location')) {
            return;
        }

        $term = get_queried_object();
        if (!$term || is_wp_error($term)) {
            return;
        }

        $this->render_location_details($term->term_id, 'archive');
    }

    /**
     * Display location information on single product page
     */
    public function display_single_product_location_info()
    {
        global $mulopimfwc_options, $product;
        
        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) 
            ? $mulopimfwc_options['enable_store_locator'] 
            : 'off';

        $display_on_single = isset($mulopimfwc_options['display_location_single_product']) 
            ? $mulopimfwc_options['display_location_single_product'] 
            : 'off';

        if ($enable_locator !== 'on' || $display_on_single !== 'on' || !is_singular('product')) {
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

        // Display all locations for this product
        echo '<div class="mulopimfwc-product-locations-wrapper">';
        
        if (count($locations) > 1) {
            echo '<h3 class="mulopimfwc-locations-title">' . 
                esc_html__('Available at Multiple Locations', 'multi-location-product-and-inventory-management') . 
                '</h3>';
        }

        foreach ($locations as $location) {
            $this->render_location_details($location->term_id, 'product', true);
        }
        
        echo '</div>';
    }

    /**
     * Render location details
     * 
     * @param int $term_id Location term ID
     * @param string $context Context: 'archive', 'product', or 'shortcode'
     * @param bool $compact Compact display mode
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
        $gallery_ids = get_term_meta($term_id, 'gallery_ids', true);

        // Get business hours status
        $status = $this->admin->is_location_open_now($term_id);

        $wrapper_class = 'mulopimfwc-location-info-wrapper';
        $wrapper_class .= ' mulopimfwc-location-' . esc_attr($context);
        $wrapper_class .= $compact ? ' mulopimfwc-location-compact' : '';

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" data-location-id="<?php echo esc_attr($term_id); ?>">
            
            <?php if (!$compact): ?>
            <!-- Full Display Mode -->
            <div class="mulopimfwc-location-header">
                <?php if ($logo_id): ?>
                    <div class="mulopimfwc-location-logo">
                        <?php echo wp_get_attachment_image($logo_id, 'medium', false, ['alt' => $location->name]); ?>
                    </div>
                <?php endif; ?>
                
                <div class="mulopimfwc-location-title-wrapper">
                    <div class="mulopimfwc-title-status-row">
                        <h2 class="mulopimfwc-location-title">
                            <svg class="mulopimfwc-location-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"/>
                            </svg>
                            <?php echo esc_html($location->name); ?>
                        </h2>
                        <?php echo $this->render_status_badge($status); ?>
                    </div>
                    <?php if ($location->description): ?>
                        <div class="mulopimfwc-location-description">
                            <?php echo wp_kses_post(wpautop($location->description)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mulopimfwc-location-content">
                
                <!-- Contact Information -->
                <div class="mulopimfwc-location-info-section">
                    <?php if ($compact): ?>
                        <div class="mulopimfwc-compact-header">
                            <h4 class="mulopimfwc-section-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"/>
                                </svg>
                                <?php echo esc_html($location->name); ?>
                            </h4>
                            <?php echo $this->render_status_badge($status); ?>
                        </div>
                    <?php else: ?>
                        <h3 class="mulopimfwc-section-title">
                            <?php esc_html_e('Contact Information', 'multi-location-product-and-inventory-management'); ?>
                        </h3>
                    <?php endif; ?>

                    <div class="mulopimfwc-info-grid">
                        
                        <?php if ($street_address || $city || $state || $postal_code || $country): ?>
                        <div class="mulopimfwc-info-item mulopimfwc-address">
                            <svg class="mulopimfwc-info-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/>
                            </svg>
                            <div class="mulopimfwc-info-content">
                                <span class="mulopimfwc-info-label"><?php esc_html_e('Address', 'multi-location-product-and-inventory-management'); ?></span>
                                <div class="mulopimfwc-info-value">
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
                        <div class="mulopimfwc-info-item mulopimfwc-phone">
                            <svg class="mulopimfwc-info-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor"/>
                            </svg>
                            <div class="mulopimfwc-info-content">
                                <span class="mulopimfwc-info-label"><?php esc_html_e('Phone', 'multi-location-product-and-inventory-management'); ?></span>
                                <a href="tel:<?php echo esc_attr($phone); ?>" class="mulopimfwc-info-value mulopimfwc-link">
                                    <?php echo esc_html($phone); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($email): ?>
                        <div class="mulopimfwc-info-item mulopimfwc-email">
                            <svg class="mulopimfwc-info-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor"/>
                            </svg>
                            <div class="mulopimfwc-info-content">
                                <span class="mulopimfwc-info-label"><?php esc_html_e('Email', 'multi-location-product-and-inventory-management'); ?></span>
                                <a href="mailto:<?php echo esc_attr($email); ?>" class="mulopimfwc-info-value mulopimfwc-link">
                                    <?php echo esc_html($email); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>

                    <?php if (!$compact && ($latitude && $longitude)): ?>
                    <div class="mulopimfwc-location-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="mulopimfwc-btn mulopimfwc-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor"/>
                            </svg>
                            <?php esc_html_e('Get Directions', 'multi-location-product-and-inventory-management'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Business Hours Section -->
                <?php $this->render_business_hours($term_id, $status, $compact); ?>

                <!-- Map -->
                <?php if ($latitude && $longitude): ?>
                <div class="mulopimfwc-location-map-section">
                    <?php if (!$compact): ?>
                        <h3 class="mulopimfwc-section-title">
                            <?php esc_html_e('Location Map', 'multi-location-product-and-inventory-management'); ?>
                        </h3>
                    <?php endif; ?>
                    <div class="mulopimfwc-map-wrapper">
                        <div id="mulopimfwc-map-<?php echo esc_attr($term_id); ?>" 
                             class="mulopimfwc-location-map" 
                             data-lat="<?php echo esc_attr($latitude); ?>" 
                             data-lng="<?php echo esc_attr($longitude); ?>"
                             data-name="<?php echo esc_attr($location->name); ?>"
                             data-address="<?php echo esc_attr($street_address . ', ' . $city); ?>">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gallery -->
                <?php if (!$compact && $gallery_ids && is_array($gallery_ids) && !empty($gallery_ids)): ?>
                <div class="mulopimfwc-location-gallery-section">
                    <h3 class="mulopimfwc-section-title">
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
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div>
        <?php
    }

    /**
     * Render business hours section
     */
    private function render_business_hours($term_id, $status, $compact = false)
    {
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

        // Get current day
        $tz = new DateTimeZone($bh['timezone'] ?? wp_timezone_string());
        $now = new DateTimeImmutable('now', $tz);
        $current_day_index = (int)$now->format('N') - 1;
        $day_keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $current_day_key = $day_keys[$current_day_index];

        ?>
        <div class="mulopimfwc-location-hours-section">
            <?php if (!$compact): ?>
                <h3 class="mulopimfwc-section-title">
                    <svg class="mulopimfwc-section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z" fill="currentColor"/>
                    </svg>
                    <?php esc_html_e('Business Hours', 'multi-location-product-and-inventory-management'); ?>
                </h3>
            <?php endif; ?>

            <div class="mulopimfwc-hours-status-wrapper">
                <?php echo $this->render_status_badge($status, true); ?>
                
                <?php if ($status['open'] && $status['next_change']): ?>
                    <div class="mulopimfwc-hours-next-change">
                        <?php 
                        printf(
                            esc_html__('Closes at %s', 'multi-location-product-and-inventory-management'),
                            esc_html($status['next_change']->format('g:i A'))
                        );
                        ?>
                    </div>
                <?php elseif (!$status['open'] && $status['next_change']): ?>
                    <div class="mulopimfwc-hours-next-change">
                        <?php 
                        printf(
                            esc_html__('Opens at %s', 'multi-location-product-and-inventory-management'),
                            esc_html($status['next_change']->format('g:i A'))
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mulopimfwc-hours-list">
                <?php foreach ($bh['days'] as $day_key => $hours): ?>
                    <?php 
                    $is_current = ($day_key === $current_day_key);
                    $row_class = 'mulopimfwc-hours-row';
                    if ($is_current) {
                        $row_class .= ' mulopimfwc-hours-current';
                    }
                    ?>
                    <div class="<?php echo esc_attr($row_class); ?>">
                        <span class="mulopimfwc-hours-day">
                            <?php echo esc_html($days_labels[$day_key]); ?>
                            <?php if ($is_current): ?>
                                <span class="mulopimfwc-today-badge"><?php esc_html_e('Today', 'multi-location-product-and-inventory-management'); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="mulopimfwc-hours-time">
                            <?php if (!empty($hours['closed'])): ?>
                                <span class="mulopimfwc-closed-text"><?php esc_html_e('Closed', 'multi-location-product-and-inventory-management'); ?></span>
                            <?php elseif (!empty($hours['all_day'])): ?>
                                <span class="mulopimfwc-open-24"><?php esc_html_e('Open 24 Hours', 'multi-location-product-and-inventory-management'); ?></span>
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
                <div class="mulopimfwc-hours-timezone">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor"/>
                    </svg>
                    <?php echo esc_html($bh['timezone']); ?>
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
                <svg class="mulopimfwc-status-icon" width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <circle cx="6" cy="6" r="6" fill="currentColor"/>
                </svg>
            <?php endif; ?>
            <span class="mulopimfwc-status-text"><?php echo esc_html($label); ?></span>
        </span>
        <?php
        return ob_get_clean();
    }

    /**
     * Location info shortcode
     * 
     * Usage: [mulopimfwc_location_info id="123" compact="yes"]
     */
    public function location_info_shortcode($atts)
    {
        global $mulopimfwc_options;
        
        $enable_locator = isset($mulopimfwc_options['enable_store_locator']) 
            ? $mulopimfwc_options['enable_store_locator'] 
            : 'off';

        if ($enable_locator !== 'on') {
            return '';
        }

        $atts = shortcode_atts([
            'id' => '',
            'slug' => '',
            'compact' => 'no'
        ], $atts);

        $term = null;

        if (!empty($atts['id'])) {
            $term = get_term($atts['id'], 'mulopimfwc_store_location');
        } elseif (!empty($atts['slug'])) {
            $term = get_term_by('slug', $atts['slug'], 'mulopimfwc_store_location');
        }

        if (!$term || is_wp_error($term)) {
            return '<p>' . esc_html__('Location not found.', 'multi-location-product-and-inventory-management') . '</p>';
        }

        ob_start();
        $this->render_location_details($term->term_id, 'shortcode', $atts['compact'] === 'yes');
        return ob_get_clean();
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
            
            // Use plugin template if theme doesn't have one
            $plugin_template = plugin_dir_path(__FILE__) . '../templates/taxonomy-mulopimfwc_store_location.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }
}

// Initialize
new MULOPIMFWC_Frontend_Location_Information();