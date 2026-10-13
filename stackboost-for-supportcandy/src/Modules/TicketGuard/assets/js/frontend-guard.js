(function($) {
    'use strict';

    var debounceTimer = null;
    var submitBtnSelector = '#wpsc-ct-submit, .wpsc-submit-btn, #wpsc-submit, .wpsc-open-ticket-btn, .wpsc-create-ticket-btn, button.wpsc-btn-primary, button[type="submit"], input[type="submit"]';
    var resetBtnSelector = '#wpsc-ct-reset, .wpsc-reset-btn, button[type="reset"], input[type="reset"], button[onclick*="reset"]';

    $(document).ready(function() {
        if (typeof stackboostTicketGuard === 'undefined' || !stackboostTicketGuard.enabled) {
            return;
        }

        var rules = stackboostTicketGuard.rules || [];
        if (!rules.length) {
            return;
        }

        // Real-time observer on standard text input/textarea fields
        $(document).on('input keyup paste change', 'input[type="text"], textarea', function() {
            window._tgModalDismissedRules = {}; // Reset dismissal tracking on user edit re-incident
            window._tgCircuitBreakerActive = false; // Reset circuit breaker on genuine user input
            window._tgEvalHistory = [];
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                evaluateGuardRules(rules);
            }, 100);
        });

        // Observer on dropdown / radio / checkbox option changes
        $(document).on('change select2:select', 'select, input[type="radio"], input[type="checkbox"]', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                evaluateGuardRules(rules);
            }, 100);
        });

        // Initialize TinyMCE / Rich Text Editor Real-time Observer
        initTinyMCEObserver(rules);

        // Explicit Reset Button Click Handler - NEVER block or disable reset buttons
        $(document).on('click', resetBtnSelector, function() {
            window._tgModalDismissedRules = {};
            window._tgModalShownRuleId = null;
            window._tgCircuitBreakerActive = false;
            window._tgEvalHistory = [];
            $('.stackboost-tg-banner, #sb-tg-submit-disabled-banner').remove();

            var $submitBtns = $(submitBtnSelector);
            $submitBtns.prop('disabled', false).removeClass('stackboost-tg-submit-disabled').css({
                'opacity': '1',
                'cursor': 'pointer',
                'pointer-events': 'auto',
                'filter': 'none'
            }).data('tg-modal-blocked', false).removeData('tg-modal-rule').removeData('tg-modal-bypassed');

            setTimeout(function() {
                evaluateGuardRules(rules);
            }, 150);
        });

        // Re-evaluate ONLY when a new SupportCandy ticket form is dynamically loaded
        $(document).ajaxComplete(function(e, xhr, settings) {
            if (!settings || !settings.data) return;
            var dataStr = typeof settings.data === 'string' ? settings.data : (typeof settings.data === 'object' ? JSON.stringify(settings.data) : '');

            // STRICT FILTER: ONLY trigger when explicitly loading a ticket form
            if (dataStr.indexOf('wpsc_get_ticket_form') === -1 && dataStr.indexOf('wpsc_open_ticket') === -1) {
                return; // Ignore ALL other AJAX calls (visibility checks, options, updates, etc.)
            }

            initTinyMCEObserver(rules);
            evaluateGuardRules(rules);
        });

        // Initial Evaluation
        evaluateGuardRules(rules);

        // Native Capture Phase Event Interceptor for Submit Buttons
        window.addEventListener('click', function(e) {
            var btn = e.target ? e.target.closest(submitBtnSelector) : null;
            if (!btn) return;

            // Ensure reset button was not clicked by mistake
            if (e.target && e.target.closest(resetBtnSelector)) {
                return;
            }

            var $btn = $(btn);

            if ($btn.hasClass('stackboost-tg-submit-disabled') || $btn.prop('disabled')) {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();

                var disabledMsg = $btn.data('tg-disabled-msg') || $btn.attr('title') || 'Submit button disabled: Please review your entry or category selection.';
                showSubmitDisabledModal(disabledMsg);
                return false;
            }

            var isBlocked = $btn.data('tg-modal-blocked');
            var isBypassed = $btn.data('tg-modal-bypassed');
            var blockedRule = $btn.data('tg-modal-rule');

            if (isBlocked && !isBypassed && blockedRule) {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                showGuidanceModal($btn, blockedRule, true); // Intercepted via user submit click
                return false;
            }
        }, true);
    });

    function initTinyMCEObserver(rules) {
        if (typeof tinymce !== 'undefined') {
            if (tinymce.editors && tinymce.editors.length) {
                $.each(tinymce.editors, function(i, editor) {
                    bindEditorEvents(editor, rules);
                });
            }

            if (!window._tgTinyMCEBound) {
                window._tgTinyMCEBound = true;
                tinymce.on('AddEditor', function(e) {
                    bindEditorEvents(e.editor, rules);
                });
            }
        }
    }

    function bindEditorEvents(editor, rules) {
        if (editor._tgBound) return;
        editor._tgBound = true;

        var triggerEval = function() {
            try {
                editor.save(); // Syncs TinyMCE HTML back to underlying textarea
            } catch (err) {}

            window._tgModalDismissedRules = {}; // Reset dismissal tracking on user edit re-incident
            window._tgCircuitBreakerActive = false; // Reset circuit breaker on genuine user typing
            window._tgEvalHistory = [];
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                evaluateGuardRules(rules);
            }, 100);
        };

        editor.on('keyup input Change ExecCommand SetContent NodeChange', triggerEval);
    }

    function triggerElementEventChain($el, callback) {
        if (!$el || !$el.length) {
            if (typeof callback === 'function') callback();
            return;
        }

        var el = $el.get(0);

        // Step 1: Pre-focus
        try {
            if (typeof el.focus === 'function') el.focus();
        } catch (e) {}

        // Step 2: Click
        if (typeof el.click === 'function') {
            try { el.click(); } catch(e) {}
        } else if (el && typeof el.dispatchEvent === 'function') {
            try { el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true })); } catch(e) {}
        }
        $el.trigger('click');

        // Step 3: Pause briefly (30ms), then Change & Select2 events
        setTimeout(function() {
            $el.trigger('change').trigger('select2:select');
            if (el && typeof el.dispatchEvent === 'function') {
                try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch(e) {}
            }

            // Step 4: Pause briefly (30ms), then Focusout & Blur
            setTimeout(function() {
                $el.trigger('focusout').trigger('blur');
                if (el && typeof el.dispatchEvent === 'function') {
                    try {
                        el.dispatchEvent(new Event('focusout', { bubbles: true }));
                        el.dispatchEvent(new Event('blur', { bubbles: true }));
                    } catch(e) {}
                }
                try {
                    if (typeof el.blur === 'function') el.blur();
                } catch(e) {}

                var $s2 = $el.next('.select2-container');
                if ($s2.length) {
                    $s2.find('.select2-selection').trigger('click').trigger('focusout').trigger('blur');
                }

                // Final pause before calling callback
                setTimeout(function() {
                    if (typeof callback === 'function') callback();
                }, 40);
            }, 30);
        }, 30);
    }

    function applyFieldSwap(swapField, swapValue, callback) {
        if (!swapField || !swapValue || window._tgSwapping) {
            if (typeof callback === 'function') callback();
            return;
        }

        var strSwapValue = String(swapValue);
        window._tgSwapping = true;

        function finishSwap() {
            window._tgSwapping = false;
            if (typeof callback === 'function') callback();
        }

        try {
            // 1. Try Select / Dropdown
            var $targetSelect = $('select[name="' + swapField + '"], select[name="' + swapField + '[]"], select[name="df_' + swapField + '"], select[name*="' + swapField + '"]');
            if ($targetSelect.length) {
                var currentVal = $targetSelect.val();
                if (Array.isArray(currentVal)) {
                    if (currentVal.indexOf(strSwapValue) !== -1 || currentVal.join(',') === strSwapValue) {
                        finishSwap();
                        return;
                    }
                } else if (String(currentVal) === strSwapValue && $targetSelect.data('tg-swapped') === strSwapValue) {
                    finishSwap();
                    return; // Already set to this option
                }

                $targetSelect.data('tg-swapped', strSwapValue);
                $targetSelect.val(swapValue);
                triggerElementEventChain($targetSelect, finishSwap);
                return;
            }

            // 2. Try Radio Button
            var $targetRadio = $('input[type="radio"][name="' + swapField + '"][value="' + swapValue + '"], input[type="radio"][name*="' + swapField + '"][value="' + swapValue + '"]');
            if ($targetRadio.length) {
                if ($targetRadio.is(':checked')) {
                    finishSwap();
                    return;
                }

                $targetRadio.prop('checked', true);
                triggerElementEventChain($targetRadio, finishSwap);
                return;
            }

            // 3. Try Checkbox
            var $targetCb = $('input[type="checkbox"][name="' + swapField + '"][value="' + swapValue + '"], input[type="checkbox"][name*="' + swapField + '"][value="' + swapValue + '"]');
            if ($targetCb.length) {
                if ($targetCb.is(':checked')) {
                    finishSwap();
                    return;
                }

                $targetCb.prop('checked', true);
                triggerElementEventChain($targetCb, finishSwap);
                return;
            }

            finishSwap();
        } catch(err) {
            finishSwap();
        }
    }

    function applyRuleSwaps(rule, callback) {
        if (!rule) {
            if (typeof callback === 'function') callback();
            return;
        }

        var swapField = rule.swap_field || '';
        var swapValue = rule.swap_value || rule.suggested_category;

        if (!swapField || !swapValue) {
            if (typeof callback === 'function') callback();
            return;
        }

        applyFieldSwap(swapField, swapValue, function() {
            if (rule.secondary_swap_field && rule.secondary_swap_value) {
                setTimeout(function() {
                    applyFieldSwap(rule.secondary_swap_field, rule.secondary_swap_value, function() {
                        if (typeof callback === 'function') callback();
                    });
                }, 100);
            } else {
                if (typeof callback === 'function') callback();
            }
        });
    }

    function evaluateGuardRules(rules) {
        if (window._tgIsEvaluating || window._tgSwapping || window._tgCircuitBreakerActive) {
            return;
        }

        var now = Date.now();
        if (!window._tgEvalHistory) window._tgEvalHistory = [];
        window._tgEvalHistory = window._tgEvalHistory.filter(function(t) { return now - t < 1000; });
        window._tgEvalHistory.push(now);

        if (window._tgEvalHistory.length > 3) {
            window._tgCircuitBreakerActive = true;
            console.warn('[TicketGuard] Circuit breaker tripped (more than 3 evaluations in 1 second). Pausing auto-evaluation until next user keystroke.');
            return;
        }

        window._tgIsEvaluating = true;

        try {
            var shouldDisableSubmit = false;
            var disableSubmitMsg = null;
            var modalBlockedRule = null;
            var matchedRuleId = null;

            // Remove existing inline banners
            $('.stackboost-tg-banner').remove();

            $.each(rules, function(index, rule) {
                if (!rule.enabled) {
                    return;
                }

                var fieldSlug = rule.monitored_field || (Array.isArray(rule.monitored_fields) && rule.monitored_fields.length ? rule.monitored_fields[0] : '');
                var monitoredFields = fieldSlug ? [fieldSlug] : (rule.monitored_fields || []);
                var keywords = rule.keywords || [];
                var matched = false;
                var $matchedField = null;

                $.each(monitoredFields, function(i, slug) {
                    if (!slug) return;

                    // Try multiple DOM selector patterns for SupportCandy custom/included fields
                    var $field = $('[name="' + slug + '"], [name="' + slug + '[]"], [name*="[' + slug + ']"], #' + slug);

                    // Check TinyMCE instance
                    var editorInstance = (typeof tinymce !== 'undefined') ? (tinymce.get(slug) || (tinymce.editors && tinymce.editors[0])) : null;

                    var val = '';
                    if (editorInstance) {
                        try {
                            val = editorInstance.getContent({ format: 'text' }) || editorInstance.getContent() || '';
                        } catch (e) {
                            val = '';
                        }
                    }

                    if (!val && $field.length) {
                        val = $field.val() || '';
                    }

                    if (!$field.length && editorInstance) {
                        $matchedField = $(editorInstance.getContainer());
                    } else if ($field.length) {
                        $matchedField = $field;
                    } else {
                        $matchedField = $('textarea').first();
                        if ($matchedField.length && !val) {
                            val = $matchedField.val() || '';
                        }
                    }

                    var valLower = val.toLowerCase();

                    $.each(keywords, function(k, kwLine) {
                        kwLine = $.trim(kwLine);
                        if (!kwLine) return;

                        // Compound AND matching via &
                        var parts = kwLine.split('&').map(function(p) { return $.trim(p); }).filter(function(p) { return p.length > 0; });
                        if (!parts.length) return;

                        var lineMatches = true;
                        $.each(parts, function(pIdx, subKw) {
                            if (valLower.indexOf(subKw.toLowerCase()) === -1) {
                                lineMatches = false;
                                return false;
                            }
                        });

                        if (lineMatches) {
                            matched = true;
                            return false;
                        }
                    });

                    if (matched) return false;
                });

                if (matched) {
                    // Check if target option(s) are ALREADY set on the form as configured
                    if (isTargetOptionAlreadySelected(rule)) {
                        return; // Option is already selected - no action needed
                    }

                    var isRuleDismissed = !!(window._tgModalDismissedRules && window._tgModalDismissedRules[rule.id]);

                    matchedRuleId = rule.id;
                    var actions = rule.actions || {};

                    if (actions.disable_submit && !isRuleDismissed) {
                        shouldDisableSubmit = true;
                        if (rule.messaging && rule.messaging.submit_disabled_message) {
                            disableSubmitMsg = formatRulePlaceholders(rule.messaging.submit_disabled_message, rule);
                        } else if (!disableSubmitMsg) {
                            disableSubmitMsg = 'Submit button disabled: Please review your entry or category selection.';
                        }
                    }

                    if (actions.show_modal && !isRuleDismissed) {
                        modalBlockedRule = rule;

                        if (window._tgModalShownRuleId !== rule.id) {
                            window._tgModalShownRuleId = rule.id;
                            var $targetBtn = $(submitBtnSelector).first();
                            showGuidanceModal($targetBtn, rule, false); // Triggered automatically while typing
                        }
                    }

                    var swapField = rule.swap_field || '';
                    var swapValue = rule.swap_value || rule.suggested_category;

                    if (actions.show_inline_warning && $matchedField && $matchedField.length && !isRuleDismissed) {
                        var warnText = (rule.messaging && rule.messaging.inline_warning) ? rule.messaging.inline_warning : 'Keywords detected: Please ensure appropriate category selection.';
                        warnText = formatRulePlaceholders(warnText, rule);
                        var level = (rule.messaging && rule.messaging.inline_level) ? rule.messaging.inline_level : 'alert';
                        var iconClass = 'dashicons-warning';
                        if (level === 'info') {
                            iconClass = 'dashicons-info';
                        } else if (level === 'warning') {
                            iconClass = 'dashicons-dismiss';
                        }

                        var hasFixItOptions = !!(swapField && swapValue);
                        var fixItBtnText = (stackboostTicketGuard.i18n && stackboostTicketGuard.i18n.fix_it) ? stackboostTicketGuard.i18n.fix_it : 'Fix It';
                        var fixItIconBtnHtml = hasFixItOptions ? '<button type="button" class="stackboost-icon-btn sb-tg-fix-it-btn" title="' + escapeHtml(fixItBtnText) + '"><span class="dashicons dashicons-admin-tools"></span></button>' : '';

                        var dismissBtnText = (stackboostTicketGuard.i18n && stackboostTicketGuard.i18n.dismiss) ? stackboostTicketGuard.i18n.dismiss : 'Dismiss';
                        var dismissIconBtnHtml = '<button type="button" class="stackboost-icon-btn sb-tg-dismiss-btn" title="' + escapeHtml(dismissBtnText) + '"><span class="dashicons dashicons-dismiss"></span></button>';

                        var bannerContentHtml = warnText || '';
                        if (bannerContentHtml.indexOf('{fix_it_button}') !== -1) {
                            bannerContentHtml = bannerContentHtml.replace(/\{fix_it_button\}/g, fixItIconBtnHtml);
                        }

                        var actionsGroupHtml = '<span class="sb-tg-banner-actions" style="margin-left: auto; display: inline-flex; align-items: center; gap: 4px;">' +
                            (warnText.indexOf('{fix_it_button}') === -1 ? fixItIconBtnHtml : '') +
                            dismissIconBtnHtml +
                            '</span>';

                        var $banner = $('<div class="stackboost-tg-banner sb-tg-' + escapeHtml(level) + '"><span class="dashicons ' + iconClass + '"></span><span style="flex:1;">' + bannerContentHtml + '</span>' + actionsGroupHtml + '</div>');

                        if (hasFixItOptions) {
                            $banner.find('.sb-tg-fix-it-btn').on('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                if (rule.id) {
                                    if (!window._tgModalDismissedRules) window._tgModalDismissedRules = {};
                                    window._tgModalDismissedRules[rule.id] = true;
                                }
                                applyRuleSwaps(rule, function() {
                                    $banner.remove();
                                    evaluateGuardRules(rules);
                                });
                            });
                        }

                        $banner.find('.sb-tg-dismiss-btn').on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (rule.id) {
                                if (!window._tgModalDismissedRules) window._tgModalDismissedRules = {};
                                window._tgModalDismissedRules[rule.id] = true;
                            }
                            $banner.remove();
                            evaluateGuardRules(rules);
                        });

                        $matchedField.after($banner);
                    }

                    if (actions.auto_swap_category && swapField && swapValue) {
                        applyRuleSwaps(rule);
                    }
                }
            });

            if (!matchedRuleId) {
                window._tgModalShownRuleId = null;
            }

            // Apply consolidated submit button state across all rules
            var $submitBtns = $(submitBtnSelector);
            $('#sb-tg-submit-disabled-banner').remove();

            if (shouldDisableSubmit && $submitBtns.length) {
                var bannerText = disableSubmitMsg || 'Submit button disabled: Please review your entry or category selection.';
                var $disBanner = $('<div id="sb-tg-submit-disabled-banner" class="stackboost-tg-banner sb-tg-warning"><span class="dashicons dashicons-lock"></span><span>' + escapeHtml(bannerText) + '</span></div>');
                $submitBtns.first().before($disBanner);
            }

            $submitBtns.each(function() {
                var $btn = $(this);
                var btnEl = this;

                if (shouldDisableSubmit) {
                    var msgText = disableSubmitMsg || 'Submit button disabled: Please review your entry or category selection.';
                    $btn.attr('title', msgText).data('tg-disabled-msg', msgText);
                    $btn.prop('disabled', true).addClass('stackboost-tg-submit-disabled').css({
                        'opacity': '0.5',
                        'cursor': 'not-allowed',
                        'pointer-events': 'auto'
                    });
                } else {
                    $btn.removeAttr('title').removeData('tg-disabled-msg');
                    $btn.prop('disabled', false).removeClass('stackboost-tg-submit-disabled').css({
                        'opacity': '1',
                        'cursor': 'pointer',
                        'pointer-events': 'auto'
                    });
                }

                if (modalBlockedRule) {
                    $btn.data('tg-modal-blocked', true).data('tg-modal-rule', modalBlockedRule);

                    if (!btnEl._tgOriginalOnClick && btnEl.onclick) {
                        btnEl._tgOriginalOnClick = btnEl.onclick;
                    }

                    btnEl.onclick = function(e) {
                        if ($btn.data('tg-modal-bypassed')) {
                            if (btnEl._tgOriginalOnClick) {
                                return btnEl._tgOriginalOnClick.call(btnEl, e);
                            }
                            return true;
                        }

                        if (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                        }

                        showGuidanceModal($btn, modalBlockedRule, true); // Direct user click
                        return false;
                    };

                } else {
                    $btn.data('tg-modal-blocked', false).removeData('tg-modal-rule');
                    if (btnEl._tgOriginalOnClick) {
                        btnEl.onclick = btnEl._tgOriginalOnClick;
                    }
                }
            });
        } finally {
            window._tgIsEvaluating = false;
        }
    }

    function formatRulePlaceholders(text, rule) {
        if (!text || typeof text !== 'string') return '';
        if (!rule) return text;

        var dropdowns = (typeof stackboostTicketGuard !== 'undefined' && stackboostTicketGuard.dropdowns) ? stackboostTicketGuard.dropdowns : {};

        var primaryFieldSlug = rule.swap_field || '';
        var primaryVal = rule.swap_value || rule.suggested_category || '';

        var secFieldSlug = rule.secondary_swap_field || '';
        var secVal = rule.secondary_swap_value || '';

        // Resolve Primary Field Label
        var primaryFieldLabel = '';
        if (primaryFieldSlug && dropdowns[primaryFieldSlug] && dropdowns[primaryFieldSlug].label) {
            primaryFieldLabel = dropdowns[primaryFieldSlug].label;
        } else if (primaryFieldSlug) {
            var $pEl = $('select[name="' + primaryFieldSlug + '"], select[name="df_' + primaryFieldSlug + '"], [name="' + primaryFieldSlug + '"]');
            if ($pEl.length) {
                var $pLabel = $('label[for="' + $pEl.attr('id') + '"]');
                if ($pLabel.length) {
                    primaryFieldLabel = $.trim($pLabel.text());
                }
            }
            if (!primaryFieldLabel) primaryFieldLabel = primaryFieldSlug;
        }

        // Resolve Primary Response / Target Option Text
        var primaryResponseText = '';
        if (primaryFieldSlug && primaryVal && dropdowns[primaryFieldSlug] && Array.isArray(dropdowns[primaryFieldSlug].options)) {
            $.each(dropdowns[primaryFieldSlug].options, function(i, opt) {
                if (String(opt.id) === String(primaryVal)) {
                    primaryResponseText = opt.name;
                    return false;
                }
            });
        }
        if (!primaryResponseText && primaryVal) {
            var $optEl = $('option[value="' + primaryVal + '"], input[value="' + primaryVal + '"]');
            if ($optEl.length) {
                if ($optEl.is('option')) {
                    primaryResponseText = $.trim($optEl.text());
                } else {
                    var $lbl = $('label[for="' + $optEl.attr('id') + '"]');
                    if ($lbl.length) primaryResponseText = $.trim($lbl.text());
                }
            }
            if (!primaryResponseText) primaryResponseText = primaryVal;
        }

        // Resolve Secondary Field Label
        var secFieldLabel = '';
        if (secFieldSlug && dropdowns[secFieldSlug] && dropdowns[secFieldSlug].label) {
            secFieldLabel = dropdowns[secFieldSlug].label;
        } else if (secFieldSlug) {
            var $sEl = $('select[name="' + secFieldSlug + '"], select[name="df_' + secFieldSlug + '"], [name="' + secFieldSlug + '"]');
            if ($sEl.length) {
                var $sLabel = $('label[for="' + $sEl.attr('id') + '"]');
                if ($sLabel.length) {
                    secFieldLabel = $.trim($sLabel.text());
                }
            }
            if (!secFieldLabel) secFieldLabel = secFieldSlug;
        }

        // Resolve Secondary Response / Target Option Text
        var secResponseText = '';
        if (secFieldSlug && secVal && dropdowns[secFieldSlug] && Array.isArray(dropdowns[secFieldSlug].options)) {
            $.each(dropdowns[secFieldSlug].options, function(i, opt) {
                if (String(opt.id) === String(secVal)) {
                    secResponseText = opt.name;
                    return false;
                }
            });
        }
        if (!secResponseText && secVal) {
            var $secOptEl = $('option[value="' + secVal + '"], input[value="' + secVal + '"]');
            if ($secOptEl.length) {
                if ($secOptEl.is('option')) {
                    secResponseText = $.trim($secOptEl.text());
                } else {
                    var $secLbl = $('label[for="' + $secOptEl.attr('id') + '"]');
                    if ($secLbl.length) secResponseText = $.trim($secLbl.text());
                }
            }
            if (!secResponseText) secResponseText = secVal;
        }

        return text
            .replace(/\{primary_field\}/g, primaryFieldLabel)
            .replace(/\{primary_response\}/g, primaryResponseText)
            .replace(/\{target_option\}/g, primaryResponseText)
            .replace(/\{secondary_field\}/g, secFieldLabel)
            .replace(/\{secondary_response\}/g, secResponseText);
    }

    function isTargetOptionAlreadySelected(rule) {
        if (!rule) return false;

        var primaryField = rule.swap_field || '';
        var primaryValue = rule.swap_value || rule.suggested_category || '';

        var secondaryField = rule.secondary_swap_field || '';
        var secondaryValue = rule.secondary_swap_value || '';

        if (!primaryField || !primaryValue) {
            return false;
        }

        function isFieldValueSet(fieldSlug, targetVal) {
            if (!fieldSlug || !targetVal) return true;

            var strTarget = String(targetVal);

            // 1. Dropdown / Select
            var $select = $('select[name="' + fieldSlug + '"], select[name="' + fieldSlug + '[]"], select[name="df_' + fieldSlug + '"], select[name*="' + fieldSlug + '"]');
            if ($select.length) {
                var val = $select.val();
                if (Array.isArray(val)) {
                    return val.indexOf(strTarget) !== -1 || val.join(',') === strTarget;
                }
                return String(val) === strTarget;
            }

            // 2. Radio Button
            var $radioChecked = $('input[type="radio"][name="' + fieldSlug + '"]:checked, input[type="radio"][name*="' + fieldSlug + '"]:checked');
            if ($radioChecked.length) {
                return String($radioChecked.val()) === strTarget;
            }

            // 3. Checkbox
            var $cbChecked = $('input[type="checkbox"][name="' + fieldSlug + '"]:checked, input[type="checkbox"][name*="' + fieldSlug + '"]:checked');
            if ($cbChecked.length) {
                var cbVals = $cbChecked.map(function() { return String($(this).val()); }).get();
                return cbVals.indexOf(strTarget) !== -1;
            }

            return false;
        }

        var primaryMatches = isFieldValueSet(primaryField, primaryValue);

        var secondaryMatches = true;
        if (secondaryField && secondaryValue) {
            secondaryMatches = isFieldValueSet(secondaryField, secondaryValue);
        }

        return primaryMatches && secondaryMatches;
    }

    function showSubmitDisabledModal(message) {
        var title = stackboostTicketGuard.i18n ? (stackboostTicketGuard.i18n.notice_title || 'Submission Disabled') : 'Submission Disabled';

        if (typeof window.stackboostAlert === 'function') {
            window.stackboostAlert(
                '<p style="font-size:14px;line-height:1.5;margin:0;">' + escapeHtml(message) + '</p>',
                title
            );
        } else {
            $('.stackboost-tg-modal-overlay').remove();
            var modalHtml = '<div class="stackboost-modal-overlay stackboost-tg-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999999;display:flex;align-items:center;justify-content:center;">' +
                '<div class="stackboost-modal-box" style="background:#fff;border-radius:6px;max-width:520px;width:90%;box-shadow:0 8px 30px rgba(0,0,0,0.35);overflow:hidden;">' +
                '<div class="stackboost-modal-header" style="padding:16px 20px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">' +
                '<h3 class="stackboost-modal-title" style="margin:0;font-size:1.15em;font-weight:600;">' + escapeHtml(title) + '</h3>' +
                '<button type="button" class="stackboost-modal-close sb-tg-close-modal" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>' +
                '</div>' +
                '<div class="stackboost-modal-body" style="padding:20px 20px 10px;">' +
                '<p style="margin:0 0 15px 0;font-size:14px;line-height:1.5;color:#444;">' + escapeHtml(message) + '</p>' +
                '</div>' +
                '<div class="stackboost-modal-footer" style="padding:14px 20px;background:#f8f9fa;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:10px;">' +
                '<button type="button" class="button button-primary sb-tg-close-modal" style="cursor:pointer;">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.dismiss : 'OK') + '</button>' +
                '</div></div></div>';

            var $overlay = $(modalHtml);
            $('body').append($overlay);
            $overlay.find('.sb-tg-close-modal').on('click', function() {
                $overlay.remove();
            });
        }
    }

    function sanitizeGuidanceHtml(htmlStr) {
        if (!htmlStr || typeof htmlStr !== 'string') return '';
        var trimmed = $.trim(htmlStr);
        if (!trimmed) return '';
        return '<div style="font-size:14px;line-height:1.5;margin:0;">' + trimmed + '</div>';
    }

    function buildGuidanceModalContent(rule) {
        var msgs = (rule && rule.messaging) ? rule.messaging : {};

        if (msgs.is_kb_steering) {
            var kbTitle = msgs.modal_title || 'Information Available';
            var kbMsg = msgs.kb_message || 'It looks like your request might be answered in our Knowledge Base articles below. Please review these resources before submitting your ticket:';
            var docsList = msgs.kb_docs || [];

            var bodyHtml = sanitizeGuidanceHtml(kbMsg);
            if (Array.isArray(docsList) && docsList.length) {
                bodyHtml += '<ul style="margin:10px 0 15px 20px; padding:0; list-style:disc; font-size:14px; line-height:1.6;">';
                $.each(docsList, function(i, doc) {
                    var docTitle = doc.title || 'Knowledge Base Article';
                    var docUrl = doc.url || '#';
                    bodyHtml += '<li><a href="' + escapeHtml(docUrl) + '" target="_blank" rel="noopener noreferrer" style="color:#2271b1; font-weight:600; text-decoration:underline;">' + escapeHtml(docTitle) + '</a></li>';
                });
                bodyHtml += '</ul>';
            }

            return {
                title: kbTitle,
                bodyHtml: bodyHtml,
                isKb: true
            };
        } else {
            var rawTitle = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
            var rawBodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

            var title = formatRulePlaceholders(rawTitle, rule);
            var bodyText = formatRulePlaceholders(rawBodyText, rule);

            return {
                title: title,
                bodyHtml: sanitizeGuidanceHtml(bodyText),
                isKb: false
            };
        }
    }

    function showGuidanceModal($btn, rule, isUserSubmitClick) {
        var modalData = buildGuidanceModalContent(rule);
        var title = modalData.title;
        var bodyHtml = modalData.bodyHtml;

        if (!window._tgModalDismissedRules) window._tgModalDismissedRules = {};

        var changeCatText = stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.change_category : 'Switch Options';
        var proceedText = stackboostTicketGuard.i18n ? (stackboostTicketGuard.i18n.proceed || stackboostTicketGuard.i18n.proceed_anyway) : 'Proceed Anyway';

        var swapField = rule.swap_field || '';
        var swapValue = rule.swap_value || rule.suggested_category;

        if (swapValue && typeof window.stackboostConfirm === 'function') {
            window.stackboostConfirm(
                bodyHtml,
                title,
                function onConfirm() {
                    // Switch Target Options
                    if (rule.id) window._tgModalDismissedRules[rule.id] = true;
                    applyRuleSwaps(rule);
                },
                function onCancel() {
                    // Proceed Anyway
                    if (rule.id) window._tgModalDismissedRules[rule.id] = true;
                    if ($btn && $btn.length) {
                        $btn.data('tg-modal-bypassed', true);
                        if (isUserSubmitClick) {
                            var btnEl = $btn.get(0);
                            if (btnEl && btnEl._tgOriginalOnClick) {
                                btnEl._tgOriginalOnClick.call(btnEl);
                            } else {
                                $btn.trigger('click');
                            }
                        }
                    }
                },
                changeCatText,
                proceedText
            );
        } else if (typeof window.stackboostAlert === 'function') {
            window.stackboostAlert(
                bodyHtml,
                title,
                function() {
                    if (rule.id) window._tgModalDismissedRules[rule.id] = true;
                }
            );
        } else {
            fallbackShowGuidanceModal($btn, rule, isUserSubmitClick);
        }
    }

    function fallbackShowGuidanceModal($btn, rule, isUserSubmitClick) {
        $('.stackboost-tg-modal-overlay').remove();

        var modalData = buildGuidanceModalContent(rule);
        var title = modalData.title;
        var bodyHtml = modalData.bodyHtml;

        var swapField = rule.swap_field || '';
        var swapValue = rule.swap_value || rule.suggested_category;

        var modalHtml = '<div class="stackboost-modal-overlay stackboost-tg-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999999;display:flex;align-items:center;justify-content:center;">' +
            '<div class="stackboost-modal-box" style="background:#fff;border-radius:6px;max-width:520px;width:90%;box-shadow:0 8px 30px rgba(0,0,0,0.35);overflow:hidden;">' +
            '<div class="stackboost-modal-header" style="padding:16px 20px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">' +
            '<h3 class="stackboost-modal-title" style="margin:0;font-size:1.15em;font-weight:600;">' + escapeHtml(title) + '</h3>' +
            '<button type="button" class="stackboost-modal-close sb-tg-close-modal" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>' +
            '</div>' +
            '<div class="stackboost-modal-body" style="padding:20px 20px 10px;">' +
            bodyHtml +
            '</div>' +
            '<div class="stackboost-modal-footer" style="padding:14px 20px;background:#f8f9fa;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:10px;">' +
            '<button type="button" class="button sb-tg-proceed-btn" style="cursor:pointer;">' + escapeHtml(stackboostTicketGuard.i18n ? (stackboostTicketGuard.i18n.proceed || stackboostTicketGuard.i18n.proceed_anyway) : 'Proceed Anyway') + '</button>';

        if (swapValue) {
            modalHtml += '<button type="button" class="button button-primary sb-tg-swap-cat-btn" data-cat="' + escapeHtml(swapValue) + '" style="cursor:pointer;">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.change_category : 'Switch Option') + '</button>';
        } else {
            modalHtml += '<button type="button" class="button button-secondary sb-tg-close-modal" style="cursor:pointer;">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.dismiss : 'Dismiss') + '</button>';
        }

        modalHtml += '</div></div></div>';

        var $overlay = $(modalHtml);
        $('body').append($overlay);

        $overlay.find('.sb-tg-close-modal').on('click', function() {
            if (rule && rule.id) window._tgModalDismissedRules[rule.id] = true;
            $overlay.remove();
        });

        $overlay.find('.sb-tg-proceed-btn').on('click', function() {
            if (rule && rule.id) window._tgModalDismissedRules[rule.id] = true;
            $overlay.remove();
            if ($btn && $btn.length) {
                $btn.data('tg-modal-bypassed', true);
                if (isUserSubmitClick) {
                    var btnEl = $btn.get(0);
                    if (btnEl && btnEl._tgOriginalOnClick) {
                        btnEl._tgOriginalOnClick.call(btnEl);
                    } else {
                        $btn.trigger('click');
                    }
                }
            }
        });

        $overlay.find('.sb-tg-swap-cat-btn').on('click', function() {
            if (rule && rule.id) window._tgModalDismissedRules[rule.id] = true;
            applyRuleSwaps(rule);
            $overlay.remove();
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery);
