(function($) {
    'use strict';

    var config = window.stackboostTicketGuardAdmin || {};
    var rules = config.rules || [];

    $(document).ready(function() {
        renderRulesTable();

        // Master Enable Toggle
        $('#stackboost_tg_enabled').on('change', function() {
            var isEnabled = $(this).is(':checked');
            if (isEnabled) {
                $('#stackboost-tg-rules-card').removeClass('stackboost-disabled-ui');
            } else {
                $('#stackboost-tg-rules-card').addClass('stackboost-disabled-ui');
            }
            saveConfig(rules, isEnabled);
        });

        // Open Modal: Add Rule
        $('#sb-tg-add-rule-btn').on('click', function() {
            openRuleModal(null);
        });

        // Close Modal
        $('.sb-tg-modal-close').on('click', function() {
            closeRuleModal();
        });

        // Edit Rule Button
        $(document).on('click', '.sb-tg-edit-rule-btn', function() {
            var ruleId = $(this).data('id');
            var rule = findRuleById(ruleId);
            if (rule) {
                openRuleModal(rule);
            }
        });

        // Delete Rule Button
        $(document).on('click', '.sb-tg-delete-rule-btn', function() {
            var ruleId = $(this).data('id');
            if (confirm(config.i18n ? config.i18n.confirm_delete : 'Delete this rule?')) {
                rules = rules.filter(function(r) { return r.id !== ruleId; });
                renderRulesTable();
                saveConfig(rules, $('#stackboost_tg_enabled').is(':checked'));
            }
        });

        // Save Rule from Modal
        $('#sb-tg-save-rule-btn').on('click', function() {
            var ruleId = $('#sb-tg-rule-id').val();
            var name = $.trim($('#sb-tg-rule-name').val());
            var keywordsText = $.trim($('#sb-tg-rule-keywords').val());

            if (!name) {
                alert('Please enter a Rule Name.');
                return;
            }

            var monitoredFields = [];
            $('.sb-tg-field-cb:checked').each(function() {
                monitoredFields.push($(this).val());
            });

            var keywords = keywordsText ? keywordsText.split('\n').map(function(k) { return $.trim(k); }).filter(function(k) { return k.length > 0; }) : [];

            var ruleObj = {
                id: ruleId || ('rule_' + Math.floor(Math.random() * 100000)),
                name: name,
                enabled: true,
                monitored_fields: monitoredFields,
                keywords: keywords,
                trigger_category: $('#sb-tg-trigger-category').val(),
                suggested_category: $('#sb-tg-suggested-category').val(),
                actions: {
                    disable_submit: $('#sb-tg-act-disable-submit').is(':checked'),
                    show_modal: $('#sb-tg-act-show-modal').is(':checked'),
                    show_inline_warning: $('#sb-tg-act-show-inline').is(':checked'),
                    auto_swap_category: $('#sb-tg-act-auto-swap').is(':checked')
                },
                messaging: {
                    inline_warning: $.trim($('#sb-tg-inline-warning').val())
                }
            };

            if (ruleId) {
                var idx = rules.findIndex(function(r) { return r.id === ruleId; });
                if (idx !== -1) {
                    rules[idx] = ruleObj;
                } else {
                    rules.push(ruleObj);
                }
            } else {
                rules.push(ruleObj);
            }

            renderRulesTable();
            closeRuleModal();
            saveConfig(rules, $('#stackboost_tg_enabled').is(':checked'));
        });
    });

    function renderRulesTable() {
        var $tbody = $('#sb-tg-rules-tbody');
        $tbody.empty();

        if (!rules || !rules.length) {
            $('#sb-tg-no-rules-msg').show();
            return;
        }

        $('#sb-tg-no-rules-msg').hide();

        $.each(rules, function(i, rule) {
            var fieldsStr = (rule.monitored_fields || []).join(', ') || 'None';
            var keywordsStr = (rule.keywords || []).join(', ') || 'None';

            var actionsHtml = '';
            if (rule.actions) {
                if (rule.actions.disable_submit) actionsHtml += '<span class="sb-tg-action-badge active">Disable Submit</span>';
                if (rule.actions.show_modal) actionsHtml += '<span class="sb-tg-action-badge active">Modal</span>';
                if (rule.actions.show_inline_warning) actionsHtml += '<span class="sb-tg-action-badge active">Inline Warning</span>';
                if (rule.actions.auto_swap_category) actionsHtml += '<span class="sb-tg-action-badge active">Category Swap</span>';
            }
            if (!actionsHtml) actionsHtml = '<span class="sb-tg-action-badge">None</span>';

            var $row = $('<tr>');
            $row.append($('<td>').html('<strong>' + escapeHtml(rule.name || 'Unnamed Rule') + '</strong>'));
            $row.append($('<td>').text(fieldsStr));
            $row.append($('<td>').text(keywordsStr));
            $row.append($('<td>').html(actionsHtml));

            var actionsCellHtml = '<button type="button" class="button button-small sb-tg-edit-rule-btn" data-id="' + escapeHtml(rule.id) + '">Edit</button>' +
                                  '<span class="sb-tg-delete-rule-btn dashicons dashicons-trash" data-id="' + escapeHtml(rule.id) + '" title="Delete"></span>';

            $row.append($('<td style="text-align: right;">').html(actionsCellHtml));
            $tbody.append($row);
        });
    }

    function openRuleModal(rule) {
        if (rule) {
            $('#sb-tg-modal-title').text('Edit Intake Steering Rule');
            $('#sb-tg-rule-id').val(rule.id);
            $('#sb-tg-rule-name').val(rule.name || '');
            $('#sb-tg-rule-keywords').val((rule.keywords || []).join('\n'));
            $('#sb-tg-trigger-category').val(rule.trigger_category || '');
            $('#sb-tg-suggested-category').val(rule.suggested_category || '');

            $('.sb-tg-field-cb').prop('checked', false);
            if (rule.monitored_fields) {
                $.each(rule.monitored_fields, function(idx, slug) {
                    $('.sb-tg-field-cb[value="' + slug + '"]').prop('checked', true);
                });
            }

            var acts = rule.actions || {};
            $('#sb-tg-act-disable-submit').prop('checked', !!acts.disable_submit);
            $('#sb-tg-act-show-modal').prop('checked', !!acts.show_modal);
            $('#sb-tg-act-show-inline').prop('checked', !!acts.show_inline_warning);
            $('#sb-tg-act-auto-swap').prop('checked', !!acts.auto_swap_category);

            var msgs = rule.messaging || {};
            $('#sb-tg-inline-warning').val(msgs.inline_warning || '');
        } else {
            $('#sb-tg-modal-title').text('Add New Intake Steering Rule');
            $('#sb-tg-rule-id').val('');
            $('#sb-tg-rule-name').val('');
            $('#sb-tg-rule-keywords').val('');
            $('#sb-tg-trigger-category').val('');
            $('#sb-tg-suggested-category').val('');
            $('.sb-tg-field-cb').prop('checked', true);
            $('#sb-tg-act-disable-submit').prop('checked', true);
            $('#sb-tg-act-show-modal').prop('checked', true);
            $('#sb-tg-act-show-inline').prop('checked', true);
            $('#sb-tg-act-auto-swap').prop('checked', false);
            $('#sb-tg-inline-warning').val('');
        }

        $('#sb-tg-modal-overlay').show();
    }

    function closeRuleModal() {
        $('#sb-tg-modal-overlay').hide();
    }

    function findRuleById(id) {
        for (var i = 0; i < rules.length; i++) {
            if (rules[i].id === id) return rules[i];
        }
        return null;
    }

    function saveConfig(rulesArray, isEnabled) {
        $.post(config.ajax_url || ajaxurl, {
            action: 'stackboost_tg_save_rules',
            nonce: config.nonce,
            enabled: isEnabled ? 'true' : 'false',
            rules: JSON.stringify(rulesArray)
        }, function(res) {
            // Options persisted
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery);
