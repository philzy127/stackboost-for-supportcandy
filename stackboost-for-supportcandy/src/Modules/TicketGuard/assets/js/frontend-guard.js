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
    });

    function evaluateGuardRules(rules) {
        $.each(rules, function(index, rule) {
            if (!rule.enabled) {
                return;
            }

            var monitoredFields = rule.monitored_fields || [];
            var keywords = rule.keywords || [];
            var matched = false;

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
                        return false;
                    }
                });

                if (matched) return false;
            });

            var $submitBtn = $('.wpsc-submit-btn, #wpsc-submit, button[type="submit"]');

            if (matched) {
                // Apply enabled actions
                if (rule.actions && rule.actions.disable_submit) {
                    $submitBtn.addClass('stackboost-tg-submit-disabled').prop('disabled', true);
                }

                if (rule.actions && rule.actions.auto_swap_category && rule.suggested_category) {
                    var $catSelect = $('select[name="df_category"], select[name="category"]');
                    if ($catSelect.length && $catSelect.val() !== rule.suggested_category) {
                        $catSelect.val(rule.suggested_category).trigger('change').trigger('change.select2');
                    }
                }
            } else {
                if (rule.actions && rule.actions.disable_submit) {
                    $submitBtn.removeClass('stackboost-tg-submit-disabled').prop('disabled', false);
                }
            }
        });
    }

})(jQuery);
