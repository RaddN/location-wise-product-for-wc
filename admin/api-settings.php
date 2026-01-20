<?php
/**
 * API Settings Page
 * 
 * Admin interface for managing API keys and webhook settings
 * 
 * @package Multi Location Product & Inventory Management for WooCommerce
 * @since 1.0.6.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class MULOPIMFWC_API_Settings {
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_mulopimfwc_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_mulopimfwc_generate_webhook_secret', array($this, 'ajax_generate_webhook_secret'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('mulopimfwc_display_options', 'mulopimfwc_api_key');
        register_setting('mulopimfwc_display_options', 'mulopimfwc_webhook_secret');
        register_setting('mulopimfwc_display_options', 'mulopimfwc_log_webhooks');
    }
    
    /**
     * Enqueue scripts for AJAX functionality
     */
    public function enqueue_scripts($hook) {
        // Check if we're on the settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'multi-location-product-and-inventory-management-settings') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_inline_script(), 'after');
    }
    
    /**
     * Get inline JavaScript for AJAX handlers
     */
    private function get_inline_script() {
        return "
        jQuery(document).ready(function($) {
            // Generate API Key
            $(document).on('click', '.mulopimfwc-generate-api-key', function(e) {
                e.preventDefault();
                var button = $(this);
                var originalText = button.text();
                button.prop('disabled', true).text('Generating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mulopimfwc_generate_api_key',
                        nonce: '" . wp_create_nonce('mulopimfwc_generate_api_key') . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            var container = button.closest('td');
                            container.html(
                                '<code style=\"font-size: 12px; padding: 8px 12px; background: #f0f0f0; display: inline-block; word-break: break-all; max-width: 600px;\">' + response.data.key + '</code>' +
                                '<p class=\"description\">" . esc_js(__('Include this in the X-API-Key header for API requests.', 'multi-location-product-and-inventory-management')) . "</p>'
                            );
                            // Show success notice
                            $('<div class=\"notice notice-success is-dismissible\" style=\"margin: 10px 0;\"><p>" . esc_js(__('API key generated successfully!', 'multi-location-product-and-inventory-management')) . "</p></div>').insertBefore(container.closest('.card')).delay(5000).fadeOut();
                        } else {
                            alert(response.data.message || 'Error generating API key');
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Error generating API key. Please try again.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Generate Webhook Secret
            $(document).on('click', '.mulopimfwc-generate-webhook-secret', function(e) {
                e.preventDefault();
                var button = $(this);
                var originalText = button.text();
                button.prop('disabled', true).text('Generating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mulopimfwc_generate_webhook_secret',
                        nonce: '" . wp_create_nonce('mulopimfwc_generate_webhook_secret') . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            var container = button.closest('td');
                            container.html(
                                '<code style=\"font-size: 12px; padding: 8px 12px; background: #f0f0f0; display: inline-block; word-break: break-all; max-width: 600px;\">' + response.data.secret + '</code>' +
                                '<p class=\"description\">" . esc_js(__('Include this in the X-Webhook-Secret header for webhook requests.', 'multi-location-product-and-inventory-management')) . "</p>'
                            );
                            // Show success notice
                            $('<div class=\"notice notice-success is-dismissible\" style=\"margin: 10px 0;\"><p>" . esc_js(__('Webhook secret generated successfully!', 'multi-location-product-and-inventory-management')) . "</p></div>').insertBefore(container.closest('.card')).delay(5000).fadeOut();
                        } else {
                            alert(response.data.message || 'Error generating webhook secret');
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Error generating webhook secret. Please try again.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        ";
    }
    
    /**
     * AJAX handler for generating API key
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('mulopimfwc_generate_api_key', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')));
        }
        
        $api_key = $this->generate_api_key();
        update_option('mulopimfwc_api_key', $api_key);
        
        wp_send_json_success(array('key' => $api_key));
    }
    
    /**
     * AJAX handler for generating webhook secret
     */
    public function ajax_generate_webhook_secret() {
        check_ajax_referer('mulopimfwc_generate_webhook_secret', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')));
        }
        
        $webhook_secret = $this->generate_api_key();
        update_option('mulopimfwc_webhook_secret', $webhook_secret);
        
        wp_send_json_success(array('secret' => $webhook_secret));
    }
    
    /**
     * Render API & Webhooks settings field
     */
    public function render_api_settings_field() {
        $api_key = get_option('mulopimfwc_api_key', '');
        $webhook_secret = get_option('mulopimfwc_webhook_secret', '');
        $log_webhooks = get_option('mulopimfwc_log_webhooks', 'no');
        
        $api_base_url = rest_url('mulopimfwc/v1/');
        
        // Show settings errors
        settings_errors('mulopimfwc_api_settings');
        ?>
        <div>           
            <div style="margin-bottom: 20px;">
                <table class="form-table">
                    <tr>
                        <th style="width: 200px;"><?php echo esc_html__('API Key', 'multi-location-product-and-inventory-management'); ?></th>
                        <td>
                            <div id="mulopimfwc-api-key-container">
                                <?php if ($api_key): ?>
                                    <code style="font-size: 12px; padding: 8px 12px; background: #f0f0f0; display: inline-block; word-break: break-all; max-width: 600px;"><?php echo esc_html($api_key); ?></code>
                                    <p class="description"><?php echo esc_html__('Include this in the X-API-Key header for API requests.', 'multi-location-product-and-inventory-management'); ?></p>
                                <?php else: ?>
                                    <button type="button" class="button button-secondary mulopimfwc-generate-api-key"><?php echo esc_html__('Generate API Key', 'multi-location-product-and-inventory-management'); ?></button>
                                    <p class="description"><?php echo esc_html__('Generate an API key to authenticate REST API requests.', 'multi-location-product-and-inventory-management'); ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Webhook Secret', 'multi-location-product-and-inventory-management'); ?></th>
                        <td>
                            <div id="mulopimfwc-webhook-secret-container">
                                <?php if ($webhook_secret): ?>
                                    <code style="font-size: 12px; padding: 8px 12px; background: #f0f0f0; display: inline-block; word-break: break-all; max-width: 600px;"><?php echo esc_html($webhook_secret); ?></code>
                                    <p class="description"><?php echo esc_html__('Include this in the X-Webhook-Secret header for webhook requests.', 'multi-location-product-and-inventory-management'); ?></p>
                                <?php else: ?>
                                    <button type="button" class="button button-secondary mulopimfwc-generate-webhook-secret"><?php echo esc_html__('Generate Webhook Secret', 'multi-location-product-and-inventory-management'); ?></button>
                                    <p class="description"><?php echo esc_html__('Generate a webhook secret to authenticate webhook requests from external systems.', 'multi-location-product-and-inventory-management'); ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Log Webhooks', 'multi-location-product-and-inventory-management'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mulopimfwc_display_options[log_webhooks]" value="yes" <?php checked($log_webhooks, 'yes'); ?>>
                                <?php echo esc_html__('Log webhook requests to files', 'multi-location-product-and-inventory-management'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Webhook logs are saved to wp-content/uploads/mulopimfwc-webhook-log-YYYY-MM-DD.log', 'multi-location-product-and-inventory-management'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Generate API key
     */
    private function generate_api_key() {
        return 'mulopimfwc_' . bin2hex(random_bytes(32));
    }
}

new MULOPIMFWC_API_Settings();

