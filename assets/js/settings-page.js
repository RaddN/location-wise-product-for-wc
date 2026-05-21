(function($) {
    "use strict";

    var config = window.mulopimfwcSettingsPage || {};
    var strings = config.strings || {};

    function getString(key, fallback) {
        return strings[key] || fallback;
    }

    function setButtonBusy($button, isBusy, label) {
        $button.prop("disabled", isBusy);
        if (label) {
            $button.text(label);
        }
    }

    function initLocationCurrencyDependency() {
        var locationPriceInput = document.querySelector('input[name="mulopimfwc_display_options[enable_location_price]"]');
        var locationCurrencyInput = document.querySelector('input[name="mulopimfwc_display_options[location_wise_currency]"]');

        if (!locationPriceInput || !locationCurrencyInput) {
            return;
        }

        var currencySwitch = locationCurrencyInput.closest(".mulopimfwc_switch");

        function syncLocationCurrencyState() {
            var shouldDisable = !!config.isManualMode || !locationPriceInput.checked;
            locationCurrencyInput.disabled = shouldDisable;

            if (shouldDisable) {
                locationCurrencyInput.checked = false;
            }

            locationCurrencyInput.classList.toggle("mulopimfwc-setting-disabled", shouldDisable);

            if (currencySwitch) {
                currencySwitch.classList.toggle("mulopimfwc-setting-disabled", shouldDisable);
            }
        }

        syncLocationCurrencyState();
        locationPriceInput.addEventListener("change", syncLocationCurrencyState);
    }

    function initTransferCostMatrix() {
        function toggleTransferCostInputs() {
            var $select = $("#mulopimfwc_shipping_calculation_method");
            var $inputs = $(".mulopimfwc-cost-table input[type=\"number\"]");

            if ($select.length && $select.val() !== "nearest_with_transfer") {
                $inputs.prop("disabled", true);
            } else {
                $inputs.prop("disabled", false);
            }
        }

        toggleTransferCostInputs();

        $(document).on("change", "#mulopimfwc_shipping_calculation_method", toggleTransferCostInputs);

        $(document).on("click", ".mulopimfwc-fill-default", function() {
            var defaultCost = window.prompt(getString("fillDefaultPrompt", "Enter default transfer cost for all locations:"), "0");

            if (defaultCost !== null && !Number.isNaN(Number(defaultCost))) {
                $(".mulopimfwc-cost-table input[type=\"number\"]:enabled").each(function() {
                    if (!$(this).val() || $(this).val() === "0") {
                        $(this).val(Number(defaultCost).toFixed(2));
                    }
                });
            }
        });

        $(document).on("click", ".mulopimfwc-clear-all", function() {
            if (window.confirm(getString("clearAllConfirm", "Are you sure you want to clear all transfer costs?"))) {
                $(".mulopimfwc-cost-table input[type=\"number\"]:enabled").val("");
            }
        });
    }

    function initNotificationCards() {
        $(document).on("click", ".mulopimfwc-position-card", function() {
            var $card = $(this);

            if ($card.hasClass("disabled")) {
                return;
            }

            $(".mulopimfwc-position-card").removeClass("selected");
            $card.addClass("selected");
            $card.find('input[type="radio"]').prop("checked", true);
        });

        $(document).on("click", ".mulopimfwc-size-card", function() {
            var $card = $(this);

            if ($card.hasClass("disabled")) {
                return;
            }

            $(".mulopimfwc-size-card").removeClass("selected");
            $card.addClass("selected");
            $card.find('input[type="radio"]').prop("checked", true);
        });
    }

    function initSocialChannels() {
        var $container = $("#mulopimfwc-social-channels");

        if (!$container.length) {
            return;
        }

        var template = $("#mulopimfwc-social-channel-template").html() || "";
        var $emptyRow = $("#mulopimfwc-social-empty");
        var $digestClock = $("#mulopimfwc-digest-clock");

        if ($digestClock.length) {
            var startUtcMs = parseInt($digestClock.data("start-utc"), 10) * 1000;
            var offsetHours = parseFloat($digestClock.data("gmt-offset")) || 0;
            var offsetMs = offsetHours * 3600 * 1000;
            var startClient = Date.now();
            var tzLabel = $digestClock.data("tz-label") || "UTC";
            var pad = function(number) {
                return number < 10 ? "0" + number : String(number);
            };
            var tick = function() {
                var nowMs = startUtcMs + offsetMs + (Date.now() - startClient);
                var date = new Date(nowMs);
                $digestClock.text(pad(date.getUTCHours()) + ":" + pad(date.getUTCMinutes()) + ":" + pad(date.getUTCSeconds()) + " " + tzLabel);
            };

            tick();
            window.setInterval(tick, 1000);
        }

        function toggleChannelFields($row) {
            var type = $row.find(".mulopimfwc-channel-type").val();
            var isTelegram = type === "telegram";

            $row.find(".mulopimfwc-field-webhook")[isTelegram ? "hide" : "show"]();
            $row.find(".mulopimfwc-field-telegram")[isTelegram ? "show" : "hide"]();
        }

        function refreshEmptyState() {
            if ($container.children(".mulopimfwc-social-channel-row").length === 0) {
                $emptyRow.show();
            } else {
                $emptyRow.hide();
            }
        }

        function openGuideFor(type) {
            $(".mulopimfwc-guide").each(function() {
                var isMatch = $(this).data("platform") === type;
                $(this).prop("open", isMatch);
            });
        }

        $("#mulopimfwc-add-social-channel").on("click", function(event) {
            event.preventDefault();

            var index = $container.children(".mulopimfwc-social-channel-row").length;
            $container.append(template.replace(/__INDEX__/g, index));
            toggleChannelFields($container.children(".mulopimfwc-social-channel-row").last());
            refreshEmptyState();
        });

        $container.on("click", ".remove-social-channel", function(event) {
            event.preventDefault();
            $(this).closest(".mulopimfwc-social-channel-row").remove();
            refreshEmptyState();
        });

        $container.on("change", ".mulopimfwc-channel-type", function() {
            var $row = $(this).closest(".mulopimfwc-social-channel-row");
            var type = $(this).val();

            toggleChannelFields($row);
            if (type) {
                openGuideFor(type);
            }
        });

        $container.on("click", ".test-social-channel, .test-social-digest-channel", function(event) {
            event.preventDefault();

            var $button = $(this);
            var $row = $button.closest(".mulopimfwc-social-channel-row");
            var isDigest = $button.hasClass("test-social-digest-channel");
            var data = {
                action: isDigest ? "mulopimfwc_test_social_digest_channel" : "mulopimfwc_test_social_channel",
                nonce: $button.data("nonce"),
                type: $row.find(".mulopimfwc-channel-type").val(),
                label: $row.find('input[name*="[label]"]').val(),
                webhook: $row.find('input[name*="[webhook]"]').val(),
                chat_id: $row.find('input[name*="[chat_id]"]').val(),
                bot_token: $row.find('input[name*="[bot_token]"]').val()
            };

            setButtonBusy($button, true, getString(isDigest ? "testingDigest" : "testing", isDigest ? "Testing digest..." : "Testing..."));

            $.post(window.ajaxurl, data, function(response) {
                var fallback = getString(isDigest ? "digestFailed" : "testFailed", isDigest ? "Digest test failed. Check the details and try again." : "Test failed. Check the details and try again.");
                var message = response && response.success
                    ? getString(isDigest ? "digestSent" : "testSent", isDigest ? "Digest test sent! Check your channel." : "Test sent! Check your channel.")
                    : (response && response.data && response.data.message ? response.data.message : fallback);

                window.alert(message);
            }).fail(function() {
                window.alert(getString(isDigest ? "digestFailed" : "testFailed", isDigest ? "Digest test failed. Check the details and try again." : "Test failed. Check the details and try again."));
            }).always(function() {
                setButtonBusy($button, false, getString(isDigest ? "testDigest" : "test", isDigest ? "Test Digest" : "Test"));
            });
        });

        $container.children(".mulopimfwc-social-channel-row").each(function() {
            toggleChannelFields($(this));
        });

        var firstType = $container.find(".mulopimfwc-channel-type").first().val();
        if (firstType) {
            openGuideFor(firstType);
        }

        refreshEmptyState();
    }

    function copyText(text) {
        if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
            return window.navigator.clipboard.writeText(text);
        }

        return new Promise(function(resolve, reject) {
            var textarea = document.createElement("textarea");
            textarea.value = text;
            textarea.setAttribute("readonly", "readonly");
            textarea.style.position = "absolute";
            textarea.style.left = "-9999px";
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand("copy");
                document.body.removeChild(textarea);
                resolve();
            } catch (error) {
                document.body.removeChild(textarea);
                reject(error);
            }
        });
    }

    function initConfirmAndCopy() {
        $(document).on("click", "[data-mulopimfwc-confirm]", function(event) {
            var message = $(this).attr("data-mulopimfwc-confirm") || "";

            if (message && !window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });

        $(document).on("click", "[data-mulopimfwc-copy-shortcode]", function(event) {
            var button = this;
            var $button = $(button);
            var text = $button.attr("data-mulopimfwc-copy-shortcode") || "";
            var originalHtml = button.innerHTML;
            var originalText = $button.find(".btn-text").text();

            event.preventDefault();

            copyText(text).then(function() {
                $button.addClass("copied");

                if (originalText) {
                    $button.find(".btn-text").text(getString("copied", "Copied!"));
                }

                window.setTimeout(function() {
                    $button.removeClass("copied");
                    button.innerHTML = originalHtml;
                }, 2000);
            }).catch(function() {
                window.alert(getString("copyFailed", "Failed to copy. Please select and copy manually."));
            });
        });
    }

    function setCollapsibleSection(button, sectionSelector, listSelector) {
        var section = button.closest(sectionSelector);

        if (!section) {
            return;
        }

        var list = section.querySelector(listSelector);
        var shouldOpen = !section.classList.contains("is-open");

        section.classList.toggle("is-open", shouldOpen);
        section.classList.toggle("is-collapsed", !shouldOpen);
        button.setAttribute("aria-expanded", shouldOpen ? "true" : "false");

        if (list) {
            list.hidden = !shouldOpen;
        }
    }

    function initTutorials() {
        $(document).on("click", "[data-mulopimfwc-toggle-params]", function() {
            setCollapsibleSection(this, ".lwp-tutorial-params-section", ".lwp-params-list");
        });

        $(document).on("click", "[data-mulopimfwc-toggle-examples]", function() {
            setCollapsibleSection(this, ".lwp-tutorial-examples-section", ".lwp-examples-list");
        });

        $(document).on("click", "[data-mulopimfwc-toggle-example]", function() {
            var header = this;
            var item = header.closest(".lwp-example-item");

            if (!item) {
                return;
            }

            var section = item.closest(".lwp-tutorial-examples-section");
            var isActive = header.classList.contains("active");

            if (section) {
                Array.prototype.forEach.call(section.querySelectorAll(".lwp-example-item"), function(otherItem) {
                    if (otherItem !== item) {
                        var otherHeader = otherItem.querySelector(".lwp-example-header");
                        if (otherHeader) {
                            otherHeader.classList.remove("active");
                        }
                    }
                });
            }

            header.classList.toggle("active", !isActive);
        });
    }

    function initDefaultTemplateFields() {
        function toggleDefaultTemplateFields() {
            var isDefault = $("#template_selection").val() === "default";

            $(".mulopimfwc-default-template-only").each(function() {
                var $field = $(this);
                var $row = $field.closest("tr");

                if (isDefault) {
                    $row.show();
                    $field.show();
                } else {
                    $row.hide();
                    $field.hide();
                }
            });
        }

        if (!$("#template_selection").length) {
            return;
        }

        window.setTimeout(toggleDefaultTemplateFields, 200);
        $("#template_selection").on("change", toggleDefaultTemplateFields);
    }

    function findByAttribute(root, selector, attributeName, attributeValue) {
        var candidates = root.querySelectorAll(selector);
        var index;

        for (index = 0; index < candidates.length; index++) {
            if (candidates[index].getAttribute(attributeName) === attributeValue) {
                return candidates[index];
            }
        }

        return null;
    }

    function initTextManagement() {
        var tab = document.getElementById("text-management-settings");
        var toggle = document.getElementById("mulopimfwc-enable-text-management");
        var translations = config.textTranslations || {};
        var locales = translations.locales || {};
        var select = document.getElementById("mulopimfwc-text-translate-select");
        var apply = document.getElementById("mulopimfwc-text-translate-apply");

        window.mulopimfwcTextTranslations = translations;

        if (select && apply && Object.keys(locales).length) {
            var applyTranslation = function() {
                var localeCode = select.value;

                if (!localeCode || !locales[localeCode] || !locales[localeCode].values) {
                    window.alert(getString("chooseLanguage", "Please choose a language first."));
                    return;
                }

                var locale = locales[localeCode];
                var label = locale.nativeLabel || locale.label || localeCode;
                var template = translations.meta && translations.meta.applyConfirm
                    ? translations.meta.applyConfirm
                    : getString("translationConfirmFallback", "Apply %s translations to all Text Management fields? This will overwrite your current values.");
                var message = template.replace("%s", label);

                if (!window.confirm(message)) {
                    return;
                }

                Object.keys(locale.values).forEach(function(key) {
                    var value = locale.values[key];
                    var field = document.getElementById(key);
                    var hidden = document.querySelector('[data-manual-hidden="true"][data-manual-for="mulopimfwc_display_options[' + key + ']"]');

                    if (field) {
                        field.value = value;
                    }

                    if (hidden) {
                        hidden.value = value;
                    }
                });

                select.value = "";
            };

            apply.addEventListener("click", applyTranslation);
            select.addEventListener("change", applyTranslation);
        }

        if (!tab || !toggle) {
            return;
        }

        var targets = tab.querySelectorAll(".mulopimfwc-text-management-toggle-target");

        function syncHiddenMirror(field, shouldMirror) {
            var tagName = field.tagName ? field.tagName.toUpperCase() : "";
            var inputType = typeof field.type === "string" ? field.type.toLowerCase() : "";
            var name = field.getAttribute("name");
            var hidden;

            if (tagName === "BUTTON" || (tagName === "INPUT" && ["submit", "button", "reset", "file"].indexOf(inputType) !== -1)) {
                return;
            }

            if (!name) {
                return;
            }

            if (findByAttribute(tab, 'input[type="hidden"][data-manual-hidden="true"]', "data-manual-for", name)) {
                return;
            }

            hidden = findByAttribute(tab, 'input[type="hidden"][data-text-toggle-hidden="true"]', "data-text-toggle-for", name);

            if (shouldMirror) {
                if (!hidden) {
                    hidden = document.createElement("input");
                    hidden.type = "hidden";
                    hidden.name = name;
                    hidden.setAttribute("data-text-toggle-hidden", "true");
                    hidden.setAttribute("data-text-toggle-for", name);
                    field.insertAdjacentElement("afterend", hidden);
                }

                hidden.value = field.value || "";
            } else if (hidden) {
                hidden.remove();
            }
        }

        function syncTextManagementState() {
            var isEnabled = !!toggle.checked;

            Array.prototype.forEach.call(targets, function(field) {
                var baseDisabled = field.getAttribute("data-text-base-disabled") === "1";
                var shouldDisable = !isEnabled || baseDisabled;

                field.disabled = shouldDisable;
                field.classList.toggle("mulopimfwc-setting-disabled", !isEnabled);
                syncHiddenMirror(field, !isEnabled);
            });
        }

        syncTextManagementState();
        toggle.addEventListener("change", syncTextManagementState);
    }

    function initColorPickers() {
        $(".mulopimfwc-color-picker-wrapper").each(function() {
            var wrapper = this;
            var $wrapper = $(wrapper);
            var colorInput = wrapper.querySelector('input[type="color"]');
            var hexInput = wrapper.querySelector('input[type="text"]');
            var preview = wrapper.querySelector(".mulopimfwc-color-preview");

            if (!colorInput || !hexInput || !preview) {
                return;
            }

            function updateSwatchBorders() {
                var currentVal = colorInput.value.toLowerCase();

                $wrapper.find(".mulopimfwc-color-swatch").each(function() {
                    var swatchColor = String($(this).attr("data-color") || "").toLowerCase();
                    this.style.borderColor = swatchColor === currentVal ? "#6366f1" : "#d1d5db";
                });
            }

            $wrapper.on("change", 'input[type="color"]', function() {
                preview.style.background = this.value;
                hexInput.value = this.value;
                updateSwatchBorders();
            });

            $wrapper.on("change", 'input[type="text"]', function() {
                var value = this.value.trim();

                if (/^#[0-9A-F]{6}$/i.test(value)) {
                    colorInput.value = value;
                    preview.style.background = value;
                    updateSwatchBorders();
                }
            });

            $wrapper.on("click", ".mulopimfwc-color-preview", function() {
                colorInput.dispatchEvent(new MouseEvent("click", {
                    bubbles: true,
                    cancelable: true,
                    view: window
                }));
            });

            $wrapper.on("click", ".mulopimfwc-color-reset", function() {
                var defaultValue = $(this).attr("data-default") || "";

                if (!defaultValue) {
                    return;
                }

                colorInput.value = defaultValue;
                hexInput.value = defaultValue;
                preview.style.background = defaultValue;
                updateSwatchBorders();
            });

            $wrapper.on("click", ".mulopimfwc-color-swatch:not(.disabled)", function() {
                var color = $(this).attr("data-color") || "";

                if (!color) {
                    return;
                }

                colorInput.value = color;
                hexInput.value = color;
                preview.style.background = color;
                updateSwatchBorders();
            });

            updateSwatchBorders();
        });
    }

    $(function() {
        initLocationCurrencyDependency();
        initTransferCostMatrix();
        initNotificationCards();
        initSocialChannels();
        initConfirmAndCopy();
        initTutorials();
        initDefaultTemplateFields();
        initTextManagement();
        initColorPickers();
    });
})(jQuery);
