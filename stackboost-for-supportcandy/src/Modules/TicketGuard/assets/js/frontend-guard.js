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

    function applyFieldSwap(swapField, swapValue) {
        if (!swapField || !swapValue || window._tgSwapping) return;

        var strSwapValue = String(swapValue);

        window._tgSwapping = true;
        try {
            // 1. Try Select / Dropdown
            var $targetSelect = $('select[name="' + swapField + '"], select[name="' + swapField + '[]"], select[name="df_' + swapField + '"], select[name*="' + swapField + '"]');
            if ($targetSelect.length) {
                var currentVal = $targetSelect.val();
                if (Array.isArray(currentVal)) {
                    if (currentVal.indexOf(strSwapValue) !== -1 || currentVal.join(',') === strSwapValue) return;
                } else if (String(currentVal) === strSwapValue || $targetSelect.data('tg-swapped') === strSwapValue) {
                    return; // Already set to this option, do NOT fire change event again
                }

                $targetSelect.data('tg-swapped', strSwapValue);
                $targetSelect.val(swapValue).trigger('change').trigger('change.select2');
                return;
            }

            // 2. Try Radio Button
            var $targetRadio = $('input[type="radio"][name="' + swapField + '"][value="' + swapValue + '"], input[type="radio"][name*="' + swapField + '"][value="' + swapValue + '"]');
            if ($targetRadio.length) {
                if ($targetRadio.is(':checked')) return; // Already checked

                $targetRadio.prop('checked', true).trigger('change');
                return;
            }

            // 3. Try Checkbox
            var $targetCb = $('input[type="checkbox"][name="' + swapField + '"][value="' + swapValue + '"], input[type="checkbox"][name*="' + swapField + '"][value="' + swapValue + '"]');
            if ($targetCb.length) {
                if ($targetCb.is(':checked')) return; // Already checked

                $targetCb.prop('checked', true).trigger('change');
                return;
            }
        } finally {
            window._tgSwapping = false;
        }
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
                    matchedRuleId = rule.id;
                    var actions = rule.actions || {};

                    if (actions.disable_submit) {
                        shouldDisableSubmit = true;
                        if (rule.messaging && rule.messaging.submit_disabled_message) {
                            disableSubmitMsg = rule.messaging.submit_disabled_message;
                        } else if (!disableSubmitMsg) {
                            disableSubmitMsg = 'Submit button disabled: Please review your entry or category selection.';
                        }
                    }

                    if (actions.show_modal) {
                        modalBlockedRule = rule;

                        var isDismissed = window._tgModalDismissedRules && window._tgModalDismissedRules[rule.id];
                        if (window._tgModalShownRuleId !== rule.id && !isDismissed) {
                            window._tgModalShownRuleId = rule.id;
                            var $targetBtn = $(submitBtnSelector).first();
                            showGuidanceModal($targetBtn, rule, false); // Triggered automatically while typing
                        }
                    }

                    if (actions.show_inline_warning && $matchedField && $matchedField.length) {
                        var warnText = (rule.messaging && rule.messaging.inline_warning) ? rule.messaging.inline_warning : 'Keywords detected: Please ensure appropriate category selection.';
                        var level = (rule.messaging && rule.messaging.inline_level) ? rule.messaging.inline_level : 'alert';
                        var iconClass = 'dashicons-warning';
                        if (level === 'info') {
                            iconClass = 'dashicons-info';
                        } else if (level === 'warning') {
                            iconClass = 'dashicons-dismiss';
                        }
                        var $banner = $('<div class="stackboost-tg-banner sb-tg-' + escapeHtml(level) + '"><span class="dashicons ' + iconClass + '"></span><span>' + escapeHtml(warnText) + '</span></div>');
                        $matchedField.after($banner);
                    }

                    var swapField = rule.swap_field || '';
                    var swapValue = rule.swap_value || rule.suggested_category;

                    if (actions.auto_swap_category && swapField && swapValue) {
                        applyFieldSwap(swapField, swapValue);
                        if (rule.secondary_swap_field && rule.secondary_swap_value) {
                            applyFieldSwap(rule.secondary_swap_field, rule.secondary_swap_value);
                        }
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
                    $btn.prop('disabled', true).addClass('stackboost-tg-submit-disabled').css({
                        'opacity': '0.5',
                        'cursor': 'not-allowed',
                        'pointer-events': 'none'
                    });
                } else {
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

    function showGuidanceModal($btn, rule, isUserSubmitClick) {
        var msgs = rule.messaging || {};
        var title = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
        var bodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

        if (!window._tgModalDismissedRules) window._tgModalDismissedRules = {};

        var changeCatText = stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.change_category : 'Switch Options';
        var proceedText = stackboostTicketGuard.i18n ? (stackboostTicketGuard.i18n.proceed || stackboostTicketGuard.i18n.proceed_anyway) : 'Proceed Anyway';

        var swapField = rule.swap_field || '';
        var swapValue = rule.swap_value || rule.suggested_category;

        if (swapValue && typeof window.stackboostConfirm === 'function') {
            window.stackboostConfirm(
                '<p style="font-size:14px;line-height:1.5;margin:0;">' + escapeHtml(bodyText) + '</p>',
                title,
                function onConfirm() {
                    // Switch Target Options
                    if (rule.id) window._tgModalDismissedRules[rule.id] = true;
                    applyFieldSwap(swapField, swapValue);
                    if (rule.secondary_swap_field && rule.secondary_swap_value) {
                        applyFieldSwap(rule.secondary_swap_field, rule.secondary_swap_value);
                    }
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
                '<p style="font-size:14px;line-height:1.5;margin:0;">' + escapeHtml(bodyText) + '</p>',
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

        var msgs = rule.messaging || {};
        var title = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
        var bodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

        var swapField = rule.swap_field || '';
        var swapValue = rule.swap_value || rule.suggested_category;

        var modalHtml = '<div class="stackboost-modal-overlay stackboost-tg-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999999;display:flex;align-items:center;justify-content:center;">' +
            '<div class="stackboost-modal-box" style="background:#fff;border-radius:6px;max-width:520px;width:90%;box-shadow:0 8px 30px rgba(0,0,0,0.35);overflow:hidden;">' +
            '<div class="stackboost-modal-header" style="padding:16px 20px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">' +
            '<h3 class="stackboost-modal-title" style="margin:0;font-size:1.15em;font-weight:600;">' + escapeHtml(title) + '</h3>' +
            '<button type="button" class="stackboost-modal-close sb-tg-close-modal" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>' +
            '</div>' +
            '<div class="stackboost-modal-body" style="padding:20px 20px 10px;">' +
            '<p style="margin:0 0 15px 0;font-size:14px;line-height:1.5;color:#444;">' + escapeHtml(bodyText) + '</p>' +
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
            applyFieldSwap(swapField, swapValue);
            if (rule.secondary_swap_field && rule.secondary_swap_value) {
                applyFieldSwap(rule.secondary_swap_field, rule.secondary_swap_value);
            }
            $overlay.remove();
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery);
