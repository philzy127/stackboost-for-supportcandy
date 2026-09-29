(function($) {
    'use strict';

    $(document).ready(function() {
        if (typeof stackboostTicketGuard === 'undefined' || !stackboostTicketGuard.enabled) {
            return;
        }

        var rules = stackboostTicketGuard.rules || [];
        if (!rules.length) {
            return;
        }

        var debounceTimer = null;

        // Debounced observer on text input/textarea fields
        $(document).on('input keyup paste change', 'input[type="text"], textarea', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                evaluateGuardRules(rules);
            }, 250);
        });

        // Re-evaluate on dynamic SupportCandy AJAX form loads
        $(document).ajaxComplete(function() {
            evaluateGuardRules(rules);
        });

        // Intercept submit click if modal notice is active
        $(document).on('click', '.wpsc-submit-btn, #wpsc-submit, button[type="submit"]', function(e) {
            var $btn = $(this);
            if ($btn.data('tg-modal-blocked') && !$btn.data('tg-modal-bypassed')) {
                e.preventDefault();
                e.stopPropagation();
                showGuidanceModal($btn, $btn.data('tg-modal-rule'));
                return false;
            }
        });
    });

    function evaluateGuardRules(rules) {
        var shouldDisableSubmit = false;
        var modalBlockedRule = null;

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
                var $field = $('[name="' + slug + '"], [name="' + slug + '[]"]');
                if (!$field.length) {
                    return;
                }

                var val = $field.val() || '';
                $.each(keywords, function(k, kw) {
                    kw = $.trim(kw);
                    if (kw && val.toLowerCase().indexOf(kw.toLowerCase()) !== -1) {
                        matched = true;
                        $matchedField = $field;
                        return false;
                    }
                });

                if (matched) return false;
            });

            if (matched) {
                var actions = rule.actions || {};

                if (actions.disable_submit) {
                    shouldDisableSubmit = true;
                }

                if (actions.show_modal) {
                    modalBlockedRule = rule;
                }

                if (actions.show_inline_warning && $matchedField) {
                    var warnText = (rule.messaging && rule.messaging.inline_warning) ? rule.messaging.inline_warning : 'Keywords detected: Please ensure appropriate category selection.';
                    var $banner = $('<div class="stackboost-tg-banner"><span class="dashicons dashicons-warning"></span><span>' + escapeHtml(warnText) + '</span></div>');
                    $matchedField.after($banner);
                }

                if (actions.auto_swap_category && rule.suggested_category) {
                    var $catSelect = $('select[name="df_category"], select[name="category"]');
                    if ($catSelect.length && $catSelect.val() !== rule.suggested_category) {
                        $catSelect.val(rule.suggested_category).trigger('change').trigger('change.select2');
                    }
                }
            }
        });

        // Apply consolidated submit button state across all rules
        var $submitBtn = $('.wpsc-submit-btn, #wpsc-submit, button[type="submit"]');

        if (shouldDisableSubmit) {
            $submitBtn.addClass('stackboost-tg-submit-disabled').prop('disabled', true);
        } else {
            $submitBtn.removeClass('stackboost-tg-submit-disabled').prop('disabled', false);
        }

        if (modalBlockedRule) {
            $submitBtn.data('tg-modal-blocked', true).data('tg-modal-rule', modalBlockedRule);
        } else {
            $submitBtn.data('tg-modal-blocked', false).removeData('tg-modal-rule');
        }
    }

    function showGuidanceModal($btn, rule) {
        $('.stackboost-tg-modal-overlay').remove();

        var msgs = rule.messaging || {};
        var title = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
        var bodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

        var modalHtml = '<div class="stackboost-modal-overlay stackboost-tg-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;display:flex;align-items:center;justify-content:center;">' +
            '<div class="stackboost-modal-box" style="background:#fff;border-radius:4px;max-width:500px;width:90%;box-shadow:0 4px 15px rgba(0,0,0,0.3);overflow:hidden;">' +
            '<div class="stackboost-modal-header" style="padding:15px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;">' +
            '<h3 class="stackboost-modal-title" style="margin:0;font-size:1.1em;">' + escapeHtml(title) + '</h3>' +
            '<button type="button" class="stackboost-modal-close sb-tg-close-modal" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>' +
            '</div>' +
            '<div class="stackboost-modal-body" style="padding:20px;">' +
            '<p style="margin:0 0 15px 0;">' + escapeHtml(bodyText) + '</p>' +
            '</div>' +
            '<div class="stackboost-modal-footer" style="padding:12px 20px;background:#f7f7f7;border-top:1px solid #ddd;display:flex;justify-content:flex-end;gap:10px;">' +
            '<button type="button" class="button sb-tg-proceed-btn">' + escapeHtml(stackboostTicketGuard.i18n ? (stackboostTicketGuard.i18n.proceed || stackboostTicketGuard.i18n.proceed_anyway) : 'Proceed Anyway') + '</button>';

        if (rule.suggested_category) {
            modalHtml += '<button type="button" class="button button-primary sb-tg-swap-cat-btn" data-cat="' + escapeHtml(rule.suggested_category) + '">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.change_category : 'Switch Category') + '</button>';
        } else {
            modalHtml += '<button type="button" class="button button-secondary sb-tg-close-modal">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.dismiss : 'Dismiss') + '</button>';
        }

        modalHtml += '</div></div></div>';

        var $overlay = $(modalHtml);
        $('body').append($overlay);

        $overlay.find('.sb-tg-close-modal').on('click', function() {
            $overlay.remove();
        });

        $overlay.find('.sb-tg-proceed-btn').on('click', function() {
            $overlay.remove();
            $btn.data('tg-modal-bypassed', true);
            $btn.trigger('click');
        });

        $overlay.find('.sb-tg-swap-cat-btn').on('click', function() {
            var catId = $(this).data('cat');
            var $catSelect = $('select[name="df_category"], select[name="category"]');
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
