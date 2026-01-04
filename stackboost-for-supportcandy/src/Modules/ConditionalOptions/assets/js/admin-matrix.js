(function($) {
    'use strict';

    // State initialization helper to avoid Array vs Object issues with JSON.stringify
    var initialRules = stackboostCO.rules;
    if (Array.isArray(initialRules) && initialRules.length === 0) {
        initialRules = {};
    }

    var state = {
        rules: initialRules, // Map: field_slug => rule object
        fieldOptionsCache: {},
        rolesCache: { wp: [], sc: [] },
        limit: 5 // Default limit
    };

    // Initialize
    $(document).ready(function() {
        if (typeof stackboostLog === 'function') {
            stackboostLog('Conditional Options: Admin JS Loaded.', state);
        }

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
            stackboostAlert(stackboostCO.i18n.limit_reached);
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

        // Initial Check for Context Lock
        updateContextLock($card, rule);

        // Delete
        $card.find('.pm-delete-rule').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            stackboostConfirm(stackboostCO.i18n.confirm_delete, 'Confirm Delete', function() {
                // BUG FIX: Read slug from the DOM element, as 'slug' variable is closure-scoped
                var currentSlug = $btn.attr('data-slug');

                if (currentSlug) {
                    delete state.rules[currentSlug];
                    if (typeof stackboostLog === 'function') {
                        stackboostLog('Conditional Options: Rule Deleted.', currentSlug);
                    }
                }
                $card.remove();
                updateCounter();
            }, null, 'Yes, Delete', 'Cancel', true);
        });

        // Field Change
        $card.find('.pm-field-select').on('change', function() {
            var newSlug = $(this).val();
            if (newSlug) {
                // Check if rule already exists
                if (state.rules[newSlug]) {
                    stackboostAlert('Rule for this field already exists.', 'Error');
                    $(this).val('');
                    return;
                }

                // Promote new rule
                state.rules[newSlug] = rule;
                if (typeof stackboostLog === 'function') {
                    stackboostLog('Conditional Options: New Rule Initialized.', { slug: newSlug, rule: rule });
                }

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

            if (typeof stackboostLog === 'function') {
                stackboostLog('Conditional Options: Context Changed.', { context: newCtx });
            }

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

    // Helper: Disable/Enable context radios based on selected rules
    function updateContextLock($card, rule) {
        var hasActiveRules = false;

        // Iterate active rules to check if any role is selected
        if (rule.option_rules) {
            $.each(rule.option_rules, function(optId, roles) {
                if (roles && roles.length > 0) {
                    hasActiveRules = true;
                    return false; // Break loop
                }
            });
        }

        var $radios = $card.find('input[type="radio"][name^="ctx_"]');

        if (hasActiveRules) {
            // Disable the unchecked radio
            $radios.not(':checked').prop('disabled', true);
        } else {
            // Enable all
            $radios.prop('disabled', false);
        }
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

            // Update Context Lock
            updateContextLock($card, rule);
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

        var payload = JSON.stringify(state.rules);

        if (typeof stackboostLog === 'function') {
            stackboostLog('Conditional Options: Saving Rules. Payload:', payload);
        }

        $.post(stackboost_admin_ajax.ajax_url, {
            action: 'stackboost_co_save_rules',
            nonce: stackboost_admin_ajax.nonce,
            rules: payload
        }, function(res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res.success) {
                stackboostAlert(res.data.message, 'Success');
            } else {
                stackboostAlert('Error: ' + res.data.message, 'Error');
            }
        });
    }

})(jQuery);
