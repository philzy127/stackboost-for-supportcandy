(function($) {
    'use strict';

    var debounceTimer = null;
    var submitBtnSelector = '#wpsc-ct-submit, .wpsc-submit-btn, #wpsc-submit, .wpsc-open-ticket-btn, .wpsc-create-ticket-btn, button.wpsc-btn-primary, button.wpsc-btn, button[onclick*="wpsc"], button[type="submit"], input[type="submit"]';

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
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                evaluateGuardRules(rules);
            }, 100);
        });

        // Initialize TinyMCE / Rich Text Editor Real-time Observer
        initTinyMCEObserver(rules);

        // Re-evaluate on dynamic SupportCandy AJAX form loads & TinyMCE init
        $(document).ajaxComplete(function() {
            initTinyMCEObserver(rules);
            evaluateGuardRules(rules);
        });

        // Initial Evaluation
        evaluateGuardRules(rules);

        // Native Capture Phase Event Interceptor for Submit Buttons
        window.addEventListener('click', function(e) {
            var btn = e.target ? e.target.closest(submitBtnSelector) : null;
            if (!btn) return;

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
                showGuidanceModal($btn, blockedRule);
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

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                evaluateGuardRules(rules);
            }, 100);
        };

        editor.on('keyup input Change ExecCommand SetContent NodeChange', triggerEval);
    }

    function evaluateGuardRules(rules) {
        var shouldDisableSubmit = false;
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

                $.each(keywords, function(k, kw) {
                    kw = $.trim(kw);
                    if (kw && val.toLowerCase().indexOf(kw.toLowerCase()) !== -1) {
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
                }

                if (actions.show_modal) {
                    modalBlockedRule = rule;

                    var isDismissed = window._tgModalDismissedRules && window._tgModalDismissedRules[rule.id];
                    if (window._tgModalShownRuleId !== rule.id && !isDismissed) {
                        window._tgModalShownRuleId = rule.id;
                        var $targetBtn = $(submitBtnSelector).first();
                        showGuidanceModal($targetBtn, rule);
                    }
                }

                if (actions.show_inline_warning && $matchedField && $matchedField.length) {
                    var warnText = (rule.messaging && rule.messaging.inline_warning) ? rule.messaging.inline_warning : 'Keywords detected: Please ensure appropriate category selection.';
                    var $banner = $('<div class="stackboost-tg-banner"><span class="dashicons dashicons-warning"></span><span>' + escapeHtml(warnText) + '</span></div>');
                    $matchedField.after($banner);
                }

                var swapField = rule.swap_field || 'df_category';
                var swapValue = rule.swap_value || rule.suggested_category;

                if (actions.auto_swap_category && swapValue) {
                    var $targetSelect = $('select[name="' + swapField + '"], select[name="' + swapField + '[]"], select[name="df_' + swapField + '"], select[name*="' + swapField + '"]');
                    if ($targetSelect.length && $targetSelect.val() !== swapValue) {
                        $targetSelect.val(swapValue).trigger('change').trigger('change.select2');
                    }
                }
            }
        });

        if (!matchedRuleId) {
            window._tgModalShownRuleId = null;
        }

        // Apply consolidated submit button state across all rules
        var $submitBtns = $(submitBtnSelector);

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

                    showGuidanceModal($btn, modalBlockedRule);
                    return false;
                };

            } else {
                $btn.data('tg-modal-blocked', false).removeData('tg-modal-rule');
                if (btnEl._tgOriginalOnClick) {
                    btnEl.onclick = btnEl._tgOriginalOnClick;
                }
            }
        });
    }

    function showGuidanceModal($btn, rule) {
        var msgs = rule.messaging || {};
        var title = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
        var bodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

        if (!window._tgModalDismissedRules) window._tgModalDismissedRules = {};

        var changeCatText = stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.change_category : 'Switch Option';
        var proceedText = stackboostTicketGuard.i18n ? (stackboostTicketGuard.i18n.proceed || stackboostTicketGuard.i18n.proceed_anyway) : 'Proceed Anyway';

        var swapField = rule.swap_field || 'df_category';
        var swapValue = rule.swap_value || rule.suggested_category;

        if (swapValue && typeof window.stackboostConfirm === 'function') {
            window.stackboostConfirm(
                '<p style="font-size:14px;line-height:1.5;margin:0;">' + escapeHtml(bodyText) + '</p>',
                title,
                function onConfirm() {
                    // Switch Target Option
                    if (rule.id) window._tgModalDismissedRules[rule.id] = true;
                    var $targetSelect = $('select[name="' + swapField + '"], select[name="' + swapField + '[]"], select[name="df_' + swapField + '"], select[name*="' + swapField + '"]');
                    if ($targetSelect.length) {
                        $targetSelect.val(swapValue).trigger('change').trigger('change.select2');
                    }
                },
                function onCancel() {
                    // Proceed Anyway
                    if (rule.id) window._tgModalDismissedRules[rule.id] = true;
                    if ($btn && $btn.length) {
                        $btn.data('tg-modal-bypassed', true);
                        var btnEl = $btn.get(0);
                        if (btnEl && btnEl._tgOriginalOnClick) {
                            btnEl._tgOriginalOnClick.call(btnEl);
                        } else {
                            $btn.trigger('click');
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
            fallbackShowGuidanceModal($btn, rule);
        }
    }

    function fallbackShowGuidanceModal($btn, rule) {
        $('.stackboost-tg-modal-overlay').remove();

        var msgs = rule.messaging || {};
        var title = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
        var bodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

        var swapField = rule.swap_field || 'df_category';
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
                var btnEl = $btn.get(0);
                if (btnEl && btnEl._tgOriginalOnClick) {
                    btnEl._tgOriginalOnClick.call(btnEl);
                } else {
                    $btn.trigger('click');
                }
            }
        });

        $overlay.find('.sb-tg-swap-cat-btn').on('click', function() {
            if (rule && rule.id) window._tgModalDismissedRules[rule.id] = true;
            var catId = $(this).data('cat');
            var $catSelect = $('select[name="' + swapField + '"], select[name="' + swapField + '[]"], select[name="df_' + swapField + '"], select[name*="' + swapField + '"]');
            if ($catSelect.length && catId) {
                $catSelect.val(catId).trigger('change').trigger('change.select2');
            }
            $overlay.remove();
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery);
