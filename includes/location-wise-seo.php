<?php
/**
 * Location-wise SEO integration for WooCommerce products
 *
 * - Adds store location(s) to product meta title & description (Yoast, Rank Math, or native fallback)
 * - Adds location(s) into WooCommerce Product structured data (JSON-LD)
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

        private function is_enabled($option_key) {
            if (!is_singular('product')) return false;
            $opts = $this->get_options();
            return isset($opts[$option_key]) && $opts[$option_key] === 'on';
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

        private function build_title_with_location($base_title, $product_id) {
            $opts = $this->get_options();
            $sep  = isset($opts['separator']) ? $opts['separator'] : ' - ';
            $fmt  = isset($opts['display_format']) ? $opts['display_format'] : 'append'; // prepend|append|brackets|none
            $loc  = $this->get_product_location_text($product_id);

            if (!$loc) return $base_title;

            switch ($fmt) {
                case 'prepend': return $loc . $sep . $base_title;
                case 'brackets': return $base_title . ' [' . $loc . ']';
                case 'none': return $base_title;
                case 'append':
                default: return $base_title . $sep . $loc;
            }
        }

        private function build_description_with_location($base_description, $product_id) {
            $loc = $this->get_product_location_text($product_id);
            if (!$loc) return $base_description;

            $suffix = ' Available at: ' . $loc . '.';

            // Avoid duplication if already present
            if ($base_description && stripos($base_description, $loc) !== false) {
                return $base_description;
            }

            $desc = trim((string) $base_description);

            // If empty, fall back to product short description
            if ($desc === '') {
                $product = wc_get_product($product_id);
                if ($product) {
                    $excerpt = wp_strip_all_tags($product->get_short_description());
                    $desc = mb_substr(trim($excerpt), 0, 140);
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
            if (!is_singular('product')) return $parts;
            $product_id = get_the_ID();
            if (!$product_id) return $parts;

            if (isset($parts['title'])) {
                $parts['title'] = $this->build_title_with_location($parts['title'], $product_id);
            }
            return $parts;
        }

        public function yoast_add_location_to_title($title) {
            if (!$this->is_enabled('location_in_meta_title') || !mulopimfwc_premium_feature()) return $title;
            if (!is_singular('product')) return $title;
            return $this->build_title_with_location($title, get_the_ID());
        }

        public function rankmath_add_location_to_title($title) {
            if (!$this->is_enabled('location_in_meta_title') || !mulopimfwc_premium_feature()) return $title;
            if (!is_singular('product')) return $title;
            return $this->build_title_with_location($title, get_the_ID());
        }

        /* -------------------------
         * Description filters
         * ------------------------- */

        public function yoast_add_location_to_description($desc) {
            if (!$this->is_enabled('location_in_meta_description') || !mulopimfwc_premium_feature()) return $desc;
            if (!is_singular('product')) return $desc;
            return $this->build_description_with_location((string)$desc, get_the_ID());
        }

        public function rankmath_add_location_to_description($desc) {
            if (!$this->is_enabled('location_in_meta_description') || !mulopimfwc_premium_feature()) return $desc;
            if (!is_singular('product')) return $desc;
            return $this->build_description_with_location((string)$desc, get_the_ID());
        }

        /**
         * Output a native <meta name="description"> if no SEO plugin is active.
         */
        public function seo_output_fallback_meta_description() {
            if (!$this->is_enabled('location_in_meta_description') || !mulopimfwc_premium_feature()) return;
            if (!is_singular('product')) return;

            // Skip if Yoast or Rank Math is active
            if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) return;

            $desc = $this->build_description_with_location('', get_the_ID());
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
            if (!is_singular('product') || !$product) return $markup;

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
    }
}


add_action('plugins_loaded', function () {
    // Only load if WooCommerce active, same guard you already use elsewhere is fine
    if (function_exists('WC')) {
        new MULOPIMFWC_Location_Wise_SEO();
    }
}, 20);
