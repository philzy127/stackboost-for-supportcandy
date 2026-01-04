(function($) {
    'use strict';

    // State
    var state = {
        rules: stackboostCO.rules || {}, // Map: field_slug => rule object
        fieldOptionsCache: {},
        rolesCache: { wp: [], sc: [] },
        limit: 5 // Default limit
    };

    // Initialize
    $(document).ready(function() {
        initLimit();
        renderRules();
        updateCounter();

        // Event Listeners
        $('#pm-add-rule-btn').on('click', addNewRule);
        $('#pm-save-all-btn').on('click', saveAllRules);
    });

    function initLimit() {
        // Simple tier check
        if (stackboostCO.tier === 'lite') {
            state.limit = 5;
        } else {
            state.limit = 999; // Unlimited
        }
    }

    function updateCounter() {
        var count = Object.keys(state.rules).length;
        var text = 'Rules Used: ' + count + ' / ' + (state.limit === 999 ? '∞' : state.limit);
        var $counter = $('.pm-limit-counter');

        $counter.text(text);

        if (count >= state.limit) {
            $counter.addClass('limit-reached');
            $('#pm-add-rule-btn').prop('disabled', true);
        } else {
            $counter.removeClass('limit-reached');
            $('#pm-add-rule-btn').prop('disabled', false);
        }
    }

    function renderRules() {
        var $container = $('#pm-rules-container');
        $container.empty();

        $.each(state.rules, function(slug, rule) {
            $container.append(buildRuleCard(slug, rule));
            // Trigger fetch options if not loaded
            if (slug) {
                loadFieldData(slug, rule.context);
            }
        });
    }

    function addNewRule() {
        // Create a temporary unique ID for new rule untill field is selected
        var tempId = 'new_' + Date.now();
        var newRule = {
            context: 'wp',
            option_rules: {}
        };

        // Add to DOM only, don't add to state.rules until field is selected to avoid key collisions
        var $card = buildRuleCard('', newRule, tempId);
        $('#pm-rules-container').prepend($card);

        // Disable Add button if limit reached (visual check)
        var count = $('#pm-rules-container .pm-rule-card').length;
        if (stackboostCO.tier === 'lite' && count > 5) {
            // Revert
            $card.remove();
            alert(stackboostCO.i18n.limit_reached);
        }
    }

    function buildRuleCard(slug, rule, tempId) {
        var cardId = slug ? 'rule-' + slug : 'rule-' + tempId;
        var $card = $('<div class="pm-rule-card" id="' + cardId + '"></div>');

        // Header
        var html = '<div class="pm-rule-header">';
        html += '<h3>' + (slug ? getFieldName(slug) : 'New Rule') + '</h3>';
        html += '<a href="#" class="pm-delete-rule" data-slug="' + slug + '" data-tempid="' + tempId + '">&times;</a>';
        html += '</div>';

        // Settings
        html += '<div class="pm-settings-row">';

        // Field Selector
        html += '<div class="pm-field-selector">';
        html += '<label>Target Field: </label>';
        html += '<select class="pm-field-select" ' + (slug ? 'disabled' : '') + '>';
        html += '<option value="">-- Select Field --</option>';
        $.each(stackboostCO.fields, function(fSlug, fName) {
            html += '<option value="' + fSlug + '" ' + (slug === fSlug ? 'selected' : '') + '>' + fName + '</option>';
        });
        html += '</select>';
        html += '</div>';

        // Context Selector
        html += '<div class="pm-context-selector">';
        html += '<label>Role Context: </label>';
        html += '<label><input type="radio" name="ctx_' + cardId + '" value="wp" ' + (rule.context === 'wp' ? 'checked' : '') + '> WP Roles</label> ';
        html += '<label><input type="radio" name="ctx_' + cardId + '" value="sc" ' + (rule.context === 'sc' ? 'checked' : '') + '> SC Roles</label>';
        html += '</div>';

        html += '</div>'; // End settings row

        // Matrix Container
        html += '<div class="pm-matrix-container">';
        html += '<div class="pm-loading-placeholder">Select a field to configure options.</div>';
        html += '</div>';

        var $el = $(html);

        // Bind Events
        $card.append($el);

        // Delete
        $card.find('.pm-delete-rule').on('click', function(e) {
            e.preventDefault();
            if (confirm(stackboostCO.i18n.confirm_delete)) {
                // BUG FIX: Read slug from the DOM element, as 'slug' variable is closure-scoped
                // and might be empty if this is a newly created rule that was just assigned a field.
                var currentSlug = $(this).attr('data-slug');

                if (currentSlug) {
                    delete state.rules[currentSlug];
                }
                $card.remove();
                updateCounter();
            }
        });

        // Field Change
        $card.find('.pm-field-select').on('change', function() {
            var newSlug = $(this).val();
            if (newSlug) {
                // Check if rule already exists
                if (state.rules[newSlug]) {
                    alert('Rule for this field already exists.');
                    $(this).val('');
                    return;
                }

                // Promote new rule
                state.rules[newSlug] = rule;
                // Update DOM ID
                $card.attr('id', 'rule-' + newSlug);
                $card.find('.pm-delete-rule').attr('data-slug', newSlug);
                $card.find('h3').text(getFieldName(newSlug));
                $(this).prop('disabled', true); // Lock field once selected

                loadFieldData(newSlug, rule.context);
                updateCounter();
            }
        });

        // Context Change
        $card.find('input[type=radio]').on('change', function() {
            var newCtx = $(this).val();
            rule.context = newCtx;
            // Clear existing rules if context switches?
            // The brief says "The Admin selects roles from the chosen context only."
            // Implicitly, yes, we should probably clear or re-render.
            // But we keep `option_rules` structure but validation roles will change.

            // Re-render matrix with new context roles
            var currentSlug = slug || $card.find('.pm-field-select').val();
            if (currentSlug) {
                renderMatrix(currentSlug, rule.context);
            }
        });

        return $card;
    }

    function getFieldName(slug) {
        return stackboostCO.fields[slug] || slug;
    }

    function loadFieldData(slug, context) {
        var $card = $('#rule-' + slug);
        var $matrix = $card.find('.pm-matrix-container');

        $matrix.html('<div class="pm-loading">Loading options...</div>');

        // Fetch Roles (if not cached)
        var rolesPromise = $.Deferred();
        if (state.rolesCache[context].length > 0) {
            rolesPromise.resolve(state.rolesCache[context]);
        } else {
            $.post(stackboost_admin_ajax.ajax_url, {
                action: 'stackboost_co_get_roles',
                nonce: stackboost_admin_ajax.nonce,
                context: context
            }, function(res) {
                if (res.success) {
                    state.rolesCache[context] = res.data;
                    rolesPromise.resolve(res.data);
                } else {
                    rolesPromise.reject();
                }
            });
        }

        // Fetch Options (if not cached)
        var optionsPromise = $.Deferred();
        if (state.fieldOptionsCache[slug]) {
            optionsPromise.resolve(state.fieldOptionsCache[slug]);
        } else {
            $.post(stackboost_admin_ajax.ajax_url, {
                action: 'stackboost_co_get_field_options',
                nonce: stackboost_admin_ajax.nonce,
                field_slug: slug,
                field_name: getFieldName(slug) // Fallback or helper
            }, function(res) {
                if (res.success) {
                    state.fieldOptionsCache[slug] = res.data;
                    optionsPromise.resolve(res.data);
                } else {
                    $matrix.html('<div class="error">Failed to load options: ' + res.data.message + '</div>');
                    optionsPromise.reject();
                }
            });
        }

        $.when(rolesPromise, optionsPromise).done(function(roles, options) {
            renderMatrixTable(slug, roles, options);
        });
    }

    function renderMatrixTable(slug, roles, options) {
        var $card = $('#rule-' + slug);
        var $matrix = $card.find('.pm-matrix-container');
        var rule = state.rules[slug];

        if (!options.length) {
            $matrix.html('<div class="notice">No options found for this field.</div>');
            return;
        }

        var html = '<table class="pm-matrix-table">';
        html += '<thead><tr><th>Option Name</th><th>Hide from Roles (Click to Toggle)</th></tr></thead>';
        html += '<tbody>';

        $.each(options, function(i, opt) {
            html += '<tr>';
            html += '<td>' + opt.name + '</td>';
            html += '<td class="pm-role-cell" data-opt-id="' + opt.id + '">';

            // Render Role Pills
            var currentHidden = rule.option_rules[opt.id] || [];

            $.each(roles, function(j, role) {
                var isHidden = currentHidden.indexOf(role.slug) > -1;
                html += '<span class="pm-role-pill ' + (isHidden ? 'selected' : '') + '" data-role="' + role.slug + '">' + role.name + '</span>';
            });

            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $matrix.html(html);

        // Bind Click Handlers for Pills
        $matrix.find('.pm-role-pill').on('click', function() {
            var $pill = $(this);
            var $cell = $pill.closest('td');
            var optId = $cell.data('opt-id');
            var roleSlug = $pill.data('role');

            $pill.toggleClass('selected');

            // Update State
            if (!rule.option_rules[optId]) rule.option_rules[optId] = [];

            if ($pill.hasClass('selected')) {
                // Add
                if (rule.option_rules[optId].indexOf(roleSlug) === -1) {
                    rule.option_rules[optId].push(roleSlug);
                }
            } else {
                // Remove
                var idx = rule.option_rules[optId].indexOf(roleSlug);
                if (idx > -1) {
                    rule.option_rules[optId].splice(idx, 1);
                }
            }
        });
    }

    // Helper to refresh matrix when context changes
    function renderMatrix(slug, context) {
        loadFieldData(slug, context);
    }

    function saveAllRules() {
        var $btn = $('#pm-save-all-btn');
        var $spinner = $btn.next('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        // Prepare Data
        // state.rules is already up to date via references

        $.post(stackboost_admin_ajax.ajax_url, {
            action: 'stackboost_co_save_rules',
            nonce: stackboost_admin_ajax.nonce,
            rules: JSON.stringify(state.rules)
        }, function(res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res.success) {
                alert(res.data.message);
            } else {
                alert('Error: ' + res.data.message);
            }
        });
    }

})(jQuery);
