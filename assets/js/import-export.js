(function ($) {
    'use strict';

    $(document).ready(function () {

        // Export Settings
        $('#mulopimfwc_export_settings').on('click', function (e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.html();

            // Disable button and show loading state
            $button.prop('disabled', true)
                .html('<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear; margin-top: 3px;"></span> ' +
                    mulopimfwcImportExport.strings.exporting);

            $.ajax({
                url: mulopimfwcImportExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_export_settings',
                    nonce: mulopimfwcImportExport.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Create download link
                        const dataStr = 'data:text/json;charset=utf-8,' +
                            encodeURIComponent(JSON.stringify(response.data.data, null, 2));
                        const downloadAnchor = document.createElement('a');
                        downloadAnchor.setAttribute('href', dataStr);
                        downloadAnchor.setAttribute('download', response.data.filename);
                        document.body.appendChild(downloadAnchor);
                        downloadAnchor.click();
                        downloadAnchor.remove();

                        // Show success message
                        showNotice('success', mulopimfwcImportExport.strings.export_success);
                    } else {
                        showNotice('error', response.data.message || mulopimfwcImportExport.strings.export_error);
                    }
                },
                error: function () {
                    showNotice('error', mulopimfwcImportExport.strings.export_error);
                },
                complete: function () {
                    // Reset button
                    $button.prop('disabled', false).html(originalText);
                }
            });
        });

        // Import Settings - Trigger file input
        $('#mulopimfwc_import_settings_btn').on('click', function (e) {
            e.preventDefault();
            $('#mulopimfwc_import_settings').click();
        });

        // Import Settings - Handle file selection
        $('#mulopimfwc_import_settings').on('change', function (e) {
            const file = e.target.files[0];

            if (!file) {
                return;
            }

            // Validate file type
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                showNotice('error', mulopimfwcImportExport.strings.invalid_file);
                $(this).val('');
                return;
            }

            // Confirm before importing
            if (!confirm(mulopimfwcImportExport.strings.confirm_import)) {
                $(this).val('');
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                importSettings(event.target.result);
            };

            reader.onerror = function () {
                showNotice('error', mulopimfwcImportExport.strings.import_error);
            };

            reader.readAsText(file);

            // Reset file input
            $(this).val('');
        });

        // Import settings via AJAX
        function importSettings(jsonData) {
            const $statusDiv = $('#import-status');

            // Validate JSON first
            let parsedData;
            try {
                parsedData = JSON.parse(jsonData);
            } catch (e) {
                showNotice('error', mulopimfwcImportExport.strings.invalid_file, $statusDiv);
                return;
            }

            // Check if it has the expected structure
            if (!parsedData.settings || typeof parsedData.settings !== 'object') {
                showNotice('error', 'Invalid settings file structure.', $statusDiv);
                return;
            }

            $statusDiv.html('<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear;"></span> ' +
                mulopimfwcImportExport.strings.importing)
                .removeClass('notice-error notice-success')
                .addClass('notice notice-info')
                .show();

            $.ajax({
                url: mulopimfwcImportExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_import_settings',
                    nonce: mulopimfwcImportExport.nonce,
                    import_data: jsonData  // Send raw JSON string
                },
                success: function (response) {
                    if (response.success) {
                        const message = response.data.message;
                        const count = response.data.imported_count || 0;
                        showNotice('success', message + ' (' + count + ' settings imported)', $statusDiv);
                        // Reload page after 2 seconds
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice('error', response.data.message || mulopimfwcImportExport.strings.import_error, $statusDiv);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Import error:', textStatus, errorThrown);
                    showNotice('error', mulopimfwcImportExport.strings.import_error, $statusDiv);
                }
            });
        }

        // Show notice helper
        function showNotice(type, message, $element) {
            const $notice = $element || $('<div></div>').insertAfter('#mulopimfwc_import_settings_btn');

            $notice.removeClass('notice-error notice-success notice-info')
                .addClass('notice notice-' + type)
                .html('<p>' + message + '</p>')
                .show();

            // Auto-hide after 5 seconds
            setTimeout(function () {
                $notice.fadeOut();
            }, 5000);
        }
    });

    // Add CSS animation for loading spinner
    if (!document.getElementById('mulopimfwc-spinner-style')) {
        const style = document.createElement('style');
        style.id = 'mulopimfwc-spinner-style';
        style.textContent = '@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }';
        document.head.appendChild(style);
    }

})(jQuery);












jQuery(document).ready(function ($) {
    $('.mulopimfwc_export_products').on('click', function (e) {
        e.preventDefault();

        const button = $(this);
        const progressContainer = $('#export-progress');
        const progressBar = $('#export-progress-bar');
        const statusText = $('#export-status-text');
        const format = button.data('format') || 'csv';

        button.prop('disabled', true);
        progressContainer.show();
        progressBar.val(0);
        statusText.text('Preparing export...');

        if (format === 'csv') {
            // ---- CSV: your existing JSON -> CSV flow ----
            $.ajax({
                url: mulopimfwcImportExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_export_products_csv',
                    nonce: mulopimfwcImportExport.nonce
                },
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            progressBar.val((e.loaded / e.total) * 100);
                        }
                    }, false);
                    return xhr;
                },
                success: function (response) {
                    if (!response.success) {
                        statusText.text('Error: ' + (response.data?.message || 'Export failed'));
                        button.prop('disabled', false);
                        return;
                    }
                    statusText.text('Processing products...');
                    progressBar.val(50);

                    statusText.text('Generating CSV file...');
                    progressBar.val(75);

                    const csv = generateCSV(response.data.products, response.data.locations);
                    downloadCSV(csv, 'products-location-data-' + getCurrentDate() + '.csv');

                    progressBar.val(100);
                    statusText.text('Export completed! (' + response.data.count + ' products)');

                    setTimeout(resetUI, 3000);
                },
                error: function (_xhr, _status, error) {
                    statusText.text('Error: ' + error);
                    button.prop('disabled', false);
                    console.error('Export error:', error);
                }
            });
        }

        function resetUI() {
            progressContainer.hide();
            button.prop('disabled', false);
            progressBar.val(0);
            statusText.text('');
        }
    });


    /**
     * Generate CSV content from products data
     */
    function generateCSV(products, locations) {
        let csv = '';

        // Build CSV headers
        const headers = [
            'Product ID',
            'Product Name',
            'Product Type',
            'SKU'
        ];

        // Add location-specific headers
        locations.forEach(location => {
            headers.push(location.name + ' - Stock');
            headers.push(location.name + ' - Price');
            headers.push(location.name + ' - Status');
        });

        headers.push('Default Stock');
        headers.push('Default Price');
        headers.push('Purchase Price');
        headers.push('Purchase Quantity');
        headers.push('Gross Profit (Default)');

        // Add location gross profit headers
        locations.forEach(location => {
            headers.push(location.name + ' - Gross Profit');
        });

        csv += headers.map(header => escapeCSV(header)).join(',') + '\n';

        // Add product rows
        products.forEach(product => {
            if (product.type === 'variable' && product.variations.length > 0) {
                // Handle variable products - one row per variation
                product.variations.forEach(variation => {
                    const row = buildVariationRow(product, variation, locations);
                    csv += row.map(cell => escapeCSV(cell)).join(',') + '\n';
                });
            } else {
                // Handle simple products
                const row = buildProductRow(product, locations);
                csv += row.map(cell => escapeCSV(cell)).join(',') + '\n';
            }
        });

        return csv;
    }

    /**
     * Build CSV row for simple product
     */
    function buildProductRow(product, locations) {
        const row = [
            product.id,
            product.title,
            product.type,
            product.sku || ''
        ];

        // Add location-specific data
        locations.forEach(location => {
            const locationData = product.location_data[location.id] || {};
            row.push(locationData.stock || '0');
            row.push(locationData.price || product.default_price || '0');
            row.push(locationData.disabled ? 'Inactive' : 'Active');
        });

        // Add default data
        row.push(product.default_stock || '0');
        row.push(product.default_price || '0');
        row.push(product.purchase_price || 'N/A');
        row.push(product.purchase_quantity || 'N/A');

        // Calculate default gross profit
        const defaultProfit = calculateProfit(product.default_price, product.purchase_price);
        row.push(defaultProfit);

        // Calculate location-specific gross profit
        locations.forEach(location => {
            const locationData = product.location_data[location.id] || {};
            const locationPrice = locationData.price || product.default_price;
            const profit = calculateProfit(locationPrice, product.purchase_price);
            row.push(profit);
        });

        return row;
    }

    /**
     * Build CSV row for variable product variation
     */
    function buildVariationRow(product, variation, locations) {
        const variationTitle = product.title + ' - ' + variation.attributes_label;

        const row = [
            variation.id,
            variationTitle,
            'variation',
            variation.sku || ''
        ];

        // Add location-specific data
        locations.forEach(location => {
            const locationData = variation.location_data[location.id] || {};
            row.push(locationData.stock || '0');
            row.push(locationData.price || variation.price || '0');
            row.push(locationData.disabled ? 'Inactive' : 'Active');
        });

        // Add default data
        row.push(variation.stock || '0');
        row.push(variation.price || '0');
        row.push(variation.purchase_price || 'N/A');
        row.push('N/A'); // Purchase quantity not applicable for variations

        // Calculate default gross profit
        const defaultProfit = calculateProfit(variation.price, variation.purchase_price);
        row.push(defaultProfit);

        // Calculate location-specific gross profit
        locations.forEach(location => {
            const locationData = variation.location_data[location.id] || {};
            const locationPrice = locationData.price || variation.price;
            const profit = calculateProfit(locationPrice, variation.purchase_price);
            row.push(profit);
        });

        return row;
    }

    /**
     * Calculate profit and return formatted string
     */
    function calculateProfit(salePrice, purchasePrice) {
        if (!purchasePrice || !salePrice || purchasePrice <= 0) {
            return 'N/A';
        }

        const profit = parseFloat(salePrice) - parseFloat(purchasePrice);
        const percentage = (profit / parseFloat(purchasePrice)) * 100;

        return profit.toFixed(2) + ' (' + percentage.toFixed(2) + '%)';
    }

    /**
     * Escape CSV special characters
     */
    function escapeCSV(value) {
        if (value === null || value === undefined) {
            return '';
        }

        const stringValue = String(value);

        // If value contains comma, quotes, or newlines, wrap in quotes and escape quotes
        if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
            return '"' + stringValue.replace(/"/g, '""') + '"';
        }

        return stringValue;
    }

    /**
     * Trigger CSV download
     */
    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');

        if (navigator.msSaveBlob) {
            // IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    /**
     * Get current date in YYYY-MM-DD format
     */
    function getCurrentDate() {
        const date = new Date();
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
});