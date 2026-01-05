(function($) {
    'use strict';

    $(document).ready(function() {
        if (typeof stackboostCORules === 'undefined' || !stackboostCORules.rules) {
            return;
        }

        var rules = stackboostCORules.rules;
        var user = stackboostCORules.user;

        // Apply Rules
        $.each(rules, function(fieldSlug, rule) {
            applyRule(fieldSlug, rule, user);
        });

        // Re-apply on AJAX Complete (for SC ticket forms loaded via AJAX)
        $(document).ajaxComplete(function(event, xhr, settings) {
            $.each(rules, function(fieldSlug, rule) {
                applyRule(fieldSlug, rule, user);
            });
        });
    });

    function applyRule(fieldSlug, rule, user) {
        // rule: { context: 'wp'|'sc', option_rules: { optId: [roles] } }
        var optionRules = rule.option_rules;

        // 1. Identify Target Elements
        var $select = $('select[name="' + fieldSlug + '"], select[name="' + fieldSlug + '[]"]');
        if ($select.length) {
            processSelect($select, rule, user);
        }

        var $inputs = $('input[name="' + fieldSlug + '"], input[name="' + fieldSlug + '[]"]');
        if ($inputs.length) {
            processInputs($inputs, rule, user);
        }
    }

    function processSelect($select, rule, user) {
        var optionRules = rule.option_rules;
        var userRoles = (rule.context === 'wp') ? user.wp_roles : user.sc_roles;

        $select.find('option').each(function() {
            var $opt = $(this);
            var val = $opt.val();

            if (!val) return; // Skip placeholders

            var shouldHide = false;
            var targetRoles = optionRules[val];

            if (targetRoles) {
                // Deny Mode Only: Hide if user HAS role
                if (hasRole(userRoles, targetRoles)) {
                    shouldHide = true;
                }
            }

            if (shouldHide) {
                $opt.remove();
            }
        });

        if ($select.hasClass('select2-hidden-accessible') || $select.data('select2')) {
             $select.trigger('change.select2');
        }
    }

    function processInputs($inputs, rule, user) {
        var optionRules = rule.option_rules;
        var userRoles = (rule.context === 'wp') ? user.wp_roles : user.sc_roles;

        $inputs.each(function() {
            var $input = $(this);
            var val = $input.val();
            var shouldHide = false;
            var targetRoles = optionRules[val];

            if (targetRoles) {
                 if (hasRole(userRoles, targetRoles)) {
                    shouldHide = true;
                }
            }

            if (shouldHide) {
                var $wrapper = $input.closest('.wpsc-checkbox, .wpsc-radio, label, .pm-input-wrapper');
                if ($wrapper.length) {
                    $wrapper.remove();
                } else {
                    $input.remove();
                }
            }
        });
    }

    function hasRole(userRoles, targetRoles) {
        for (var i = 0; i < userRoles.length; i++) {
            if (targetRoles.indexOf(userRoles[i]) > -1) {
                return true;
            }
        }
        return false;
    }

})(jQuery);
