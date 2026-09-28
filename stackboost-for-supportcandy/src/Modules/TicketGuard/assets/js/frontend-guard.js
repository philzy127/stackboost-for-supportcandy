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
            if ($btn.data('tg-modal-blocked')) {
                e.preventDefault();
                e.stopPropagation();
                showGuidanceModal($btn.data('tg-modal-rule'));
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

            var monitoredFields = rule.monitored_fields || [];
            var keywords = rule.keywords || [];
            var matched = false;
            var $matchedField = null;

            $.each(monitoredFields, function(i, fieldSlug) {
                var $field = $('[name="' + fieldSlug + '"], [name="' + fieldSlug + '[]"]');
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

    function showGuidanceModal(rule) {
        $('.stackboost-tg-modal-overlay').remove();

        var msgs = rule.messaging || {};
        var title = msgs.modal_title || (stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.notice_title : 'Category Guidance');
        var bodyText = msgs.modal_body || 'It looks like your ticket content relates to a specific department. Please consider updating your category selection before submitting.';

        var modalHtml = '<div class="stackboost-modal-overlay stackboost-tg-modal-overlay">' +
            '<div class="stackboost-modal-box" style="max-width: 500px; width: 90%;">' +
            '<div class="stackboost-modal-header">' +
            '<h3 class="stackboost-modal-title">' + escapeHtml(title) + '</h3>' +
            '<button type="button" class="stackboost-modal-close sb-tg-close-modal">&times;</button>' +
            '</div>' +
            '<div class="stackboost-modal-body" style="padding: 15px;">' +
            '<p>' + escapeHtml(bodyText) + '</p>' +
            '</div>' +
            '<div class="stackboost-modal-footer">' +
            '<button type="button" class="button button-secondary sb-tg-close-modal">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.dismiss : 'Dismiss') + '</button>';

        if (rule.suggested_category) {
            modalHtml += '<button type="button" class="button button-primary sb-tg-swap-cat-btn" data-cat="' + escapeHtml(rule.suggested_category) + '">' + escapeHtml(stackboostTicketGuard.i18n ? stackboostTicketGuard.i18n.change_category : 'Switch Category') + '</button>';
        }

        modalHtml += '</div></div></div>';

        var $overlay = $(modalHtml);
        $('body').append($overlay);

        $overlay.find('.sb-tg-close-modal').on('click', function() {
            $overlay.remove();
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
