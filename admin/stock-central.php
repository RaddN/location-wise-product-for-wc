<?php

if (!defined('ABSPATH')) exit;

class mulopimfwc_Stock_Central
{
    private $product_table;
    private $view_mode = 'modern';

    public function __construct() {}

    public function register_screen_options()
    {
        $this->get_product_table($this->get_current_view_mode());

        add_screen_option('per_page', [
            'label' => __('Number of items per page:', 'multi-location-product-and-inventory-management'),
            'default' => 20,
            'option' => 'mulopimfwc_stock_central_per_page',
        ]);
    }

    private function get_current_view_mode()
    {
        $requested_mode = isset($_REQUEST['stock_central_view'])
            ? sanitize_text_field(wp_unslash($_REQUEST['stock_central_view']))
            : '';
        if (!in_array($requested_mode, ['modern', 'classic'], true)) {
            $requested_mode = '';
        }

        $user_id = get_current_user_id();
        if ($requested_mode === '' && $user_id) {
            $saved_mode = get_user_meta($user_id, 'mulopimfwc_stock_central_view_mode', true);
            if (in_array($saved_mode, ['modern', 'classic'], true)) {
                $requested_mode = $saved_mode;
            }
        }

        if ($requested_mode === '') {
            $requested_mode = 'modern';
        }

        if ($user_id && isset($_REQUEST['stock_central_view'])) {
            update_user_meta($user_id, 'mulopimfwc_stock_central_view_mode', $requested_mode);
        }

        $this->view_mode = $requested_mode;
        return $this->view_mode;
    }

    private function get_product_table($view_mode = null)
    {
        if ($view_mode === null) {
            $view_mode = $this->get_current_view_mode();
        }

        if (
            $this->product_table instanceof mulopimfwc_Product_Location_Table &&
            method_exists($this->product_table, 'get_view_mode') &&
            $this->product_table->get_view_mode() === $view_mode
        ) {
            return $this->product_table;
        }

        // Include required file for WP_List_Table
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }

        // Include our custom table class
        require_once plugin_dir_path(__FILE__) . '../includes/class-product-location-table.php';

        $this->product_table = new mulopimfwc_Product_Location_Table($view_mode);

        return $this->product_table;
    }

    public function location_stock_page_content()
    {
        $view_mode = $this->get_current_view_mode();
        $is_classic_mode = $view_mode === 'classic';
        $product_table = $this->get_product_table($view_mode);
        $can_manage_products = class_exists('MULOPIMFWC_Location_Managers')
            ? MULOPIMFWC_Location_Managers::user_has_capability('manage_products')
            : current_user_can('manage_woocommerce');
        $is_store_manage_stock_enabled = get_option('woocommerce_manage_stock', 'no') === 'yes';

        $modern_url = add_query_arg([
            'stock_central_view' => 'modern',
            'paged' => false,
        ]);
        $classic_url = add_query_arg([
            'stock_central_view' => 'classic',
            'paged' => false,
        ]);

        // Prepare the items to display in the table
        $product_table->prepare_items();

?>
        <div class="wrap mlsctock-cenral-main view-mode-<?php echo esc_attr($view_mode); ?>">
            <h1 style="display: none !important;"><?php echo esc_html__('Location Wise Products Stock Management', 'multi-location-product-and-inventory-management'); ?></h1>
            <div class="mlsctock-cenral-header">
                <div class="mlsctock-cenral-header-copy">
                    <h1><?php echo esc_html__('Location Wise Products Stock Management', 'multi-location-product-and-inventory-management'); ?></h1>
                    <p><?php echo esc_html__('Manage stock levels and prices for each product by location.', 'multi-location-product-and-inventory-management'); ?></p>
                </div>
                <div class="mulopimfwc-header-actions">
                    <?php if ($can_manage_products) : ?>
                        <div class="mulopimfwc-import-export-wrap">
                            <button type="button" class="button button-secondary mulopimfwc-stock-central-import-export-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="mulopimfwc-import-export-menu">
                                <span class="mulopimfwc-import-export-toggle-icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 0.72 0.72" xmlns="http://www.w3.org/2000/svg" fill="#fff"><path fill-rule="evenodd" d="M.594.558.592.561l-.09.09-.003.002-.004.002-.002.001L.49.657.487.658.483.659H.474L.47.658.467.657.464.655.461.653.458.651l-.09-.09a.03.03 0 0 1 .04-.045l.003.002.039.04V.27A.03.03 0 0 1 .476.24H.48a.03.03 0 0 1 .03.03v.288L.549.519a.03.03 0 0 1 .04-.002l.003.002a.03.03 0 0 1 .005.037zM.129.159l.09-.09.003-.003.003-.002.003-.002.003-.001L.234.06h.01l.004.001.002.001.003.001.002.001.002.002.002.002.001.001.09.09.002.003a.03.03 0 0 1 0 .037L.35.202.347.204a.03.03 0 0 1-.037 0L.307.202.27.162V.45a.03.03 0 0 1-.026.03H.236A.03.03 0 0 1 .21.454V.162L.171.201.168.203A.03.03 0 0 1 .126.162z"/></svg>
                                </span>
                                <span class="mulopimfwc-import-export-toggle-label"><?php echo esc_html__('Import Export', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="mulopimfwc-import-export-toggle-chevron" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" focusable="false" role="img">
                                        <path d="M5.5 7.5 10 12l4.5-4.5 1.2 1.2L10 14.4 4.3 8.7l1.2-1.2Z" fill="currentColor"></path>
                                    </svg>
                                </span>
                            </button>
                            <div id="mulopimfwc-import-export-menu" class="mulopimfwc-import-export-menu" hidden="hidden">
                                <div class="mulopimfwc-ie-menu-header">
                                    <div class="mulopimfwc-ie-menu-title">
                                        <span class="mulopimfwc-ie-menu-title-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" focusable="false" role="img">
                                                <path d="M4 4h7a1 1 0 0 1 1 1v5h-2V6H6v12h4v2H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Zm9 2h6a1 1 0 0 1 1 1v3h-2V8h-4v2h-2V7a1 1 0 0 1 1-1Zm0 6h2v4h3l-4 4-4-4h3v-4Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <strong><?php echo esc_html__('Stock Central Migration', 'multi-location-product-and-inventory-management'); ?></strong>
                                    </div>
                                    <span><?php echo esc_html__('Use a canonical CSV for full product and location data migration.', 'multi-location-product-and-inventory-management'); ?></span>
                                </div>

                                <div class="mulopimfwc-ie-tabs" role="tablist" aria-label="<?php echo esc_attr__('Import Export Tabs', 'multi-location-product-and-inventory-management'); ?>">
                                    <button
                                        type="button"
                                        class="mulopimfwc-ie-tab is-active"
                                        id="mulopimfwc-ie-tab-export"
                                        data-ie-tab="export"
                                        role="tab"
                                        aria-selected="true"
                                        aria-controls="mulopimfwc-ie-panel-export">
                                        <span class="mulopimfwc-ie-tab-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                <path d="M4 3h5a1 1 0 0 1 1 1v3H8V5H5v10h3v2H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 4h2v4h3l-4 4-4-4h3V7Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html__('Export', 'multi-location-product-and-inventory-management'); ?></span>
                                    </button>
                                    <button
                                        type="button"
                                        class="mulopimfwc-ie-tab"
                                        id="mulopimfwc-ie-tab-import"
                                        data-ie-tab="import"
                                        role="tab"
                                        aria-selected="false"
                                        aria-controls="mulopimfwc-ie-panel-import">
                                        <span class="mulopimfwc-ie-tab-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                <path d="M9 3h2v4h3l-4 4-4-4h3V3Zm-5 9h2v3h8v-3h2v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html__('Import', 'multi-location-product-and-inventory-management'); ?></span>
                                    </button>
                                </div>

                                <div id="mulopimfwc-ie-panel-export" class="mulopimfwc-ie-panel is-active" data-ie-panel="export" role="tabpanel" aria-labelledby="mulopimfwc-ie-tab-export">
                                    <p class="mulopimfwc-ie-panel-copy">
                                        <?php echo esc_html__('Export a full, versioned CSV including products, variations, terms, locations, relationships, and location inventory.', 'multi-location-product-and-inventory-management'); ?>
                                    </p>
                                    <ul class="mulopimfwc-ie-checklist">
                                        <li>
                                            <span class="mulopimfwc-ie-check-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                    <path d="m7.7 13.3-3-3 1.4-1.4 1.6 1.6 4.2-4.2 1.4 1.4-5.6 5.6Z" fill="currentColor"></path>
                                                </svg>
                                            </span>
                                            <span><?php echo esc_html__('Schema versioned canonical format', 'multi-location-product-and-inventory-management'); ?></span>
                                        </li>
                                        <li>
                                            <span class="mulopimfwc-ie-check-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                    <path d="m7.7 13.3-3-3 1.4-1.4 1.6 1.6 4.2-4.2 1.4 1.4-5.6 5.6Z" fill="currentColor"></path>
                                                </svg>
                                            </span>
                                            <span><?php echo esc_html__('Ready for cross-site import with mapping', 'multi-location-product-and-inventory-management'); ?></span>
                                        </li>
                                        <li>
                                            <span class="mulopimfwc-ie-check-icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                    <path d="m7.7 13.3-3-3 1.4-1.4 1.6 1.6 4.2-4.2 1.4 1.4-5.6 5.6Z" fill="currentColor"></path>
                                                </svg>
                                            </span>
                                            <span><?php echo esc_html__('Optional custom meta whitelist', 'multi-location-product-and-inventory-management'); ?></span>
                                        </li>
                                    </ul>
                                    <label for="mulopimfwc-stock-central-custom-meta" class="mulopimfwc-ie-field-label">
                                        <span class="mulopimfwc-ie-field-label-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                <path d="M3 5.8C3 4.3 4.3 3 5.8 3h8.4C15.7 3 17 4.3 17 5.8v8.4c0 1.5-1.3 2.8-2.8 2.8H5.8C4.3 17 3 15.7 3 14.2V5.8Zm3.4 1.8v4.8h2.2V7.6H6.4Zm5 0v4.8h2.2V7.6h-2.2Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html__('Custom Meta Whitelist (optional)', 'multi-location-product-and-inventory-management'); ?></span>
                                    </label>
                                    <input type="text" id="mulopimfwc-stock-central-custom-meta" placeholder="_yoast_wpseo_title,_yoast_wpseo_metadesc" />
                                    <button type="button" class="button button-primary mulopimfwc-stock-central-export">
                                        <span class="mulopimfwc-ie-action-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                <path d="M4 3h5a1 1 0 0 1 1 1v3H8V5H5v10h3v2H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 4h2v4h3l-4 4-4-4h3V7Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html__('Export Full CSV', 'multi-location-product-and-inventory-management'); ?></span>
                                    </button>
                                </div>

                                <div id="mulopimfwc-ie-panel-import" class="mulopimfwc-ie-panel" data-ie-panel="import" role="tabpanel" aria-labelledby="mulopimfwc-ie-tab-import" hidden="hidden">
                                    <p class="mulopimfwc-ie-panel-copy">
                                        <?php echo esc_html__('Import runs dry-run validation first, then applies changes after confirmation.', 'multi-location-product-and-inventory-management'); ?>
                                    </p>
                                    <div class="mulopimfwc-stock-central-dropzone" role="button" tabindex="0" aria-label="<?php echo esc_attr__('Drag and drop CSV file here or press Enter to browse', 'multi-location-product-and-inventory-management'); ?>">
                                        <span class="mulopimfwc-stock-central-dropzone-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" focusable="false" role="img">
                                                <path d="M12 2a5 5 0 0 1 5 5v1.2a4.8 4.8 0 0 1 1.2 9.4H17v-2h1.2a2.8 2.8 0 1 0 0-5.6H16V7a3 3 0 1 0-6 0v3H7.8a2.8 2.8 0 1 0 0 5.6H9v2H7.8a4.8 4.8 0 1 1 1.2-9.4V7a5 5 0 0 1 5-5Zm-1 12V9h2v5h3l-4 4-4-4h3Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <strong><?php echo esc_html__('Drag & drop CSV/ZIP file here', 'multi-location-product-and-inventory-management'); ?></strong>
                                        <span><?php echo esc_html__('or click to browse from your computer', 'multi-location-product-and-inventory-management'); ?></span>
                                        <em class="mulopimfwc-stock-central-dropzone-file" data-empty-label="<?php echo esc_attr__('No file selected', 'multi-location-product-and-inventory-management'); ?>"><?php echo esc_html__('No file selected', 'multi-location-product-and-inventory-management'); ?></em>
                                    </div>
                                    <label for="mulopimfwc-stock-central-import-mode" class="mulopimfwc-ie-field-label">
                                        <span class="mulopimfwc-ie-field-label-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                <path d="M4 4h12v2H4V4Zm0 5h12v2H4V9Zm0 5h8v2H4v-2Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html__('Import Mode', 'multi-location-product-and-inventory-management'); ?></span>
                                    </label>
                                    <select id="mulopimfwc-stock-central-import-mode">
                                        <option value="create_update"><?php echo esc_html__('Create + Update', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="update_only"><?php echo esc_html__('Update Only', 'multi-location-product-and-inventory-management'); ?></option>
                                    </select>
                                    <label class="mulopimfwc-import-export-check">
                                        <input type="checkbox" id="mulopimfwc-stock-central-auto-create-terms" checked="checked" />
                                        <span><?php echo esc_html__('Auto-create missing terms/locations', 'multi-location-product-and-inventory-management'); ?></span>
                                    </label>
                                    <label class="mulopimfwc-import-export-check">
                                        <input type="checkbox" id="mulopimfwc-stock-central-sync-location-profile" checked="checked" />
                                        <span><?php echo esc_html__('Sync full location profile', 'multi-location-product-and-inventory-management'); ?></span>
                                    </label>
                                    <label class="mulopimfwc-import-export-check">
                                        <input type="checkbox" id="mulopimfwc-stock-central-import-media" />
                                        <span><?php echo esc_html__('Sideload media from URL refs', 'multi-location-product-and-inventory-management'); ?></span>
                                    </label>
                                    <button type="button" class="button button-primary mulopimfwc-stock-central-import-btn">
                                        <span class="mulopimfwc-ie-action-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" focusable="false" role="img">
                                                <path d="M9 3h2v4h3l-4 4-4-4h3V3Zm-5 9h2v3h8v-3h2v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4Z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo esc_html__('Select File & Import', 'multi-location-product-and-inventory-management'); ?></span>
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="mulopimfwc-stock-central-import-file" accept=".csv,.zip" hidden="hidden" />
                        </div>
                    <?php endif; ?>
                    <div class="mulopimfwc-view-switch-wrap">
                        <div class="mulopimfwc-view-switch <?php echo $is_classic_mode ? 'is-classic' : 'is-modern'; ?>" role="group" aria-label="<?php echo esc_attr__('Stock Central View Mode', 'multi-location-product-and-inventory-management'); ?>">
                            <a href="<?php echo esc_url($modern_url); ?>" class="mulopimfwc-view-switch-option <?php echo $is_classic_mode ? '' : 'is-active'; ?>">
                                <?php echo esc_html__('Modern', 'multi-location-product-and-inventory-management'); ?>
                            </a>
                            <a href="<?php echo esc_url($classic_url); ?>" class="mulopimfwc-view-switch-option <?php echo $is_classic_mode ? 'is-active' : ''; ?>">
                                <?php echo esc_html__('Classic', 'multi-location-product-and-inventory-management'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div id="mulopimfwc-stock-central-import-export-status" class="mulopimfwc-stock-central-import-export-status" aria-live="polite">
                <span class="mulopimfwc-stock-central-import-export-status-message"></span>
                <span class="mulopimfwc-stock-central-active-job-meta" hidden="hidden"></span>
                <div class="mulopimfwc-stock-central-status-actions">
                    <button type="button" class="button-link mulopimfwc-stock-central-view-log" hidden="hidden">
                        <?php echo esc_html__('View Log', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                    <button type="button" class="button-link mulopimfwc-stock-central-pause-job" hidden="hidden">
                        <?php echo esc_html__('Pause', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                    <button type="button" class="button-link mulopimfwc-stock-central-resume-job" hidden="hidden">
                        <?php echo esc_html__('Resume', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                    <button type="button" class="button-link mulopimfwc-stock-central-cancel-job" hidden="hidden">
                        <?php echo esc_html__('Cancel', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                </div>
            </div>
            <div id="mulopimfwc-stock-central-import-export-log-panel" class="mulopimfwc-stock-central-import-export-log-panel" hidden="hidden">
                <div class="mulopimfwc-stock-central-import-export-log-header">
                    <strong><?php echo esc_html__('Import Export Log', 'multi-location-product-and-inventory-management'); ?></strong>
                    <div class="mulopimfwc-stock-central-import-export-log-actions">
                        <button type="button" class="button-link mulopimfwc-stock-central-log-clear">
                            <?php echo esc_html__('Clear', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                        <button type="button" class="button-link mulopimfwc-stock-central-log-close">
                            <?php echo esc_html__('Close', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                    </div>
                </div>
                <div id="mulopimfwc-stock-central-import-export-log-list" class="mulopimfwc-stock-central-import-export-log-list"></div>
            </div>

            <form method="get" id="stock-central-form">
                <input type="hidden" name="page" value="<?php echo isset($_REQUEST['page']) ? esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))) : 'location-stock-management'; ?>" />
                <input type="hidden" name="stock_central_view" value="<?php echo esc_attr($view_mode); ?>" />
                <?php if ($is_classic_mode && $can_manage_products) : ?>
                    <div class="mulopimfwc-classic-toolbar">
                        <div class="mulopimfwc-classic-toolbar-left">
                            <strong class="mulopimfwc-classic-toolbar-title"><?php echo esc_html__('Classic Row Editor', 'multi-location-product-and-inventory-management'); ?></strong>
                            <span class="mulopimfwc-classic-toolbar-hint"><?php echo esc_html__('Edit product rows inline, then save or reset all changes.', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="mulopimfwc-classic-toolbar-right">
                            <button type="button" class="button button-primary" id="mulopimfwc-classic-save-all">
                                <?php echo esc_html__('Save All Product Changes', 'multi-location-product-and-inventory-management'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="mulopimfwc-classic-reset-all" disabled="disabled">
                                <?php echo esc_html__('Reset All Changes', 'multi-location-product-and-inventory-management'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="mulopimfwc-classic-retry-failed" style="display:none;" disabled="disabled">
                                <?php echo esc_html__('Retry Failed Rows', 'multi-location-product-and-inventory-management'); ?>
                            </button>
                            <span id="mulopimfwc-classic-save-progress" class="description" aria-live="polite"></span>
                            <span class="spinner" id="mulopimfwc-classic-save-spinner" aria-hidden="true"></span>
                            <span id="mulopimfwc-classic-dirty-count" class="description">
                                <?php echo esc_html__('No unsaved product rows.', 'multi-location-product-and-inventory-management'); ?>
                            </span>
                        </div>
                    </div>
                    <div id="mulopimfwc-classic-save-failures" class="mulopimfwc-classic-save-failures" aria-live="polite" hidden="hidden"></div>
                <?php endif; ?>
                <?php $product_table->search_box(__('Search Products', 'multi-location-product-and-inventory-management'), 'search_products'); ?>
                <?php $product_table->display(); ?>
            </form>
        </div>

        <style>
            .mlsctock-cenral-main {
                border: 2px solid #d1d1d4;
                border-radius: 8px;
                background-color: #f9fafb;
                margin: 20px 20px 0px 0px;
            }

            .mlsctock-cenral-header {
                background-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 25px 25px;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                box-sizing: border-box;
                width: 100%;
            }

            .mlsctock-cenral-header-copy {
                flex: 1 1 auto;
                min-width: 240px;
            }

            .mlsctock-cenral-header h1 {
                color: #ffffff;
                font-weight: 700;
                font-size: 30px;
                padding: 0;
                margin: 0;
            }

            .mlsctock-cenral-header p {
                color: #f3e8ff;
                font-size: 18px;
                margin: 6px 0px 0px;
            }

            .mulopimfwc-view-switch-wrap {
                flex: 0 0 auto;
                padding-top: 0;
            }

            .mulopimfwc-header-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-left: auto;
            }

            .mulopimfwc-import-export-wrap {
                position: relative;
                padding-top: 0;
            }

            .mulopimfwc-stock-central-import-export-toggle {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                gap: 7px;
                min-height: 40px !important;
                padding: 0 14px !important;
                border-radius: 999px !important;
                border-color: rgba(255, 255, 255, 0.44) !important;
                color: #fff !important;
                background: rgb(15 23 42 / 6%) !important;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18), 0 1px 2px rgba(15, 23, 42, 0.15);
                font-size: 13px !important;
                font-weight: 600 !important;
                line-height: 1 !important;
                text-shadow: 0 1px 0 rgba(15, 23, 42, 0.25);
                transition: background-color 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
            }

            .mulopimfwc-stock-central-import-export-toggle:hover {
                background: rgba(15, 23, 42, 0.44) !important;
                border-color: rgba(255, 255, 255, 0.62) !important;
                transform: translateY(-1px);
            }

            .mulopimfwc-stock-central-import-export-toggle:focus-visible {
                outline: none;
                box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.42), 0 0 0 4px rgba(37, 99, 235, 0.5);
            }

            .mulopimfwc-stock-central-import-export-toggle.is-open {
                background: rgba(15, 23, 42, 0.5) !important;
                border-color: rgba(255, 255, 255, 0.72) !important;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 3px 8px rgba(15, 23, 42, 0.22);
            }

            .mulopimfwc-import-export-toggle-label {
                letter-spacing: 0.01em;
            }

            .mulopimfwc-import-export-toggle-icon svg,
            .mulopimfwc-import-export-toggle-chevron svg {
                width: 14px;
                height: 14px;
                display: block;
            }

            .mulopimfwc-import-export-toggle-chevron {
                transition: transform 0.16s ease;
            }

            .mulopimfwc-stock-central-import-export-toggle.is-open .mulopimfwc-import-export-toggle-chevron {
                transform: rotate(180deg);
            }

            .mulopimfwc-import-export-menu {
                position: absolute;
                right: 0;
                top: calc(100% + 8px);
                min-width: 410px;
                background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
                border: 1px solid #dbe3ee;
                border-radius: 14px;
                box-shadow: 0 16px 34px rgba(15, 23, 42, 0.2);
                padding: 16px;
                z-index: 100;
                display: flex;
                flex-direction: column;
                gap: 12px;
                max-height: 70vh;
                overflow: auto;
            }

            .mulopimfwc-import-export-menu[hidden] {
                display: none !important;
            }

            .mlsctock-cenral-header .mulopimfwc-import-export-menu p {
                margin: 0;
                font-size: 12px;
                line-height: 1.4;
                color: #4b5563;
            }

            .mlsctock-cenral-header .mulopimfwc-import-export-menu .mulopimfwc-ie-panel-copy {
                color: #4b5563 !important;
            }

            .mulopimfwc-ie-menu-header {
                display: flex;
                flex-direction: column;
                gap: 5px;
                border: 1px solid #e5e7eb;
                background: #f8fafc;
                border-radius: 10px;
                padding: 10px 11px;
            }

            .mulopimfwc-ie-menu-title {
                display: flex;
                align-items: center;
                gap: 7px;
            }

            .mulopimfwc-ie-menu-title-icon {
                width: 22px;
                height: 22px;
                border-radius: 7px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #dbeafe;
                color: #2563eb;
                flex: 0 0 auto;
            }

            .mulopimfwc-ie-menu-title-icon svg {
                width: 14px;
                height: 14px;
                display: block;
            }

            .mulopimfwc-ie-menu-header strong {
                font-size: 13px;
                color: #111827;
            }

            .mulopimfwc-ie-menu-header>span {
                font-size: 12px;
                color: #6b7280;
                line-height: 1.35;
            }

            .mulopimfwc-ie-tabs {
                display: grid;
                grid-template-columns: 1fr 1fr;
                background: #f3f4f6;
                border: 1px solid #e5e7eb;
                border-radius: 999px;
                padding: 3px;
                gap: 4px;
            }

            .mulopimfwc-ie-tab {
                border: 0;
                background: transparent;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                color: #4b5563;
                line-height: 1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                min-height: 32px;
                cursor: pointer;
                transition: all 0.16s ease;
            }

            .mulopimfwc-ie-tab-icon svg {
                width: 14px;
                height: 14px;
                display: block;
            }

            .mulopimfwc-ie-tab.is-active {
                background: #ffffff;
                color: #111827;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
            }

            .mulopimfwc-ie-panel {
                display: flex;
                flex-direction: column;
                gap: 9px;
            }

            .mulopimfwc-ie-panel[hidden] {
                display: none !important;
            }

            .mulopimfwc-ie-panel-copy {
                margin: 0;
                color: #4b5563;
                font-size: 12px;
                line-height: 1.4;
            }

            .mulopimfwc-ie-checklist {
                margin: 0;
                padding: 0;
                color: #374151;
                font-size: 12px;
                line-height: 1.5;
                list-style: none;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .mulopimfwc-ie-checklist li {
                display: flex;
                align-items: flex-start;
                gap: 7px;
            }

            .mulopimfwc-ie-check-icon {
                width: 16px;
                height: 16px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #dcfce7;
                color: #16a34a;
                margin-top: 1px;
                flex: 0 0 auto;
            }

            .mulopimfwc-ie-check-icon svg {
                width: 11px;
                height: 11px;
                display: block;
            }

            .mulopimfwc-ie-field-label {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .mulopimfwc-ie-field-label-icon svg {
                width: 14px;
                height: 14px;
                display: block;
                color: #2563eb;
            }

            .mulopimfwc-ie-panel .button-primary {
                width: 100%;
                justify-content: center;
                display: inline-flex !important;
                align-items: center;
                gap: 7px;
                border-radius: 8px;
            }

            .mulopimfwc-stock-central-dropzone {
                border: 1px dashed #93c5fd;
                background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
                border-radius: 10px;
                padding: 14px 12px;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 4px;
                color: #1f2937;
                cursor: pointer;
                transition: border-color 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
            }

            .mulopimfwc-stock-central-dropzone:focus {
                outline: none;
                border-color: #2563eb;
                box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
            }

            .mulopimfwc-stock-central-dropzone.is-dragover {
                border-color: #2563eb;
                background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
                box-shadow: 0 6px 16px rgba(37, 99, 235, 0.18);
            }

            .mulopimfwc-stock-central-dropzone-icon svg {
                width: 24px;
                height: 24px;
                display: block;
                color: #2563eb;
            }

            .mulopimfwc-stock-central-dropzone strong {
                font-size: 12px;
                color: #111827;
            }

            .mulopimfwc-stock-central-dropzone span {
                font-size: 11px;
                color: #6b7280;
            }

            .mulopimfwc-stock-central-dropzone-file {
                font-style: normal;
                font-size: 11px;
                color: #1d4ed8;
                margin-top: 4px;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mulopimfwc-import-export-menu label {
                font-size: 12px;
                font-weight: 600;
                color: #374151;
            }

            .mulopimfwc-import-export-menu input[type="text"],
            .mulopimfwc-import-export-menu select {
                width: 100%;
                max-width: 100%;
            }

            .mulopimfwc-import-export-check {
                font-weight: 500 !important;
                font-size: 12px !important;
                display: flex;
                align-items: center;
                gap: 7px;
                color: #4b5563 !important;
            }

            .mulopimfwc-import-export-check input[type="checkbox"] {
                margin: 0 !important;
            }

            .mulopimfwc-ie-option-icon svg {
                width: 14px;
                height: 14px;
                display: block;
                color: #2563eb;
            }

            .mulopimfwc-ie-action-icon svg {
                width: 14px;
                height: 14px;
                display: block;
            }

            .mulopimfwc-stock-central-import-export-status {
                margin: 10px 25px 0;
                padding: 8px 10px;
                border-radius: 6px;
                background: #eef2ff;
                border: 1px solid #c7d2fe;
                color: #3730a3;
                display: none;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                font-size: 12px;
            }

            .mulopimfwc-stock-central-import-export-status-message {
                min-width: 0;
                flex: 1 1 auto;
            }

            .mulopimfwc-stock-central-active-job-meta {
                flex: 0 0 auto;
                color: #4338ca;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.01em;
                white-space: nowrap;
            }

            .mulopimfwc-stock-central-active-job-meta[hidden] {
                display: none !important;
            }

            .mulopimfwc-stock-central-status-actions {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }

            .mulopimfwc-stock-central-view-log {
                font-size: 12px;
                font-weight: 600;
                color: #1e40af;
                text-decoration: none;
            }

            .mulopimfwc-stock-central-view-log[hidden] {
                display: none !important;
            }

            .mulopimfwc-stock-central-pause-job,
            .mulopimfwc-stock-central-resume-job,
            .mulopimfwc-stock-central-cancel-job {
                font-size: 12px;
                font-weight: 600;
                text-decoration: none;
            }

            .mulopimfwc-stock-central-pause-job[hidden],
            .mulopimfwc-stock-central-resume-job[hidden],
            .mulopimfwc-stock-central-cancel-job[hidden] {
                display: none !important;
            }

            .mulopimfwc-stock-central-cancel-job {
                color: #b91c1c;
            }

            .mulopimfwc-stock-central-import-export-status.is-error {
                background: #fef2f2;
                border-color: #fecaca;
                color: #991b1b;
            }

            .mulopimfwc-stock-central-import-export-status.is-error .mulopimfwc-stock-central-view-log {
                color: #7f1d1d;
            }

            .mulopimfwc-stock-central-import-export-status.is-error .mulopimfwc-stock-central-active-job-meta,
            .mulopimfwc-stock-central-import-export-status.is-error .mulopimfwc-stock-central-pause-job,
            .mulopimfwc-stock-central-import-export-status.is-error .mulopimfwc-stock-central-resume-job,
            .mulopimfwc-stock-central-import-export-status.is-error .mulopimfwc-stock-central-cancel-job {
                color: #7f1d1d;
            }

            .mulopimfwc-stock-central-import-export-status.is-success {
                background: #ecfdf5;
                border-color: #bbf7d0;
                color: #065f46;
            }

            .mulopimfwc-stock-central-import-export-status.is-success .mulopimfwc-stock-central-view-log {
                color: #065f46;
            }

            .mulopimfwc-stock-central-import-export-status.is-success .mulopimfwc-stock-central-active-job-meta,
            .mulopimfwc-stock-central-import-export-status.is-success .mulopimfwc-stock-central-pause-job,
            .mulopimfwc-stock-central-import-export-status.is-success .mulopimfwc-stock-central-resume-job,
            .mulopimfwc-stock-central-import-export-status.is-success .mulopimfwc-stock-central-cancel-job {
                color: #065f46;
            }

            .mulopimfwc-stock-central-import-export-log-panel {
                margin: 8px 25px 0;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                background: #ffffff;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
                overflow: hidden;
            }

            .mulopimfwc-stock-central-import-export-log-panel[hidden] {
                display: none !important;
            }

            .mulopimfwc-stock-central-import-export-log-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                padding: 8px 10px;
                border-bottom: 1px solid #e5e7eb;
                background: #f8fafc;
            }

            .mulopimfwc-stock-central-import-export-log-header strong {
                font-size: 12px;
                color: #111827;
            }

            .mulopimfwc-stock-central-import-export-log-actions {
                display: inline-flex;
                align-items: center;
                gap: 12px;
            }

            .mulopimfwc-stock-central-import-export-log-actions .button-link {
                font-size: 12px;
                font-weight: 600;
            }

            .mulopimfwc-stock-central-import-export-log-list {
                max-height: 220px;
                overflow: auto;
                padding: 8px 10px;
                display: flex;
                flex-direction: column;
                gap: 6px;
                font-size: 12px;
                color: #374151;
            }

            .mulopimfwc-stock-central-log-entry {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                line-height: 1.35;
            }

            .mulopimfwc-stock-central-log-time {
                color: #6b7280;
                flex: 0 0 auto;
                font-variant-numeric: tabular-nums;
            }

            .mulopimfwc-stock-central-log-text {
                flex: 1 1 auto;
                min-width: 0;
                word-break: break-word;
            }

            .mulopimfwc-stock-central-log-entry.is-error .mulopimfwc-stock-central-log-text {
                color: #991b1b;
            }

            .mulopimfwc-stock-central-log-entry.is-success .mulopimfwc-stock-central-log-text {
                color: #065f46;
            }

            .mulopimfwc-stock-central-log-empty {
                color: #6b7280;
                font-style: italic;
            }

            .mulopimfwc-view-switch {
                position: relative;
                display: inline-grid;
                grid-template-columns: 1fr 1fr;
                min-width: 200px;
                padding: 3px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.16);
                border: 1px solid rgba(255, 255, 255, 0.32);
                backdrop-filter: blur(2px);
                transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
            }

            .mulopimfwc-view-switch:hover {
                background: rgba(255, 255, 255, 0.24);
                border-color: rgba(255, 255, 255, 0.55);
                box-shadow: 0 6px 18px rgba(15, 23, 42, 0.2);
                transform: translateY(-1px);
            }

            .mulopimfwc-view-switch::after {
                content: "";
                position: absolute;
                top: 3px;
                bottom: 3px;
                left: 3px;
                width: calc(50% - 3px);
                border-radius: 999px;
                background: #ffffff;
                box-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
                transition: transform 0.18s ease;
                pointer-events: none;
            }

            .mulopimfwc-view-switch.is-classic::after {
                transform: translateX(100%);
            }

            .mulopimfwc-view-switch-option {
                position: relative;
                z-index: 1;
                text-align: center;
                padding: 8px 14px;
                font-size: 13px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.85);
                text-decoration: none;
                border-radius: 999px;
                transition: color 0.18s ease, background 0.18s ease;
            }

            .mulopimfwc-view-switch-option:hover {
                color: #ffffff;
            }

            .mulopimfwc-view-switch-option:not(.is-active):hover {
                background: rgba(255, 255, 255, 0.14);
            }

            .mulopimfwc-view-switch-option:focus {
                outline: none;
                box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.55);
            }

            .mulopimfwc-view-switch-option.is-active {
                color: #111827;
            }

            .mulopimfwc-classic-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 16px;
                padding: 12px 14px;
                border: 1px solid #d6d9df;
                background: #f8f9fb;
                border-radius: 6px;
                position: sticky;
                top: 32px;
                z-index: 20;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            }

            .mulopimfwc-classic-toolbar-left {
                display: flex;
                flex-direction: column;
                gap: 2px;
                min-width: 220px;
            }

            .mulopimfwc-classic-toolbar-title {
                font-size: 13px;
                font-weight: 700;
                color: #111827;
                line-height: 1.3;
            }

            .mulopimfwc-classic-toolbar-hint {
                font-size: 12px;
                color: #6b7280;
                line-height: 1.35;
            }

            .mulopimfwc-classic-toolbar-right {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-end;
                flex-direction: row-reverse;
            }

            #mulopimfwc-classic-save-progress {
                color: #1f2937;
                font-weight: 500;
                min-height: 18px;
            }

            #mulopimfwc-classic-save-spinner {
                float: none;
                margin: 0;
                visibility: hidden;
            }

            #mulopimfwc-classic-save-spinner.is-active {
                visibility: visible;
            }

            .mulopimfwc-classic-save-failures {
                margin: -6px 0 14px;
                padding: 10px 12px;
                border: 1px solid #f5d18a;
                background: #fffbeb;
                border-radius: 6px;
                color: #92400e;
                font-size: 12px;
                line-height: 1.5;
            }

            .mulopimfwc-classic-save-failures strong {
                color: #7c2d12;
            }

            .mulopimfwc-classic-save-failure-list {
                margin: 6px 0 0 18px;
                max-height: 168px;
                overflow: auto;
            }

            .mulopimfwc-classic-save-failure-product {
                font-weight: 600;
                color: #7c2d12;
            }

            @media (max-width: 782px) {
                .mlsctock-cenral-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .mulopimfwc-view-switch-wrap {
                    width: 100%;
                    display: flex;
                    justify-content: flex-end;
                }

                .mulopimfwc-header-actions {
                    width: 100%;
                    margin-left: 0;
                    justify-content: space-between;
                    align-items: center;
                }

                .mulopimfwc-import-export-wrap {
                    padding-top: 0;
                }

                .mulopimfwc-import-export-menu {
                    left: 0;
                    right: auto;
                    min-width: min(360px, calc(100vw - 64px));
                }

                .mulopimfwc-stock-central-import-export-status {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .mulopimfwc-stock-central-status-actions {
                    width: 100%;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .mulopimfwc-classic-toolbar {
                    top: 46px;
                    flex-direction: column;
                    align-items: flex-start;
                }

                .mulopimfwc-classic-toolbar-right {
                    width: 100%;
                    justify-content: flex-start;
                }
            }

            .mlsctock-cenral-main form {
                padding: 20px 25px;
                background-color: #ffffff;
            }

            .mlsctock-cenral-main form table {
                border-color: #e5e7eb !important;
            }

            .mlsctock-cenral-main form table thead {
                background-color: #e5e7eb;
            }

            .mlsctock-cenral-main form .widefat thead td,
            .mlsctock-cenral-main form .widefat thead th {
                border-bottom: 1px solid #e5e7eb !important;
            }

            .mlsctock-cenral-main form .alternate,
            .mlsctock-cenral-main form .striped>tbody>:nth-child(odd),
            .mlsctock-cenral-main form ul.striped>:nth-child(odd) {
                background-color: #f9fafb;
            }

            .mlsctock-cenral-main form .widefat td,
            .mlsctock-cenral-main form .widefat th {
                padding: 10px 10px;
            }

            .mlsctock-cenral-main form .widefat td,
            .mlsctock-cenral-main form th.check-column {
                padding: 20px 10px;
            }

            .mlsctock-cenral-main form th#image {
                width: 5%;
            }

            .mlsctock-cenral-main form .product-thumbnail {
                border-radius: 6px;
            }

            .mlsctock-cenral-main form .widefat thead th {
                font-size: 16px;
                font-weight: 500;
            }

            .mlsctock-cenral-main .mulopimfwc-product-id {
                color: #6b7280;
                font-weight: 400;
            }

            .mlsctock-cenral-main .mulopimfwc-product-status {
                display: inline-flex;
                align-items: center;
                margin-left: 8px;
                padding: 2px 8px;
                border-radius: 999px;
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                color: #334155;
                font-size: 11px;
                font-weight: 600;
                text-transform: capitalize;
                line-height: 1.4;
            }

            .mlsctock-cenral-main .mulopimfwc-product-status-draft,
            .mlsctock-cenral-main .mulopimfwc-product-status-auto-draft {
                background: #fffbeb;
                border-color: #fde68a;
                color: #92400e;
            }

            .mlsctock-cenral-main .mulopimfwc-product-status-pending {
                background: #eff6ff;
                border-color: #bfdbfe;
                color: #1d4ed8;
            }

            .mlsctock-cenral-main .mulopimfwc-product-status-private {
                background: #f3f4f6;
                border-color: #d1d5db;
                color: #374151;
            }

            .mlsctock-cenral-main .mulopimfwc-product-status-future {
                background: #ecfdf5;
                border-color: #a7f3d0;
                color: #065f46;
            }

            .mlsctock-cenral-main form .deactivate-location {
                background-color: #fef2f2;
                color: #dc2626;
                border-color: #fecaca;
            }

            .mlsctock-cenral-main form .activate-location {
                background-color: #f0fdf4;
                color: #15803d;
                border-color: #bbf7d0;
            }

            .mlsctock-cenral-main form .add-location,
            a.button.button-small.manage-product-location {
                background: #2563eb ! important;
                border-color: #2563eb !important;
                color: #ffffff;
                padding: 5px !important;
                font-weight: 500;
                font-size: 13px !important;
                width: 100%;
                text-align: center;
            }

            .mlsctock-cenral-main form .gross-profit-container,
            .mlsctock-cenral-main form .purchase-price-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .mlsctock-cenral-main form .gross-profit-container .amount bdi {
                color: #15803d;
                font-weight: 500;
                font-size: 14px;
                background-color: #f0fdf4;
                padding: 2px;
                margin-right: 4px;
            }

            .location-actions {
                margin-bottom: 0px;
            }

            /* Accordion Styles */
            .variation-stock-item.accordion-item,
            .variation-price-item.accordion-item,
            .variation-gross-profit-item.accordion-item {
                margin-bottom: 10px;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                overflow: hidden;
                background-color: #ffffff;
            }

            .variation-stock-item .accordion-header,
            .variation-price-item .accordion-header,
            .variation-gross-profit-item .accordion-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 12px;
                background-color: #f9fafb;
                cursor: pointer;
                user-select: none;
                transition: background-color 0.2s ease;
                border-bottom: 1px solid #e5e7eb;
            }

            .variation-stock-item .accordion-header:hover,
            .variation-price-item .accordion-header:hover,
            .variation-gross-profit-item .accordion-header:hover {
                background-color: #f3f4f6;
            }

            .variation-stock-item.accordion-expanded .accordion-header,
            .variation-price-item.accordion-expanded .accordion-header,
            .variation-gross-profit-item.accordion-expanded .accordion-header {
                background-color: #e5e7eb;
            }

            .variation-stock-item .accordion-header strong,
            .variation-price-item .accordion-header strong,
            .variation-gross-profit-item .accordion-header strong {
                font-weight: 600;
                color: #374151;
                flex: 1;
            }

            .accordion-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                font-size: 18px;
                font-weight: bold;
                color: #6b7280;
                border-radius: 4px;
                background-color: #ffffff;
                transition: transform 0.2s ease;
            }

            .variation-stock-item .accordion-content,
            .variation-price-item .accordion-content,
            .variation-gross-profit-item .accordion-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease, padding 0.3s ease;
                padding: 0 12px;
            }

            .variation-stock-item .accordion-content.accordion-open,
            .variation-price-item .accordion-content.accordion-open,
            .variation-gross-profit-item .accordion-content.accordion-open {
                max-height: 2000px;
                padding: 10px 12px;
            }

            .variation-stock-item .accordion-content .location-stock-item,
            .variation-price-item .accordion-content .location-price-item,
            .variation-gross-profit-item .accordion-content .location-gross-profit-item {
                margin-bottom: 8px;
            }

            .variation-stock-item .accordion-content .location-stock-item:last-child,
            .variation-price-item .accordion-content .location-price-item:last-child,
            .variation-gross-profit-item .accordion-content .location-gross-profit-item:last-child {
                margin-bottom: 0;
            }

            /* Filter styles */
            .mlsctock-cenral-main form .alignleft.actions.filters-section {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
                margin-bottom: 15px;
            }

            .mlsctock-cenral-main form .alignleft.actions select {
                min-width: 150px;
                padding: 5px;
            }

            .mlsctock-cenral-main form .alignleft.actions #filter-submit {
                margin-left: 0;
            }

            /* Bulk actions section - positioned near bulk actions dropdown */
            .mlsctock-cenral-main form .alignleft.actions.bulk-actions-section {
                display: flex;
                align-items: center;
                gap: 5px;
                margin-left: 10px;
                margin-bottom: 15px;
                padding: 8px 12px;
                background-color: #f0f9ff;
                border: 1px solid #bae6fd;
                border-radius: 4px;
                margin-top: -45px;
            }

            .mlsctock-cenral-main form .alignleft.actions.bulk-actions-section label {
                font-weight: 500;
                white-space: nowrap;
            }

            .view-mode-classic thead th {
                font-size: 13px !important;
            }

            /* Bulk actions notice */
            .mlsctock-cenral-main .notice {
                margin: 15px 0;
            }

            .mlsctock-cenral-main.view-mode-classic {
                border-color: #cfd6dc;
                background-color: #f3f4f6;
            }

            .mlsctock-cenral-main.view-mode-classic .mlsctock-cenral-header {
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            }

            .mlsctock-cenral-main.view-mode-classic form {
                background: #ffffff;
            }

            .view-mode-classic .check-column {
                width: 2% !important;
            }

            .view-mode-classic .column-actions {
                width: 5%;
            }

            .view-mode-classic .column-title {
                width: 10%;
            }

            .view-mode-classic .column-classic_manage_stock,
            .view-mode-classic .column-classic_purchase {
                width: 12%;
            }

            .mlsctock-cenral-main .bulkactions {
                display: flex;
            }

            .view-mode-classic .column-classic_default {
                width: 14%;
            }

            .view-mode-classic .column-classic_location_wise {
                width: 27%;
            }

            .mulopimfwc-classic-editor {
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            .mulopimfwc-classic-section {
                border: 1px solid #dde2e8;
                border-radius: 6px;
                padding: 10px;
                background: #fbfcfd;
                margin-top: 10px;
            }

            td .mulopimfwc-classic-section:first-child {
                margin-top: 0;
            }

            .mulopimfwc-classic-section h4 {
                margin: 0 0 10px;
                font-size: 13px;
                color: #1f2937;
            }

            .mulopimfwc-classic-section-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 8px;
                flex-wrap: wrap;
            }

            .mulopimfwc-classic-add-location-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-left: auto;
            }

            .mulopimfwc-classic-add-location-wrap select {
                min-width: 180px;
            }

            .mulopimfwc-classic-add-location-wrap select:disabled,
            .mulopimfwc-classic-add-location-wrap .mulopimfwc-classic-add-location-btn:disabled {
                cursor: not-allowed;
                opacity: 0.7;
            }

            .mulopimfwc-classic-add-location-empty-state {
                margin: 2px 0 0;
                flex: 1 1 100%;
                font-size: 12px;
                color: #6b7280;
            }

            .mulopimfwc-classic-add-location-empty-state a {
                color: #1d4ed8;
                text-decoration: none;
            }

            .mulopimfwc-classic-add-location-empty-state a:hover {
                text-decoration: underline;
            }

            .mulopimfwc-classic-add-location-empty-state.is-hidden {
                display: none;
            }

            .mulopimfwc-classic-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 8px 10px;
            }

            .mulopimfwc-classic-grid label {
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: #4b5563;
                margin-bottom: 4px;
            }

            .mulopimfwc-classic-checkbox-wrap {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                color: #1f2937;
                font-weight: 600;
                margin-bottom: 0;
            }

            .mulopimfwc-classic-checkbox-wrap input[type="checkbox"] {
                margin: 0;
            }

            .mulopimfwc-classic-variation-manage-list {
                display: grid;
                gap: 8px;
            }

            .mulopimfwc-classic-variation-manage-item {
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 8px 10px;
                background: #ffffff;
            }

            .mulopimfwc-classic-variation-manage-title {
                font-weight: 600;
                color: #1f2937;
            }

            .mulopimfwc-classic-grid input:not([type="checkbox"]),
            .mulopimfwc-classic-grid select,
            .mulopimfwc-classic-location-table input,
            .mulopimfwc-classic-location-table select {
                width: 100%;
                min-height: 30px;
                font-size: 12px;
            }

            .mulopimfwc-classic-price-input-wrap {
                position: relative;
                display: block;
            }

            .mulopimfwc-classic-price-input-wrap .mulopimfwc-classic-number {
                padding-right: 10px;
            }

            .mulopimfwc-classic-price-suffix {
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 11px;
                line-height: 1;
                color: #6b7280;
                pointer-events: none;
                white-space: nowrap;
            }

            .mulopimfwc-classic-location-table th,
            .mulopimfwc-classic-location-table td {
                padding: 6px !important;
                vertical-align: middle;
                font-size: 12px;
            }

            .mulopimfwc-classic-location-label {
                font-weight: 600;
                color: #111827;
            }

            .mulopimfwc-classic-manage-stock-disabled {
                border: 1px solid #f5c2c7;
                background: #fff5f5;
                border-radius: 6px;
                padding: 10px 12px;
                color: #7f1d1d;
                line-height: 1.45;
            }

            .mulopimfwc-classic-manage-stock-disabled p {
                margin: 0 0 6px;
            }

            .mulopimfwc-classic-manage-stock-disabled p:last-child {
                margin-bottom: 0;
            }

            .mulopimfwc-classic-manage-stock-disabled a {
                color: #1d4ed8;
                word-break: break-all;
            }

            .mulopimfwc-classic-no-locations-row td {
                text-align: center;
                color: #6b7280;
                font-style: italic;
            }

            .mulopimfwc-classic-variation {
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                background: #fff;
                margin-bottom: 10px;
            }

            .mulopimfwc-classic-variation summary {
                cursor: pointer;
                padding: 9px 10px;
                font-weight: 600;
                color: #1f2937;
                border-bottom: 1px solid #edf0f3;
            }

            .mulopimfwc-classic-variation[open] summary {
                background: #f8fafc;
            }

            .mulopimfwc-classic-variation .mulopimfwc-classic-grid {
                margin: 10px;
            }

            .mulopimfwc-classic-actions {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .mulopimfwc-classic-reset-row,
            .mulopimfwc-classic-save-row {
                min-width: 34px;
                height: 32px;
                line-height: 1;
                padding: 0;
                font-size: 18px;
            }

            .mulopimfwc-classic-product-row.is-dirty .mulopimfwc-classic-save-row {
                box-shadow: 0 0 0 1px #2563eb;
            }

            .mulopimfwc-classic-row-status {
                font-size: 11px;
                color: #6b7280;
                min-width: 60px;
            }

            .mulopimfwc-classic-row-status.is-success {
                color: #166534;
            }

            .mulopimfwc-classic-row-status.is-error {
                color: #b91c1c;
            }

            .mulopimfwc-classic-validation-error {
                border-color: #dc2626 !important;
                box-shadow: 0 0 0 1px #dc2626 !important;
                background: #fef2f2 !important;
            }

            .mulopimfwc-classic-error-cell {
                position: relative;
            }

            .mulopimfwc-classic-error-cell::after {
                content: attr(data-error);
                position: absolute;
                left: 50%;
                bottom: calc(100% + 10px);
                transform: translateX(-50%) translateY(4px);
                background: #111827;
                color: #fff;
                font-size: 11px;
                line-height: 1.35;
                padding: 7px 9px;
                border-radius: 6px;
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.25);
                max-width: 250px;
                min-width: 140px;
                text-align: left;
                white-space: normal;
                z-index: 9999;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.16s ease, transform 0.16s ease, visibility 0.16s ease;
            }

            .mulopimfwc-classic-error-cell::before {
                content: "";
                position: absolute;
                left: 50%;
                bottom: calc(100% + 4px);
                transform: translateX(-50%);
                border-width: 6px 6px 0 6px;
                border-style: solid;
                border-color: #111827 transparent transparent transparent;
                z-index: 9998;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.16s ease, visibility 0.16s ease;
            }

            .mulopimfwc-classic-error-cell:hover::after,
            .mulopimfwc-classic-error-cell:focus-within::after,
            .mulopimfwc-classic-error-cell:hover::before,
            .mulopimfwc-classic-error-cell:focus-within::before {
                opacity: 1;
                visibility: visible;
            }

            .mulopimfwc-classic-error-cell:hover::after,
            .mulopimfwc-classic-error-cell:focus-within::after {
                transform: translateX(-50%) translateY(0);
            }
        </style>

        <script>
            (function($) {
                $(document).ready(function() {
                    // Initialize accordions - first item expanded
                    $('.location-stock-container, .location-price-container, .gross-profit-container').each(function() {
                        var $container = $(this);
                        var $accordionItems = $container.find('.accordion-item');

                        // First item should be expanded
                        $accordionItems.first().addClass('accordion-expanded').find('.accordion-content').addClass('accordion-open');
                        $accordionItems.first().find('.accordion-icon').text('-');
                    });

                    // Handle accordion toggle
                    $(document).on('click', '.accordion-header', function(e) {
                        e.preventDefault();
                        var $header = $(this);
                        var $item = $header.closest('.accordion-item');
                        var $content = $header.siblings('.accordion-content');
                        var $icon = $header.find('.accordion-icon');
                        var targetId = $header.data('accordion-target');

                        // Toggle expanded class
                        $item.toggleClass('accordion-expanded');
                        $content.toggleClass('accordion-open');

                        // Update icon
                        if ($item.hasClass('accordion-expanded')) {
                            $icon.text('-');
                        } else {
                            $icon.text('+');
                        }
                    });

                    // Handle form submission - update URL on submit
                    var $form = $('#stock-central-form');
                    var isClassicMode = <?php echo $is_classic_mode ? 'true' : 'false'; ?>;
                    var canManageProducts = <?php echo $can_manage_products ? 'true' : 'false'; ?>;
                    var isStoreManageStockEnabled = <?php echo $is_store_manage_stock_enabled ? 'true' : 'false'; ?>;
                    var rowSnapshots = {};
                    var ajaxNonce = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.nonce) ? mulopimfwc_locationWiseProducts.nonce : '';
                    var editableClassicColumns = ['classic_manage_stock', 'classic_default', 'classic_location_wise', 'classic_purchase'];
                    var rowFieldSelector = editableClassicColumns.map(function(columnName) {
                        return '.column-' + columnName + ' [data-field]';
                    }).join(', ');

                    // Update URL when form is submitted
                    function updateURL() {
                        var formData = $form.serialize();
                        var url = window.location.pathname + '?' + formData;
                        window.history.pushState({
                            path: url
                        }, '', url);
                    }

                    // Handle form submission (Filter button or search)
                    $form.on('submit', function(e) {
                        updateURL();
                        // Form will submit normally
                    });

                    // Handle bulk action selection - validate but don't submit
                    $('select[name="action"], select[name="action2"]').on('change', function() {
                        var action = $(this).val();
                        // Just show/hide location selector, don't submit
                        toggleBulkLocationSelector();
                    });

                    // Handle bulk action Apply button click
                    $('input#doaction, input#doaction2').on('click', function(e) {
                        var $button = $(this);
                        var action = $('select[name="action"]').val();
                        var action2 = $('select[name="action2"]').val();
                        var currentAction = (action && action !== '-1') ? action : ((action2 && action2 !== '-1') ? action2 : '');

                        if (currentAction === 'bulk_assign_location' || currentAction === 'bulk_remove_location') {
                            if (!$('#bulk-location-id').val()) {
                                e.preventDefault();
                                alert('<?php echo esc_js(__('Please select a location first', 'multi-location-product-and-inventory-management')); ?>');
                                return false;
                            }
                        }
                        // If validation passes, form will submit normally
                    });

                    // Show/hide bulk location selector based on selected bulk action
                    function toggleBulkLocationSelector() {
                        var action = $('select[name="action"]').val();
                        var action2 = $('select[name="action2"]').val();
                        var currentAction = (action && action !== '-1') ? action : ((action2 && action2 !== '-1') ? action2 : '');

                        if (currentAction === 'bulk_assign_location' || currentAction === 'bulk_remove_location') {
                            $('.bulk-actions-section').fadeIn(200);
                        } else {
                            $('.bulk-actions-section').fadeOut(200);
                        }
                    }

                    // Move bulk actions section to be right after bulk actions dropdown
                    function positionBulkActionsSection() {
                        var $bulkActions = $('.tablenav.top .bulkactions, .tablenav.top .alignleft.actions.bulkactions');
                        var $bulkSection = $('.bulk-actions-section');

                        if ($bulkActions.length && $bulkSection.length) {
                            // Find the bulk actions container
                            var $bulkContainer = $bulkActions.closest('.alignleft, .bulkactions').parent();
                            if ($bulkContainer.length) {
                                $bulkSection.detach().insertAfter($bulkContainer);
                            } else {
                                $bulkSection.detach().insertAfter($bulkActions);
                            }
                        }
                    }

                    // Position on load and after any DOM changes
                    positionBulkActionsSection();

                    // Also position after table is ready
                    setTimeout(positionBulkActionsSection, 100);

                    // Initial state - hide by default
                    $('.bulk-actions-section').hide();
                    toggleBulkLocationSelector();

                    // Restore filters from URL on page load
                    var urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('s') || urlParams.has('filter-by-location') || urlParams.has('filter-by-category') ||
                        urlParams.has('filter-by-type') || urlParams.has('filter-by-stock-status') || urlParams.has('filter-by-brand')) {
                        // Filters are already in URL, form will auto-populate
                    }

                    if (!isClassicMode || !canManageProducts) {
                        return;
                    }

                    var $saveAllButton = $('#mulopimfwc-classic-save-all');
                    var $resetAllButton = $('#mulopimfwc-classic-reset-all');
                    var $retryFailedButton = $('#mulopimfwc-classic-retry-failed');
                    var $saveProgress = $('#mulopimfwc-classic-save-progress');
                    var $saveSpinner = $('#mulopimfwc-classic-save-spinner');
                    var $saveFailures = $('#mulopimfwc-classic-save-failures');
                    var saveAllInProgress = false;
                    var failedSaveAllRows = [];
                    var saveAllButtonDefaultText = '<?php echo esc_js(__('Save All Product Changes', 'multi-location-product-and-inventory-management')); ?>';
                    var saveAllSavingText = '<?php echo esc_js(__('Saving...', 'multi-location-product-and-inventory-management')); ?>';

                    function escapeHtml(text) {
                        return $('<div>').text(text || '').html();
                    }

                    function normalizeClassicCurrencySymbol(symbol) {
                        var normalized = (symbol || '').toString().trim();
                        return normalized || '$';
                    }

                    function normalizeClassicCurrencyRate(rate) {
                        var parsed = parseFloat(rate);
                        if (isNaN(parsed) || parsed <= 0) {
                            return 1;
                        }
                        return parsed;
                    }

                    function normalizeClassicCurrencyShouldConvert(value) {
                        if (typeof value === 'boolean') {
                            return value;
                        }
                        var normalized = (value || '').toString().toLowerCase();
                        return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
                    }

                    function getDefaultCurrencySymbolForRow($row) {
                        if (!$row || !$row.length) {
                            return normalizeClassicCurrencySymbol('');
                        }

                        var $wrap = $row.find('.mulopimfwc-classic-add-location-wrap').first();
                        return normalizeClassicCurrencySymbol($wrap.attr('data-default-currency-symbol'));
                    }

                    function getDefaultCurrencyRateForRow($row) {
                        if (!$row || !$row.length) {
                            return 1;
                        }

                        var $wrap = $row.find('.mulopimfwc-classic-add-location-wrap').first();
                        return normalizeClassicCurrencyRate($wrap.attr('data-default-currency-rate'));
                    }

                    function getDefaultCurrencyShouldConvertForRow($row) {
                        if (!$row || !$row.length) {
                            return false;
                        }

                        var $wrap = $row.find('.mulopimfwc-classic-add-location-wrap').first();
                        return normalizeClassicCurrencyShouldConvert($wrap.attr('data-default-currency-should-convert'));
                    }

                    function getOptionCurrencySymbol($option, fallbackSymbol) {
                        if (!$option || !$option.length) {
                            return normalizeClassicCurrencySymbol(fallbackSymbol);
                        }

                        return normalizeClassicCurrencySymbol($option.attr('data-currency-symbol') || fallbackSymbol);
                    }

                    function getOptionCurrencyRate($option, fallbackRate) {
                        if (!$option || !$option.length) {
                            return normalizeClassicCurrencyRate(fallbackRate);
                        }

                        return normalizeClassicCurrencyRate($option.attr('data-currency-rate') || fallbackRate);
                    }

                    function getOptionCurrencyShouldConvert($option, fallbackShouldConvert) {
                        if (!$option || !$option.length) {
                            return normalizeClassicCurrencyShouldConvert(fallbackShouldConvert);
                        }

                        var optionValue = $option.attr('data-currency-should-convert');
                        if (optionValue === undefined) {
                            return normalizeClassicCurrencyShouldConvert(fallbackShouldConvert);
                        }

                        return normalizeClassicCurrencyShouldConvert(optionValue);
                    }

                    function buildClassicPriceInput(fieldName, currencySymbol) {
                        var safeSymbol = escapeHtml(normalizeClassicCurrencySymbol(currencySymbol));
                        return '' +
                            '<div class=\"mulopimfwc-classic-price-input-wrap\" data-currency-symbol=\"' + safeSymbol + '\">' +
                            '<input type=\"number\" class=\"mulopimfwc-classic-field mulopimfwc-classic-number\" data-field=\"' + fieldName + '\" data-initial-value=\"\" value=\"\" min=\"0\" step=\"0.01\">' +
                            '<span class=\"mulopimfwc-classic-price-suffix\" aria-hidden=\"true\">' + safeSymbol + '</span>' +
                            '</div>';
                    }

                    function parseIds(raw) {
                        if (!raw) {
                            return [];
                        }

                        var ids = raw.toString().split(',').map(function(value) {
                            return parseInt($.trim(value), 10);
                        }).filter(function(value) {
                            return !isNaN(value) && value > 0;
                        });

                        ids.sort(function(a, b) {
                            return a - b;
                        });
                        return ids;
                    }

                    function arrayEquals(first, second) {
                        if (first.length !== second.length) {
                            return false;
                        }
                        for (var i = 0; i < first.length; i++) {
                            if (String(first[i]) !== String(second[i])) {
                                return false;
                            }
                        }
                        return true;
                    }

                    function showNotice(message, type) {
                        var noticeClass = 'notice notice-' + type + ' is-dismissible';
                        var $notice = $('<div class=\"' + noticeClass + ' mulopimfwc-classic-notice\"><p>' + message + '</p></div>');
                        $('.mulopimfwc-classic-notice').remove();
                        $('.mlsctock-cenral-main').prepend($notice);
                    }

                    function setRowStatus($row, text, type) {
                        var $status = $row.find('.mulopimfwc-classic-row-status');
                        $status.removeClass('is-success is-error');
                        if (type === 'success') {
                            $status.addClass('is-success');
                        } else if (type === 'error') {
                            $status.addClass('is-error');
                        }
                        $status.text(text || '');
                    }

                    function setSaveAllProgressText(text) {
                        if ($saveProgress.length) {
                            $saveProgress.text(text || '');
                        }
                    }

                    function getRowProductMeta($row) {
                        var productId = parseInt($row.data('product-id'), 10);
                        if (isNaN(productId)) {
                            productId = 0;
                        }

                        var productLabel = $.trim($row.find('.column-title strong').first().text());
                        if (!productLabel) {
                            productLabel = '<?php echo esc_js(__('Product', 'multi-location-product-and-inventory-management')); ?> #' + productId;
                        }

                        return {
                            id: productId,
                            label: productLabel
                        };
                    }

                    function normalizeSaveErrorMessage(errorData) {
                        var fallbackMessage = '<?php echo esc_js(__('Unable to save this product row.', 'multi-location-product-and-inventory-management')); ?>';
                        if (!errorData) {
                            return fallbackMessage;
                        }

                        if (typeof errorData === 'string') {
                            return errorData;
                        }

                        if (errorData && typeof errorData.message === 'string' && errorData.message !== '') {
                            return errorData.message;
                        }

                        return fallbackMessage;
                    }

                    function removeFailedRowByProductId(productId) {
                        failedSaveAllRows = failedSaveAllRows.filter(function(item) {
                            return parseInt(item.productId, 10) !== parseInt(productId, 10);
                        });
                    }

                    function renderSaveAllFailures() {
                        if (!$saveFailures.length) {
                            return;
                        }

                        if (!failedSaveAllRows.length) {
                            $saveFailures.empty().attr('hidden', 'hidden');
                            if ($retryFailedButton.length && !saveAllInProgress) {
                                $retryFailedButton.hide().prop('disabled', true);
                            }
                            return;
                        }

                        var failureTitle = failedSaveAllRows.length === 1
                            ? '<?php echo esc_js(__('1 row failed to save.', 'multi-location-product-and-inventory-management')); ?>'
                            : failedSaveAllRows.length + ' <?php echo esc_js(__('rows failed to save.', 'multi-location-product-and-inventory-management')); ?>';

                        var html = '<strong>' + escapeHtml(failureTitle) + '</strong>';
                        html += '<ul class=\"mulopimfwc-classic-save-failure-list\">';
                        failedSaveAllRows.forEach(function(item) {
                            var label = item && item.productLabel ? item.productLabel : '<?php echo esc_js(__('Unknown product', 'multi-location-product-and-inventory-management')); ?>';
                            var message = item && item.message ? item.message : '';
                            html += '<li><span class=\"mulopimfwc-classic-save-failure-product\">' + escapeHtml(label) + '</span>';
                            if (message) {
                                html += ': ' + escapeHtml(message);
                            }
                            html += '</li>';
                        });
                        html += '</ul>';

                        $saveFailures.html(html).removeAttr('hidden');
                        if ($retryFailedButton.length && !saveAllInProgress) {
                            $retryFailedButton.show().prop('disabled', false);
                        }
                    }

                    function clearSaveAllFailures() {
                        failedSaveAllRows = [];
                        if ($saveFailures.length) {
                            $saveFailures.empty().attr('hidden', 'hidden');
                        }
                        if ($retryFailedButton.length && !saveAllInProgress) {
                            $retryFailedButton.hide().prop('disabled', true);
                        }
                    }

                    function setSaveAllRunning(isRunning) {
                        saveAllInProgress = !!isRunning;
                        if ($saveSpinner.length) {
                            $saveSpinner.toggleClass('is-active', saveAllInProgress);
                        }
                        if ($saveAllButton.length) {
                            $saveAllButton.prop('disabled', saveAllInProgress);
                            if (!saveAllInProgress) {
                                $saveAllButton.text(saveAllButtonDefaultText);
                            }
                        }
                        if ($resetAllButton.length) {
                            var dirtyCount = $('.mulopimfwc-classic-product-row.is-dirty').length;
                            $resetAllButton.prop('disabled', saveAllInProgress || dirtyCount === 0);
                        }
                        if ($retryFailedButton.length) {
                            if (saveAllInProgress) {
                                $retryFailedButton.prop('disabled', true);
                            } else if (failedSaveAllRows.length) {
                                $retryFailedButton.show().prop('disabled', false);
                            } else {
                                $retryFailedButton.hide().prop('disabled', true);
                            }
                        }
                    }

                    function runSaveAllQueue(rowsToProcess) {
                        if (saveAllInProgress) {
                            return;
                        }

                        var queue = [];
                        (rowsToProcess || []).forEach(function(rowNode) {
                            var $row = $(rowNode);
                            if ($row.length && $row.hasClass('is-dirty')) {
                                queue.push($row);
                            }
                        });

                        if (!queue.length) {
                            showNotice('<?php echo esc_js(__('No unsaved product rows.', 'multi-location-product-and-inventory-management')); ?>', 'info');
                            setSaveAllProgressText('');
                            return;
                        }

                        clearSaveAllFailures();
                        setSaveAllRunning(true);
                        if ($saveAllButton.length) {
                            $saveAllButton.text(saveAllSavingText);
                        }

                        var totalRows = queue.length;
                        var completedRows = 0;
                        var successCount = 0;

                        function processNext() {
                            if (!queue.length) {
                                setSaveAllRunning(false);
                                if (failedSaveAllRows.length > 0) {
                                    setSaveAllProgressText('<?php echo esc_js(__('Completed with failures:', 'multi-location-product-and-inventory-management')); ?> ' + successCount + '/' + totalRows);
                                    renderSaveAllFailures();
                                    showNotice(successCount + ' <?php echo esc_js(__('rows saved,', 'multi-location-product-and-inventory-management')); ?> ' + failedSaveAllRows.length + ' <?php echo esc_js(__('rows failed.', 'multi-location-product-and-inventory-management')); ?>', 'warning');
                                } else {
                                    clearSaveAllFailures();
                                    setSaveAllProgressText('<?php echo esc_js(__('Completed:', 'multi-location-product-and-inventory-management')); ?> ' + successCount + '/' + totalRows);
                                    showNotice('<?php echo esc_js(__('All changed product rows saved successfully.', 'multi-location-product-and-inventory-management')); ?>', 'success');
                                }
                                return;
                            }

                            var $row = queue.shift();
                            var rowMeta = getRowProductMeta($row);
                            setSaveAllProgressText('<?php echo esc_js(__('Saving row', 'multi-location-product-and-inventory-management')); ?> ' + (completedRows + 1) + '/' + totalRows + ': ' + rowMeta.label);

                            saveRow($row).done(function() {
                                successCount++;
                                removeFailedRowByProductId(rowMeta.id);
                            }).fail(function(errorData) {
                                var message = normalizeSaveErrorMessage(errorData);
                                removeFailedRowByProductId(rowMeta.id);
                                failedSaveAllRows.push({
                                    productId: rowMeta.id,
                                    productLabel: rowMeta.label,
                                    message: message
                                });
                            }).always(function() {
                                completedRows++;
                                processNext();
                            });
                        }

                        processNext();
                    }

                    function updateDirtyCount() {
                        var count = $('.mulopimfwc-classic-product-row.is-dirty').length;
                        var $count = $('#mulopimfwc-classic-dirty-count');
                        if ($resetAllButton.length) {
                            $resetAllButton.prop('disabled', count === 0 || saveAllInProgress);
                        }
                        if (!$count.length) {
                            return;
                        }
                        if (count === 0) {
                            $count.text('<?php echo esc_js(__('No unsaved product rows.', 'multi-location-product-and-inventory-management')); ?>');
                        } else if (count === 1) {
                            $count.text('<?php echo esc_js(__('1 product row has unsaved changes.', 'multi-location-product-and-inventory-management')); ?>');
                        } else {
                            $count.text(count + ' <?php echo esc_js(__('product rows have unsaved changes.', 'multi-location-product-and-inventory-management')); ?>');
                        }
                    }

                    function hasUnsavedClassicRows() {
                        return $('.mulopimfwc-classic-product-row.is-dirty').length > 0;
                    }

                    function handleClassicBeforeUnload(event) {
                        if (!hasUnsavedClassicRows()) {
                            return;
                        }

                        var warningMessage = '<?php echo esc_js(__('You have unsaved Classic row changes. Leaving this page will discard them.', 'multi-location-product-and-inventory-management')); ?>';
                        event.preventDefault();
                        event.returnValue = warningMessage;
                        return warningMessage;
                    }

                    window.addEventListener('beforeunload', handleClassicBeforeUnload);

                    function setRowDirty($row, isDirty) {
                        $row.toggleClass('is-dirty', !!isDirty);
                        $row.find('.mulopimfwc-classic-save-row').prop('disabled', !isDirty);
                        $row.find('.mulopimfwc-classic-reset-row').prop('disabled', !isDirty);
                        updateDirtyCount();
                    }

                    function getFieldValue($field) {
                        if ($field.is(':checkbox')) {
                            return $field.is(':checked') ? 'yes' : 'no';
                        }

                        return ($field.val() || '').toString();
                    }

                    function getNumericFieldValue($field) {
                        if (!$field || !$field.length) {
                            return 0;
                        }

                        var raw = ($field.val() || '').toString();
                        var value = parseFloat(raw);
                        return isNaN(value) ? 0 : value;
                    }

                    function convertClassicPriceToBaseCurrency(value, $field) {
                        if (!isFinite(value) || value <= 0) {
                            return value;
                        }

                        if (!$field || !$field.length) {
                            return value;
                        }

                        var $currencyContainer = $field.closest('tr[data-currency-rate], .mulopimfwc-classic-price-input-wrap[data-currency-rate]').first();
                        if (!$currencyContainer.length) {
                            return value;
                        }

                        var shouldConvert = normalizeClassicCurrencyShouldConvert($currencyContainer.attr('data-currency-should-convert'));
                        if (!shouldConvert) {
                            return value;
                        }

                        var rate = normalizeClassicCurrencyRate($currencyContainer.attr('data-currency-rate'));
                        if (rate <= 0) {
                            return value;
                        }

                        return value / rate;
                    }

                    function clearRowValidationErrors($row) {
                        $row.find('.mulopimfwc-classic-validation-error').removeClass('mulopimfwc-classic-validation-error');
                        $row.find('.mulopimfwc-classic-error-cell').removeClass('mulopimfwc-classic-error-cell').removeAttr('data-error');
                    }

                    function getFieldErrorContainer($field) {
                        if (!$field || !$field.length) {
                            return $();
                        }

                        var $container = $field.closest('.mulopimfwc-classic-location-table td');
                        if ($container.length) {
                            return $container.first();
                        }

                        $container = $field.closest('.mulopimfwc-classic-grid > div');
                        if ($container.length) {
                            return $container.first();
                        }

                        $container = $field.closest('.mulopimfwc-classic-variation-manage-item');
                        if ($container.length) {
                            return $container.first();
                        }

                        return $field.parent();
                    }

                    function setFieldValidationTooltip($field, message) {
                        var $container = getFieldErrorContainer($field);
                        if (!$container.length) {
                            return;
                        }

                        $container.addClass('mulopimfwc-classic-error-cell');
                        if (!$container.attr('data-error')) {
                            $container.attr('data-error', message);
                        }
                    }

                    function clearFieldValidationError($field) {
                        if (!$field || !$field.length) {
                            return;
                        }

                        $field.removeClass('mulopimfwc-classic-validation-error');
                        var $container = getFieldErrorContainer($field);
                        if ($container.length) {
                            $container.removeClass('mulopimfwc-classic-error-cell').removeAttr('data-error');
                        }
                    }

                    function isDefaultManageStockEnabledForRow($row) {
                        if (!isStoreManageStockEnabled) {
                            return false;
                        }

                        var productType = (($row.attr('data-product-type') || '') + '').toLowerCase();
                        var supportsManageStock = ['grouped', 'external'].indexOf(productType) === -1;
                        if (!supportsManageStock) {
                            return false;
                        }

                        var $defaultManageStockField = $row.find('.column-classic_manage_stock .mulopimfwc-classic-default-field[data-field=\"manage_stock\"]').first();
                        return !$defaultManageStockField.length || $defaultManageStockField.is(':checked');
                    }

                    function isVariationManageStockEnabledForRow($row, variationId) {
                        if (!isStoreManageStockEnabled) {
                            return false;
                        }

                        var $variationManageStockField = $row.find('.column-classic_manage_stock .mulopimfwc-classic-variation-manage-item[data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"manage_stock\"]').first();
                        return !$variationManageStockField.length || $variationManageStockField.is(':checked');
                    }

                    function toggleStockFieldVisibility($field, isVisible) {
                        if (!$field || !$field.length) {
                            return;
                        }

                        if (!isVisible) {
                            clearFieldValidationError($field);
                        }

                        $field.prop('disabled', !isVisible);
                        var $container = getFieldErrorContainer($field);
                        if ($container.length) {
                            $container.toggle(isVisible);
                        } else {
                            $field.toggle(isVisible);
                        }
                    }

                    function toggleTableColumnVisibilityByFields($table, fieldNames, isVisible, fallbackIndexes) {
                        if (!$table || !$table.length) {
                            return;
                        }

                        var fields = $.isArray(fieldNames) ? fieldNames : [fieldNames];
                        if (!fields.length) {
                            return;
                        }

                        var hiddenIndexes = [];
                        var $firstDataRow = $table.find('tbody tr').filter(function() {
                            return $(this).find('[data-field]').length > 0;
                        }).first();

                        if ($firstDataRow.length) {
                            fields.forEach(function(fieldName) {
                                var $targetField = $firstDataRow.find('[data-field=\"' + fieldName + '\"]').first();
                                if (!$targetField.length) {
                                    return;
                                }

                                var cellIndex = $targetField.closest('td').index();
                                if (cellIndex >= 0 && hiddenIndexes.indexOf(cellIndex) === -1) {
                                    hiddenIndexes.push(cellIndex);
                                }
                            });
                        }

                        if (!hiddenIndexes.length && $.isArray(fallbackIndexes) && fallbackIndexes.length) {
                            fallbackIndexes.forEach(function(index) {
                                var parsed = parseInt(index, 10);
                                if (!isNaN(parsed) && parsed >= 0 && hiddenIndexes.indexOf(parsed) === -1) {
                                    hiddenIndexes.push(parsed);
                                }
                            });
                        }

                        if (!hiddenIndexes.length) {
                            return;
                        }

                        hiddenIndexes.sort(function(a, b) {
                            return a - b;
                        });

                        if (!isVisible) {
                            fields.forEach(function(fieldName) {
                                $table.find('tbody [data-field=\"' + fieldName + '\"]').each(function() {
                                    clearFieldValidationError($(this));
                                });
                            });
                        }

                        var $headCells = $table.find('thead tr').first().children();
                        hiddenIndexes.forEach(function(index) {
                            if ($headCells.length > index) {
                                $headCells.eq(index).toggle(isVisible);
                            }
                        });

                        $table.find('tbody tr').each(function() {
                            var $cells = $(this).children();
                            hiddenIndexes.forEach(function(index) {
                                if ($cells.length > index) {
                                    $cells.eq(index).toggle(isVisible);
                                }
                            });
                        });

                        fields.forEach(function(fieldName) {
                            $table.find('tbody [data-field=\"' + fieldName + '\"]').prop('disabled', !isVisible);
                        });
                    }

                    function applyClassicManageStockVisibility($row) {
                        if (!$row || !$row.length) {
                            return;
                        }

                        if (!isStoreManageStockEnabled) {
                            return;
                        }

                        var productType = (($row.attr('data-product-type') || '') + '').toLowerCase();
                        var isVariable = productType === 'variable';
                        var defaultManageStockEnabled = isDefaultManageStockEnabledForRow($row);

                        var $defaultStockField = $row.find('.column-classic_default .mulopimfwc-classic-default-field[data-field=\"stock_quantity\"]').first();
                        var $defaultBackordersField = $row.find('.column-classic_default .mulopimfwc-classic-default-field[data-field=\"backorders\"]').first();
                        toggleStockFieldVisibility($defaultStockField, defaultManageStockEnabled);
                        toggleStockFieldVisibility($defaultBackordersField, defaultManageStockEnabled);
                        var $defaultSection = $row.find('.column-classic_default > .mulopimfwc-classic-section').filter(function() {
                            return $(this).children('.mulopimfwc-classic-grid').length > 0;
                        }).first();
                        if ($defaultSection.length) {
                            var $defaultItems = $defaultSection.find('> .mulopimfwc-classic-grid > div');
                            var hasRenderedItems = $defaultItems.length > 0;
                            var hasDisplayedItems = $defaultItems.filter(function() {
                                return $(this).css('display') !== 'none';
                            }).length > 0;
                            var shouldShowDefaultSection = hasRenderedItems && (isVariable ? defaultManageStockEnabled : hasDisplayedItems);
                            $defaultSection.toggle(shouldShowDefaultSection);
                        }

                        if (!isVariable) {
                            var $productLocationTable = $row.find('.column-classic_location_wise .mulopimfwc-classic-product-location-table').first();
                            toggleTableColumnVisibilityByFields($productLocationTable, ['stock', 'backorders'], defaultManageStockEnabled, [1, 4]);
                            return;
                        }

                        getVariationIdsForRow($row).forEach(function(variationId) {
                            var variationManageStockEnabled = isVariationManageStockEnabledForRow($row, variationId);
                            var $variationStockField = $row.find('.column-classic_default [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"stock_quantity\"]').first();
                            var $variationBackordersField = $row.find('.column-classic_default [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"backorders\"]').first();
                            var $variationLocationTable = $row.find('.column-classic_location_wise .mulopimfwc-classic-variation-location-table[data-variation-id=\"' + variationId + '\"]').first();

                            toggleStockFieldVisibility($variationStockField, variationManageStockEnabled);
                            toggleStockFieldVisibility($variationBackordersField, variationManageStockEnabled);
                            toggleTableColumnVisibilityByFields($variationLocationTable, ['stock', 'backorders'], variationManageStockEnabled, [1, 4]);
                        });
                    }

                    function rowHasValidationErrors($row) {
                        return $row.find('.mulopimfwc-classic-validation-error, .mulopimfwc-classic-error-cell').length > 0;
                    }

                    function addRowValidationError(errors, $field, message) {
                        if ($field && $field.length) {
                            $field.each(function() {
                                var $target = $(this);
                                $target.addClass('mulopimfwc-classic-validation-error');
                                setFieldValidationTooltip($target, message);
                            });
                        }
                        errors.push({
                            field: $field,
                            message: message
                        });
                    }

                    function getVariationIdsForRow($row) {
                        var variationIds = [];
                        $row.find('[data-variation-id]').each(function() {
                            var variationId = parseInt($(this).data('variation-id'), 10);
                            if (!isNaN(variationId) && variationId > 0 && variationIds.indexOf(variationId) === -1) {
                                variationIds.push(variationId);
                            }
                        });
                        variationIds.sort(function(a, b) {
                            return a - b;
                        });
                        return variationIds;
                    }

                    function validateClassicRow($row) {
                        clearRowValidationErrors($row);

                        var messages = {
                            regularVsPurchase: '<?php echo esc_js(__('Regular price cannot be less than purchase price', 'multi-location-product-and-inventory-management')); ?>',
                            saleVsRegular: '<?php echo esc_js(__('Sale price must be less than regular price', 'multi-location-product-and-inventory-management')); ?>',
                            saleVsPurchase: '<?php echo esc_js(__('Sale price cannot be less than purchase price', 'multi-location-product-and-inventory-management')); ?>',
                            locationRegularVsPurchase: '<?php echo esc_js(__('Location regular price cannot be less than purchase price', 'multi-location-product-and-inventory-management')); ?>',
                            locationSaleVsPurchase: '<?php echo esc_js(__('Location sale price cannot be less than purchase price', 'multi-location-product-and-inventory-management')); ?>',
                            locationSaleVsLocationRegular: '<?php echo esc_js(__('Location sale price must be less than location regular price', 'multi-location-product-and-inventory-management')); ?>',
                            stockVsPurchaseQty: '<?php echo esc_js(__('Stock quantity cannot be greater than purchase quantity', 'multi-location-product-and-inventory-management')); ?>',
                            purchaseQtyVsStock: '<?php echo esc_js(__('Purchase quantity cannot be less than stock quantity', 'multi-location-product-and-inventory-management')); ?>',
                            totalLocationStockExceeded: '<?php echo esc_js(__('Total location stock exceeds default stock', 'multi-location-product-and-inventory-management')); ?>',
                            totalVariationLocationStockExceeded: '<?php echo esc_js(__('Total location stock exceeds variation default stock', 'multi-location-product-and-inventory-management')); ?>',
                            generic: '<?php echo esc_js(__('Please fix the validation errors before saving.', 'multi-location-product-and-inventory-management')); ?>'
                        };

                        var errors = [];
                        var productType = (($row.attr('data-product-type') || '') + '').toLowerCase();
                        var isGrouped = productType === 'grouped';
                        var isVariable = productType === 'variable';
                        var isExternal = productType === 'external';
                        var supportsManageStock = isStoreManageStockEnabled && ['grouped', 'external'].indexOf(productType) === -1;

                        var $defaultManageStockField = $row.find('.column-classic_manage_stock .mulopimfwc-classic-default-field[data-field=\"manage_stock\"]').first();
                        var defaultManageStockEnabled = supportsManageStock ? (!$defaultManageStockField.length || $defaultManageStockField.is(':checked')) : false;

                        var $defaultStockField = $row.find('.column-classic_default .mulopimfwc-classic-default-field[data-field=\"stock_quantity\"]').first();
                        var $defaultRegularField = $row.find('.column-classic_default .mulopimfwc-classic-default-field[data-field=\"regular_price\"]').first();
                        var $defaultSaleField = $row.find('.column-classic_default .mulopimfwc-classic-default-field[data-field=\"sale_price\"]').first();
                        var $defaultPurchasePriceField = $row.find('.column-classic_purchase .mulopimfwc-classic-default-field[data-field=\"purchase_price\"]').first();
                        var $defaultPurchaseQtyField = $row.find('.column-classic_purchase .mulopimfwc-classic-default-field[data-field=\"purchase_quantity\"]').first();

                        var defaultStock = getNumericFieldValue($defaultStockField);
                        var defaultRegularPrice = getNumericFieldValue($defaultRegularField);
                        var defaultSalePrice = getNumericFieldValue($defaultSaleField);
                        var defaultPurchasePrice = getNumericFieldValue($defaultPurchasePriceField);
                        var defaultPurchaseQty = getNumericFieldValue($defaultPurchaseQtyField);

                        if (!isGrouped && !isVariable) {
                            if (defaultPurchasePrice > 0 && defaultRegularPrice > 0 && defaultRegularPrice < defaultPurchasePrice) {
                                addRowValidationError(errors, $defaultRegularField, messages.regularVsPurchase);
                            }

                            if (defaultSalePrice > 0 && defaultRegularPrice > 0 && defaultSalePrice >= defaultRegularPrice) {
                                addRowValidationError(errors, $defaultSaleField, messages.saleVsRegular);
                            }

                            if (defaultSalePrice > 0 && defaultPurchasePrice > 0 && defaultSalePrice < defaultPurchasePrice) {
                                addRowValidationError(errors, $defaultSaleField, messages.saleVsPurchase);
                            }
                        }

                        if (!isGrouped && !isVariable && !isExternal && defaultManageStockEnabled) {
                            if (defaultPurchaseQty > 0 && defaultStock > defaultPurchaseQty) {
                                addRowValidationError(errors, $defaultStockField, messages.stockVsPurchaseQty);
                            }

                            if (defaultStock > 0 && defaultPurchaseQty < defaultStock) {
                                addRowValidationError(errors, $defaultPurchaseQtyField, messages.purchaseQtyVsStock);
                            }

                            var totalLocationStock = 0;
                            var $positiveLocationStockFields = $();
                            $row.find('.column-classic_location_wise .mulopimfwc-classic-product-location-table tbody tr.mulopimfwc-classic-product-location-row [data-field=\"stock\"]').each(function() {
                                var $field = $(this);
                                var stockValue = getNumericFieldValue($field);
                                if (stockValue > 0) {
                                    totalLocationStock += stockValue;
                                    $positiveLocationStockFields = $positiveLocationStockFields.add($field);
                                }
                            });

                            if (defaultStock > 0 && totalLocationStock > defaultStock && $positiveLocationStockFields.length) {
                                $positiveLocationStockFields.each(function() {
                                    var $field = $(this);
                                    $field.addClass('mulopimfwc-classic-validation-error');
                                    setFieldValidationTooltip($field, messages.totalLocationStockExceeded);
                                });
                                addRowValidationError(errors, $positiveLocationStockFields.first(), messages.totalLocationStockExceeded);
                            }
                        }

                        if (!isGrouped && !isVariable) {
                            $row.find('.column-classic_location_wise .mulopimfwc-classic-product-location-table tbody tr.mulopimfwc-classic-product-location-row').each(function() {
                                var $locationRow = $(this);
                                var $locationRegularField = $locationRow.find('[data-field=\"regular_price\"]').first();
                                var $locationSaleField = $locationRow.find('[data-field=\"sale_price\"]').first();
                                var locationRegularPrice = getNumericFieldValue($locationRegularField);
                                var locationSalePrice = getNumericFieldValue($locationSaleField);
                                var locationRegularPriceBase = convertClassicPriceToBaseCurrency(locationRegularPrice, $locationRegularField);
                                var locationSalePriceBase = convertClassicPriceToBaseCurrency(locationSalePrice, $locationSaleField);

                                if (locationRegularPrice > 0 && defaultPurchasePrice > 0 && locationRegularPriceBase < defaultPurchasePrice) {
                                    addRowValidationError(errors, $locationRegularField, messages.locationRegularVsPurchase);
                                }

                                if (locationSalePrice > 0 && defaultPurchasePrice > 0 && locationSalePriceBase < defaultPurchasePrice) {
                                    addRowValidationError(errors, $locationSaleField, messages.locationSaleVsPurchase);
                                }

                                if (locationSalePrice > 0 && locationRegularPrice > 0 && locationSalePrice >= locationRegularPrice) {
                                    addRowValidationError(errors, $locationSaleField, messages.locationSaleVsLocationRegular);
                                }

                            });
                        }

                        if (isVariable) {
                            getVariationIdsForRow($row).forEach(function(variationId) {
                                var $variationRegularField = $row.find('.column-classic_default [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"regular_price\"]').first();
                                var $variationSaleField = $row.find('.column-classic_default [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"sale_price\"]').first();
                                var $variationStockField = $row.find('.column-classic_default [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"stock_quantity\"]').first();
                                var $variationPurchasePriceField = $row.find('.column-classic_purchase [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"purchase_price\"]').first();
                                var $variationPurchaseQtyField = $row.find('.column-classic_purchase [data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"purchase_quantity\"]').first();
                                var $variationManageStockField = $row.find('.column-classic_manage_stock .mulopimfwc-classic-variation-manage-item[data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field[data-field=\"manage_stock\"]').first();

                                var variationRegularPrice = getNumericFieldValue($variationRegularField);
                                var variationSalePrice = getNumericFieldValue($variationSaleField);
                                var variationStock = getNumericFieldValue($variationStockField);
                                var variationPurchasePrice = getNumericFieldValue($variationPurchasePriceField);
                                var variationPurchaseQty = getNumericFieldValue($variationPurchaseQtyField);
                                var variationManageStockEnabled = !$variationManageStockField.length || $variationManageStockField.is(':checked');

                                if (variationPurchasePrice > 0 && variationRegularPrice > 0 && variationRegularPrice < variationPurchasePrice) {
                                    addRowValidationError(errors, $variationRegularField, messages.regularVsPurchase);
                                }

                                if (variationSalePrice > 0 && variationRegularPrice > 0 && variationSalePrice >= variationRegularPrice) {
                                    addRowValidationError(errors, $variationSaleField, messages.saleVsRegular);
                                }

                                if (variationManageStockEnabled) {
                                    if (variationPurchaseQty > 0 && variationStock > variationPurchaseQty) {
                                        addRowValidationError(errors, $variationStockField, messages.stockVsPurchaseQty);
                                    }

                                    if (variationStock > 0 && variationPurchaseQty < variationStock) {
                                        addRowValidationError(errors, $variationPurchaseQtyField, messages.purchaseQtyVsStock);
                                    }
                                }

                                var $variationLocationRows = $row.find('.column-classic_location_wise .mulopimfwc-classic-variation-location-table[data-variation-id=\"' + variationId + '\"] tbody tr.mulopimfwc-classic-variation-location-row');
                                var variationLocationStockTotal = 0;
                                var $positiveVariationLocationStockFields = $();

                                $variationLocationRows.each(function() {
                                    var $variationLocationRow = $(this);
                                    var $variationLocationRegularField = $variationLocationRow.find('[data-field=\"regular_price\"]').first();
                                    var $variationLocationSaleField = $variationLocationRow.find('[data-field=\"sale_price\"]').first();
                                    var $variationLocationStockField = $variationLocationRow.find('[data-field=\"stock\"]').first();
                                    var variationLocationRegularPrice = getNumericFieldValue($variationLocationRegularField);
                                    var variationLocationSalePrice = getNumericFieldValue($variationLocationSaleField);
                                    var variationLocationStock = getNumericFieldValue($variationLocationStockField);
                                    var variationLocationRegularPriceBase = convertClassicPriceToBaseCurrency(variationLocationRegularPrice, $variationLocationRegularField);
                                    var variationLocationSalePriceBase = convertClassicPriceToBaseCurrency(variationLocationSalePrice, $variationLocationSaleField);

                                    if (variationLocationRegularPrice > 0 && variationPurchasePrice > 0 && variationLocationRegularPriceBase < variationPurchasePrice) {
                                        addRowValidationError(errors, $variationLocationRegularField, messages.locationRegularVsPurchase);
                                    }

                                    if (variationLocationSalePrice > 0 && variationPurchasePrice > 0 && variationLocationSalePriceBase < variationPurchasePrice) {
                                        addRowValidationError(errors, $variationLocationSaleField, messages.locationSaleVsPurchase);
                                    }

                                    if (variationLocationSalePrice > 0 && variationLocationRegularPrice > 0 && variationLocationSalePrice >= variationLocationRegularPrice) {
                                        addRowValidationError(errors, $variationLocationSaleField, messages.locationSaleVsLocationRegular);
                                    }

                                    if (variationManageStockEnabled && variationLocationStock > 0) {
                                        variationLocationStockTotal += variationLocationStock;
                                        $positiveVariationLocationStockFields = $positiveVariationLocationStockFields.add($variationLocationStockField);
                                    }
                                });

                                if (variationManageStockEnabled && variationStock > 0 && variationLocationStockTotal > variationStock && $positiveVariationLocationStockFields.length) {
                                    $positiveVariationLocationStockFields.each(function() {
                                        var $field = $(this);
                                        $field.addClass('mulopimfwc-classic-validation-error');
                                        setFieldValidationTooltip($field, messages.totalVariationLocationStockExceeded);
                                    });
                                    addRowValidationError(errors, $positiveVariationLocationStockFields.first(), messages.totalVariationLocationStockExceeded);
                                }
                            });
                        }

                        if (errors.length) {
                            return {
                                valid: false,
                                message: errors[0].message || messages.generic
                            };
                        }

                        return {
                            valid: true,
                            message: ''
                        };
                    }

                    function storeRowSnapshot($row) {
                        var productId = String($row.data('product-id'));
                        var columnHtml = {};
                        editableClassicColumns.forEach(function(columnName) {
                            columnHtml[columnName] = $row.find('.column-' + columnName).html();
                        });

                        rowSnapshots[productId] = {
                            columnHtml: columnHtml,
                            originalLocationIds: $row.attr('data-original-location-ids') || ''
                        };
                    }

                    function resetRowFromSnapshot($row) {
                        var productId = String($row.data('product-id'));
                        var snapshot = rowSnapshots[productId];
                        if (!snapshot) {
                            return false;
                        }

                        if (snapshot.columnHtml) {
                            editableClassicColumns.forEach(function(columnName) {
                                if (snapshot.columnHtml.hasOwnProperty(columnName)) {
                                    $row.find('.column-' + columnName).html(snapshot.columnHtml[columnName]);
                                }
                            });
                        }

                        $row.attr('data-original-location-ids', snapshot.originalLocationIds);
                        clearRowValidationErrors($row);
                        applyClassicManageStockVisibility($row);
                        syncAllLocationsOption($row.find('.mulopimfwc-classic-add-location-select').first());
                        setRowStatus($row, '');
                        setRowDirty($row, false);
                        return true;
                    }

                    function getCurrentLocationIds($row) {
                        var ids = [];
                        $row.find('.mulopimfwc-classic-product-location-table tbody tr.mulopimfwc-classic-product-location-row').each(function() {
                            var locationId = parseInt($(this).data('location-id'), 10);
                            if (!isNaN(locationId) && locationId > 0) {
                                ids.push(locationId);
                            }
                        });
                        ids.sort(function(a, b) {
                            return a - b;
                        });
                        return ids;
                    }

                    function isRowDirty($row) {
                        var dirty = false;
                        $row.find(rowFieldSelector).each(function() {
                            var $field = $(this);
                            var initial = ($field.attr('data-initial-value') || '').toString();
                            var current = getFieldValue($field);
                            if (initial !== current) {
                                dirty = true;
                                return false;
                            }
                        });
                        if (dirty) {
                            return true;
                        }

                        var originalIds = parseIds($row.attr('data-original-location-ids'));
                        var currentIds = getCurrentLocationIds($row);
                        return !arrayEquals(originalIds, currentIds);
                    }

                    function refreshRowAsSaved($row) {
                        var rowMeta = getRowProductMeta($row);
                        var currentIds = getCurrentLocationIds($row);
                        $row.attr('data-original-location-ids', currentIds.join(','));
                        $row.find(rowFieldSelector).each(function() {
                            var $field = $(this);
                            $field.attr('data-initial-value', getFieldValue($field));
                        });
                        storeRowSnapshot($row);
                        setRowDirty($row, false);
                        removeFailedRowByProductId(rowMeta.id);
                        if (!saveAllInProgress) {
                            renderSaveAllFailures();
                        }
                    }

                    function ensureNoLocationRow($table, colspan) {
                        var $tbody = $table.find('tbody');
                        var hasRows = $tbody.find('tr.mulopimfwc-classic-product-location-row, tr.mulopimfwc-classic-variation-location-row').length > 0;
                        if (hasRows) {
                            $tbody.find('.mulopimfwc-classic-no-locations-row').remove();
                        } else if (!$tbody.find('.mulopimfwc-classic-no-locations-row').length) {
                            $tbody.append('<tr class=\"mulopimfwc-classic-no-locations-row\"><td colspan=\"' + colspan + '\"><?php echo esc_js(__('No locations assigned yet.', 'multi-location-product-and-inventory-management')); ?></td></tr>');
                        }
                    }

                    function hasRealLocationOptions($select) {
                        if (!$select || !$select.length) {
                            return false;
                        }

                        return $select.find('option').filter(function() {
                            var value = ($(this).val() || '').toString();
                            return value !== '' && value !== '__all__';
                        }).length > 0;
                    }

                    function refreshAddLocationControls($row) {
                        if (!$row || !$row.length) {
                            return;
                        }

                        var $wrap = $row.find('.mulopimfwc-classic-add-location-wrap').first();
                        if (!$wrap.length) {
                            return;
                        }

                        var $select = $wrap.find('.mulopimfwc-classic-add-location-select').first();
                        var $button = $wrap.find('.mulopimfwc-classic-add-location-btn').first();
                        var $message = $row.find('.mulopimfwc-classic-add-location-empty-state').first();
                        if (!$select.length || !$button.length || !$message.length) {
                            return;
                        }

                        var hasRealOptions = hasRealLocationOptions($select);
                        var hasLocationCatalog = (($wrap.attr('data-has-location-catalog') || 'no') + '').toLowerCase() === 'yes';
                        if (hasRealOptions && !hasLocationCatalog) {
                            hasLocationCatalog = true;
                            $wrap.attr('data-has-location-catalog', 'yes');
                        }

                        $select.prop('disabled', !hasRealOptions);
                        $button.prop('disabled', !hasRealOptions);

                        if (hasRealOptions) {
                            $message.addClass('is-hidden').empty();
                            return;
                        }

                        var noLocationsMessage = ($wrap.attr('data-no-locations-message') || '').toString();
                        var allAssignedMessage = ($wrap.attr('data-all-assigned-message') || '').toString();
                        var createLocationLabel = ($wrap.attr('data-create-location-label') || '').toString();
                        var createLocationUrl = ($wrap.attr('data-create-location-url') || '').toString();
                        var canManageLocations = (($wrap.attr('data-can-manage-locations') || 'no') + '').toLowerCase() === 'yes';

                        $message.removeClass('is-hidden').empty();
                        if (hasLocationCatalog) {
                            $message.text(allAssignedMessage);
                            return;
                        }

                        $message.text(noLocationsMessage);
                        if (canManageLocations && createLocationLabel && createLocationUrl) {
                            $message.append(' ');
                            $message.append($('<a></a>').attr('href', createLocationUrl).text(createLocationLabel));
                            $message.append('.');
                        }
                    }

                    function syncAllLocationsOption($select) {
                        if (!$select || !$select.length) {
                            return;
                        }

                        var hasRealOptions = hasRealLocationOptions($select);
                        var hasAllOption = $select.find('option[value=\"__all__\"]').length > 0;

                        if (hasRealOptions && !hasAllOption) {
                            var $allOption = $('<option value=\"__all__\"><?php echo esc_js(__('All locations', 'multi-location-product-and-inventory-management')); ?></option>');
                            var $placeholder = $select.find('option[value=\"\"]').first();
                            if ($placeholder.length) {
                                $allOption.insertAfter($placeholder);
                            } else {
                                $select.prepend($allOption);
                            }
                        } else if (!hasRealOptions && hasAllOption) {
                            $select.find('option[value=\"__all__\"]').remove();
                        }

                        var $row = $select.closest('.mulopimfwc-classic-product-row');
                        if ($row.length) {
                            refreshAddLocationControls($row);
                        }
                    }

                    function buildProductLocationRow(locationId, locationName, supportsManageStock, rowMode, currencySymbol, currencyRate, currencyShouldConvert) {
                        var disabled = supportsManageStock ? '' : ' disabled=\"disabled\"';
                        var safeCurrencySymbol = normalizeClassicCurrencySymbol(currencySymbol);
                        var escapedCurrencySymbol = escapeHtml(safeCurrencySymbol);
                        var safeCurrencyRate = normalizeClassicCurrencyRate(currencyRate);
                        var escapedCurrencyRate = escapeHtml(String(safeCurrencyRate));
                        var escapedShouldConvert = escapeHtml(normalizeClassicCurrencyShouldConvert(currencyShouldConvert) ? 'yes' : 'no');
                        if (rowMode === 'location_only') {
                            return '' +
                                '<tr class=\"mulopimfwc-classic-product-location-row\" data-location-id=\"' + locationId + '\" data-location-name=\"' + escapeHtml(locationName) + '\" data-currency-symbol=\"' + escapedCurrencySymbol + '\" data-currency-rate=\"' + escapedCurrencyRate + '\" data-currency-should-convert=\"' + escapedShouldConvert + '\">' +
                                '<td class=\"mulopimfwc-classic-location-label\">' + escapeHtml(locationName) + '</td>' +
                                '<td><button type=\"button\" class=\"button-link-delete mulopimfwc-classic-remove-location\" title=\"<?php echo esc_js(__('Remove location', 'multi-location-product-and-inventory-management')); ?>\">&#10005;</button></td>' +
                                '</tr>';
                        } else if (rowMode === 'price_only') {
                            return '' +
                                '<tr class=\"mulopimfwc-classic-product-location-row\" data-location-id=\"' + locationId + '\" data-location-name=\"' + escapeHtml(locationName) + '\" data-currency-symbol=\"' + escapedCurrencySymbol + '\" data-currency-rate=\"' + escapedCurrencyRate + '\" data-currency-should-convert=\"' + escapedShouldConvert + '\">' +
                                '<td class=\"mulopimfwc-classic-location-label\">' + escapeHtml(locationName) + '</td>' +
                                '<td>' + buildClassicPriceInput('regular_price', safeCurrencySymbol) + '</td>' +
                                '<td>' + buildClassicPriceInput('sale_price', safeCurrencySymbol) + '</td>' +
                                '<td><button type=\"button\" class=\"button-link-delete mulopimfwc-classic-remove-location\" title=\"<?php echo esc_js(__('Remove location', 'multi-location-product-and-inventory-management')); ?>\">&#10005;</button></td>' +
                                '</tr>';
                        }

                        return '' +
                            '<tr class=\"mulopimfwc-classic-product-location-row\" data-location-id=\"' + locationId + '\" data-location-name=\"' + escapeHtml(locationName) + '\" data-currency-symbol=\"' + escapedCurrencySymbol + '\" data-currency-rate=\"' + escapedCurrencyRate + '\" data-currency-should-convert=\"' + escapedShouldConvert + '\">' +
                            '<td class=\"mulopimfwc-classic-location-label\">' + escapeHtml(locationName) + '</td>' +
                            '<td><input type=\"number\" class=\"mulopimfwc-classic-field mulopimfwc-classic-number\" data-field=\"stock\" data-initial-value=\"\" value=\"\" min=\"0\" step=\"1\"' + disabled + '></td>' +
                            '<td>' + buildClassicPriceInput('regular_price', safeCurrencySymbol) + '</td>' +
                            '<td>' + buildClassicPriceInput('sale_price', safeCurrencySymbol) + '</td>' +
                            '<td><select class=\"mulopimfwc-classic-field mulopimfwc-classic-select\" data-field=\"backorders\" data-initial-value=\"no\"' + disabled + '>' +
                            '<option value=\"no\"><?php echo esc_js(__('Do not allow', 'multi-location-product-and-inventory-management')); ?></option>' +
                            '<option value=\"notify\"><?php echo esc_js(__('Allow, but notify', 'multi-location-product-and-inventory-management')); ?></option>' +
                            '<option value=\"yes\"><?php echo esc_js(__('Allow', 'multi-location-product-and-inventory-management')); ?></option>' +
                            '</select></td>' +
                            '<td><button type=\"button\" class=\"button-link-delete mulopimfwc-classic-remove-location\" title=\"<?php echo esc_js(__('Remove location', 'multi-location-product-and-inventory-management')); ?>\">&#10005;</button></td>' +
                            '</tr>';
                    }

                    function buildVariationLocationRow(locationId, locationName, supportsManageStock, currencySymbol, currencyRate, currencyShouldConvert) {
                        var includeStockFields = !!supportsManageStock;
                        var safeCurrencySymbol = normalizeClassicCurrencySymbol(currencySymbol);
                        var escapedCurrencySymbol = escapeHtml(safeCurrencySymbol);
                        var safeCurrencyRate = normalizeClassicCurrencyRate(currencyRate);
                        var escapedCurrencyRate = escapeHtml(String(safeCurrencyRate));
                        var escapedShouldConvert = escapeHtml(normalizeClassicCurrencyShouldConvert(currencyShouldConvert) ? 'yes' : 'no');
                        return '' +
                            '<tr class=\"mulopimfwc-classic-variation-location-row\" data-location-id=\"' + locationId + '\" data-location-name=\"' + escapeHtml(locationName) + '\" data-currency-symbol=\"' + escapedCurrencySymbol + '\" data-currency-rate=\"' + escapedCurrencyRate + '\" data-currency-should-convert=\"' + escapedShouldConvert + '\">' +
                            '<td class=\"mulopimfwc-classic-location-label\">' + escapeHtml(locationName) + '</td>' +
                            (includeStockFields ? '<td><input type=\"number\" class=\"mulopimfwc-classic-field mulopimfwc-classic-number\" data-field=\"stock\" data-initial-value=\"\" value=\"\" min=\"0\" step=\"1\"></td>' : '') +
                            '<td>' + buildClassicPriceInput('regular_price', safeCurrencySymbol) + '</td>' +
                            '<td>' + buildClassicPriceInput('sale_price', safeCurrencySymbol) + '</td>' +
                            (includeStockFields ? '<td><select class=\"mulopimfwc-classic-field mulopimfwc-classic-select\" data-field=\"backorders\" data-initial-value=\"no\">' +
                            '<option value=\"no\"><?php echo esc_js(__('Do not allow', 'multi-location-product-and-inventory-management')); ?></option>' +
                            '<option value=\"notify\"><?php echo esc_js(__('Allow, but notify', 'multi-location-product-and-inventory-management')); ?></option>' +
                            '<option value=\"yes\"><?php echo esc_js(__('Allow', 'multi-location-product-and-inventory-management')); ?></option>' +
                            '</select></td>' : '') +
                            '</tr>';
                    }

                    function collectRowPayload($row) {
                        var payload = {
                            action: 'save_product_quick_edit_data',
                            product_id: parseInt($row.data('product-id'), 10),
                            security: ajaxNonce,
                            default: {},
                            locations: [],
                            location_ids: [],
                            removed_location_ids: [],
                            variations: []
                        };

                        $row.find('.mulopimfwc-classic-default-field').each(function() {
                            var field = $(this).data('field');
                            if (field) {
                                payload.default[field] = getFieldValue($(this));
                            }
                        });

                        $row.find('.mulopimfwc-classic-product-location-table tbody tr.mulopimfwc-classic-product-location-row').each(function() {
                            var $locationRow = $(this);
                            var locationId = parseInt($locationRow.data('location-id'), 10);
                            if (isNaN(locationId) || locationId <= 0) {
                                return;
                            }
                            var locationPayload = {
                                id: locationId
                            };
                            $locationRow.find('[data-field]').each(function() {
                                var field = $(this).data('field');
                                if (field) {
                                    locationPayload[field] = getFieldValue($(this));
                                }
                            });
                            payload.locations.push(locationPayload);
                            payload.location_ids.push(locationId);
                        });

                        var originalIds = parseIds($row.attr('data-original-location-ids'));
                        payload.removed_location_ids = originalIds.filter(function(locationId) {
                            return payload.location_ids.indexOf(locationId) === -1;
                        });

                        getVariationIdsForRow($row).forEach(function(variationId) {
                            var variationPayload = {
                                id: variationId,
                                default: {},
                                locations: []
                            };

                            $row.find('[data-variation-id=\"' + variationId + '\"] .mulopimfwc-classic-variation-default-field').each(function() {
                                var field = $(this).data('field');
                                if (field) {
                                    variationPayload.default[field] = getFieldValue($(this));
                                }
                            });

                            var $variationTable = $row.find('.mulopimfwc-classic-variation-location-table[data-variation-id=\"' + variationId + '\"]').first();
                            $variationTable.find('tbody tr.mulopimfwc-classic-variation-location-row').each(function() {
                                var $variationLocationRow = $(this);
                                var locationId = parseInt($variationLocationRow.data('location-id'), 10);
                                if (isNaN(locationId) || locationId <= 0) {
                                    return;
                                }
                                var variationLocationPayload = {
                                    id: locationId
                                };
                                $variationLocationRow.find('[data-field]').each(function() {
                                    var field = $(this).data('field');
                                    if (field) {
                                        variationLocationPayload[field] = getFieldValue($(this));
                                    }
                                });
                                variationPayload.locations.push(variationLocationPayload);
                            });

                            payload.variations.push(variationPayload);
                        });

                        return payload;
                    }

                    function saveRow($row) {
                        var deferred = $.Deferred();
                        var validation = validateClassicRow($row);
                        if (!validation.valid) {
                            setRowStatus($row, '');
                            setRowDirty($row, true);
                            deferred.reject({
                                validation: true,
                                message: validation.message || ''
                            });
                            return deferred.promise();
                        }

                        var payload = collectRowPayload($row);
                        var $saveButton = $row.find('.mulopimfwc-classic-save-row');
                        var $resetButton = $row.find('.mulopimfwc-classic-reset-row');

                        setRowStatus($row, '<?php echo esc_js(__('Saving...', 'multi-location-product-and-inventory-management')); ?>');
                        $saveButton.prop('disabled', true).text('...');
                        $resetButton.prop('disabled', true);

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: payload
                        }).done(function(response) {
                            if (response && response.success) {
                                refreshRowAsSaved($row);
                                setRowStatus($row, '<?php echo esc_js(__('Saved', 'multi-location-product-and-inventory-management')); ?>', 'success');
                                deferred.resolve(response);
                            } else {
                                var message = response && response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Unable to save this product row.', 'multi-location-product-and-inventory-management')); ?>';
                                setRowStatus($row, message, 'error');
                                setRowDirty($row, true);
                                deferred.reject({
                                    message: message
                                });
                            }
                        }).fail(function() {
                            var ajaxErrorMessage = '<?php echo esc_js(__('AJAX error while saving.', 'multi-location-product-and-inventory-management')); ?>';
                            setRowStatus($row, ajaxErrorMessage, 'error');
                            setRowDirty($row, true);
                            deferred.reject({
                                message: ajaxErrorMessage
                            });
                        }).always(function() {
                            $saveButton.html('&#10003;');
                            $resetButton.prop('disabled', !$row.hasClass('is-dirty'));
                        });

                        return deferred.promise();
                    }

                    $('.mulopimfwc-classic-product-row').each(function() {
                        var $row = $(this);
                        storeRowSnapshot($row);
                        applyClassicManageStockVisibility($row);
                        syncAllLocationsOption($row.find('.mulopimfwc-classic-add-location-select').first());
                        setRowDirty($row, false);
                    });
                    clearSaveAllFailures();
                    setSaveAllProgressText('');
                    setSaveAllRunning(false);
                    updateDirtyCount();

                    $(document).on('input change', '.mulopimfwc-classic-product-row .column-classic_manage_stock [data-field], .mulopimfwc-classic-product-row .column-classic_default [data-field], .mulopimfwc-classic-product-row .column-classic_location_wise [data-field], .mulopimfwc-classic-product-row .column-classic_purchase [data-field]', function() {
                        var $row = $(this).closest('.mulopimfwc-classic-product-row');
                        var hadValidationErrors = rowHasValidationErrors($row);
                        clearFieldValidationError($(this));
                        applyClassicManageStockVisibility($row);
                        setRowStatus($row, '');
                        setRowDirty($row, isRowDirty($row));
                        if (hadValidationErrors) {
                            validateClassicRow($row);
                        }
                    });

                    $(document).on('click', '.mulopimfwc-classic-add-location-btn', function() {
                        var $row = $(this).closest('.mulopimfwc-classic-product-row');
                        var hadValidationErrors = rowHasValidationErrors($row);
                        var $select = $row.find('.mulopimfwc-classic-add-location-select').first();
                        var rowDefaultCurrencySymbol = getDefaultCurrencySymbolForRow($row);
                        var rowDefaultCurrencyRate = getDefaultCurrencyRateForRow($row);
                        var rowDefaultCurrencyShouldConvert = getDefaultCurrencyShouldConvertForRow($row);
                        var selectedValue = ($select.val() || '').toString();
                        if (!selectedValue) {
                            return;
                        }

                        var locationsToAdd = [];
                        if (selectedValue === '__all__') {
                            $select.find('option').each(function() {
                                var value = ($(this).val() || '').toString();
                                if (value === '' || value === '__all__') {
                                    return;
                                }
                                var locationId = parseInt(value, 10);
                                if (isNaN(locationId) || locationId <= 0) {
                                    return;
                                }
                                var $option = $(this);
                                locationsToAdd.push({
                                    id: locationId,
                                    name: $option.text(),
                                    currencySymbol: getOptionCurrencySymbol($option, rowDefaultCurrencySymbol),
                                    currencyRate: getOptionCurrencyRate($option, rowDefaultCurrencyRate),
                                    currencyShouldConvert: getOptionCurrencyShouldConvert($option, rowDefaultCurrencyShouldConvert)
                                });
                            });
                        } else {
                            var locationId = parseInt(selectedValue, 10);
                            if (isNaN(locationId) || locationId <= 0) {
                                return;
                            }
                            var $selectedOption = $select.find('option:selected');
                            locationsToAdd.push({
                                id: locationId,
                                name: $selectedOption.text(),
                                currencySymbol: getOptionCurrencySymbol($selectedOption, rowDefaultCurrencySymbol),
                                currencyRate: getOptionCurrencyRate($selectedOption, rowDefaultCurrencyRate),
                                currencyShouldConvert: getOptionCurrencyShouldConvert($selectedOption, rowDefaultCurrencyShouldConvert)
                            });
                        }

                        if (!locationsToAdd.length) {
                            syncAllLocationsOption($select);
                            return;
                        }

                        var productType = (($row.attr('data-product-type') || '') + '').toLowerCase();
                        var supportsManageStock = isStoreManageStockEnabled && ['grouped', 'external'].indexOf(productType) === -1;
                        var rowMode = 'full';
                        if (productType === 'variable' || productType === 'grouped') {
                            rowMode = 'location_only';
                        } else if (productType === 'external' || !supportsManageStock) {
                            rowMode = 'price_only';
                        }

                        $row.find('.mulopimfwc-classic-product-location-table .mulopimfwc-classic-no-locations-row').remove();
                        var $productTbody = $row.find('.mulopimfwc-classic-product-location-table tbody');
                        var $variationTables = $row.find('.mulopimfwc-classic-variation-location-table');
                        $variationTables.each(function() {
                            var $variationTable = $(this);
                            $variationTable.find('.mulopimfwc-classic-no-locations-row').remove();
                        });

                        var addedCount = 0;
                        locationsToAdd.forEach(function(locationData) {
                            if ($row.find('.mulopimfwc-classic-product-location-row[data-location-id=\"' + locationData.id + '\"]').length) {
                                return;
                            }

                            $productTbody.append(buildProductLocationRow(locationData.id, locationData.name, supportsManageStock, rowMode, locationData.currencySymbol, locationData.currencyRate, locationData.currencyShouldConvert));

                            $variationTables.each(function() {
                                var $variationTable = $(this);
                                if ($variationTable.find('.mulopimfwc-classic-variation-location-row[data-location-id=\"' + locationData.id + '\"]').length) {
                                    return;
                                }
                                $variationTable.find('tbody').append(buildVariationLocationRow(locationData.id, locationData.name, isStoreManageStockEnabled, locationData.currencySymbol, locationData.currencyRate, locationData.currencyShouldConvert));
                            });

                            $select.find('option[value=\"' + locationData.id + '\"]').remove();
                            addedCount++;
                        });

                        $select.val('');
                        syncAllLocationsOption($select);
                        if (addedCount > 0) {
                            applyClassicManageStockVisibility($row);
                            setRowDirty($row, true);
                            if (hadValidationErrors) {
                                validateClassicRow($row);
                            }
                        }
                    });

                    $(document).on('click', '.mulopimfwc-classic-remove-location', function() {
                        var $locationRow = $(this).closest('.mulopimfwc-classic-product-location-row');
                        var $row = $(this).closest('.mulopimfwc-classic-product-row');
                        var hadValidationErrors = rowHasValidationErrors($row);
                        var locationId = parseInt($locationRow.data('location-id'), 10);
                        var locationName = ($locationRow.data('location-name') || '').toString();
                        var locationCurrencySymbol = normalizeClassicCurrencySymbol($locationRow.attr('data-currency-symbol') || getDefaultCurrencySymbolForRow($row));
                        var locationCurrencyRate = normalizeClassicCurrencyRate($locationRow.attr('data-currency-rate') || getDefaultCurrencyRateForRow($row));
                        var locationCurrencyShouldConvert = normalizeClassicCurrencyShouldConvert($locationRow.attr('data-currency-should-convert') || getDefaultCurrencyShouldConvertForRow($row));

                        if (isNaN(locationId) || locationId <= 0) {
                            return;
                        }

                        $locationRow.remove();
                        $row.find('.mulopimfwc-classic-variation-location-row[data-location-id=\"' + locationId + '\"]').remove();

                        var productTableColspan = $row.find('.mulopimfwc-classic-product-location-table thead th').length || 6;
                        ensureNoLocationRow($row.find('.mulopimfwc-classic-product-location-table'), productTableColspan);
                        $row.find('.mulopimfwc-classic-variation-location-table').each(function() {
                            var variationTableColspan = $(this).find('thead th').length || 5;
                            ensureNoLocationRow($(this), variationTableColspan);
                        });

                        var $select = $row.find('.mulopimfwc-classic-add-location-select').first();
                        if ($select.find('option[value=\"' + locationId + '\"]').length === 0) {
                            $select.append('<option value=\"' + locationId + '\" data-currency-symbol=\"' + escapeHtml(locationCurrencySymbol) + '\" data-currency-rate=\"' + escapeHtml(String(locationCurrencyRate)) + '\" data-currency-should-convert=\"' + escapeHtml(locationCurrencyShouldConvert ? 'yes' : 'no') + '\">' + escapeHtml(locationName) + '</option>');
                        }
                        syncAllLocationsOption($select);
                        applyClassicManageStockVisibility($row);
                        setRowDirty($row, true);
                        if (hadValidationErrors) {
                            validateClassicRow($row);
                        }
                    });

                    $(document).on('click', '.mulopimfwc-classic-reset-row', function() {
                        var $row = $(this).closest('.mulopimfwc-classic-product-row');
                        resetRowFromSnapshot($row);
                    });

                    $(document).on('click', '.mulopimfwc-classic-save-row', function() {
                        var $row = $(this).closest('.mulopimfwc-classic-product-row');
                        if (!$row.hasClass('is-dirty')) {
                            return;
                        }

                        saveRow($row).done(function(response) {
                            if (response && response.data && response.data.message) {
                                showNotice(response.data.message, 'success');
                            }
                        }).fail(function(errorData) {
                            if (errorData && errorData.validation) {
                                return;
                            }
                            var message = errorData && errorData.message ? errorData.message : errorData;
                            if (message) {
                                showNotice(message, 'error');
                            }
                        });
                    });

                    $('#mulopimfwc-classic-save-all').on('click', function() {
                        runSaveAllQueue($('.mulopimfwc-classic-product-row.is-dirty').toArray());
                    });

                    $('#mulopimfwc-classic-retry-failed').on('click', function() {
                        if (saveAllInProgress) {
                            return;
                        }

                        if (!failedSaveAllRows.length) {
                            showNotice('<?php echo esc_js(__('No failed product rows to retry.', 'multi-location-product-and-inventory-management')); ?>', 'info');
                            return;
                        }

                        var retryRows = [];
                        failedSaveAllRows.forEach(function(item) {
                            var productId = parseInt(item.productId, 10);
                            if (isNaN(productId) || productId <= 0) {
                                return;
                            }
                            var $row = $('.mulopimfwc-classic-product-row[data-product-id=\"' + productId + '\"]').first();
                            if ($row.length && $row.hasClass('is-dirty')) {
                                retryRows.push($row.get(0));
                            }
                        });

                        if (!retryRows.length) {
                            clearSaveAllFailures();
                            setSaveAllProgressText('');
                            showNotice('<?php echo esc_js(__('No failed product rows are currently pending changes.', 'multi-location-product-and-inventory-management')); ?>', 'info');
                            return;
                        }

                        runSaveAllQueue(retryRows);
                    });

                    $('#mulopimfwc-classic-reset-all').on('click', function() {
                        if (saveAllInProgress) {
                            return;
                        }

                        var dirtyRows = $('.mulopimfwc-classic-product-row.is-dirty').toArray();
                        if (!dirtyRows.length) {
                            showNotice('<?php echo esc_js(__('No unsaved product rows.', 'multi-location-product-and-inventory-management')); ?>', 'info');
                            return;
                        }

                        dirtyRows.forEach(function(row) {
                            resetRowFromSnapshot($(row));
                        });

                        clearSaveAllFailures();
                        setSaveAllProgressText('');
                        showNotice('<?php echo esc_js(__('All unsaved product row changes were reset.', 'multi-location-product-and-inventory-management')); ?>', 'success');
                    });
                });
            })(jQuery);
        </script>
<?php
    }
}
