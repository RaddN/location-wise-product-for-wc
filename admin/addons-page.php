<?php
if (!defined('ABSPATH')) {
    exit;
}

class MULOPIMFWC_Addons_Page
{
    private const MANIFEST_TRANSIENT = 'mulopimfwc_addons_manifest_cache_v2';
    private const INSTALLED_STATUS_OPTION = 'mulopimfwc_addons_installed_status';
    private const LAST_ERROR_OPTION = 'mulopimfwc_addons_last_error';
    private const LAST_CHECK_OPTION = 'mulopimfwc_addons_last_update_check';
    private const NOTICE_TRANSIENT = 'mulopimfwc_addons_admin_notice';
    private const MANIFEST_PRODUCT_SLUG = 'multi-location-product-inventory-management-for-woocommerce-pro';
    private const DEFAULT_MANIFEST_URL = 'https://plugincy.com/wp-json/plugincy/v1/' . self::MANIFEST_PRODUCT_SLUG . '/addons';

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_post_mulopimfwc_addon_action', [$this, 'handle_action']);
    }

    public function render_page()
    {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to manage add-ons.', 'multi-location-product-and-inventory-management-pro'));
        }

        $license_valid = $this->has_valid_license();
        $addons = $this->get_addons_with_manifest(false);
        $statuses = $this->get_addon_statuses($addons);
        $last_error = get_option(self::LAST_ERROR_OPTION, '');
        $last_check = (int) get_option(self::LAST_CHECK_OPTION, 0);
        $notice = get_transient(self::NOTICE_TRANSIENT);
        if (is_array($notice)) {
            delete_transient(self::NOTICE_TRANSIENT);
        }
        ?>
        <div class="wrap mulopimfwc-addons-page">
            <h1><?php echo esc_html__('Addons', 'multi-location-product-and-inventory-management-pro'); ?></h1>

            <?php settings_errors('mulopimfwc_addons'); ?>

            <?php if (is_array($notice) && !empty($notice['message'])) : ?>
                <div class="notice notice-<?php echo esc_attr(!empty($notice['type']) ? (string) $notice['type'] : 'info'); ?>">
                    <p><?php echo esc_html((string) $notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$license_valid) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html__('Activate a valid Plugincy license to install or update private add-ons.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($last_error !== '') : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($last_error); ?></p>
                </div>
            <?php endif; ?>

            <p>
                <a class="button" href="<?php echo esc_url($this->action_url('refresh', 'mulopimfwc-pos-connector')); ?>">
                    <?php echo esc_html__('Check for Addon Updates', 'multi-location-product-and-inventory-management-pro'); ?>
                </a>
                <?php if ($last_check > 0) : ?>
                    <span class="description">
                        <?php
                        printf(
                            esc_html__('Last checked: %s', 'multi-location-product-and-inventory-management-pro'),
                            esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check))
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </p>

            <div class="mulopimfwc-addons-grid">
                <?php foreach ($addons as $addon) : ?>
                    <?php $status = $statuses[$addon['slug']] ?? $this->get_addon_status($addon); ?>
                    <div class="mulopimfwc-addon-card">
                        <div class="mulopimfwc-addon-card__header">
                            <div>
                                <h2><?php echo esc_html($addon['name']); ?></h2>
                                <p><?php echo esc_html($addon['description']); ?></p>
                            </div>
                            <span class="mulopimfwc-addon-status mulopimfwc-addon-status--<?php echo esc_attr($status['state']); ?>">
                                <?php echo esc_html($status['label']); ?>
                            </span>
                        </div>

                        <dl class="mulopimfwc-addon-meta">
                            <dt><?php echo esc_html__('Current Version', 'multi-location-product-and-inventory-management-pro'); ?></dt>
                            <dd><?php echo esc_html($status['installed_version'] ?: __('Not installed', 'multi-location-product-and-inventory-management-pro')); ?></dd>
                            <dt><?php echo esc_html__('Latest Version', 'multi-location-product-and-inventory-management-pro'); ?></dt>
                            <dd><?php echo esc_html($addon['version']); ?></dd>
                            <dt><?php echo esc_html__('Requires', 'multi-location-product-and-inventory-management-pro'); ?></dt>
                            <dd><?php echo esc_html($addon['requires']); ?></dd>
                        </dl>

                        <?php if (!empty($addon['changelog'])) : ?>
                            <p class="description"><?php echo wp_kses_post($addon['changelog']); ?></p>
                        <?php endif; ?>

                        <div class="mulopimfwc-addon-actions">
                            <?php $this->render_actions($addon, $status, $license_valid); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
            .mulopimfwc-addons-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 16px;
                margin-top: 18px;
            }
            .mulopimfwc-addon-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 18px;
                max-width: 350px;
            }
            .mulopimfwc-addon-card__header {
                align-items: flex-start;
                display: flex;
                gap: 14px;
                justify-content: space-between;
            }
            .mulopimfwc-addon-card h2 {
                margin: 0 0 8px;
            }
            .mulopimfwc-addon-card p {
                margin-top: 0;
            }
            .mulopimfwc-addon-status {
                border-radius: 999px;
                display: inline-block;
                font-size: 12px;
                font-weight: 600;
                line-height: 1;
                padding: 7px 10px;
                white-space: nowrap;
            }
            .mulopimfwc-addon-status--active {
                background: #edfaef;
                color: #0a6b21;
            }
            .mulopimfwc-addon-status--inactive {
                background: #fff8e5;
                color: #8a5a00;
            }
            .mulopimfwc-addon-status--missing {
                background: #f0f0f1;
                color: #50575e;
            }
            .mulopimfwc-addon-status--update {
                background: #e7f5ff;
                color: #005c99;
            }
            .mulopimfwc-addon-meta {
                display: grid;
                grid-template-columns: 130px minmax(0, 1fr);
                margin: 14px 0;
            }
            .mulopimfwc-addon-meta dt,
            .mulopimfwc-addon-meta dd {
                border-top: 1px solid #f0f0f1;
                margin: 0;
                padding: 8px 0;
            }
            .mulopimfwc-addon-meta dt {
                color: #646970;
                font-weight: 600;
            }
            .mulopimfwc-addon-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 16px;
            }
        </style>
        <?php
    }

    private function render_actions(array $addon, array $status, bool $license_valid)
    {
        if (!$status['installed']) {
            printf(
                '<a class="button button-primary %1$s" href="%2$s">%3$s</a>',
                !$license_valid || empty($addon['package']) ? 'disabled' : '',
                $license_valid && !empty($addon['package']) ? esc_url($this->action_url('install', $addon['slug'])) : '#',
                esc_html__('Install', 'multi-location-product-and-inventory-management-pro')
            );
            return;
        }

        if (!$status['active']) {
            printf(
                '<a class="button button-primary" href="%1$s">%2$s</a>',
                esc_url($this->action_url('activate', $addon['slug'])),
                esc_html__('Activate', 'multi-location-product-and-inventory-management-pro')
            );
        } else {
            printf(
                '<a class="button" href="%1$s">%2$s</a>',
                esc_url($this->action_url('deactivate', $addon['slug'])),
                esc_html__('Deactivate', 'multi-location-product-and-inventory-management-pro')
            );
        }

        if ($status['update_available'] && $license_valid && !empty($addon['package'])) {
            printf(
                '<a class="button" href="%1$s">%2$s</a>',
                esc_url($this->action_url('update', $addon['slug'])),
                esc_html__('Update', 'multi-location-product-and-inventory-management-pro')
            );
        }

        printf(
            '<a class="button" href="%1$s" onclick="return confirm(%2$s);">%3$s</a>',
            esc_url($this->action_url('delete', $addon['slug'])),
            esc_attr(wp_json_encode(__('Delete this add-on plugin?', 'multi-location-product-and-inventory-management-pro'))),
            esc_html__('Delete', 'multi-location-product-and-inventory-management-pro')
        );
    }

    public function handle_action()
    {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to manage add-ons.', 'multi-location-product-and-inventory-management-pro'));
        }

        $action = isset($_GET['addon_action']) ? sanitize_key(wp_unslash($_GET['addon_action'])) : '';
        $slug = isset($_GET['addon']) ? sanitize_key(wp_unslash($_GET['addon'])) : '';

        check_admin_referer('mulopimfwc_addon_' . $action . '_' . $slug);

        try {
            $addons = $this->get_addons_with_manifest(in_array($action, ['refresh', 'install', 'update'], true));
            if (!isset($addons[$slug])) {
                throw new RuntimeException(__('Unknown add-on.', 'multi-location-product-and-inventory-management-pro'));
            }

            $addon = $addons[$slug];

            if (in_array($action, ['install', 'update'], true)) {
                if (!$this->has_valid_license()) {
                    throw new RuntimeException(__('A valid Plugincy license is required to download this add-on.', 'multi-location-product-and-inventory-management-pro'));
                }

                $this->install_or_update($addon);
                $message = $action === 'install'
                    ? __('Add-on installed successfully.', 'multi-location-product-and-inventory-management-pro')
                    : __('Add-on updated successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'activate') {
                $this->activate($addon);
                $message = __('Add-on activated successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'deactivate') {
                $this->deactivate($addon);
                $message = __('Add-on deactivated successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'delete') {
                $this->delete($addon);
                $message = __('Add-on deleted successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'refresh') {
                $message = __('Add-on metadata refreshed.', 'multi-location-product-and-inventory-management-pro');
            } else {
                throw new RuntimeException(__('Unsupported add-on action.', 'multi-location-product-and-inventory-management-pro'));
            }

            delete_option(self::LAST_ERROR_OPTION);
            set_transient(self::NOTICE_TRANSIENT, ['type' => 'success', 'message' => $message], 60);
            add_settings_error('mulopimfwc_addons', 'mulopimfwc_addon_success', $message, 'updated');
        } catch (Throwable $exception) {
            update_option(self::LAST_ERROR_OPTION, $exception->getMessage(), false);
            set_transient(self::NOTICE_TRANSIENT, ['type' => 'error', 'message' => $exception->getMessage()], 60);
            add_settings_error('mulopimfwc_addons', 'mulopimfwc_addon_error', $exception->getMessage(), 'error');
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=mulopimfwc-addons'));
        exit;
    }

    private function action_url(string $action, string $slug): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'mulopimfwc_addon_action',
                    'addon_action' => $action,
                    'addon' => $slug,
                ],
                admin_url('admin-post.php')
            ),
            'mulopimfwc_addon_' . $action . '_' . $slug
        );
    }

    private function get_addons_with_manifest(bool $force): array
    {
        $addons = $this->get_default_addons();
        $manifest = $this->get_manifest($force);

        foreach ($addons as $slug => $addon) {
            if (isset($manifest[$slug]) && is_array($manifest[$slug])) {
                $addons[$slug] = $this->merge_manifest_addon($addon, $manifest[$slug]);
            }
        }

        return $addons;
    }

    private function merge_manifest_addon(array $addon, array $manifest): array
    {
        foreach (['version', 'package', 'checksum', 'changelog', 'plugin_file'] as $key) {
            if (array_key_exists($key, $manifest)) {
                $addon[$key] = $manifest[$key];
            }
        }

        foreach (['name', 'description'] as $key) {
            if (isset($manifest[$key]) && is_scalar($manifest[$key]) && trim((string) $manifest[$key]) !== '') {
                $addon[$key] = $manifest[$key];
            }
        }

        if (isset($manifest['requires_label']) && is_scalar($manifest['requires_label']) && trim((string) $manifest['requires_label']) !== '') {
            $addon['requires'] = $manifest['requires_label'];
        }

        return $addon;
    }

    private function get_default_addons(): array
    {
        return apply_filters('mulopimfwc_available_addons', [
            'mulopimfwc-pos-connector' => [
                'slug' => 'mulopimfwc-pos-connector',
                'name' => __('Multi Location POS Connector', 'multi-location-product-and-inventory-management-pro'),
                'description' => __('Connect Multi Location stock and pricing with supported POS systems. OpenPOS is supported in v1.', 'multi-location-product-and-inventory-management-pro'),
                'version' => '1.0.0',
                'requires' => __('WooCommerce, OpenPOS, Multi Location Pro', 'multi-location-product-and-inventory-management-pro'),
                'plugin_file' => 'mulopimfwc-pos-connector/mulopimfwc-pos-connector.php',
                'package' => '',
                'checksum' => '',
                'changelog' => '',
            ],
        ]);
    }

    private function get_manifest(bool $force): array
    {
        if (!$this->has_valid_license()) {
            delete_transient(self::MANIFEST_TRANSIENT);
            return [];
        }

        if (!$force) {
            $cached = get_transient(self::MANIFEST_TRANSIENT);
            if (is_array($cached)) {
                return $cached;
            }
        }

        update_option(self::LAST_CHECK_OPTION, time(), false);

        $endpoint = apply_filters('mulopimfwc_addons_manifest_url', self::DEFAULT_MANIFEST_URL);
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'license' => get_option('mulopimfwc_license_key', ''),
                'site_url' => home_url(),
                'plugin_version' => defined('MULOPIMFWC_VERSION') ? MULOPIMFWC_VERSION : '',
                'product_slug' => self::MANIFEST_PRODUCT_SLUG,
                'addons' => implode(',', array_keys($this->get_default_addons())),
            ],
        ]);

        if (is_wp_error($response)) {
            update_option(self::LAST_ERROR_OPTION, $response->get_error_message(), false);
            return [];
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);
        if (!is_array($body)) {
            update_option(self::LAST_ERROR_OPTION, __('The Plugincy add-on manifest response was invalid.', 'multi-location-product-and-inventory-management-pro'), false);
            return [];
        }

        if ($response_code < 200 || $response_code >= 300) {
            $message = isset($body['message']) && is_scalar($body['message'])
                ? sanitize_text_field((string) $body['message'])
                : __('The Plugincy add-on manifest request failed.', 'multi-location-product-and-inventory-management-pro');
            update_option(self::LAST_ERROR_OPTION, $message, false);
            return [];
        }

        $server_license = isset($body['license']) && is_array($body['license']) ? $body['license'] : [];
        if (isset($body['success']) && !$body['success'] && !empty($server_license)) {
            $server_message = isset($server_license['message']) && is_scalar($server_license['message'])
                ? sanitize_text_field((string) $server_license['message'])
                : __('The Plugincy license server did not return signed add-on downloads for this license.', 'multi-location-product-and-inventory-management-pro');
            update_option(self::LAST_ERROR_OPTION, $server_message, false);
        }

        $addons = isset($body['addons']) && is_array($body['addons']) ? $body['addons'] : $body;
        $normalized = [];

        foreach ($addons as $slug => $data) {
            if (is_int($slug) && isset($data['slug'])) {
                $slug = sanitize_key((string) $data['slug']);
            } else {
                $slug = sanitize_key((string) $slug);
            }

            if (!is_array($data) || $slug === '') {
                continue;
            }

            $normalized[$slug] = [
                'version' => isset($data['version']) ? sanitize_text_field((string) $data['version']) : '1.0.0',
                'package' => isset($data['download_url']) ? esc_url_raw((string) $data['download_url']) : (isset($data['package']) ? esc_url_raw((string) $data['package']) : ''),
                'checksum' => isset($data['checksum']) ? sanitize_text_field((string) $data['checksum']) : '',
                'changelog' => isset($data['changelog']) ? wp_kses_post((string) $data['changelog']) : '',
            ];
        }

        if (isset($body['success']) && $body['success']) {
            delete_option(self::LAST_ERROR_OPTION);
        }

        $cache_ttl = $this->get_manifest_cache_ttl($body, $normalized);
        if ($cache_ttl > 0) {
            set_transient(self::MANIFEST_TRANSIENT, $normalized, $cache_ttl);
        } else {
            delete_transient(self::MANIFEST_TRANSIENT);
        }

        return $normalized;
    }

    private function get_manifest_cache_ttl(array $body, array $addons): int
    {
        $has_package = false;
        foreach ($addons as $addon) {
            if (!empty($addon['package'])) {
                $has_package = true;
                break;
            }
        }

        if (!$has_package) {
            return 6 * HOUR_IN_SECONDS;
        }

        $token_ttl = isset($body['token_ttl']) ? absint($body['token_ttl']) : 0;
        if ($token_ttl <= 0) {
            return 2 * MINUTE_IN_SECONDS;
        }

        return max(30, min(5 * MINUTE_IN_SECONDS, $token_ttl - 30));
    }

    private function get_addon_status(array $addon): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        $installed = $plugin_file !== '';
        $active = $installed && is_plugin_active($plugin_file);
        $installed_version = '';

        if ($installed) {
            $plugins = get_plugins();
            $installed_version = isset($plugins[$plugin_file]['Version']) ? (string) $plugins[$plugin_file]['Version'] : '';
        }

        $update_available = $installed && $installed_version !== '' && version_compare($installed_version, (string) $addon['version'], '<');

        if ($update_available) {
            $state = 'update';
            $label = __('Update available', 'multi-location-product-and-inventory-management-pro');
        } elseif ($active) {
            $state = 'active';
            $label = __('Active', 'multi-location-product-and-inventory-management-pro');
        } elseif ($installed) {
            $state = 'inactive';
            $label = __('Installed', 'multi-location-product-and-inventory-management-pro');
        } else {
            $state = 'missing';
            $label = __('Not installed', 'multi-location-product-and-inventory-management-pro');
        }

        return [
            'installed' => $installed,
            'active' => $active,
            'installed_version' => $installed_version,
            'update_available' => $update_available,
            'state' => $state,
            'label' => $label,
            'plugin_file' => $plugin_file,
        ];
    }

    private function get_addon_statuses(array $addons): array
    {
        $statuses = [];
        $stored_statuses = [];
        $checked_at = time();

        foreach ($addons as $slug => $addon) {
            $status_slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : sanitize_key((string) $slug);
            $status = $this->get_addon_status($addon);
            $statuses[$status_slug] = $status;
            $stored_statuses[$status_slug] = [
                'installed' => (bool) $status['installed'],
                'active' => (bool) $status['active'],
                'installed_version' => (string) $status['installed_version'],
                'update_available' => (bool) $status['update_available'],
                'state' => (string) $status['state'],
                'plugin_file' => (string) $status['plugin_file'],
                'checked_at' => $checked_at,
            ];
        }

        update_option(self::INSTALLED_STATUS_OPTION, $stored_statuses, false);

        return $statuses;
    }

    private function resolve_plugin_file(array $addon): string
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugins = get_plugins();
        $expected_file = isset($addon['plugin_file']) && is_scalar($addon['plugin_file'])
            ? wp_normalize_path((string) $addon['plugin_file'])
            : '';
        $addon_slug = isset($addon['slug']) && is_scalar($addon['slug'])
            ? sanitize_key((string) $addon['slug'])
            : '';

        if ($expected_file !== '' && isset($plugins[$expected_file])) {
            return $expected_file;
        }

        $expected_basename = $expected_file !== '' ? basename($expected_file) : '';
        $expected_name = isset($addon['name']) && is_scalar($addon['name'])
            ? strtolower(trim((string) $addon['name']))
            : '';

        foreach ($plugins as $plugin_file => $data) {
            $plugin_file = wp_normalize_path((string) $plugin_file);
            $plugin_dir = sanitize_key(basename(dirname($plugin_file)));

            if ($addon_slug !== '' && ($plugin_dir === $addon_slug || strpos($plugin_dir, $addon_slug . '-') === 0)) {
                return $plugin_file;
            }

            if ($expected_basename !== '' && basename($plugin_file) === $expected_basename) {
                return $plugin_file;
            }

            $text_domain = isset($data['TextDomain']) ? sanitize_key((string) $data['TextDomain']) : '';
            if ($addon_slug !== '' && $text_domain === $addon_slug) {
                return $plugin_file;
            }

            $plugin_name = isset($data['Name']) ? strtolower(trim((string) $data['Name'])) : '';
            if ($expected_name !== '' && $plugin_name === $expected_name) {
                return $plugin_file;
            }
        }

        return '';
    }

    private function install_or_update(array $addon): void
    {
        if (empty($addon['package'])) {
            throw new RuntimeException(__('No signed download URL was returned for this add-on.', 'multi-location-product-and-inventory-management-pro'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $package = download_url($addon['package'], 30);
        if (is_wp_error($package)) {
            throw new RuntimeException($package->get_error_message());
        }

        try {
            if (!empty($addon['checksum'])) {
                $actual = hash_file('sha256', $package);
                if (!hash_equals(strtolower((string) $addon['checksum']), strtolower($actual))) {
                    throw new RuntimeException(__('The downloaded add-on package failed checksum verification.', 'multi-location-product-and-inventory-management-pro'));
                }
            }

            $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
            $result = $upgrader->install($package, ['overwrite_package' => true]);

            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_message());
            }

            if (!$result) {
                throw new RuntimeException(__('WordPress could not install the add-on package.', 'multi-location-product-and-inventory-management-pro'));
            }

            wp_clean_plugins_cache(true);
            $this->persist_addon_status($addon);
            delete_transient(self::MANIFEST_TRANSIENT);
        } finally {
            if (is_string($package) && file_exists($package)) {
                @unlink($package);
            }
        }
    }

    private function activate(array $addon): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        if ($plugin_file === '') {
            throw new RuntimeException(__('The add-on is not installed.', 'multi-location-product-and-inventory-management-pro'));
        }

        $result = activate_plugin($plugin_file, '', false, true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        wp_clean_plugins_cache(true);
        $this->persist_addon_status($addon);
    }

    private function deactivate(array $addon): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        if ($plugin_file !== '') {
            deactivate_plugins($plugin_file);
            wp_clean_plugins_cache(true);
            $this->persist_addon_status($addon);
        }
    }

    private function delete(array $addon): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        if ($plugin_file === '') {
            return;
        }

        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file);
        }

        $result = delete_plugins([$plugin_file]);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        wp_clean_plugins_cache(true);
        $this->persist_addon_status($addon);
    }

    private function persist_addon_status(array $addon): void
    {
        $slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';
        if ($slug === '') {
            return;
        }

        $statuses = get_option(self::INSTALLED_STATUS_OPTION, []);
        if (!is_array($statuses)) {
            $statuses = [];
        }

        $status = $this->get_addon_status($addon);
        $statuses[$slug] = [
            'installed' => (bool) $status['installed'],
            'active' => (bool) $status['active'],
            'installed_version' => (string) $status['installed_version'],
            'update_available' => (bool) $status['update_available'],
            'state' => (string) $status['state'],
            'plugin_file' => (string) $status['plugin_file'],
            'checked_at' => time(),
        ];

        update_option(self::INSTALLED_STATUS_OPTION, $statuses, false);
    }

    private function has_valid_license(): bool
    {
        return function_exists('mulopimfwc_is_license_valid') && mulopimfwc_is_license_valid();
    }
}

MULOPIMFWC_Addons_Page::instance();
