(function ($) {
    'use strict';

    $(document).ready(function () {
        const adminAjaxUrl = (
            typeof mulopimfwcImportExport !== 'undefined' &&
            mulopimfwcImportExport &&
            typeof mulopimfwcImportExport.ajax_url === 'string' &&
            mulopimfwcImportExport.ajax_url.trim() !== ''
        ) ? mulopimfwcImportExport.ajax_url : (window.ajaxurl || '');

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
                url: adminAjaxUrl,
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

        // Clear Plugin Cache
        $('#mulopimfwc_clear_cache').on('click', function (e) {
            e.preventDefault();

            if (mulopimfwcImportExport.strings.confirm_clear_cache &&
                !confirm(mulopimfwcImportExport.strings.confirm_clear_cache)) {
                return;
            }

            const $button = $(this);
            const originalText = $button.html();
            const $statusDiv = $('#mulopimfwc_clear_cache_status');

            $button.prop('disabled', true)
                .html('<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear; margin-top: 3px;"></span> ' +
                    mulopimfwcImportExport.strings.clearing_cache);

            $.ajax({
                url: adminAjaxUrl,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_clear_cache',
                    nonce: mulopimfwcImportExport.nonce
                },
                success: function (response) {
                    if (response.success) {
                        showNotice('success', response.data.message || mulopimfwcImportExport.strings.clear_cache_success, $statusDiv);
                    } else {
                        showNotice('error', response.data.message || mulopimfwcImportExport.strings.clear_cache_error, $statusDiv);
                    }
                },
                error: function () {
                    showNotice('error', mulopimfwcImportExport.strings.clear_cache_error, $statusDiv);
                },
                complete: function () {
                    $button.prop('disabled', false).html(originalText);
                }
            });
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
                url: adminAjaxUrl,
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
    const cfg = window.mulopimfwcImportExport || {};
    const ajaxUrl = (
        typeof cfg.ajax_url === 'string' &&
        cfg.ajax_url.trim() !== ''
    ) ? cfg.ajax_url : (window.ajaxurl || '');
    const nonce = cfg.nonce || '';
    const fullExportAction = cfg.full_export_action || 'mulopimfwc_export_full_products_csv';
    const fullImportAction = cfg.full_import_action || 'mulopimfwc_import_full_products_csv';
    const $stockCentralStatus = $('#mulopimfwc-stock-central-import-export-status');
    const $stockCentralStatusMessage = $stockCentralStatus.find('.mulopimfwc-stock-central-import-export-status-message');
    const $stockCentralViewLogBtn = $stockCentralStatus.find('.mulopimfwc-stock-central-view-log');
    const $stockCentralLogPanel = $('#mulopimfwc-stock-central-import-export-log-panel');
    const $stockCentralLogList = $('#mulopimfwc-stock-central-import-export-log-list');
    const maxLogLines = 250;

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#039;'
            }[char];
        });
    }

    function getNowTimeLabel() {
        try {
            return new Date().toLocaleTimeString([], { hour12: false });
        } catch (_e) {
            return '';
        }
    }

    function ensureLogEmptyState() {
        if (!$stockCentralLogList.length) {
            return;
        }
        if ($stockCentralLogList.children().length) {
            return;
        }
        $stockCentralLogList.html('<div class="mulopimfwc-stock-central-log-empty">No logs yet.</div>');
    }

    function trimLogLines() {
        if (!$stockCentralLogList.length) {
            return;
        }
        const $rows = $stockCentralLogList.find('.mulopimfwc-stock-central-log-entry');
        const oversize = $rows.length - maxLogLines;
        if (oversize > 0) {
            $rows.slice(0, oversize).remove();
        }
    }

    function refreshViewLogState() {
        if (!$stockCentralViewLogBtn.length) {
            return;
        }
        const hasRows = $stockCentralLogList.length && $stockCentralLogList.find('.mulopimfwc-stock-central-log-entry').length > 0;
        $stockCentralViewLogBtn.prop('hidden', !hasRows);
        if (!hasRows) {
            $stockCentralLogPanel.prop('hidden', true);
        }
    }

    function appendStockCentralLog(message, type) {
        if (!$stockCentralLogList.length || !message) {
            return;
        }
        const safeMessage = String(message).trim();
        if (safeMessage === '') {
            return;
        }
        const normalizedType = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
        const entryClass = normalizedType === 'info' ? 'mulopimfwc-stock-central-log-entry' : ('mulopimfwc-stock-central-log-entry is-' + normalizedType);
        const timeLabel = escapeHtml(getNowTimeLabel());
        const rowHtml = '<div class="' + entryClass + '">' +
            '<span class="mulopimfwc-stock-central-log-time">[' + timeLabel + ']</span>' +
            '<span class="mulopimfwc-stock-central-log-text">' + escapeHtml(safeMessage) + '</span>' +
            '</div>';

        $stockCentralLogList.find('.mulopimfwc-stock-central-log-empty').remove();
        $stockCentralLogList.append(rowHtml);
        trimLogLines();
        refreshViewLogState();
        $stockCentralLogList.scrollTop($stockCentralLogList[0].scrollHeight);
    }

    function appendValidationLines(data) {
        if (!data || typeof data !== 'object') {
            return;
        }
        const errors = Array.isArray(data.errors) ? data.errors : [];
        const warnings = Array.isArray(data.warnings) ? data.warnings : [];
        const maxLinesPerType = 30;

        if (errors.length) {
            errors.slice(0, maxLinesPerType).forEach(function (line) {
                appendStockCentralLog(line, 'error');
            });
            if (errors.length > maxLinesPerType) {
                appendStockCentralLog('...and ' + (errors.length - maxLinesPerType) + ' more errors.', 'error');
            }
        }

        if (warnings.length) {
            warnings.slice(0, maxLinesPerType).forEach(function (line) {
                appendStockCentralLog(line, 'info');
            });
            if (warnings.length > maxLinesPerType) {
                appendStockCentralLog('...and ' + (warnings.length - maxLinesPerType) + ' more warnings.', 'info');
            }
        }
    }

    function normalizeLogType(level) {
        const normalized = String(level || '').toLowerCase();
        if (normalized === 'error') {
            return 'error';
        }
        if (normalized === 'success') {
            return 'success';
        }
        return 'info';
    }

    function appendServerLogs(data) {
        if (!data || typeof data !== 'object' || !Array.isArray(data.logs)) {
            return;
        }
        data.logs.forEach(function (entry) {
            if (typeof entry === 'string') {
                appendStockCentralLog(entry, 'info');
                return;
            }
            if (!entry || typeof entry !== 'object') {
                return;
            }
            const message = (entry.message || '').toString();
            if (message.trim() === '') {
                return;
            }
            appendStockCentralLog(message, normalizeLogType(entry.level));
        });
    }

    function startImportProgressHints(mode) {
        const isDryRun = String(mode || '').toLowerCase() === 'dry_run';
        const steps = isDryRun ? [
            'Dry-run step: reading and validating CSV rows...',
            'Dry-run step: checking taxonomies, terms, and locations...',
            'Dry-run step: validating products and variations...',
            'Dry-run step: validating relationships and location inventory...'
        ] : [
            'Apply step: processing taxonomies and locations...',
            'Apply step: mapping media and processing products...',
            'Apply step: processing variations and relationships...',
            'Apply step: syncing location inventory and finalizing...'
        ];

        let stepIndex = 0;
        const intervalMs = 3200;
        const intervalId = window.setInterval(function () {
            if (stepIndex < steps.length) {
                appendStockCentralLog(steps[stepIndex], 'info');
                stepIndex += 1;
                return;
            }

            if ((stepIndex - steps.length) < 3) {
                appendStockCentralLog('Import request is still running on the server...', 'info');
                stepIndex += 1;
                return;
            }

            window.clearInterval(intervalId);
        }, intervalMs);

        return function stopImportProgressHints() {
            window.clearInterval(intervalId);
        };
    }

    function base64ToBlob(base64, type) {
        const binary = atob(base64 || '');
        const len = binary.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return new Blob([bytes], { type: type || 'text/csv;charset=utf-8;' });
    }

    function triggerDownloadBlob(blob, filename) {
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function getValue(...selectors) {
        for (const selector of selectors) {
            const $el = $(selector);
            if ($el.length) {
                return ($el.val() || '').toString();
            }
        }
        return '';
    }

    function getChecked(...selectors) {
        for (const selector of selectors) {
            const $el = $(selector);
            if ($el.length) {
                return !!$el.is(':checked');
            }
        }
        return false;
    }

    function getImportOptions(mode, confirmed) {
        return {
            mode: mode || 'dry_run',
            confirmed: !!confirmed,
            auto_create_terms: getChecked('#mulopimfwc-stock-central-auto-create-terms', '#mulopimfwc_import_auto_create_terms'),
            sync_location_profile: getChecked('#mulopimfwc-stock-central-sync-location-profile', '#mulopimfwc_import_sync_location_profile'),
            import_media: getChecked('#mulopimfwc-stock-central-import-media', '#mulopimfwc_import_media'),
            meta_whitelist: getValue('#mulopimfwc-stock-central-custom-meta', '#mulopimfwc_import_meta_whitelist'),
            field_mode: 'set_non_empty'
        };
    }

    function setSettingsProgress(message, percent) {
        const $container = $('#export-progress');
        const $bar = $('#export-progress-bar');
        const $text = $('#export-status-text');
        if (!$container.length) {
            return;
        }
        if (message) {
            $container.show();
            if ($bar.length && typeof percent === 'number') {
                $bar.val(percent);
            }
            $text.text(message);
        } else {
            $container.hide();
            $bar.val(0);
            $text.text('');
        }
    }

    function setStockCentralStatus(message, type) {
        if (!$stockCentralStatus.length) {
            return;
        }
        if (!message) {
            $stockCentralStatus.hide().removeClass('is-error is-success');
            if ($stockCentralStatusMessage.length) {
                $stockCentralStatusMessage.text('');
            } else {
                $stockCentralStatus.text('');
            }
            return;
        }

        if ($stockCentralStatusMessage.length) {
            $stockCentralStatusMessage.text(message);
        } else {
            $stockCentralStatus.text(message);
        }
        $stockCentralStatus.show();
        $stockCentralStatus.toggleClass('is-error', type === 'error');
        $stockCentralStatus.toggleClass('is-success', type === 'success');
        appendStockCentralLog(message, type);
    }

    function renderSummary(prefix, summary, errors, warnings) {
        let msg = prefix;
        if (summary) {
            const parts = [];
            if (typeof summary.rows_processed !== 'undefined') parts.push('Processed: ' + summary.rows_processed);
            if (typeof summary.rows_failed !== 'undefined') parts.push('Failed: ' + summary.rows_failed);
            if (typeof summary.products_created !== 'undefined') parts.push('Created products: ' + summary.products_created);
            if (typeof summary.products_updated !== 'undefined') parts.push('Updated products: ' + summary.products_updated);
            if (typeof summary.variations_created !== 'undefined') parts.push('Created variations: ' + summary.variations_created);
            if (typeof summary.variations_updated !== 'undefined') parts.push('Updated variations: ' + summary.variations_updated);
            if (typeof summary.categories_created !== 'undefined') parts.push('Created Category: ' + summary.categories_created);
            if (typeof summary.locations_created !== 'undefined') parts.push('Created Locations: ' + summary.locations_created);
            if (typeof summary.tags_created !== 'undefined') parts.push('Created Tags: ' + summary.tags_created);
            if (typeof summary.brands_created !== 'undefined') parts.push('Created Brands: ' + summary.brands_created);
            if (typeof summary.attributes_created !== 'undefined') parts.push('Created Attribute: ' + summary.attributes_created);
            if (parts.length) msg += ' ' + parts.join(' | ');
        }
        if (errors && errors.length) {
            msg += ' | Errors: ' + errors.length;
        }
        if (warnings && warnings.length) {
            msg += ' | Warnings: ' + warnings.length;
        }
        return msg;
    }

    function downloadFailedRowsIfAny(base64) {
        if (!base64) {
            return;
        }
        const blob = base64ToBlob(base64, 'text/csv;charset=utf-8;');
        triggerDownloadBlob(blob, 'mulopimfwc-import-failed-rows.csv');
        appendStockCentralLog('Failed rows report downloaded (mulopimfwc-import-failed-rows.csv).', 'info');
    }

    function runFullImport(file, mode, confirmed, onDone) {
        if (!ajaxUrl) {
            const msg = 'Import failed: missing admin AJAX URL.';
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'error');
            if (typeof onDone === 'function') onDone(false, { message: msg });
            return;
        }

        const formData = new FormData();
        formData.append('action', fullImportAction);
        formData.append('nonce', nonce);
        formData.append('csv_file', file);
        formData.append('options', JSON.stringify(getImportOptions(mode, confirmed)));
        appendStockCentralLog('Import pipeline request sent to server. Waiting for pass-by-pass result details...', 'info');
        const stopProgressHints = startImportProgressHints(mode);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function (response) {
            stopProgressHints();
            if (!response || !response.success) {
                const data = response && response.data ? response.data : {};
                setSettingsProgress(data.message || cfg.strings.full_import_error || 'Import failed', 100);
                setStockCentralStatus(data.message || cfg.strings.full_import_error || 'Import failed', 'error');
                appendServerLogs(data);
                if (!Array.isArray(data.logs) || !data.logs.length) {
                    appendValidationLines(data);
                }
                if (data.failed_rows_csv_base64) {
                    downloadFailedRowsIfAny(data.failed_rows_csv_base64);
                }
                if (typeof onDone === 'function') onDone(false, data);
                return;
            }

            const data = response.data || {};
            const msg = renderSummary(data.message || cfg.strings.full_import_success || 'Import completed', data.summary, data.errors, data.warnings);
            const hasErrors = Array.isArray(data.errors) && data.errors.length > 0;
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, hasErrors ? 'error' : 'success');
            appendServerLogs(data);
            if (data.job && data.job.job_id) {
                appendStockCentralLog('Job ID: ' + data.job.job_id + ' (' + (data.job.status || 'completed') + ')', hasErrors ? 'error' : 'success');
            }
            if (!Array.isArray(data.logs) || !data.logs.length) {
                appendValidationLines(data);
            }
            if (data.failed_rows_csv_base64) {
                downloadFailedRowsIfAny(data.failed_rows_csv_base64);
            }
            if (typeof onDone === 'function') onDone(true, data);
        }).fail(function (_xhr, _status, error) {
            stopProgressHints();
            let detail = error || '';
            if (_xhr && typeof _xhr.responseText === 'string') {
                const raw = _xhr.responseText.trim();
                if (raw && raw.charAt(0) === '<') {
                    detail = 'Server returned HTML instead of JSON.';
                }
            }
            const msg = (cfg.strings.full_import_error || 'Import failed') + ': ' + detail;
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'error');
            if (_xhr && typeof _xhr.responseText === 'string') {
                const rawResponse = _xhr.responseText.trim();
                if (rawResponse !== '') {
                    const excerpt = rawResponse.length > 500 ? (rawResponse.slice(0, 500) + '...') : rawResponse;
                    appendStockCentralLog('Server response: ' + excerpt, 'error');
                }
            }
            if (typeof onDone === 'function') onDone(false, { message: msg });
        });
    }

    function handleImportFile(file) {
        if (!file || !/\.csv$/i.test(file.name || '')) {
            alert(cfg.strings.invalid_csv_file || 'Please select a valid CSV file.');
            return;
        }

        appendStockCentralLog('File selected: ' + (file.name || 'unnamed.csv') + ' (' + (file.size || 0) + ' bytes)', 'info');

        setSettingsProgress(cfg.strings.full_importing_dry_run || 'Running dry run...', 20);
        setStockCentralStatus(cfg.strings.full_importing_dry_run || 'Running dry run...');

        runFullImport(file, 'dry_run', false, function (ok, data) {
            if (!ok) return;
            const confirmMsg = (cfg.strings.confirm_dry_run_apply || 'Dry run completed. Continue with import?') +
                '\n\n' +
                renderSummary('Dry run summary:', data.summary, data.errors, data.warnings);
            if (!window.confirm(confirmMsg)) {
                appendStockCentralLog('Import cancelled by user after dry-run.', 'info');
                return;
            }

            const selectedMode = getValue('#mulopimfwc-stock-central-import-mode', '#mulopimfwc_import_mode') || 'create_update';
            setSettingsProgress(cfg.strings.full_importing_apply || 'Applying import...', 55);
            setStockCentralStatus(cfg.strings.full_importing_apply || 'Applying import...');
            runFullImport(file, selectedMode, true, function () {
                setTimeout(function () {
                    setSettingsProgress('', 0);
                }, 4500);
            });
        });
    }

    $(document).on('click', '.mulopimfwc_export_products, .mulopimfwc-stock-central-export', function (e) {
        e.preventDefault();
        if (!ajaxUrl) {
            const msg = 'Export failed: missing admin AJAX URL.';
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'error');
            return;
        }
        const $btn = $(this);
        const original = $btn.html();
        $btn.prop('disabled', true).text(cfg.strings.full_exporting || 'Preparing export...');
        setSettingsProgress(cfg.strings.full_exporting || 'Preparing export...', 20);
        setStockCentralStatus(cfg.strings.full_exporting || 'Preparing export...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: fullExportAction,
                nonce: nonce,
                options: JSON.stringify({
                    meta_whitelist: getValue('#mulopimfwc-stock-central-custom-meta', '#mulopimfwc_import_meta_whitelist')
                })
            }
        }).done(function (response) {
            if (!response || !response.success) {
                const message = (response && response.data && response.data.message) || 'Export failed';
                setSettingsProgress(message, 100);
                setStockCentralStatus(message, 'error');
                return;
            }
            const data = response.data || {};
            const filename = data.filename || ('mulopimfwc-stock-central-full-' + Date.now() + '.csv');
            const blob = base64ToBlob(data.csv_base64, 'text/csv;charset=utf-8;');
            triggerDownloadBlob(blob, filename);
            const msg = renderSummary('Export completed.', data.summary, [], []);
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'success');
            appendStockCentralLog('Export file downloaded: ' + filename, 'success');
            setTimeout(function () { setSettingsProgress('', 0); }, 3500);
        }).fail(function (_xhr, _status, error) {
            const msg = 'Export failed: ' + error;
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'error');
        }).always(function () {
            $btn.prop('disabled', false).html(original);
        });
    });

    $(document).on('click', '#mulopimfwc_import_products_btn, .mulopimfwc-stock-central-import-btn', function (e) {
        e.preventDefault();
        const $fileInput = $('#mulopimfwc-stock-central-import-file').length
            ? $('#mulopimfwc-stock-central-import-file')
            : $('#mulopimfwc_import_products_file');
        $fileInput.trigger('click');
    });

    function updateStockCentralDropzoneFileLabel(fileName) {
        const fallback = 'No file selected';
        const $labels = $('.mulopimfwc-stock-central-dropzone-file');
        if (!$labels.length) {
            return;
        }
        $labels.each(function () {
            const $label = $(this);
            const emptyLabel = ($label.data('empty-label') || fallback).toString();
            const text = (fileName || '').toString().trim();
            $label.text(text !== '' ? text : emptyLabel);
        });
    }

    $(document).on('change', '#mulopimfwc_import_products_file, #mulopimfwc-stock-central-import-file', function () {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (file) {
            updateStockCentralDropzoneFileLabel(file.name);
        } else {
            updateStockCentralDropzoneFileLabel('');
        }
        this.value = '';
        if (!file) return;
        handleImportFile(file);
    });

    function setImportExportTab($menu, targetTab) {
        if (!$menu || !$menu.length) {
            return;
        }
        const tab = targetTab === 'import' ? 'import' : 'export';
        $menu.find('.mulopimfwc-ie-tab').each(function () {
            const $btn = $(this);
            const isActive = $btn.data('ie-tab') === tab;
            $btn.toggleClass('is-active', isActive);
            $btn.attr('aria-selected', isActive ? 'true' : 'false');
        });
        $menu.find('.mulopimfwc-ie-panel').each(function () {
            const $panel = $(this);
            const isActive = $panel.data('ie-panel') === tab;
            $panel.toggleClass('is-active', isActive);
            $panel.prop('hidden', !isActive);
        });
    }

    function setStockCentralImportExportMenuState($wrap, isOpen) {
        const $toggle = $wrap.find('.mulopimfwc-stock-central-import-export-toggle').first();
        const $menu = $wrap.find('.mulopimfwc-import-export-menu').first();
        if (!$toggle.length || !$menu.length) {
            return;
        }
        $menu.prop('hidden', !isOpen);
        $toggle.attr('aria-expanded', isOpen ? 'true' : 'false');
        $toggle.toggleClass('is-open', !!isOpen);
        if (!isOpen) {
            $menu.find('.mulopimfwc-stock-central-dropzone').removeClass('is-dragover');
        }
    }

    function closeAllStockCentralImportExportMenus() {
        $('.mulopimfwc-import-export-wrap').each(function () {
            setStockCentralImportExportMenuState($(this), false);
        });
    }

    $('.mulopimfwc-import-export-wrap').each(function () {
        const $wrap = $(this);
        const $menu = $wrap.find('.mulopimfwc-import-export-menu').first();
        if ($menu.length) {
            setImportExportTab($menu, 'export');
        }
        setStockCentralImportExportMenuState($wrap, false);
    });

    $(document).on('click', '.mulopimfwc-ie-tab', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $menu = $btn.closest('.mulopimfwc-import-export-menu');
        const tab = ($btn.data('ie-tab') || '').toString();
        setImportExportTab($menu, tab);
    });

    $(document).on('click', '.mulopimfwc-stock-central-import-export-toggle', function (e) {
        e.preventDefault();
        const $wrap = $(this).closest('.mulopimfwc-import-export-wrap');
        const isOpen = $(this).attr('aria-expanded') === 'true';
        closeAllStockCentralImportExportMenus();
        setStockCentralImportExportMenuState($wrap, !isOpen);
    });

    $(document).on('click', '.mulopimfwc-import-export-menu .mulopimfwc-stock-central-export', function () {
        closeAllStockCentralImportExportMenus();
    });

    $(document).on('click', '.mulopimfwc-stock-central-view-log', function (e) {
        e.preventDefault();
        if (!$stockCentralLogPanel.length) {
            return;
        }
        $stockCentralLogPanel.prop('hidden', false);
        if ($stockCentralLogList.length) {
            $stockCentralLogList.scrollTop($stockCentralLogList[0].scrollHeight);
        }
    });

    $(document).on('click', '.mulopimfwc-stock-central-log-close', function (e) {
        e.preventDefault();
        $stockCentralLogPanel.prop('hidden', true);
    });

    $(document).on('click', '.mulopimfwc-stock-central-log-clear', function (e) {
        e.preventDefault();
        if (!$stockCentralLogList.length) {
            return;
        }
        $stockCentralLogList.empty();
        ensureLogEmptyState();
        refreshViewLogState();
    });

    $(document).on('click', '.mulopimfwc-stock-central-dropzone', function (e) {
        e.preventDefault();
        const $fileInput = $('#mulopimfwc-stock-central-import-file').length
            ? $('#mulopimfwc-stock-central-import-file')
            : $('#mulopimfwc_import_products_file');
        $fileInput.trigger('click');
    });

    $(document).on('keydown', '.mulopimfwc-stock-central-dropzone', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        e.preventDefault();
        const $fileInput = $('#mulopimfwc-stock-central-import-file').length
            ? $('#mulopimfwc-stock-central-import-file')
            : $('#mulopimfwc_import_products_file');
        $fileInput.trigger('click');
    });

    $(document).on('dragenter dragover', '.mulopimfwc-stock-central-dropzone', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('is-dragover');
    });

    $(document).on('dragleave dragend drop', '.mulopimfwc-stock-central-dropzone', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('is-dragover');
    });

    $(document).on('drop', '.mulopimfwc-stock-central-dropzone', function (e) {
        const dt = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer : null;
        const file = dt && dt.files && dt.files[0] ? dt.files[0] : null;
        if (!file) {
            return;
        }
        updateStockCentralDropzoneFileLabel(file.name);
        handleImportFile(file);
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllStockCentralImportExportMenus();
        }
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.mulopimfwc-import-export-wrap').length) {
            closeAllStockCentralImportExportMenus();
        }
    });

    ensureLogEmptyState();
    refreshViewLogState();
});
