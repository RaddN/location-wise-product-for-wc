<?php

/**
 * Plugin Analytics Integration Class
 * 
 * This class handles both tracking and deactivation analytics
 * for your WordPress plugin using the Product Analytics Pro API.
 */

 if (!defined('ABSPATH')) {
    exit;
}

class mulopimfwc_anaylytics
{

    private $product_id;
    private $analytics_api_url;
    private $plugin_version;
    private $plugin_name;
    private $plugin_file;

    public function __construct($product_id, $analytics_api_url, $plugin_version, $plugin_name, $plugin_file = null)
    {
        $this->product_id = $product_id;
        $this->analytics_api_url = rtrim($analytics_api_url, '/');
        $this->plugin_version = $plugin_version;
        $this->plugin_name = $plugin_name;
        $this->plugin_file = $plugin_file;

        global $mulopimfwc_options;

        // Hook into plugin activation/deactivation using the correct file path
        if ($this->plugin_file && (!isset($mulopimfwc_options["allow_data_share"]) || ( isset($mulopimfwc_options["allow_data_share"])  && $mulopimfwc_options["allow_data_share"] === 'on'))) {
            register_activation_hook($this->plugin_file, array($this, 'on_plugin_activation'));
            register_deactivation_hook($this->plugin_file, array($this, 'on_plugin_deactivation'));
        }
        if (!isset($mulopimfwc_options["allow_data_share"]) || (isset($mulopimfwc_options["allow_data_share"])  && $mulopimfwc_options["allow_data_share"] === 'on')) {
            // Send tracking data periodically (weekly)
            add_action('wp_loaded', array($this, 'schedule_tracking'));
            add_action('send_plugin_analytics_' . $this->product_id, array($this, 'send_tracking_data'));
        }

        // Add deactivation feedback form
        add_action('admin_footer', array($this, 'add_deactivation_feedback_form'));
    }

    /**
     * Called when plugin is activated
     */
    public function on_plugin_activation()
    {
        // Send initial tracking data
        $this->send_tracking_data();

        // Schedule weekly tracking
        if (!wp_next_scheduled('send_plugin_analytics_' . $this->product_id)) {
            wp_schedule_event(time(), 'weekly', 'send_plugin_analytics_' . $this->product_id);
        }
    }

    /**
     * Called when plugin is deactivated
     */
    public function on_plugin_deactivation()
    {
        // Clear scheduled event
        wp_clear_scheduled_hook('send_plugin_analytics_' . $this->product_id);

        // Note: Deactivation reason will be sent via AJAX from the feedback form
    }

    /**
     * Schedule tracking if not already scheduled
     */
    public function schedule_tracking()
    {
        if (!wp_next_scheduled('send_plugin_analytics_' . $this->product_id)) {
            wp_schedule_event(time(), 'weekly', 'send_plugin_analytics_' . $this->product_id);
        }
    }

    /**
     * Send tracking data to analytics API
     */
    public function send_tracking_data()
    {
        $data = $this->collect_site_data();

        $response = wp_remote_post($this->analytics_api_url . '/track/' . $this->product_id, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        return true;
    }

    /**
     * Send deactivation data to analytics API
     */
    public function send_deactivation_data($reason = '')
    {
        $data = array(
            'site_url' => home_url(),
            'reason' => $reason,
        );

        $response = wp_remote_post($this->analytics_api_url . '/deactivate/' . $this->product_id, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return true;
    }

    /**
     * Collect comprehensive site data
     */
    private function collect_site_data()
    {
        global $wpdb;

        return array(
            'site_url' => home_url(),
            'multisite' => is_multisite(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'server_software' => sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')),
            'mysql_version' => $wpdb->db_version(),
            'location' => $this->get_site_location(),
            'plugin_version' => $this->plugin_version,
            'other_plugins' => $this->get_other_plugins(),
            'active_theme' => get_option('stylesheet'),
            'using_pro' => "0",
            'license_key' => $this->get_license_key(),
        );
    }

    /**
     * Get site location based on timezone
     */
    private function get_site_location()
    {
        $timezone = get_option('timezone_string');
        if (empty($timezone)) {
            return 'Unknown';
        }

        // Extract country/region from timezone
        $parts = explode('/', $timezone);
        return isset($parts[0]) ? $parts[0] : 'Unknown';
    }

    /**
     * Get list of other active plugins
     */
    private function get_other_plugins()
    {
        $active_plugins = get_option('active_plugins', array());
        $plugins = array();

        foreach ($active_plugins as $plugin_path) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
            if (!empty($plugin_data['Name']) && $plugin_data['Name'] !== $this->plugin_name) {
                $plugins[] = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                );
            }
        }

        return $plugins;
    }

    /**
     * Get license key if available
     * Override this method based on your plugin's license system
     */
    private function get_license_key()
    {
        // Example: Get license from options
        return get_option('mulopimfwclicense_key', '');
    }

    /**
     * Add deactivation feedback form
     */
    public function add_deactivation_feedback_form()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'plugins') {
            // Get the correct plugin basename
            $plugin_file = $this->plugin_file;
            $plugin_basename = plugin_basename($plugin_file);
            $plugin_slug = dirname($plugin_basename);
?>
            <div id="plugin-deactivation-feedback" style="display:none;">
                <div class="feedback-overlay">
                    <div class="feedback-modal">
                        <div class="modal-header">
                            <div style="display: flex; gap:10px;align-items: center;">
                                <div class="plugincy_icon" style=" line-height: 1; "><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="#5738C0"/><path d="M13.758 4.453c.726.622 1.079 1.145 1.195 2.098-.019 1.053-.633 1.813-1.244 2.627-.42.581-.59.861-.542 1.589l.146.483.589-.164.77-.211.387-.108c.636-.173 1.152-.267 1.816-.267-.093-.7-.164-1.09-.563-1.688-.082-.644-.082-.644 0-1.313.478-.548.787-.722 1.5-.855.777.146 1.049.4 1.5 1.043-.014.804-.116 1.205-.563 1.875l.012.656c-.011.657-.011.657-.234 1.02-.461.271-.749.264-1.277.2l-.322-.213c-.655-.248-1.097-.021-1.752.19-1.257.398-1.257.398-1.863.398l-.164.363c-.211.387-.211.387-.586.574l-.738-.012c-1.135.109-1.744.82-2.524 1.593-.412.406-.823.746-1.285 1.091-.568.458-.568.458-.566 1.053.325.802.754.939 1.521 1.286l.51.215.512.229q.626.278 1.257.545l-.246-.405c-1.112-1.912-1.112-1.912-.879-2.97.553-.669.956-1.085 1.828-1.183.761.073 1.141.339 1.734.808.287.575.308 1.068.188 1.688a21 21 0 0 1-1.125 2.063 134 134 0 0 0 1.758-.785l.505-.22c.947-.414.947-.414 1.603-1.176.106-.664-.006-1.16-.304-1.756-.067-.719-.103-1.152.293-1.769.642-.413 1.022-.413 1.77-.295.563.387.563.387.938.938-.015.826-.222 1.501-.563 2.25l.188.188c.055.52.085.821-.229 1.252-.449.333-.675.264-1.225.201-1.311-.049-2.269.488-3.405 1.072-.579.287-.579.287-.954.287l-.058.445c-.129.492-.129.492-.692.867-.544.068-.821.081-1.277-.234-.223-.328-.223-.328-.223-1.078l-.31-.097c-.768-.247-1.488-.514-2.21-.876-.8-.374-1.454-.387-2.325-.311-.405-.029-.405-.029-.737-.28-.32-.433-.283-.722-.23-1.249l.188-.563c-.271-.523-.271-.523-.644-1.078-.397-.662-.659-1.128-.82-1.887.209-.826.653-1.338 1.276-1.909.603-.301 1.221-.275 1.875-.188.812.413 1.349.831 1.688 1.688 0 1.175-.527 2.027-1.125 3l.418-.384.555-.507.547-.501c.542-.483.542-.483 1.076-.872.434-.317.434-.317.481-.75l-.043-.385-.035-.726.375-.375c-.062-.703-.462-1.217-.844-1.793-.671-1.014-1.041-1.844-.843-3.082.352-.797.808-1.233 1.547-1.688 1.133-.302 1.983-.211 3.023.328" fill="#EBE6F6"/><path d="M18.375 12.75c.563.387.563.387.938.938-.015.826-.222 1.501-.563 2.25l.188.188c.055.52.085.821-.229 1.252-.449.333-.675.264-1.225.201-1.311-.049-2.269.488-3.405 1.072-.579.287-.579.287-.954.287l-.058.445c-.129.492-.129.492-.692.867-.544.068-.821.081-1.277-.234-.223-.328-.223-.328-.223-1.078l-.31-.097c-.768-.247-1.488-.514-2.21-.876-.91-.423-1.743-.414-2.73-.34l-.188-1.313.375-.188.188.375 1.5-.188.044.347c.109.429.109.429.492.643l.449.203.485.223.51.225.512.234a121 121 0 0 0 1.258.564l-.246-.405c-1.112-1.912-1.112-1.912-.879-2.97.553-.669.956-1.085 1.828-1.183.761.073 1.141.339 1.734.808.287.575.308 1.068.188 1.688a21 21 0 0 1-1.125 2.063 134 134 0 0 0 1.758-.785l.505-.22c.947-.414.947-.414 1.603-1.176.106-.664-.006-1.16-.304-1.756-.067-.719-.103-1.152.293-1.769.642-.413 1.022-.413 1.77-.295" fill="#E0DBF4"/><path d="M13.16 4.535c.596.384.922.662 1.278 1.278.292 1.513-.515 2.512-1.313 3.75l-.283.478c-.206.347-.429.686-.654 1.022h-.375c-.229-.304-.229-.304-.481-.725l-.276-.456-.286-.483-.288-.478c-1.125-1.873-1.125-1.873-.919-3.108.795-1.456 2.064-1.849 3.598-1.277" fill="#623AB2"/><path d="M6.938 10.875c.516.337.849.572 1.125 1.125.114 1.243-.114 1.896-.82 2.906l-.234.359c-.2.296-.416.579-.634.86H6a20 20 0 0 1-.867-1.324l-.239-.373c-.504-.832-.714-1.43-.581-2.428.82-.992 1.344-1.234 2.625-1.125" fill="#623DB8"/><path d="M18.375 12.75c.563.387.563.387.938.938-.015.826-.222 1.501-.563 2.25l.188.188c.054.517.084.82-.224 1.25-.461.34-.734.282-1.299.238-1.091-.037-1.845.238-2.79.761L14.25 18l.438-.172.57-.238.567-.231c.581-.354.698-.583.863-1.234-.058-.502-.15-.862-.375-1.313-.067-.719-.103-1.152.293-1.769.641-.411 1.021-.411 1.769-.293" fill="#E7E6FB"/><path d="M18.375 6.75c.551.387.551.387.938.938-.014.804-.116 1.205-.563 1.875l.012.656c-.012.656-.012.656-.235 1.019-.461.271-.749.264-1.277.2l-.346-.203c-.53-.226-.803-.131-1.354.015-.487.129-.487.129-.926.188l-.563-.375.984-.281.554-.158c.526-.124.526-.124 1.276-.124-.093-.7-.164-1.09-.563-1.688-.082-.644-.082-.644 0-1.313.618-.709 1.135-.924 2.063-.75" fill="#E7E3F7"/><path d="M6.938 10.5c.812.413 1.349.831 1.688 1.688 0 1.175-.527 2.027-1.125 3l.418-.384.555-.507.547-.501c.481-.429.974-.832 1.479-1.233l.375-.375v.375l.563.188-.384.21c-.69.439-1.286.953-1.901 1.489l-.357.308c-1.05.864-1.05.864-1.483 2.117l-.188-.563L6 16.687l-.375-.375v.75l.375.375c-.727-.164-.727-.164-.938-.375-.03-.575.003-.948.188-1.5-.271-.523-.271-.523-.644-1.078-.397-.662-.659-1.128-.82-1.887.21-.826.654-1.338 1.277-1.909.603-.301 1.221-.275 1.875-.188m-2.391 1.067c-.338.624-.299 1.039-.234 1.746.22.532.511 1.004.82 1.488l.234.389c.198.32.414.629.633.936h.375a18 18 0 0 0 .867-1.219l.239-.341c.584-.893.693-1.503.582-2.566-.336-.527-.566-.846-1.125-1.125-1.031-.057-1.641-.031-2.391.692" fill="#E8E2F3"/><path d="M12 14.508c.764.159 1.039.432 1.5 1.055.115.942-.115 1.448-.633 2.227l-.356.549-.323.411h-.375c-.324-.412-.324-.412-.68-.961l-.359-.541c-.306-.627-.358-.997-.274-1.685.461-.623.736-.895 1.5-1.055" fill="#5949D9"/><path d="M12.106 5.133c.704.181.977.465 1.394 1.055.062.541-.006.988-.188 1.5-.42.498-.669.551-1.313.645-.563-.082-.563-.082-.996-.375-.429-.621-.53-1.015-.503-1.77.502-.562.868-.875 1.606-1.055" fill="#663CB2"/><path d="M6.75 11.625c.457.305.457.305.75.75 0 .659-.069.989-.504 1.488-.595.274-.94.2-1.558.012-.434-.651-.455-.923-.375-1.688.414-.69.934-.67 1.688-.563" fill="#E0D7EF"/><path d="M12.563 14.25c1.046.592 1.046.592 1.313 1.125.2 1.343-.442 2.251-1.125 3.375l.516-.293c.64-.283.738-.286 1.359-.082l-.315.151-.412.201-.409.198c-.393.194-.393.194-.74.575l-.375-.375-.188.375h-.375v-.563h.375l.141-.492c.235-.632.235-.632.633-1.195.402-.643.481-.944.352-1.688-.281-.469-.281-.469-.75-.75-.689-.115-.992-.091-1.57.305-.389.568-.377.895-.305 1.571l.188.563-.375.188c-.44-.687-.473-1.261-.375-2.063.755-.914 1.252-1.239 2.438-1.125" fill="#E7E4F8"/><path d="M18.375 13.125c.363.305.363.305.563.75-.02.937-.39 1.508-.938 2.25-.686-.229-.831-.532-1.163-1.154-.201-.464-.246-.787-.149-1.283.423-.682.933-.673 1.688-.563" fill="#685FE4"/><path d="M18.563 7.313c.375.563.375.563.352 1.09-.189.686-.495 1.153-.915 1.722-.686-.229-.831-.532-1.163-1.154-.201-.464-.246-.787-.149-1.283.451-.795 1.085-.576 1.875-.375" fill="#6E59D1"/><path d="M7.125 10.875c.617.044.765.204 1.184.68.398.795.374 1.095.129 1.945a20 20 0 0 1-.938 1.688l.418-.384.555-.507.547-.501c.482-.429.975-.832 1.48-1.233l.375-.375v.375l.563.188-.385.21c-.687.438-1.277.952-1.888 1.489l-.693.604-.306.269c-.633.523-.983.658-1.791.615l.28-.438.364-.57.363-.567c.496-.793.626-1.431.493-2.363-.209-.433-.447-.746-.75-1.125" fill="#E4DFF4"/><path d="M6.938 10.875c.551.469.551.469.938.938l-.563.563-.188-.563c-.752-.034-.752-.034-1.5 0-.518.518-.484.976-.563 1.688l-.75-.188c-.058-.598-.058-.598 0-1.313.812-.983 1.35-1.255 2.625-1.125" fill="#693DAF"/><path d="M12 15.141c.563.047.563.047.938.422.063.593.094.972-.223 1.488-.34.199-.34.199-.821.164-.456-.152-.456-.152-.831-.527-.047-.563-.047-.563 0-1.125.375-.375.375-.375.938-.422" fill="#E7E4F8"/><path d="m12.563 3.938.375.375-.33.015c-1.026.074-1.668.246-2.483.922-.383.574-.485.917-.474 1.602.216 1.006.869 1.851 1.412 2.711a22 22 0 0 1 .375.75c-.755-.282-1.028-.719-1.441-1.394l-.342-.55c-.471-.832-.618-1.614-.467-2.556.36-.816.807-1.239 1.582-1.676.649-.194 1.121-.278 1.793-.199" fill="#ECE7F4"/><path d="M12.106 5.133c.704.181.977.465 1.394 1.055.062.541-.006.988-.188 1.5-.42.498-.669.551-1.313.645-.563-.082-.563-.082-.996-.375-.429-.621-.53-1.015-.503-1.77.502-.562.868-.875 1.606-1.055m-1.043.89c-.286.537-.158.905 0 1.477.353.429.353.429.926.445.579-.018.579-.018.938-.375.265-.593.176-.953.012-1.57-.218-.259-.218-.259-.82-.234-.652-.004-.652-.004-1.055.258" fill="#E7E1F3"/><path d="M10.688 17.25h.375c.75 1.015.75 1.015.75 1.5l.375.188h-.375v.563h.375l.188-.563.75.188c-.058.352-.058.352-.188.75-.606.404-.797.463-1.5.375-.352-.199-.352-.199-.563-.563q.082-.658.188-1.313l-.375-.563z" fill="#D8D5F7"/><path d="M16.5 16.313c.854-.069.854-.069 1.277.281l.223.281.188-.563h.375v.75l-.577.056c-.981.175-1.751.6-2.631 1.053l-.542.204L14.25 18l.445-.179.575-.243.573-.237c.499-.231.499-.231.628-.685z" fill="#DCDBF9"/><path d="m11.25 10.875.258.281c.281.324.281.324.68.281.305-.273.305-.273.563-.563l.375.375c-.047.481-.047.481-.188.938-.52.26-.925.223-1.5.188L10.875 12c.164-.914.164-.914.375-1.125" fill="#6549C5"/><path d="M5.813 12c.586.07.586.07 1.125.188a5.6 5.6 0 0 1 .188.938c-.375.375-.375.375-.949.41-.552-.036-.552-.036-.739-.223-.035-.457-.035-.457 0-.938z" fill="#633AB1"/><path d="m12.563 14.625-.188.563c-.574.223-.574.223-1.125.375-.034.74-.018 1.237.375 1.875l-.563.188c-.501-.71-.708-1.192-.563-2.063.619-.837 1.028-1.058 2.063-.938" fill="#6752D1"/><path d="M17.707 13.465c.481.035.481.035.856.41-.07.586-.07.586-.188 1.125q-.562.011-1.125 0c-.188-.188-.188-.188-.211-.75.031-.736.031-.736.668-.785" fill="#BCB8F3"/><path d="M17.707 7.465c.481.035.481.035.856.41-.07.586-.07.586-.188 1.125q-.562.011-1.125 0c-.188-.188-.188-.188-.211-.75.031-.736.031-.736.668-.785" fill="#BFB5EA"/><path d="m5.625 15.563.375.188v.375l.34-.188c.41-.188.41-.188.973-.188v1.125l-.188-.563L6 16.687l-.375-.375v.75l.375.375c-.727-.164-.727-.164-.938-.375a19 19 0 0 1 0-.938l.563-.188z" fill="#F1EEF9"/><path d="M18.938 7.875h.188c.047 1.055.047 1.055-.375 1.688a19 19 0 0 0-.188 1.125l-.188-.375H18l-.188.563-.563-.563.188-.563.563.375.152-.387c.253-.625.518-1.244.786-1.863" fill="#EAE8F9"/><path d="M11.625 15.563q.471.08.938.188v.938a17 17 0 0 1-.938.188c-.188-.188-.188-.188-.211-.656.024-.471.024-.471.211-.658" fill="#5946D3"/><path d="m5.625 16.125.328.199c.489.204.683.163 1.172-.012-.164.727-.164.727-.375.938q-.562.011-1.125 0a17 17 0 0 1-.188-.938z" fill="#6B4EC5"/><path d="m14.625 18.375-.315.151-.412.201-.409.198c-.392.194-.392.194-.739.575l-.375-.375-.188.375h-.375v-.563h.375l.188-.563.188.375.609-.293c.563-.271.859-.28 1.453-.082" fill="#F3F3FD"/><path d="M11.625 14.813c1.204-.091 1.204-.091 1.676.305.246.55.24.973.199 1.57l-.375.188-.164-.633c-.222-.715-.336-.922-.984-1.266z" fill="#523FD2"/><path d="m17.25 10.313.563.375.188-.375h.75c-.107.538-.161.724-.563 1.125-.492.023-.492.023-.938 0z" fill="#B2A9EB"/><path d="M16.875 16.313c.563 0 .563 0 .879.281l.246.281.188-.563h.375v.75c-.633.117-.633.117-1.313.188l-.375-.375z" fill="#534FEA"/><path d="M5.063 12.188h.375l.375 1.313.938.188-.188.375c-.539-.058-.539-.058-1.125-.188-.429-.644-.408-.935-.375-1.688" fill="#E8E2F3"/><path d="m12.152 5.191.411.059v.563c-1.242.375-1.242.375-1.875.375.808-.923.808-.923 1.465-.996" fill="#D8CCEA"/><path d="M8.625 17.813c.99.097 1.607.423 2.438.938l-.188.375-2.25-1.125z" fill="#EAE8F9"/><path d="M16.5 10.875v.188q-.45.11-.902.211l-.508.119c-.465.045-.465.045-1.027-.33.898-.306 1.51-.414 2.438-.188" fill="#D2CCF1"/><path d="M7.125 10.875c.486.067.721.162 1.09.492.316.631.178 1.083.035 1.758h-.188l-.152-.703c-.155-.629-.34-1.062-.785-1.547" fill="#DBD2EE"/><path d="m10.688 12.188.188.375.563.188-.433.258a29 29 0 0 0-1.068.679l-.563-.188.861-.696c.289-.236.289-.236.452-.617" fill="#DAD3F1"/><path d="m12.563 15.938.375.75c-.223.293-.223.293-.563.563-.504-.058-.504-.058-.938-.188V16.5l.352.094c.391.108.391.108.773.094z" fill="#DFDBF7"/><path d="m12.375 18.938.375.188-.375.75-.938-.188.188-.563.563.375z" fill="#665CE2"/><path d="m17.25 9.75 1.313.375-.188.563v-.375H18l-.188.563c-.281-.246-.281-.246-.563-.563z" fill="#F1F0FB"/><path d="m10.875 15.938.75 1.5-.563.188c-.392-.831-.392-.831-.305-1.371z" fill="#6856D7"/><path d="M17.25 15.188h.938l-.188.938-.563-.188c-.129-.387-.129-.387-.188-.75" fill="#5048E4"/><path d="m13.125 11.063 1.5.188-.188.375c-.574.117-.574.117-1.125.188z" fill="#E0DBF4"/><path d="M17.25 9.188h.938l-.188.938-.563-.188c-.129-.387-.129-.387-.188-.75" fill="#5742CF"/><path d="m11.063 15.188.375.188-.563 1.313H10.5c-.035-.539-.035-.539 0-1.125z" fill="#5640CD"/><path d="m8.063 13.875.188.375.375.188-1.313.938c.211-.557.417-1 .75-1.5" fill="#6F51C5"/><path d="m17.063 12.75.375.188-.258.328c-.275.38-.483.747-.68 1.172-.141-.422-.141-.422-.188-.938.363-.433.363-.433.75-.75" fill="#DBD8F7"/><path d="m7.688 11.813.375.188v1.125l-.563.188c-.023-1.078-.023-1.078.188-1.5" fill="#5830B2"/><path d="M18.375 6.938c.363.106.363.106.75.375.141.68.141.68.188 1.313l-.188-.75h-.375c-.305-.375-.305-.375-.563-.75z" fill="#DCD7F4"/><path d="m14.438 6.563.375.375c-.062.618-.117.867-.563 1.313-.235-.706-.069-1.007.188-1.688" fill="#E0DAF2"/></svg></div>
                                <h3>Quick Feedback</h3>
                            </div>
                            <button type="button" class="close-button" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>If you have a moment, please share why you are deactivating <?php echo esc_html($this->plugin_name); ?>:</p>
                            <form id="deactivation-feedback-form">
                                <div class="feedback-options">
                                    <label class="feedback-option">
                                        <input type="radio" name="reason" value="temporary">
                                        <span class="radio-button"></span>
                                        It's a temporary deactivation.
                                    </label>
                                    <label class="feedback-option">
                                        <input type="radio" name="reason" value="not-working">
                                        <span class="radio-button"></span>
                                        The plugin isn't working properly.
                                    </label>
                                    <label class="feedback-option">
                                        <input type="radio" name="reason" value="better-plugin">
                                        <span class="radio-button"></span>
                                        I found a better alternative plugin.
                                    </label>
                                    <label class="feedback-option">
                                        <input type="radio" name="reason" value="missing-feature">
                                        <span class="radio-button"></span>
                                        It's missing a specific feature.
                                    </label>
                                    <label class="feedback-option">
                                        <input type="radio" name="reason" value="other">
                                        <span class="radio-button"></span>
                                        Other
                                    </label>
                                </div>
                                <div class="other-reason-container" style="display:none;">
                                    <textarea name="other_reason" placeholder="Please tell us more..." rows="3"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Submit & Deactivate</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .feedback-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .feedback-modal {
                    background: #ffffff;
                    border-radius: 8px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }

                .modal-header {
                    padding: 24px 24px 8px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .modal-header h3 {
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #1a1a1a;
                }

                .close-button {
                    background: none;
                    border: none;
                    font-size: 20px;
                    color: #666;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                    transition: background-color 0.2s ease;
                }

                .close-button:hover {
                    background: #f5f5f5;
                }

                .modal-body {
                    padding: 16px 24px 24px;
                }

                .modal-body p {
                    margin: 0 0 20px;
                    color: #555;
                    font-size: 14px;
                    line-height: 1.5;
                }

                .feedback-options {
                    margin-bottom: 16px;
                }

                .feedback-option {
                    display: flex;
                    align-items: center;
                    margin: 0 0 12px;
                    padding: 0;
                    cursor: pointer;
                    font-size: 14px;
                    color: #333;
                    line-height: 1.4;
                }

                .feedback-option:hover {
                    color: #432fb8;
                }

                .feedback-option input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                    cursor: pointer;
                    height: 0;
                    width: 0;
                }

                .radio-button {
                    height: 16px;
                    width: 16px;
                    background: #ffffff;
                    border: 2px solid #ddd;
                    border-radius: 50%;
                    margin-right: 12px;
                    flex-shrink: 0;
                    position: relative;
                    transition: all 0.2s ease;
                }

                .feedback-option input[type="radio"]:checked+.radio-button {
                    border-color: #432fb8;
                    background: #432fb8;
                }

                .feedback-option input[type="radio"]:checked+.radio-button:after {
                    content: "";
                    position: absolute;
                    display: block;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -50%);
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    background: white;
                }

                .other-reason-container {
                    margin-top: 16px;
                    animation: slideDown 0.3s ease-out;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        max-height: 0;
                        transform: translateY(-10px);
                    }

                    to {
                        opacity: 1;
                        max-height: 100px;
                        transform: translateY(0);
                    }
                }

                .other-reason-container textarea {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    resize: vertical;
                    font-family: inherit;
                    font-size: 14px;
                    line-height: 1.4;
                    transition: border-color 0.2s ease;
                    box-sizing: border-box;
                }

                .other-reason-container textarea:focus {
                    outline: none;
                    border-color: #432fb8;
                    box-shadow: 0 0 0 1px #432fb8;
                }

                .modal-footer {
                    display: flex;
                    justify-content: flex-end;
                    margin-top: 20px;
                    padding-top: 16px;
                    border-top: 1px solid #eee;
                }

                .btn {
                    padding: 8px 16px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 120px;
                }

                .btn-primary {
                    background: #432fb8;
                    color: white;
                }

                .btn-primary:hover {
                    background: #005a87;
                }

                /* Responsive design */
                @media (max-width: 640px) {
                    .feedback-modal {
                        margin: 20px;
                        width: calc(100% - 40px);
                    }

                    .modal-header {
                        padding: 20px 20px 8px;
                    }

                    .modal-body {
                        padding: 16px 20px 20px;
                    }

                    .btn {
                        width: 100%;
                    }
                }
            </style>

            <script>
                jQuery(document).ready(function($) {
                    var pluginBasename = '<?php echo esc_js($plugin_basename); ?>';
                    var pluginSlug = '<?php echo esc_js($plugin_slug); ?>';
                    var deactivateUrl = '';

                    // Multiple selectors to catch the deactivation link
                    var selectors = [
                        'tr[data-slug="' + pluginSlug + '"] .deactivate a',
                        'tr[data-plugin="' + pluginBasename + '"] .deactivate a',
                        '.wp-list-table.plugins tr[data-slug="' + pluginSlug + '"] .row-actions .deactivate a'
                    ];

                    // Try each selector
                    selectors.forEach(function(selector) {
                        $(selector).on('click', function(e) {
                            e.preventDefault();
                            deactivateUrl = $(this).attr('href');
                            $('#plugin-deactivation-feedback').show();
                        });
                    });

                    // Fallback: Find deactivation link by searching for plugin basename in the URL
                    $('a[href*="action=deactivate"]').each(function() {
                        var href = $(this).attr('href');
                        if (href.indexOf(encodeURIComponent(pluginBasename)) > -1) {
                            $(this).on('click', function(e) {
                                e.preventDefault();
                                deactivateUrl = $(this).attr('href');
                                $('#plugin-deactivation-feedback').show();
                            });
                        }
                    });

                    // Handle feedback form submission
                    $('#deactivation-feedback-form').on('submit', function(e) {
                        e.preventDefault();

                        var reason = $('input[name="reason"]:checked').val();
                        var otherReason = $('textarea[name="other_reason"]').val();

                        if (reason === 'other' && otherReason) {
                            reason = otherReason;
                        }

                        $(this).find("button.btn.btn-primary").text("Deactivating...");

                        // Send deactivation data
                        $.ajax({
                            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                            type: 'POST',
                            data: {
                                action: 'send_deactivation_feedback',
                                reason: reason || 'no-reason-provided',
                                nonce: '<?php echo esc_js(wp_create_nonce('deactivation_feedback')); ?>'
                            },
                            complete: function() {
                                // Proceed with deactivation
                                window.location.href = deactivateUrl;
                            }
                        });
                    });

                    // Handle other reason text area
                    $('input[name="reason"]').change(function() {
                        if ($(this).val() === 'other') {
                            $('.other-reason-container').slideDown(300);
                        } else {
                            $('.other-reason-container').slideUp(300);
                        }
                    });

                    // Handle close button
                    $('.close-button').click(function() {
                        $('#plugin-deactivation-feedback').hide();
                    });

                    // Handle overlay click to close
                    $('.feedback-overlay').click(function(e) {
                        if (e.target === this) {
                            $('#plugin-deactivation-feedback').hide();
                        }
                    });

                    // Handle escape key
                    $(document).keyup(function(e) {
                        if (e.keyCode === 27) { // ESC key
                            $('#plugin-deactivation-feedback').hide();
                        }
                    });
                });
            </script>
<?php
        }
    }
}
