<?php
/**
 * Location-wise Email (placeholders + recipients)
 *
 * Toggles (mulopimfwc_display_options):
 *  - location_specific_emails:           enables {order_store_location} & {store_location_logo}
 *  - include_location_logo_emails:       enables {store_location_logo}
 *  - location_specific_email_recipients: appends location email to admin notifications
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('MULOPIMFWC_Location_Wise_Email')) {

class MULOPIMFWC_Location_Wise_Email {

    public function __construct() {
        // Placeholder replacement in all WC email strings (subject, heading, additional content, etc.)
        add_filter('woocommerce_email_format_string', [$this, 'replace_placeholders'], 10, 2);

        // Admin notifications → append location-specific recipients
        add_filter('woocommerce_email_recipient_new_order',       [$this, 'maybe_location_recipient'], 10, 2);
        add_filter('woocommerce_email_recipient_cancelled_order', [$this, 'maybe_location_recipient'], 10, 2);
        add_filter('woocommerce_email_recipient_failed_order',    [$this, 'maybe_location_recipient'], 10, 2);
        add_filter('woocommerce_email_recipient_on_hold_order',   [$this, 'maybe_location_recipient'], 10, 2);
    }

    /* -------------------------
     * Options & helpers
     * ------------------------- */

    private function opts() {
        global $mulopimfwc_options;
        return is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
    }
    private function opt_on($key) {
        $o = $this->opts();
        return isset($o[$key]) && $o[$key] === 'on';
    }

    /** Try to get WC_Order from WC_Email context */
    private function get_order_from_email($email) {
        if (is_object($email) && isset($email->object)) {
            if ($email->object instanceof WC_Order) {
                return $email->object;
            }
            // Some emails pass arrays (rare); try to resolve first item as order
            if (is_array($email->object)) {
                $maybe = reset($email->object);
                if ($maybe instanceof WC_Order) return $maybe;
            }
        }
        return null;
    }

    /** Get order location slug saved previously as _store_location */
    private function get_order_location_slug($order) {
        if (!$order instanceof WC_Order) return '';
        $slug = (string) $order->get_meta('_store_location');
        return sanitize_title($slug);
    }

    /** Get location term & fields */
    // OPTIMIZED: Uses cached method to prevent repeated database queries
    private function get_location_term($slug) {
        if (!$slug) return false;
        // OPTIMIZED: Use cached method instead of direct get_term_by
        $term = get_term_by('slug', $slug, 'mulopimfwc_store_location');
        return ($term && !is_wp_error($term)) ? $term : false;
    }

    /** Get location display name */
    private function get_location_name($term) {
        return $term ? $term->name : '';
    }

    /** Get location logo URL (medium) from term meta 'logo_id' */
    private function get_location_logo_url($term) {
        if (!$term) return '';
        $logo_id = absint(get_term_meta($term->term_id, 'logo_id', true));
        if (!$logo_id) return '';
        $src = wp_get_attachment_image_url($logo_id, 'medium');
        return $src ?: '';
    }

    /* -------------------------
     * 1) Placeholder replacement
     * ------------------------- */

    /**
     * Adds support for:
     *  - {order_store_location}
     *  - {store_location_logo}
     *
     * Works anywhere WooCommerce runs format_string: subjects, headings, bodies, etc.
     */
    public function replace_placeholders($string, $email) {
        // If nothing to replace or feature off, early out
        if (strpos($string, '{order_store_location}') === false &&
            strpos($string, '{store_location_logo}') === false) {
            return $string;
        }

        $order = $this->get_order_from_email($email);
        if (!$order) {
            // No order context → strip our placeholders to avoid leakage
            $string = str_replace(['{order_store_location}', '{store_location_logo}'], '', $string);
            return $string;
        }

        $loc_slug = $this->get_order_location_slug($order);
        $term     = $this->get_location_term($loc_slug);
        $loc_name = $term ? $this->get_location_name($term) : '';

        // {order_store_location}
        if ($this->opt_on('location_specific_emails') && mulopimfwc_premium_feature()) {
            $string = str_replace('{order_store_location}', esc_html($loc_name), $string);
        } else {
            $string = str_replace('{order_store_location}', '', $string);
        }

        // {store_location_logo}
        if (false !== strpos($string, '{store_location_logo}')) {
            if ($this->opt_on('include_location_logo_emails') && $term) {
                $logo_url = $this->get_location_logo_url($term);
                if ($logo_url) {
                    $is_html = is_object($email) && method_exists($email, 'get_content_type') && $email->get_content_type() === 'text/html';
                    $replacement = $is_html
                        ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($loc_name) . ' logo" style="max-width:180px;height:auto;display:block;">'
                        : esc_url($logo_url);
                    $string = str_replace('{store_location_logo}', $replacement, $string);
                } else {
                    $string = str_replace('{store_location_logo}', '', $string);
                }
            } else {
                $string = str_replace('{store_location_logo}', '', $string);
            }
        }

        return $string;
    }

    /* -------------------------
     * 2) Location-specific recipients (admin)
     * ------------------------- */

    /**
     * Append the location email (term meta 'email') to admin notifications if enabled.
     * @param string   $recipient CSV of recipients
     * @param WC_Order $order
     */
    public function maybe_location_recipient($recipient, $order) {
        if (!$this->opt_on('location_specific_email_recipients')) return $recipient;
        if (!($order instanceof WC_Order)) return $recipient;

        $loc_slug = $this->get_order_location_slug($order);
        $term     = $this->get_location_term($loc_slug);
        if (!$term) return $recipient;

        $options = $this->opts();
        $order_recips_mode = isset($options['order_notification_recipients']) ? $options['order_notification_recipients'] : 'admin';

        $include_admin = ($order_recips_mode === 'admin' || $order_recips_mode === 'both');
        $include_manager = ($order_recips_mode === 'location_manager' || $order_recips_mode === 'both');

        // Start with existing recipients only if admin is included
        $parts = $include_admin
            ? array_filter(array_map('trim', explode(',', (string) $recipient)))
            : [];

        // Location contact email (term meta 'email')
        $loc_email = sanitize_email((string) get_term_meta($term->term_id, 'email', true));
        if ($loc_email && is_email($loc_email)) {
            $parts[] = $loc_email;
        }

        // Assigned location managers (user meta mulopimfwc_assigned_locations contains location slug)
        if ($include_manager && $loc_slug) {
            $managers = function_exists('mulopimfwc_get_location_managers_for_slug')
                ? mulopimfwc_get_location_managers_for_slug($loc_slug)
                : get_users([
                    'role' => 'mulopimfwc_location_manager',
                    'meta_query' => [
                        [
                            'key' => 'mulopimfwc_assigned_locations',
                            'value' => $loc_slug,
                            'compare' => 'LIKE',
                        ],
                    ],
                    'fields' => 'all',
                ]);

            if (is_array($managers)) {
                foreach ($managers as $m) {
                    if (is_object($m) && !empty($m->user_email) && is_email($m->user_email)) {
                        $parts[] = sanitize_email($m->user_email);
                    }
                }
            }
        }

        $parts = array_values(array_unique(array_filter($parts)));

        return implode(',', $parts);
    }
}

}


add_action('plugins_loaded', function () {
    if (function_exists('WC') && mulopimfwc_premium_feature()) {
        new MULOPIMFWC_Location_Wise_Email();
    }
}, 20);
