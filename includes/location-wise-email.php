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
        // Add more email IDs here if you also want routing for them:
        // add_filter('woocommerce_email_recipient_on_hold_order',  [$this, 'maybe_location_recipient'], 10, 2);
        // add_filter('woocommerce_email_recipient_completed_order',[$this, 'maybe_location_recipient'], 10, 2);
    }

    /* -------------------------
     * Options & helpers
     * ------------------------- */

    private function opts() {
        return get_option('mulopimfwc_display_options', []);
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
    private function get_location_term($slug) {
        if (!$slug) return false;
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

        $loc_email = sanitize_email((string) get_term_meta($term->term_id, 'email', true));
        if (!$loc_email || !is_email($loc_email)) return $recipient;

        // Append (keeps HQ recipients intact). To replace entirely, just: return $loc_email;
        $parts = array_filter(array_map('trim', explode(',', (string) $recipient)));
        $parts[] = $loc_email;
        $parts = array_unique($parts);

        return implode(',', $parts);
    }
}

}


add_action('plugins_loaded', function () {
    if (function_exists('WC') && mulopimfwc_premium_feature()) {
        new MULOPIMFWC_Location_Wise_Email();
    }
}, 20);
