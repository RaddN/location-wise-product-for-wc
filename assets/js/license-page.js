(function () {
    'use strict';

    var config = window.mulopimfwcLicensePage || {};
    var strings = config.strings || {};
    var ajaxUrl = config.ajaxUrl || window.ajaxurl || '';
    var hasQueuedBackgroundRequest = false;

    if (ajaxUrl && typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = ajaxUrl;
    }

    function postForm(params, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                callback(xhr);
            }
        };
        xhr.send(params.toString());
    }

    function sendBackgroundLicenseCheck() {
        if (!ajaxUrl || hasQueuedBackgroundRequest || !config.backgroundCheck) {
            return;
        }

        hasQueuedBackgroundRequest = true;
        var params = new URLSearchParams();
        params.append('action', 'mulopimfwc_background_license_check');
        params.append('nonce', config.backgroundNonce || '');
        postForm(params, function () {});
    }

    function scheduleBackgroundLicenseCheck() {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(sendBackgroundLicenseCheck, {
                timeout: 4000
            });
            return;
        }

        window.setTimeout(sendBackgroundLicenseCheck, 1500);
    }

    function removeLicense(button) {
        if (!ajaxUrl || !window.confirm(strings.removeConfirm || 'Are you sure you want to remove the license from this site?')) {
            return;
        }

        button.textContent = strings.removing || 'Removing...';
        button.disabled = true;

        var params = new URLSearchParams();
        params.append('action', 'mulopimfwc_remove_license');
        params.append('nonce', config.removeLicenseNonce || '');

        postForm(params, function (xhr) {
            if (xhr.status === 200) {
                button.textContent = strings.success || 'Success';
                button.style.backgroundColor = '#28a745';
                button.style.borderColor = '#28a745';
                window.setTimeout(function () {
                    button.style.transition = 'opacity 0.5s';
                    button.style.opacity = '0';
                    window.setTimeout(function () {
                        button.style.display = 'none';
                    }, 500);
                }, 800);

                var licenseInput = document.getElementById('mulopimfwc_license_key');
                if (licenseInput) {
                    licenseInput.value = '';
                }
                return;
            }

            button.textContent = strings.removeLicense || 'Remove License';
            button.disabled = false;
            window.alert(strings.removeFailed || 'Failed to remove license. Please try again.');
        });
    }

    function checkForUpdates(button) {
        if (!ajaxUrl) {
            return;
        }

        var originalText = button.textContent;
        button.textContent = strings.checking || 'Checking...';
        button.disabled = true;

        var params = new URLSearchParams();
        params.append('action', 'mulopimfwc_check_updates');
        params.append('nonce', config.checkUpdatesNonce || '');

        postForm(params, function (xhr) {
            button.textContent = originalText;
            button.disabled = false;

            if (xhr.status !== 200) {
                window.alert(strings.checkError || 'Error checking for updates. Please try again.');
                return;
            }

            var response = {};
            try {
                response = JSON.parse(xhr.responseText);
            } catch (error) {
                window.alert(strings.checkError || 'Error checking for updates. Please try again.');
                return;
            }

            if (!response.success) {
                window.alert((strings.checkErrorPrefix || 'Error checking for updates:') + ' ' + response.data);
                return;
            }

            if (response.data && response.data.update_available) {
                var template = strings.updateAvailable || 'Update available! Version %s is ready to install.';
                window.alert(template.replace('%s', response.data.new_version || ''));
                window.location.reload();
                return;
            }

            window.alert(strings.latest || 'You have the latest version installed.');
        });
    }

    document.addEventListener('click', function (event) {
        var confirmButton = event.target.closest('[data-mulopimfwc-license-confirm]');
        if (confirmButton && !window.confirm(confirmButton.getAttribute('data-mulopimfwc-license-confirm') || '')) {
            event.preventDefault();
            return;
        }

        var removeButton = event.target.closest('#mulopimfwc-remove-license-btn');
        if (removeButton) {
            event.preventDefault();
            removeLicense(removeButton);
            return;
        }

        var updatesButton = event.target.closest('#check-updates-btn');
        if (updatesButton) {
            event.preventDefault();
            checkForUpdates(updatesButton);
        }
    });

    if (document.readyState === 'complete') {
        scheduleBackgroundLicenseCheck();
    } else {
        window.addEventListener('load', scheduleBackgroundLicenseCheck, {
            once: true
        });
    }
})();
