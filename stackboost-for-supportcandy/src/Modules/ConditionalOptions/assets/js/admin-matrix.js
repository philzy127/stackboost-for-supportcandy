(function($) {
    'use strict';

    console.log('Conditional Options JS Loaded - v5.0 (Simplified)');

    // State initialization
    var initialRules = stackboostCO.rules;
    if (Array.isArray(initialRules) && initialRules.length === 0) {
        initialRules = {};
    }

    var state = {
        rules: initialRules,
        isEnabled: stackboostCO.enabled,
        fieldOptionsCache: {},
        rolesCache: { wp: [], sc: [] },
        limit: (stackboostCO.tier === 'lite') ? 5 : 999,
        currentEditingSlug: null,
        isNewRule: false
    };

    $(document).ready(function() {
        renderRulesTable();
        updateCounter();
        initModalEvents();
        initToggle();

        // Set initial button state
        $('#pm-add-rule-btn').prop('disabled', !state.isEnabled);

        $('#pm-add-rule-btn').on('click', function(e) {
            e.preventDefault();
            var count = Object.keys(state.rules).length;
            if (count >= state.limit) {
                stackboostAlert(stackboostCO.i18n.limit_reached);
                return;
            }
            openModal(null);
        });
    });

    function initToggle() {
        // Toggle Logic
        $('#stackboost_co_enabled').on('change', function() {
            var isChecked = $(this).is(':checked');
            state.isEnabled = isChecked;

            // Toggle UI state
            var $rulesCard = $('#stackboost-co-rules-card');
            if (isChecked) {
                $rulesCard.removeClass('stackboost-disabled-ui');
            } else {
                $rulesCard.addClass('stackboost-disabled-ui');
            }

            // Toggle Button State
            $('#pm-add-rule-btn').prop('disabled', !isChecked);

            // Auto-save
            saveToServer();
        });
    }

    // --- Table Rendering ---

    function renderRulesTable() {
        var $tbody = $('#pm-rules-table-body');
        $tbody.empty();

        var slugs = Object.keys(state.rules);

        if (slugs.length === 0) {
            $('#pm-no-rules-msg').show();
            $('.pm-rules-wrapper table').hide();
        } else {
            $('#pm-no-rules-msg').hide();
            $('.pm-rules-wrapper table').show();

            slugs.forEach(function(slug) {
                var rule = state.rules[slug];
                var fieldName = getFieldName(slug);
                var contextLabel = (rule.context === 'wp') ? 'WP Roles' : 'SupportCandy Roles';

                var row = '<tr>';
                row += '<td><strong>' + fieldName + '</strong><br><small style="color:#666">' + slug + '</small></td>';
                row += '<td>' + contextLabel + '</td>';
                row += '<td style="text-align: right;">';
                row += '<button type="button" class="stackboost-icon-btn pm-edit-rule" data-slug="' + slug + '" title="Edit"><span class="dashicons dashicons-edit"></span></button>';
                row += '<button type="button" class="stackboost-icon-btn pm-delete-rule" data-slug="' + slug + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
                row += '</td>';
                row += '</tr>';

                $tbody.append(row);
            });
        }
        updateCounter();
    }

    // --- Modal Logic ---

    function initModalEvents() {
        $(document).on('click', '.pm-edit-rule', function(e) {
            e.preventDefault();
            var slug = $(this).data('slug');
            openModal(slug);
        });

        $(document).on('click', '.pm-delete-rule', function(e) {
            e.preventDefault();
            var slug = $(this).data('slug');
            stackboostConfirm(stackboostCO.i18n.confirm_delete, 'Delete Rule', function() {
                delete state.rules[slug];
                saveToServer(function() {
                     renderRulesTable();
                });
            }, null, 'Delete', 'Cancel', true);
        });

        $('.stackboost-modal-close, .button-secondary').on('click', function(e) {
            e.preventDefault();
            closeModal();
        });

        $('#stackboost-co-modal-overlay').on('click', function(e) {
            if ($(e.target).is('#stackboost-co-modal-overlay')) {
                closeModal();
            }
        });

        $('#pm-modal-field-select').on('change', function() {
            var slug = $(this).val();
            if (slug) {
                var context = $('input[name="modal_context"]:checked').val();
                loadMatrix(slug, context);
            } else {
                $('#pm-modal-matrix').html('<div class="pm-loading-placeholder">Select a field to configure options.</div>');
            }
        });

        $('input[name="modal_context"]').on('change', function() {
            var context = $(this).val();
            var slug = $('#pm-modal-field-select').val();
            if (slug) {
                loadMatrix(slug, context);
            }
        });

        $('#pm-modal-save-btn').on('click', function(e) {
            e.preventDefault();
            saveModal();
        });
    }

    function openModal(slug) {
        var $modal = $('#stackboost-co-modal-overlay');
        var $title = $modal.find('.stackboost-modal-title');
        var $fieldSelect = $('#pm-modal-field-select');
        var $radiosContext = $('input[name="modal_context"]');

        $fieldSelect.val('').trigger('change');
        $('#pm-modal-matrix').empty();

        if (slug) {
            state.isNewRule = false;
            state.currentEditingSlug = slug;
            var rule = state.rules[slug];

            $title.text('Edit Rule: ' + getFieldName(slug));
            $fieldSelect.val(slug).trigger('change').prop('disabled', true);
            $radiosContext.filter('[value="' + rule.context + '"]').prop('checked', true);

            loadMatrix(slug, rule.context, rule.option_rules);
        } else {
            state.isNewRule = true;
            state.currentEditingSlug = null;

            $title.text('Add New Rule');
            $fieldSelect.prop('disabled', false);
            $radiosContext.filter('[value="sc"]').prop('checked', true);

            $('#pm-modal-matrix').html('<div class="pm-loading-placeholder">Select a field to configure options.</div>');
        }

        // Show Modal
        $modal.addClass('active').show().css({
            'display': 'flex',
            'visibility': 'visible',
            'opacity': '1',
            'z-index': '999999'
        });

        // Initialize Select2
        if ($.fn.select2) {
            $fieldSelect.select2({
                width: '100%',
                dropdownParent: $modal
            });
        }
    }

    function closeModal() {
        var $modal = $('#stackboost-co-modal-overlay');
        $modal.removeClass('active').hide().css({
            'display': 'none',
            'visibility': 'hidden',
            'opacity': '0'
        });

        var $fieldSelect = $('#pm-modal-field-select');
        if ($fieldSelect.data('select2')) {
            $fieldSelect.select2('destroy');
        }
    }

    function saveModal() {
        var slug = $('#pm-modal-field-select').val();
        if (!slug) {
            alert('Please select a field.');
            return;
        }

        var context = $('input[name="modal_context"]:checked').val();

        var optionRules = {};
        $('.pm-matrix-table tbody tr').each(function() {
            var $row = $(this);
            var $cell = $row.find('.pm-role-cell');
            var optId = $cell.data('opt-id');
            var selectedRoles = [];

            $cell.find('.pm-role-pill.selected').each(function() {
                selectedRoles.push($(this).data('role'));
            });

            if (selectedRoles.length > 0) {
                optionRules[optId] = selectedRoles;
            }
        });

        if (state.isNewRule && state.rules[slug]) {
            alert('A rule for this field already exists. Please edit the existing rule.');
            return;
        }

        var newRule = {
            context: context,
            option_rules: optionRules
        };

        state.rules[slug] = newRule;

        var $btn = $('#pm-modal-save-btn');
        $btn.text('Saving...').prop('disabled', true);

        saveToServer(function() {
            $btn.text('Save Rule').prop('disabled', false);
            closeModal();
            renderRulesTable();
        }, function() {
            $btn.text('Save Rule').prop('disabled', false);
        });
    }

    function saveToServer(successCallback, failCallback) {
        // Show saving notice
        stackboost_show_toast('Saving...', 'info');

        var payload = JSON.stringify(state.rules);

        $.post(stackboost_admin_ajax.ajax_url, {
            action: 'stackboost_co_save_rules',
            nonce: stackboost_admin_ajax.nonce,
            rules: payload,
            enabled: state.isEnabled
        }, function(res) {
            if (res.success) {
                if (successCallback) successCallback();
                stackboost_show_toast(res.data.message || 'Settings saved successfully.', 'success');
            } else {
                if (failCallback) failCallback();
                stackboostAlert('Error: ' + res.data.message, 'Error');
            }
        }).fail(function() {
            if (failCallback) failCallback();
             stackboostAlert('Server Error.', 'Error');
        });
    }

    // --- Matrix Logic ---

    function loadMatrix(slug, context, existingRules) {
        var $container = $('#pm-modal-matrix');
        $container.html('<div class="pm-loading">Loading options & roles...</div>');

        var rolesPromise = $.Deferred();
        if (state.rolesCache[context] && state.rolesCache[context].length > 0) {
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

        var optionsPromise = $.Deferred();
        if (state.fieldOptionsCache[slug]) {
            optionsPromise.resolve(state.fieldOptionsCache[slug]);
        } else {
             $.post(stackboost_admin_ajax.ajax_url, {
                action: 'stackboost_co_get_field_options',
                nonce: stackboost_admin_ajax.nonce,
                field_slug: slug
            }, function(res) {
                if (res.success) {
                    state.fieldOptionsCache[slug] = res.data;
                    optionsPromise.resolve(res.data);
                } else {
                    optionsPromise.reject(res.data.message);
                }
            });
        }

        $.when(rolesPromise, optionsPromise).done(function(roles, options) {
            var rulesToApply = existingRules || {};
            renderMatrixHTML(roles, options, rulesToApply);
        }).fail(function(err) {
            $container.html('<div class="error">Error: ' + (err || 'Could not load data') + '</div>');
        });
    }

    function renderMatrixHTML(roles, options, selectedMap) {
        var $container = $('#pm-modal-matrix');

        if (!options.length) {
            $container.html('<div class="notice">No options found for this field.</div>');
            return;
        }

        var html = '<table class="pm-matrix-table">';
        html += '<thead><tr><th>Option Name</th><th>Target Roles (' + stackboostCO.i18n.toggle_all + ')</th></tr></thead>';
        html += '<tbody>';

        $.each(options, function(i, opt) {
            html += '<tr>';
            html += '<td>' + opt.name + '</td>';
            html += '<td class="pm-role-cell" data-opt-id="' + opt.id + '">';

            // Toggle All Button for Row
            html += '<span class="pm-toggle-all-row dashicons dashicons-yes" title="Select All / None"></span>';

            var currentSelected = selectedMap[opt.id] || [];

            $.each(roles, function(j, role) {
                var isSelected = currentSelected.indexOf(role.slug) > -1;
                html += '<span class="pm-role-pill ' + (isSelected ? 'selected' : '') + '" data-role="' + role.slug + '">' + role.name + '</span>';
            });

            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $container.html(html);

        // Bind Click Handlers
        $container.find('.pm-role-pill').on('click', function() {
            $(this).toggleClass('selected');
        });

        // Toggle All Handler
        $container.find('.pm-toggle-all-row').on('click', function() {
            var $btn = $(this);
            var $cell = $btn.closest('td');
            var $pills = $cell.find('.pm-role-pill');

            // Check state: if all are selected, deselect all. Otherwise, select all.
            var allSelected = $pills.length === $pills.filter('.selected').length;

            if (allSelected) {
                $pills.removeClass('selected');
                $btn.removeClass('active'); // Optional visual state
            } else {
                $pills.addClass('selected');
                $btn.addClass('active');
            }
        });
    }

    function getFieldName(slug) {
        return stackboostCO.fields[slug] || slug;
    }

    function updateCounter() {
        var count = Object.keys(state.rules).length;
        var text = 'Rules Used: ' + count + ' / ' + (state.limit === 999 ? '∞' : state.limit);
        $('.pm-limit-counter').text(text);

        if (count >= state.limit) {
            $('.pm-limit-counter').addClass('limit-reached');
        } else {
             $('.pm-limit-counter').removeClass('limit-reached');
        }
    }

})(jQuery);
