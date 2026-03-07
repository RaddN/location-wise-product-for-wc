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
    const ieV2Enabled = !!cfg.ie_v2_enabled;
    const ieActions = cfg.ie_actions || {};
    const uploadChunkSize = Number(cfg.upload_chunk_size || (8 * 1024 * 1024));
    const maxUploadBytes = Number(cfg.max_upload_bytes || (2 * 1024 * 1024 * 1024));
    const $stockCentralStatus = $('#mulopimfwc-stock-central-import-export-status');
    const $stockCentralStatusMessage = $stockCentralStatus.find('.mulopimfwc-stock-central-import-export-status-message');
    const $stockCentralActiveJobMeta = $stockCentralStatus.find('.mulopimfwc-stock-central-active-job-meta');
    const $stockCentralViewLogBtn = $stockCentralStatus.find('.mulopimfwc-stock-central-view-log');
    const $stockCentralPauseJobBtn = $stockCentralStatus.find('.mulopimfwc-stock-central-pause-job');
    const $stockCentralResumeJobBtn = $stockCentralStatus.find('.mulopimfwc-stock-central-resume-job');
    const $stockCentralCancelJobBtn = $stockCentralStatus.find('.mulopimfwc-stock-central-cancel-job');
    const $stockCentralLogPanel = $('#mulopimfwc-stock-central-import-export-log-panel');
    const $stockCentralLogList = $('#mulopimfwc-stock-central-import-export-log-list');
    const maxLogLines = 250;
    let statusContextJob = null;
    let activeWatchToken = 0;
    let activeWatchJobId = '';

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

    function postAjax(data) {
        return $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json'
        });
    }

    function postAjaxForm(formData) {
        return $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        });
    }

    function isFinalJobStatus(status) {
        return ['completed', 'failed', 'cancelled'].indexOf(String(status || '')) !== -1;
    }

    function summarizeJobStatus(job) {
        if (!job || typeof job !== 'object') {
            return '';
        }
        const parts = [];
        if (job.phase) parts.push('Phase: ' + job.phase);
        if (typeof job.progress_percent !== 'undefined') parts.push(Number(job.progress_percent).toFixed(2) + '%');
        if (typeof job.rows_processed !== 'undefined' && typeof job.rows_total !== 'undefined') {
            parts.push('Rows: ' + job.rows_processed + '/' + job.rows_total);
        }
        if (typeof job.rows_failed !== 'undefined' && Number(job.rows_failed) > 0) {
            parts.push('Failed: ' + job.rows_failed);
        }
        if (job.current_pass) {
            parts.push('Pass: ' + job.current_pass);
        }
        if (job.eta_seconds) {
            parts.push('ETA ~' + job.eta_seconds + 's');
        }
        return parts.join(' | ');
    }

    function updateActiveJobControls(job) {
        if (!$stockCentralStatus.length) {
            return;
        }

        const hasJob = !!(job && job.job_id);
        const status = String((job && job.status) || '');
        const type = String((job && job.type) || 'job');
        const isActive = hasJob && !isFinalJobStatus(status);

        const canPause = isActive && ['queued', 'running', 'uploading'].indexOf(status) !== -1;
        const canResume = isActive && status === 'paused';
        const canCancel = isActive;

        $stockCentralPauseJobBtn.prop('hidden', !canPause).prop('disabled', !canPause);
        $stockCentralResumeJobBtn.prop('hidden', !canResume).prop('disabled', !canResume);
        $stockCentralCancelJobBtn.prop('hidden', !canCancel).prop('disabled', !canCancel);

        if ($stockCentralActiveJobMeta.length) {
            if (isActive) {
                $stockCentralActiveJobMeta
                    .text('Active ' + type + ' job: ' + job.job_id)
                    .prop('hidden', false);
            } else {
                $stockCentralActiveJobMeta.text('').prop('hidden', true);
            }
        }
    }

    function streamJobEvents(jobId, cursorRef) {
        return postAjax({
            action: ieActions.get_job_events || 'mulopimfwc_ie_get_job_events',
            nonce: nonce,
            job_id: jobId,
            cursor: cursorRef.value || 0
        }).done(function (response) {
            if (!response || !response.success || !response.data || !Array.isArray(response.data.events)) {
                return;
            }
            response.data.events.forEach(function (evt) {
                if (!evt || typeof evt !== 'object') {
                    return;
                }
                const level = String(evt.level || 'info').toLowerCase();
                appendStockCentralLog(evt.message || '', level === 'warning' ? 'info' : normalizeLogType(level));
            });
            cursorRef.value = Number(response.data.next_cursor || cursorRef.value || 0);
        });
    }

    function waitForJobTerminal(jobId, options) {
        const opts = options && typeof options === 'object' ? options : {};
        const cursorRef = { value: Number(opts.cursor || 0) };
        const terminalStatuses = Array.isArray(opts.terminalStatuses) && opts.terminalStatuses.length
            ? opts.terminalStatuses.map(function (status) { return String(status || '').toLowerCase(); })
            : ['completed', 'failed', 'cancelled', 'awaiting_confirmation'];
        const appendStatusLog = typeof opts.appendStatusLog === 'boolean' ? opts.appendStatusLog : false;
        const shouldStop = typeof opts.shouldStop === 'function' ? opts.shouldStop : null;
        const onUpdate = typeof opts.onUpdate === 'function' ? opts.onUpdate : null;
        return new Promise(function (resolve, reject) {
            let stopped = false;

            function finish(err, payload) {
                if (stopped) {
                    return;
                }
                stopped = true;
                if (err) {
                    reject(err);
                } else {
                    resolve(payload);
                }
            }

            function isStoppedExternally() {
                if (!shouldStop) {
                    return false;
                }
                try {
                    return !!shouldStop();
                } catch (_err) {
                    return false;
                }
            }

            function poll() {
                if (stopped) {
                    return;
                }
                if (isStoppedExternally()) {
                    finish(new Error('__stopped__'));
                    return;
                }
                postAjax({
                    action: ieActions.get_job_status || 'mulopimfwc_ie_get_job_status',
                    nonce: nonce,
                    job_id: jobId
                }).done(function (response) {
                    if (!response || !response.success || !response.data) {
                        finish(new Error((response && response.data && response.data.message) || 'Failed to fetch job status.'));
                        return;
                    }
                    const job = response.data;
                    applyJobStatusToUi(job, { appendLog: appendStatusLog });
                    if (onUpdate) {
                        try {
                            onUpdate(job);
                        } catch (_err) {
                            // Keep polling even if UI callback fails.
                        }
                    }

                    streamJobEvents(jobId, cursorRef).always(function () {
                        if (isStoppedExternally()) {
                            finish(new Error('__stopped__'));
                            return;
                        }
                        if (terminalStatuses.indexOf(String(job.status || '').toLowerCase()) !== -1) {
                            finish(null, job);
                            return;
                        }
                        window.setTimeout(poll, 2500);
                    });
                }).fail(function (_xhr, _status, error) {
                    finish(new Error(error || 'Failed to poll job status.'));
                });
            }

            poll();
        });
    }

    function watchActiveJob(jobOrId, options) {
        if (!ieV2Enabled) {
            return;
        }
        const opts = options && typeof options === 'object' ? options : {};
        const initialJob = (jobOrId && typeof jobOrId === 'object') ? jobOrId : null;
        const jobId = initialJob ? String(initialJob.job_id || '') : String(jobOrId || '');
        if (jobId === '') {
            return;
        }

        if (activeWatchJobId === jobId) {
            return;
        }

        const watchToken = ++activeWatchToken;
        activeWatchJobId = jobId;
        if (initialJob) {
            applyJobStatusToUi(initialJob, { appendLog: false });
        }
        if (!opts.silent) {
            appendStockCentralLog('Tracking active job: ' + jobId, 'info');
        }

        waitForJobTerminal(jobId, {
            terminalStatuses: ['completed', 'failed', 'cancelled'],
            appendStatusLog: false,
            shouldStop: function () {
                return watchToken !== activeWatchToken;
            }
        }).then(function () {
            if (watchToken !== activeWatchToken) {
                return;
            }
            activeWatchJobId = '';
        }).catch(function (err) {
            if (watchToken !== activeWatchToken) {
                return;
            }
            activeWatchJobId = '';
            if (err && err.message === '__stopped__') {
                return;
            }
            const message = err && err.message ? err.message : 'Failed to monitor active job.';
            appendStockCentralLog(message, 'error');
        });
    }

    function discoverActiveImportJob() {
        if (!ieV2Enabled || !ajaxUrl) {
            return;
        }
        postAjax({
            action: ieActions.get_active_jobs || 'mulopimfwc_ie_get_active_jobs',
            nonce: nonce,
            type: 'import',
            limit: 1
        }).done(function (response) {
            if (!response || !response.success || !response.data || !Array.isArray(response.data.jobs) || !response.data.jobs.length) {
                return;
            }
            watchActiveJob(response.data.jobs[0], { silent: true });
        }).fail(function () {
            // Keep UI usable even if active-job discovery fails.
        });
    }

    function performJobControlAction(actionName, startedMessage) {
        if (!ieV2Enabled || !statusContextJob || !statusContextJob.job_id) {
            setStockCentralStatus('No active job selected.', 'error');
            return;
        }
        const jobId = statusContextJob.job_id;
        if (startedMessage) {
            setStockCentralStatus(startedMessage, 'info');
        }
        postAjax({
            action: actionName,
            nonce: nonce,
            job_id: jobId
        }).done(function (response) {
            if (!response || !response.success || !response.data) {
                const errorMessage = (response && response.data && response.data.message) || 'Job action failed.';
                setStockCentralStatus(errorMessage, 'error');
                return;
            }
            applyJobStatusToUi(response.data, { appendLog: true });
            if (!isFinalJobStatus(response.data.status)) {
                watchActiveJob(response.data, { silent: true });
            }
        }).fail(function (_xhr, _status, error) {
            setStockCentralStatus('Job action failed: ' + (error || 'Unknown error'), 'error');
        });
    }

    function triggerDownloadFromUrl(url) {
        if (!url) {
            return;
        }
        const link = document.createElement('a');
        link.href = url;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    async function sha256HexFromBlob(blob) {
        if (!window.crypto || !window.crypto.subtle) {
            return '';
        }
        const buffer = await blob.arrayBuffer();
        const digest = await window.crypto.subtle.digest('SHA-256', buffer);
        const bytes = new Uint8Array(digest);
        let hex = '';
        for (let i = 0; i < bytes.length; i++) {
            hex += bytes[i].toString(16).padStart(2, '0');
        }
        return hex;
    }

    async function maybeComputeFileHash(file) {
        const maxHashBytes = 200 * 1024 * 1024;
        if (!file || file.size > maxHashBytes) {
            return '';
        }
        try {
            return await sha256HexFromBlob(file);
        } catch (_err) {
            return '';
        }
    }

    async function uploadFileInChunks(jobId, uploadId, file) {
        const totalChunks = Math.max(1, Math.ceil((file.size || 0) / uploadChunkSize));
        let warnedNoChunkHash = false;
        for (let i = 0; i < totalChunks; i++) {
            const start = i * uploadChunkSize;
            const end = Math.min(file.size, start + uploadChunkSize);
            const chunk = file.slice(start, end);
            const chunkSha = await sha256HexFromBlob(chunk);
            if (!chunkSha && !warnedNoChunkHash) {
                warnedNoChunkHash = true;
                appendStockCentralLog('Browser lacks WebCrypto chunk hashing; continuing with server-side checksum validation.', 'info');
            }
            const formData = new FormData();
            formData.append('action', ieActions.upload_chunk || 'mulopimfwc_ie_upload_chunk');
            formData.append('nonce', nonce);
            formData.append('job_id', jobId);
            formData.append('upload_id', uploadId);
            formData.append('chunk_index', String(i));
            formData.append('chunk_count', String(totalChunks));
            if (chunkSha) {
                formData.append('chunk_sha256', chunkSha);
            }
            formData.append('chunk', chunk, (file.name || 'upload.bin') + '.part');

            const response = await postAjaxForm(formData);
            if (!response || !response.success) {
                throw new Error((response && response.data && response.data.message) || 'Chunk upload failed.');
            }
            const percent = Math.round(((i + 1) / totalChunks) * 100);
            setSettingsProgress((cfg.strings.uploading_chunks || 'Uploading file in chunks...') + ' (' + (i + 1) + '/' + totalChunks + ')', percent);
        }
        return totalChunks;
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

    function setStockCentralStatus(message, type, options) {
        if (!$stockCentralStatus.length) {
            return;
        }
        const opts = options && typeof options === 'object' ? options : {};
        const appendLog = typeof opts.appendLog === 'boolean' ? opts.appendLog : true;
        if (!message) {
            $stockCentralStatus.hide().removeClass('is-error is-success');
            if ($stockCentralStatusMessage.length) {
                $stockCentralStatusMessage.text('');
            } else {
                $stockCentralStatus.text('');
            }
            statusContextJob = null;
            updateActiveJobControls(null);
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
        if (appendLog) {
            appendStockCentralLog(message, type);
        }
    }

    function applyJobStatusToUi(job, options) {
        if (!job || typeof job !== 'object') {
            return;
        }
        const statusLine = summarizeJobStatus(job) || ('Job status: ' + (job.status || 'unknown'));
        const statusType = (job.status === 'failed' || job.status === 'cancelled')
            ? 'error'
            : (job.status === 'completed' ? 'success' : 'info');
        const progress = Number(job.progress_percent || 0);
        setSettingsProgress(statusLine, progress);
        setStockCentralStatus(statusLine, statusType, options);

        statusContextJob = {
            job_id: String(job.job_id || ''),
            type: String(job.type || ''),
            status: String(job.status || '')
        };
        updateActiveJobControls(statusContextJob);
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

    function runLegacyImportRequest(file, mode, confirmed, onDone) {
        const formData = new FormData();
        formData.append('action', fullImportAction);
        formData.append('nonce', nonce);
        formData.append('csv_file', file);
        formData.append('options', JSON.stringify(getImportOptions(mode, confirmed)));
        const stopProgressHints = startImportProgressHints(mode);

        postAjaxForm(formData).done(function (response) {
            stopProgressHints();
            if (!response || !response.success) {
                const data = response && response.data ? response.data : {};
                if (typeof onDone === 'function') onDone(false, data);
                return;
            }
            if (typeof onDone === 'function') onDone(true, response.data || {});
        }).fail(function (_xhr, _status, error) {
            stopProgressHints();
            if (typeof onDone === 'function') onDone(false, { message: error || 'Import request failed.' });
        });
    }

    async function runV2ImportWorkflow(file, selectedMode) {
        const startResponse = await postAjax({
            action: ieActions.start_import || 'mulopimfwc_ie_start_import',
            nonce: nonce,
            options: JSON.stringify(getImportOptions('dry_run', false))
        });
        if (!startResponse || !startResponse.success || !startResponse.data) {
            const payload = startResponse && startResponse.data ? startResponse.data : {};
            if (payload && payload.active_job && payload.active_job.job_id) {
                watchActiveJob(payload.active_job);
            } else {
                discoverActiveImportJob();
            }
            throw new Error((payload && payload.message) || 'Failed to start import job.');
        }
        const jobId = startResponse.data.job_id;
        const uploadId = startResponse.data.upload_id;
        applyJobStatusToUi(startResponse.data, { appendLog: false });
        appendStockCentralLog('Import job created: ' + jobId, 'info');

        const totalChunks = await uploadFileInChunks(jobId, uploadId, file);
        const targetSha = await maybeComputeFileHash(file);

        const finishResponse = await postAjax({
            action: ieActions.finish_upload || 'mulopimfwc_ie_finish_upload',
            nonce: nonce,
            job_id: jobId,
            upload_id: uploadId,
            chunk_count: totalChunks,
            filename: file.name || 'upload.bin',
            target_sha256: targetSha
        });
        if (!finishResponse || !finishResponse.success) {
            throw new Error((finishResponse && finishResponse.data && finishResponse.data.message) || 'Failed to finish upload.');
        }

        const dryRunResponse = await postAjax({
            action: ieActions.start_dry_run || 'mulopimfwc_ie_start_dry_run',
            nonce: nonce,
            job_id: jobId,
            options: JSON.stringify(getImportOptions('dry_run', false))
        });
        if (!dryRunResponse || !dryRunResponse.success) {
            throw new Error((dryRunResponse && dryRunResponse.data && dryRunResponse.data.message) || 'Failed to start dry-run.');
        }

        const dryRunTerminal = await waitForJobTerminal(jobId);
        if (dryRunTerminal.status === 'failed' || dryRunTerminal.status === 'cancelled') {
            throw new Error('Dry-run ended with status: ' + dryRunTerminal.status);
        }

        if (dryRunTerminal.status === 'awaiting_confirmation') {
            const confirmMsg = (cfg.strings.confirm_dry_run_apply || 'Dry run completed. Continue with import?') +
                '\n\n' +
                summarizeJobStatus(dryRunTerminal);
            if (!window.confirm(confirmMsg)) {
                appendStockCentralLog('Import cancelled by user after dry-run.', 'info');
                return dryRunTerminal;
            }

            const applyResponse = await postAjax({
                action: ieActions.confirm_apply || 'mulopimfwc_ie_confirm_apply',
                nonce: nonce,
                job_id: jobId,
                options: JSON.stringify(getImportOptions(selectedMode, true))
            });
            if (!applyResponse || !applyResponse.success) {
                throw new Error((applyResponse && applyResponse.data && applyResponse.data.message) || 'Failed to start apply phase.');
            }

            const applyTerminal = await waitForJobTerminal(jobId);
            if (applyTerminal.status !== 'completed') {
                throw new Error('Apply ended with status: ' + applyTerminal.status);
            }
            return applyTerminal;
        }

        return dryRunTerminal;
    }

    function runFullImport(file, mode, confirmed, onDone) {
        if (!ajaxUrl) {
            const msg = 'Import failed: missing admin AJAX URL.';
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'error');
            if (typeof onDone === 'function') onDone(false, { message: msg });
            return;
        }

        if (!ieV2Enabled) {
            runLegacyImportRequest(file, mode, confirmed, onDone);
            return;
        }

        if (mode !== 'dry_run') {
            if (typeof onDone === 'function') {
                onDone(true, { message: 'Apply phase is managed by the v2 workflow.' });
            }
            return;
        }

        const selectedMode = getValue('#mulopimfwc-stock-central-import-mode', '#mulopimfwc_import_mode') || 'create_update';
        (async function () {
            const terminal = await runV2ImportWorkflow(file, selectedMode);
            if (typeof onDone === 'function') onDone(true, terminal || {});
        })().catch(function (err) {
            if (typeof onDone === 'function') onDone(false, { message: err && err.message ? err.message : 'Import failed.' });
        });
    }

    function handleImportFile(file) {
        if (!file || (!/\.csv$/i.test(file.name || '') && !/\.zip$/i.test(file.name || ''))) {
            alert(cfg.strings.invalid_csv_file || 'Please select a valid CSV/ZIP file.');
            return;
        }
        if (file.size > maxUploadBytes) {
            alert('File is too large. Maximum supported size is 2GB.');
            return;
        }

        appendStockCentralLog('File selected: ' + (file.name || 'unnamed.csv') + ' (' + (file.size || 0) + ' bytes)', 'info');

        setSettingsProgress(cfg.strings.full_importing_dry_run || 'Running dry run...', 20);
        setStockCentralStatus(cfg.strings.full_importing_dry_run || 'Running dry run...');

        if (!ieV2Enabled) {
            runFullImport(file, 'dry_run', false, function (ok, data) {
                if (!ok) {
                    const message = data && data.message ? data.message : (cfg.strings.full_import_error || 'Import failed.');
                    setSettingsProgress(message, 100);
                    setStockCentralStatus(message, 'error');
                    appendServerLogs(data || {});
                    if (data && data.failed_rows_csv_base64) {
                        downloadFailedRowsIfAny(data.failed_rows_csv_base64);
                    }
                    return;
                }
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
                runFullImport(file, selectedMode, true, function (applyOk, applyData) {
                    if (!applyOk) {
                        const applyMessage = applyData && applyData.message ? applyData.message : (cfg.strings.full_import_error || 'Import failed.');
                        setSettingsProgress(applyMessage, 100);
                        setStockCentralStatus(applyMessage, 'error');
                        return;
                    }
                    setSettingsProgress(cfg.strings.full_import_success || 'Import completed successfully.', 100);
                    setStockCentralStatus(cfg.strings.full_import_success || 'Import completed successfully.', 'success');
                    setTimeout(function () {
                        setSettingsProgress('', 0);
                    }, 4500);
                });
            });
            return;
        }

        runFullImport(file, 'dry_run', false, function (ok, data) {
            if (!ok) {
                if (data && data.active_job && data.active_job.job_id) {
                    watchActiveJob(data.active_job);
                }
                const message = data && data.message ? data.message : (cfg.strings.full_import_error || 'Import failed.');
                setSettingsProgress(message, 100);
                setStockCentralStatus(message, 'error');
                appendStockCentralLog(message, 'error');
                return;
            }
            const message = data && data.status ? ('Import job ended with status: ' + data.status) : (cfg.strings.full_import_success || 'Import completed successfully.');
            setSettingsProgress(message, 100);
            setStockCentralStatus(message, 'success');
            setTimeout(function () {
                setSettingsProgress('', 0);
            }, 4500);
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

        const fallbackLegacy = function () {
            postAjax({
                action: fullExportAction,
                nonce: nonce,
                options: JSON.stringify({
                    meta_whitelist: getValue('#mulopimfwc-stock-central-custom-meta', '#mulopimfwc_import_meta_whitelist')
                })
            }).done(function (response) {
                if (!response || !response.success) {
                    const message = (response && response.data && response.data.message) || 'Export failed';
                    setSettingsProgress(message, 100);
                    setStockCentralStatus(message, 'error');
                    return;
                }
                const data = response.data || {};
                if (data.download_url) {
                    triggerDownloadFromUrl(data.download_url);
                    setSettingsProgress('Export completed.', 100);
                    setStockCentralStatus('Export completed.', 'success');
                    return;
                }
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
        };

        if (!ieV2Enabled) {
            fallbackLegacy();
            return;
        }

        postAjax({
            action: ieActions.start_export || 'mulopimfwc_ie_start_export',
            nonce: nonce,
            options: JSON.stringify({
                meta_whitelist: getValue('#mulopimfwc-stock-central-custom-meta', '#mulopimfwc_import_meta_whitelist')
            })
        }).done(function (response) {
            if (!response || !response.success || !response.data || !response.data.job_id) {
                const message = (response && response.data && response.data.message) || 'Failed to start export job.';
                setSettingsProgress(message, 100);
                setStockCentralStatus(message, 'error');
                $btn.prop('disabled', false).html(original);
                return;
            }
            const jobId = response.data.job_id;
            appendStockCentralLog('Export job started: ' + jobId, 'info');
            waitForJobTerminal(jobId).then(function (terminal) {
                if (terminal.status !== 'completed') {
                    throw new Error('Export ended with status: ' + terminal.status);
                }
                if (terminal.download_url) {
                    triggerDownloadFromUrl(terminal.download_url);
                }
                setSettingsProgress('Export completed.', 100);
                setStockCentralStatus('Export completed.', 'success');
                setTimeout(function () { setSettingsProgress('', 0); }, 3500);
            }).catch(function (err) {
                const message = err && err.message ? err.message : 'Export failed.';
                setSettingsProgress(message, 100);
                setStockCentralStatus(message, 'error');
                appendStockCentralLog(message, 'error');
            }).finally(function () {
                $btn.prop('disabled', false).html(original);
            });
        }).fail(function (_xhr, _status, error) {
            const msg = 'Export failed: ' + error;
            setSettingsProgress(msg, 100);
            setStockCentralStatus(msg, 'error');
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

    $(document).on('click', '.mulopimfwc-stock-central-pause-job', function (e) {
        e.preventDefault();
        performJobControlAction(ieActions.pause_job || 'mulopimfwc_ie_pause_job', 'Pausing active job...');
    });

    $(document).on('click', '.mulopimfwc-stock-central-resume-job', function (e) {
        e.preventDefault();
        performJobControlAction(ieActions.resume_job || 'mulopimfwc_ie_resume_job', 'Resuming active job...');
    });

    $(document).on('click', '.mulopimfwc-stock-central-cancel-job', function (e) {
        e.preventDefault();
        if (!window.confirm('Cancel this active job?')) {
            return;
        }
        performJobControlAction(ieActions.cancel_job || 'mulopimfwc_ie_cancel_job', 'Cancelling active job...');
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
    updateActiveJobControls(null);
    if (ieV2Enabled) {
        discoverActiveImportJob();
    }
});
