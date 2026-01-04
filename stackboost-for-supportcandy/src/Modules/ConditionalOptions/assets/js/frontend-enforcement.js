(function($) {
    'use strict';

    $(document).ready(function() {
        if (typeof stackboostCORules === 'undefined' || !stackboostCORules.rules) {
            return;
        }

        var rules = stackboostCORules.rules;
        var user = stackboostCORules.user;

        // Admin Bypass
        if (user.is_admin) {
            return;
        }

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
        var context = rule.context;
        var optionRules = rule.option_rules; // { option_id: [excluded_roles] }

        // 1. Identify Target Elements
        // SC fields typically have names like `cust_12` or `priority`, etc.
        // We look for Selects, Checkboxes, Radios.

        // Selects (Dropdowns, Multi-selects)
        var $select = $('select[name="' + fieldSlug + '"], select[name="' + fieldSlug + '[]"]');
        if ($select.length) {
            processSelect($select, rule, user);
        }

        // Radios / Checkboxes
        // SC structure: name="slug" or name="slug[]"
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

            // Skip placeholders
            if (!val) return;

            // Check if this option has restrictions
            if (optionRules[val]) {
                var excludedRoles = optionRules[val];
                if (isUserRestricted(userRoles, excludedRoles)) {
                    $opt.remove();
                }
            }
        });

        // Trigger update for Select2/SelectWoo if present
        if ($select.hasClass('select2-hidden-accessible') || $select.data('select2')) {
             $select.trigger('change.select2');
        }
        // SupportCandy uses SelectWoo
        if ($.fn.selectWoo) {
             // Sometimes strictly triggering change isn't enough if the option data is cached.
             // But usually removing the DOM option and triggering change works.
        }
    }

    function processInputs($inputs, rule, user) {
        var optionRules = rule.option_rules;
        var userRoles = (rule.context === 'wp') ? user.wp_roles : user.sc_roles;

        $inputs.each(function() {
            var $input = $(this);
            var val = $input.val();

            if (optionRules[val]) {
                var excludedRoles = optionRules[val];
                if (isUserRestricted(userRoles, excludedRoles)) {
                    // Remove the parent container usually to hide the label too
                    // SC Radio/Checkbox structure: <div><input><label></div> or similar
                    // We try to find the closest logical wrapper
                    var $wrapper = $input.closest('.wpsc-checkbox, .wpsc-radio, label, .pm-input-wrapper');

                    if ($wrapper.length) {
                        $wrapper.remove();
                    } else {
                        $input.remove(); // Fallback
                    }
                }
            }
        });
    }

    function isUserRestricted(userRoles, excludedRoles) {
        for (var i = 0; i < userRoles.length; i++) {
            if (excludedRoles.indexOf(userRoles[i]) > -1) {
                return true; // Match found -> Hide it
            }
        }
        return false;
    }

})(jQuery);
