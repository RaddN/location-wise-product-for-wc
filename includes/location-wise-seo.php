<?php
/**
 * Location-wise SEO integration for WooCommerce products
 *
 * - Adds store location(s) to product/location-archive meta title & description
 *   (Yoast, Rank Math, or native fallback)
 * - Adds location(s) into WooCommerce Product structured data (JSON-LD)
 * - Outputs location archive JSON-LD (CollectionPage + Place)
 *
 * Toggle with:
 *  - mulopimfwc_display_options['location_in_meta_title']        = 'on'|'off'
 *  - mulopimfwc_display_options['location_in_meta_description']  = 'on'|'off'
 *  - mulopimfwc_display_options['location_structured_data']      = 'on'|'off'
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('MULOPIMFWC_Location_Wise_SEO')) {

    class MULOPIMFWC_Location_Wise_SEO {

        public function __construct() {
            // Core title parts
            add_filter('document_title_parts', [$this, 'seo_add_location_to_title'], 20);

            // Fallback <meta name="description"> when no SEO plugin is active
            add_action('wp_head', [$this, 'seo_output_fallback_meta_description'], 1);
            add_action('wp_head', [$this, 'seo_output_location_archive_structured_data'], 2);

            // Yoast SEO
            add_filter('wpseo_title',    [$this, 'yoast_add_location_to_title'], 20);
            add_filter('wpseo_metadesc', [$this, 'yoast_add_location_to_description'], 20);

            // Rank Math
            add_filter('rank_math/frontend/title',       [$this, 'rankmath_add_location_to_title'], 20);
            add_filter('rank_math/frontend/description', [$this, 'rankmath_add_location_to_description'], 20);

            // WooCommerce structured data
            add_filter('woocommerce_structured_data_product', [$this, 'schema_add_location_to_product'], 20, 2);
        }

        /* -------------------------
         * Utilities
         * ------------------------- */

        private function get_options() {
            global $mulopimfwc_options;
            return is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        private function is_supported_seo_context() {
            return is_singular('product') || (bool) $this->get_current_location_archive_term();
        }

        private function is_enabled($option_key) {
            if (!$this->is_supported_seo_context()) return false;
            $opts = $this->get_options();
            return isset($opts[$option_key]) && $opts[$option_key] === 'on';
        }

        private function get_current_location_archive_term() {
            if (function_exists('mulopimfwc_get_current_location_archive_term')) {
                $term = mulopimfwc_get_current_location_archive_term([
                    'allow_native' => true,
                    'allow_request' => true,
                    'allow_cookie' => false,
                ]);
                if ($term && !is_wp_error($term) && isset($term->taxonomy) && $term->taxonomy === 'mulopimfwc_store_location') {
                    return $term;
                }
            }

            if (!is_tax('mulopimfwc_store_location')) return null;
            $term = get_queried_object();
            if (!$term || is_wp_error($term)) return null;
            if (!isset($term->taxonomy) || $term->taxonomy !== 'mulopimfwc_store_location') return null;
            return $term;
        }

        /**
         * Return a human-friendly product location string, e.g. "Dhaka, Chittagong"
         */
        private function get_product_location_text($product_id) {
            $terms = get_the_terms($product_id, 'mulopimfwc_store_location');
            if (empty($terms) || is_wp_error($terms)) {
                return '';
            }
            $names = array_unique(array_filter(array_map(function($t){ return $t->name; }, $terms)));
            return implode(', ', $names);
        }

        private function get_context_location_text($product_id = 0) {
            if ($product_id) {
                return $this->get_product_location_text($product_id);
            }
            $term = $this->get_current_location_archive_term();
            return $term ? $term->name : '';
        }

        private function build_title_with_location($base_title, $product_id = 0) {
            $opts = $this->get_options();
            $sep  = isset($opts['separator']) ? $opts['separator'] : ' - ';
            $fmt  = isset($opts['display_format']) ? $opts['display_format'] : 'append'; // prepend|append|brackets|none
            $loc  = $this->get_context_location_text($product_id);

            if (!$loc) return $base_title;

            switch ($fmt) {
                case 'prepend': return $loc . $sep . $base_title;
                case 'brackets': return $base_title . ' [' . $loc . ']';
                case 'none': return $base_title;
                case 'append':
                default: return $base_title . $sep . $loc;
            }
        }

        private function build_description_with_location($base_description, $product_id = 0) {
            $loc = $this->get_context_location_text($product_id);
            if (!$loc) return $base_description;

            $suffix = ' Available at: ' . $loc . '.';

            // Avoid duplication if already present
            if ($base_description && stripos($base_description, $loc) !== false) {
                return $base_description;
            }

            $desc = trim((string) $base_description);

            // If empty, fall back to product short description or archive term description
            if ($desc === '') {
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $excerpt = wp_strip_all_tags($product->get_short_description());
                        $desc = mb_substr(trim($excerpt), 0, 140);
                    }
                } else {
                    $term = $this->get_current_location_archive_term();
                    if ($term) {
                        $term_desc = wp_strip_all_tags(term_description($term->term_id, 'mulopimfwc_store_location'));
                        $desc = mb_substr(trim($term_desc), 0, 140);
                    }
                }
            }

            // Keep concise
            $desc = rtrim($desc, " .") . $suffix;
            return $desc;
        }

        /* -------------------------
         * Title filters
         * ------------------------- */

        public function seo_add_location_to_title($parts) {
            if (!$this->is_enabled('location_in_meta_title') || !mulopimfwc_premium_feature()) return $parts;
            $product_id = is_singular('product') ? get_the_ID() : 0;
            if (is_singular('product') && !$product_id) return $parts;

            if (isset($parts['title'])) {
                $parts['title'] = $this->build_title_with_location($parts['title'], $product_id);
            }
            return $parts;
        }

        public function yoast_add_location_to_title($title) {
            if (!$this->is_enabled('location_in_meta_title') || !mulopimfwc_premium_feature()) return $title;
            $product_id = is_singular('product') ? get_the_ID() : 0;
            return $this->build_title_with_location($title, $product_id);
        }

        public function rankmath_add_location_to_title($title) {
            if (!$this->is_enabled('location_in_meta_title') || !mulopimfwc_premium_feature()) return $title;
            $product_id = is_singular('product') ? get_the_ID() : 0;
            return $this->build_title_with_location($title, $product_id);
        }

        /* -------------------------
         * Description filters
         * ------------------------- */

        public function yoast_add_location_to_description($desc) {
            if (!$this->is_enabled('location_in_meta_description') || !mulopimfwc_premium_feature()) return $desc;
            $product_id = is_singular('product') ? get_the_ID() : 0;
            return $this->build_description_with_location((string)$desc, $product_id);
        }

        public function rankmath_add_location_to_description($desc) {
            if (!$this->is_enabled('location_in_meta_description') || !mulopimfwc_premium_feature()) return $desc;
            $product_id = is_singular('product') ? get_the_ID() : 0;
            return $this->build_description_with_location((string)$desc, $product_id);
        }

        /**
         * Output a native <meta name="description"> if no SEO plugin is active.
         */
        public function seo_output_fallback_meta_description() {
            if (!$this->is_enabled('location_in_meta_description') || !mulopimfwc_premium_feature()) return;

            // Skip if Yoast or Rank Math is active
            if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) return;

            $product_id = is_singular('product') ? get_the_ID() : 0;
            $desc = $this->build_description_with_location('', $product_id);
            $desc = esc_attr(mb_substr(trim($desc), 0, 160));
            if ($desc !== '') {
                echo '<meta name="description" content="' . $desc . '">' . "\n";
            }
        }

        /* -------------------------
         * Structured data (JSON-LD)
         * ------------------------- */

        /**
         * Add locations to WooCommerce Product schema.org markup
         *
         * @param array      $markup  Structured data for product
         * @param WC_Product $product Product object
         * @return array
         */
        public function schema_add_location_to_product($markup, $product) {
            if (!$this->is_enabled('location_structured_data') || !mulopimfwc_premium_feature()) return $markup;
            if (!$product) return $markup;

            $terms = get_the_terms($product->get_id(), 'mulopimfwc_store_location');
            if (empty($terms) || is_wp_error($terms)) return $markup;

            $places = [];
            foreach ($terms as $t) {
                $place = [
                    '@type' => 'Place',
                    'name'  => $t->name,
                ];

                $places[] = $place;
            }

            if (!empty($places)) {
                $markup['availableAtOrFrom'] = count($places) === 1 ? $places[0] : $places;
            }

            return $markup;
        }

        /**
         * Output archive-level schema for location taxonomy pages.
         */
        public function seo_output_location_archive_structured_data() {
            if (!$this->is_enabled('location_structured_data') || !mulopimfwc_premium_feature()) return;

            $term = $this->get_current_location_archive_term();
            if (!$term) return;

            $place = [
                '@type' => 'Place',
                'name'  => $term->name,
            ];

            $street = trim((string) get_term_meta($term->term_id, 'street_address', true));
            $city   = trim((string) get_term_meta($term->term_id, 'city', true));
            $state  = trim((string) get_term_meta($term->term_id, 'state', true));
            $zip    = trim((string) get_term_meta($term->term_id, 'postal_code', true));
            $country = trim((string) get_term_meta($term->term_id, 'country', true));
            $phone  = trim((string) get_term_meta($term->term_id, 'phone', true));
            $email  = trim((string) get_term_meta($term->term_id, 'email', true));
            $lat    = trim((string) get_term_meta($term->term_id, 'latitude', true));
            $lng    = trim((string) get_term_meta($term->term_id, 'longitude', true));

            $address = array_filter([
                'streetAddress'   => $street,
                'addressLocality' => $city,
                'addressRegion'   => $state,
                'postalCode'      => $zip,
                'addressCountry'  => $country,
            ], function($value) {
                return $value !== '';
            });

            if (!empty($address)) {
                $place['address'] = array_merge(['@type' => 'PostalAddress'], $address);
            }

            if ($phone !== '') {
                $place['telephone'] = $phone;
            }
            if ($email !== '') {
                $place['email'] = $email;
            }

            if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
                $place['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $lat,
                    'longitude' => (float) $lng,
                ];
            }

            $term_link = get_term_link($term);
            if (!is_wp_error($term_link)) {
                $place['url'] = $term_link;
            }

            $schema = [
                '@context'   => 'https://schema.org',
                '@type'      => 'CollectionPage',
                'name'       => is_tax('mulopimfwc_store_location') ? single_term_title('', false) : $term->name,
                'mainEntity' => $place,
            ];

            if (!is_wp_error($term_link)) {
                $schema['url'] = $term_link;
            }

            $term_desc = wp_strip_all_tags(term_description($term->term_id, 'mulopimfwc_store_location'));
            if ($term_desc !== '') {
                $schema['description'] = $term_desc;
            }

            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
    }
}


add_action('plugins_loaded', function () {
    // Only load if WooCommerce active, same guard you already use elsewhere is fine
    if (function_exists('WC')) {
        new MULOPIMFWC_Location_Wise_SEO();
    }
}, 20);
