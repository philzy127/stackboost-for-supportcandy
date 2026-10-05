(function($) {
    'use strict';

    console.log('[StackBoost TicketGuard] admin-rules.js loaded.');

    var config = window.stackboostTicketGuardAdmin || {};
    var rules = config.rules || [];
    if (!Array.isArray(rules)) {
        rules = [];
    }

    $(document).ready(function() {
        console.log('[StackBoost TicketGuard] DOM ready. Initial rules count:', rules.length);
        renderRulesTable();

        // Master Enable Toggle
        $(document).on('change', '#stackboost_tg_enabled', function() {
            var isEnabled = $(this).is(':checked');
            console.log('[StackBoost TicketGuard] Master toggle changed:', isEnabled);
            $('#sb_tg_enabled_hidden').val(isEnabled ? '1' : '0');
            if (isEnabled) {
                $('#stackboost-tg-rules-card').removeClass('stackboost-disabled-ui');
            } else {
                $('#stackboost-tg-rules-card').addClass('stackboost-disabled-ui');
            }
            saveConfig(rules, isEnabled, 'Feature status updated.');
        });

        // Toggle visibility of conditional action sub-options
        $(document).on('change', '#sb-tg-act-disable-submit, #sb-tg-act-auto-swap, #sb-tg-act-show-modal, #sb-tg-act-show-inline', function() {
            toggleConditionalSections();
        });

        // Dynamic Primary Swap Target Options population
        $(document).on('change', '#sb-tg-swap-field', function() {
            var selectedSlug = $(this).val();
            populateSwapValueDropdown(selectedSlug, null);
        });

        // Dynamic Secondary Swap Target Options population
        $(document).on('change', '#sb-tg-sec-swap-field', function() {
            var selectedSlug = $(this).val();
            populateSecSwapValueDropdown(selectedSlug, null);
        });

        // Toggle BetterDocs KB Steering vs Standard Settings
        $(document).on('change', '#sb-tg-modal-is-kb', function() {
            var isKb = $(this).is(':checked');
            if (isKb) {
                $('#sb-tg-modal-standard-settings').hide();
                $('#sb-tg-modal-kb-settings').show();
                initSelect2();
                setTimeout(function() {
                    initModalRichTextEditors();
                }, 50);
            } else {
                $('#sb-tg-modal-kb-settings').hide();
                $('#sb-tg-modal-standard-settings').show();
                setTimeout(function() {
                    initModalRichTextEditors();
                }, 50);
            }
        });

        // Open Modal: Add Rule (Delegated & Direct Handlers)
        function handleAddRuleClick(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[StackBoost TicketGuard] Add New Rule button clicked.');

            if (!$('#stackboost_tg_enabled').is(':checked')) {
                console.log('[StackBoost TicketGuard] Enabling feature toggle automatically.');
                $('#stackboost_tg_enabled').prop('checked', true).trigger('change');
            }

            openRuleModal(null);
        }

        $(document).on('click', '#sb-tg-add-rule-btn', handleAddRuleClick);
        $('#sb-tg-add-rule-btn').on('click', handleAddRuleClick);

        // Close Modal
        $(document).on('click', '.sb-tg-modal-close', function(e) {
            e.preventDefault();
            console.log('[StackBoost TicketGuard] Close Modal button clicked.');
            closeRuleModal();
        });

        // Edit Rule Button
        $(document).on('click', '.sb-tg-edit-rule-btn', function(e) {
            e.preventDefault();
            var ruleId = $(this).data('id');
            console.log('[StackBoost TicketGuard] Edit rule clicked for ID:', ruleId);
            var rule = findRuleById(ruleId);
            if (rule) {
                openRuleModal(rule);
            }
        });

        // Delete Rule Button
        $(document).on('click', '.sb-tg-delete-rule-btn', function(e) {
            e.preventDefault();
            var ruleId = $(this).data('id');
            console.log('[StackBoost TicketGuard] Delete rule clicked for ID:', ruleId);
            if (confirm(config.i18n ? config.i18n.confirm_delete : 'Delete this rule?')) {
                rules = rules.filter(function(r) { return String(r.id) !== String(ruleId); });
                renderRulesTable();
                saveConfig(rules, $('#stackboost_tg_enabled').is(':checked'), 'Rule deleted.');
            }
        });

        // Save Rule from Modal
        $(document).on('click', '#sb-tg-save-rule-btn', function(e) {
            e.preventDefault();
            console.log('[StackBoost TicketGuard] Save Rule inside modal clicked.');

            if (typeof tinymce !== 'undefined') {
                try { tinymce.triggerSave(); } catch(err) {}
            }

            var ruleId = $('#sb-tg-rule-id').val();
            var name = $.trim($('#sb-tg-rule-name').val());
            var monitoredField = $('#sb-tg-monitored-field').val();
            var keywordsText = $.trim($('#sb-tg-rule-keywords').val());
            var swapField = $('#sb-tg-swap-field').val() || '';
            var swapValue = $('#sb-tg-swap-value').val() || '';
            var secSwapField = $('#sb-tg-sec-swap-field').val() || '';
            var secSwapValue = $('#sb-tg-sec-swap-value').val() || '';
            var inlineLevel = $('#sb-tg-inline-level').val() || 'alert';

            if (!name) {
                alert('Please enter a Rule Name.');
                return;
            }

            if (!monitoredField) {
                alert('Please select a Monitored Field.');
                return;
            }

            var keywords = keywordsText ? keywordsText.split('\n').map(function(k) { return $.trim(k); }).filter(function(k) { return k.length > 0; }) : [];

            var ruleObj = {
                id: ruleId || ('rule_' + Math.floor(Math.random() * 100000)),
                name: name,
                enabled: true,
                monitored_field: monitoredField,
                monitored_fields: [monitoredField],
                keywords: keywords,
                swap_field: swapField,
                swap_value: swapValue,
                secondary_swap_field: secSwapField,
                secondary_swap_value: secSwapValue,
                suggested_category: swapValue,
                actions: {
                    disable_submit: $('#sb-tg-act-disable-submit').is(':checked'),
                    show_modal: $('#sb-tg-act-show-modal').is(':checked'),
                    show_inline_warning: $('#sb-tg-act-show-inline').is(':checked'),
                    auto_swap_category: $('#sb-tg-act-auto-swap').is(':checked')
                },
                messaging: {
                    modal_title: $.trim($('#sb-tg-modal-custom-title').val()),
                    modal_body: $.trim($('#sb-tg-modal-custom-body').val()),
                    inline_warning: $.trim($('#sb-tg-inline-warning').val()),
                    inline_level: inlineLevel,
                    submit_disabled_message: $.trim($('#sb-tg-submit-disabled-msg').val()),
                    is_kb_steering: $('#sb-tg-modal-is-kb').is(':checked'),
                    kb_message: $.trim($('#sb-tg-modal-kb-message').val()),
                    kb_doc_ids: $('#sb-tg-modal-kb-docs').val() || []
                }
            };

            if (ruleId) {
                var idx = findRuleIndex(ruleId);
                if (idx !== -1) {
                    rules[idx] = ruleObj;
                } else {
                    rules.push(ruleObj);
                }
            } else {
                rules.push(ruleObj);
            }

            console.log('[StackBoost TicketGuard] Saving rule object:', ruleObj);
            renderRulesTable();
            closeRuleModal();
            saveConfig(rules, $('#stackboost_tg_enabled').is(':checked'), 'Rule saved successfully.');
        });
    });

    function toggleConditionalSections() {
        if ($('#sb-tg-act-disable-submit').is(':checked')) {
            $('#sb-tg-submit-msg-container').show();
        } else {
            $('#sb-tg-submit-msg-container').hide();
        }

        if ($('#sb-tg-act-show-modal').is(':checked')) {
            $('#sb-tg-modal-container').show();
        } else {
            $('#sb-tg-modal-container').hide();
        }

        if ($('#sb-tg-act-show-inline').is(':checked')) {
            $('#sb-tg-inline-container').show();
        } else {
            $('#sb-tg-inline-container').hide();
        }

        if ($('#sb-tg-act-auto-swap').is(':checked') || $('#sb-tg-act-show-modal').is(':checked') || $('#sb-tg-act-show-inline').is(':checked')) {
            $('#sb-tg-swap-container').show();
        } else {
            $('#sb-tg-swap-container').hide();
        }

        setTimeout(function() {
            initModalRichTextEditors();
        }, 50);
    }

    function initSelect2() {
        var select2Func = $.fn.selectWoo || $.fn.select2;
        if (!select2Func) return;

        var $modalBox = $('#sb-tg-modal-overlay');
        var $selects = $('#sb-tg-monitored-field, #sb-tg-swap-field, #sb-tg-swap-value, #sb-tg-sec-swap-field, #sb-tg-sec-swap-value, #sb-tg-modal-kb-docs');

        $selects.each(function() {
            var $s = $(this);
            if (!$s.is(':visible')) {
                return;
            }

            if ($s.data('select2') || $s.hasClass('select2-hidden-accessible')) {
                try { $s.select2('destroy'); } catch(e) {}
            }
            try {
                select2Func.call($s, {
                    width: '100%',
                    dropdownParent: $modalBox
                });
            } catch(e) {}
        });
    }

    function populateSwapValueDropdown(fieldSlug, selectedVal) {
        var $valSelect = $('#sb-tg-swap-value');
        $valSelect.empty();
        $valSelect.append('<option value="">-- Select Target Option --</option>');

        if (!fieldSlug || !config.dropdowns || !config.dropdowns[fieldSlug]) {
            initSelect2();
            return;
        }

        var optionsList = (config.dropdowns[fieldSlug].options || []).slice();
        optionsList.sort(function(a, b) {
            return String(a.name || '').localeCompare(String(b.name || ''));
        });

        $.each(optionsList, function(i, opt) {
            var $opt = $('<option>').val(opt.id).text(opt.name);
            if (String(opt.id) === String(selectedVal)) {
                $opt.prop('selected', true);
            }
            $valSelect.append($opt);
        });

        initSelect2();
    }

    function populateSecSwapValueDropdown(fieldSlug, selectedVal) {
        var $valSelect = $('#sb-tg-sec-swap-value');
        $valSelect.empty();
        $valSelect.append('<option value="">-- Select Secondary Option --</option>');

        if (!fieldSlug || !config.dropdowns || !config.dropdowns[fieldSlug]) {
            initSelect2();
            return;
        }

        var optionsList = (config.dropdowns[fieldSlug].options || []).slice();
        optionsList.sort(function(a, b) {
            return String(a.name || '').localeCompare(String(b.name || ''));
        });

        $.each(optionsList, function(i, opt) {
            var $opt = $('<option>').val(opt.id).text(opt.name);
            if (String(opt.id) === String(selectedVal)) {
                $opt.prop('selected', true);
            }
            $valSelect.append($opt);
        });

        initSelect2();
    }

    function renderRulesTable() {
        var $tbody = $('#sb-tg-rules-tbody');
        $tbody.empty();

        if (!Array.isArray(rules)) {
            rules = [];
        }

        $('#sb_tg_rules_hidden').val(JSON.stringify(rules));

        if (!rules.length) {
            $('#sb-tg-no-rules-msg').show();
            return;
        }

        $('#sb-tg-no-rules-msg').hide();

        $.each(rules, function(i, rule) {
            var fieldSlug = rule.monitored_field || (Array.isArray(rule.monitored_fields) ? rule.monitored_fields[0] : '');
            var fieldLabel = (config.fields && config.fields[fieldSlug]) ? config.fields[fieldSlug] : (fieldSlug || 'None');
            var keywordsStr = (rule.keywords || []).join(', ') || 'None';

            var actionsHtml = '';
            if (rule.actions) {
                if (rule.actions.disable_submit) actionsHtml += '<span class="sb-tg-action-badge active">Disable Submit</span>';
                if (rule.actions.show_modal) actionsHtml += '<span class="sb-tg-action-badge active">Modal</span>';
                if (rule.actions.show_inline_warning) actionsHtml += '<span class="sb-tg-action-badge active">Inline Warning</span>';
                if (rule.actions.auto_swap_category) actionsHtml += '<span class="sb-tg-action-badge active">Option Swap</span>';
            }
            if (!actionsHtml) actionsHtml = '<span class="sb-tg-action-badge">None</span>';

            var $row = $('<tr>');
            $row.append($('<td>').html('<strong>' + escapeHtml(rule.name || 'Unnamed Rule') + '</strong>'));
            $row.append($('<td>').text(fieldLabel));
            $row.append($('<td>').text(keywordsStr));
            $row.append($('<td>').html(actionsHtml));

            var actionsCellHtml = '<button type="button" class="stackboost-icon-btn sb-tg-edit-rule-btn" data-id="' + escapeHtml(rule.id) + '" title="Edit"><span class="dashicons dashicons-edit"></span></button>' +
                                  '<button type="button" class="stackboost-icon-btn sb-tg-delete-rule-btn" data-id="' + escapeHtml(rule.id) + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';

            $row.append($('<td style="text-align: right;">').html(actionsCellHtml));
            $tbody.append($row);
        });
    }

    function openRuleModal(rule) {
        console.log('[StackBoost TicketGuard] openRuleModal called.', rule);

        var $overlay = $('#sb-tg-modal-overlay');
        if ($overlay.parent().get(0) !== document.body) {
            console.log('[StackBoost TicketGuard] Moving modal overlay to body.');
            $('body').append($overlay);
        }

        // Destroy leftover select2 controls before updating form values
        var select2Func = $.fn.selectWoo || $.fn.select2;
        var $selects = $('#sb-tg-monitored-field, #sb-tg-swap-field, #sb-tg-swap-value, #sb-tg-sec-swap-field, #sb-tg-sec-swap-value, #sb-tg-modal-kb-docs');
        $selects.each(function() {
            var $s = $(this);
            if (select2Func && ($s.data('select2') || $s.hasClass('select2-hidden-accessible'))) {
                try { $s.select2('destroy'); } catch(e) {}
            }
        });

        if (rule) {
            $('#sb-tg-modal-title').text('Edit Intake Steering Rule');
            $('#sb-tg-rule-id').val(rule.id);
            $('#sb-tg-rule-name').val(rule.name || '');

            var monitoredSlug = rule.monitored_field || (Array.isArray(rule.monitored_fields) ? rule.monitored_fields[0] : '');
            $('#sb-tg-monitored-field').val(monitoredSlug || '');

            $('#sb-tg-rule-keywords').val((rule.keywords || []).join('\n'));

            var acts = rule.actions || {};
            $('#sb-tg-act-disable-submit').prop('checked', !!acts.disable_submit);
            $('#sb-tg-act-show-modal').prop('checked', !!acts.show_modal);
            $('#sb-tg-act-show-inline').prop('checked', !!acts.show_inline_warning);
            $('#sb-tg-act-auto-swap').prop('checked', !!acts.auto_swap_category);

            var swapField = rule.swap_field || '';
            var swapValue = rule.swap_value || rule.suggested_category || '';
            $('#sb-tg-swap-field').val(swapField);
            populateSwapValueDropdown(swapField, swapValue);

            var secSwapField = rule.secondary_swap_field || '';
            var secSwapValue = rule.secondary_swap_value || '';
            $('#sb-tg-sec-swap-field').val(secSwapField);
            populateSecSwapValueDropdown(secSwapField, secSwapValue);

            var msgs = rule.messaging || {};
            var isKb = !!msgs.is_kb_steering;
            $('#sb-tg-modal-is-kb').prop('checked', isKb);
            if (isKb) {
                $('#sb-tg-modal-standard-settings').hide();
                $('#sb-tg-modal-kb-settings').show();
            } else {
                $('#sb-tg-modal-kb-settings').hide();
                $('#sb-tg-modal-standard-settings').show();
            }

            $('#sb-tg-modal-custom-title').val(msgs.modal_title || '');
            setEditorContent('sb-tg-modal-custom-body', msgs.modal_body || '');
            $('#sb-tg-inline-warning').val(msgs.inline_warning || '');
            $('#sb-tg-inline-level').val(msgs.inline_level || 'alert');
            $('#sb-tg-submit-disabled-msg').val(msgs.submit_disabled_message || '');
            setEditorContent('sb-tg-modal-kb-message', msgs.kb_message || '');
            $('#sb-tg-modal-kb-docs').val(msgs.kb_doc_ids || []);
        } else {
            $('#sb-tg-modal-title').text('Add New Intake Steering Rule');
            $('#sb-tg-rule-id').val('');
            $('#sb-tg-rule-name').val('');
            $('#sb-tg-monitored-field').val('');
            $('#sb-tg-rule-keywords').val('');

            // Clear all action toggle checkboxes on new rule creation
            $('#sb-tg-act-disable-submit').prop('checked', false);
            $('#sb-tg-act-show-modal').prop('checked', false);
            $('#sb-tg-act-show-inline').prop('checked', false);
            $('#sb-tg-act-auto-swap').prop('checked', false);

            $('#sb-tg-swap-field').val('');
            populateSwapValueDropdown('', null);

            $('#sb-tg-sec-swap-field').val('');
            populateSecSwapValueDropdown('', null);

            $('#sb-tg-modal-is-kb').prop('checked', false);
            $('#sb-tg-modal-kb-settings').hide();
            $('#sb-tg-modal-standard-settings').show();

            $('#sb-tg-modal-custom-title').val('');
            setEditorContent('sb-tg-modal-custom-body', '');
            $('#sb-tg-inline-warning').val('');
            $('#sb-tg-inline-level').val('alert');
            $('#sb-tg-submit-disabled-msg').val('');
            setEditorContent('sb-tg-modal-kb-message', '');
            $('#sb-tg-modal-kb-docs').val([]);
        }

        toggleConditionalSections();

        // Reset scroll positions to top
        $overlay.scrollTop(0);
        $overlay.find('.stackboost-modal-body').scrollTop(0);

        // Display overlay instantly with flex
        $overlay.css({
            'display': 'flex',
            'visibility': 'visible',
            'opacity': 1,
            'z-index': 9999999
        });

        initSelect2();
        setTimeout(function() {
            initModalRichTextEditors();
        }, 100);
    }

    function initModalRichTextEditors() {
        if (typeof tinymce === 'undefined') return;

        var selectors = ['#sb-tg-modal-custom-body', '#sb-tg-modal-kb-message'];

        $.each(selectors, function(i, sel) {
            var $el = $(sel);
            var id = sel.replace('#', '');

            if (!$el.length || !$el.is(':visible')) {
                var existingEd = tinymce.get(id);
                if (existingEd) {
                    try { existingEd.remove(); } catch(e) {}
                }
                return;
            }

            var ed = tinymce.get(id);
            if (ed) {
                try {
                    ed.setContent($el.val() || '');
                } catch(e) {}
                return;
            }

            try {
                tinymce.init({
                    selector: sel,
                    menubar: false,
                    statusbar: false,
                    height: 160,
                    plugins: 'lists link wordpress',
                    toolbar: 'bold italic blockquote numlist bullist link',
                    setup: function(editor) {
                        editor.on('change keyup ExecCommand', function() {
                            editor.save();
                        });
                    }
                });
            } catch(e) {}
        });
    }

    function setEditorContent(id, content) {
        if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get(id);
            if (ed) {
                try { ed.setContent(content || ''); } catch(e) {}
            }
        }
        $('#' + id).val(content || '');
    }

    function closeRuleModal() {
        console.log('[StackBoost TicketGuard] closeRuleModal called.');

        var select2Func = $.fn.selectWoo || $.fn.select2;
        var $selects = $('#sb-tg-monitored-field, #sb-tg-swap-field, #sb-tg-swap-value, #sb-tg-sec-swap-field, #sb-tg-sec-swap-value, #sb-tg-modal-kb-docs');

        $selects.each(function() {
            var $s = $(this);
            if (select2Func && ($s.data('select2') || $s.hasClass('select2-hidden-accessible'))) {
                try { $s.select2('destroy'); } catch(e) {}
            }
        });

        if (typeof tinymce !== 'undefined') {
            ['sb-tg-modal-custom-body', 'sb-tg-modal-kb-message'].forEach(function(id) {
                var ed = tinymce.get(id);
                if (ed) {
                    try { ed.remove(); } catch(e) {}
                }
            });
        }

        $('#sb-tg-modal-overlay').scrollTop(0);
        $('#sb-tg-modal-overlay .stackboost-modal-body').scrollTop(0);

        $('#sb-tg-modal-overlay').css({
            'display': 'none',
            'visibility': 'hidden',
            'opacity': 0
        });
    }

    function findRuleIndex(ruleId) {
        if (!Array.isArray(rules)) return -1;
        for (var i = 0; i < rules.length; i++) {
            if (String(rules[i].id) === String(ruleId)) {
                return i;
            }
        }
        return -1;
    }

    function findRuleById(ruleId) {
        var idx = findRuleIndex(ruleId);
        return idx !== -1 ? rules[idx] : null;
    }

    function showNotice(msg, isError) {
        if (typeof window.stackboostToast === 'function') {
            window.stackboostToast(msg);
        } else {
            $('.sb-tg-toast').remove();
            var bg = isError ? '#d63638' : '#008a20';
            var icon = isError ? 'dashicons-dismiss' : 'dashicons-yes';
            var $toast = $('<div class="sb-tg-toast" style="position: fixed; bottom: 30px; right: 30px; background: ' + bg + '; color: #fff; padding: 10px 18px; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); font-weight: 600; font-size: 13px; z-index: 9999999; display: flex; align-items: center; gap: 8px;"><span class="dashicons ' + icon + '" style="font-size:18px; width:18px; height:18px; line-height:18px;"></span><span>' + escapeHtml(msg) + '</span></div>');
            $('body').append($toast);
            $toast.fadeIn(200).delay(2500).fadeOut(300, function() {
                $(this).remove();
            });
        }
    }

    function saveConfig(rulesArray, isEnabled, feedbackMsg, callback) {
        console.log('[StackBoost TicketGuard] saveConfig called. Enabled:', isEnabled, 'Rules:', rulesArray);

        $('#sb_tg_enabled_hidden').val(isEnabled ? '1' : '0');
        $('#sb_tg_rules_hidden').val(JSON.stringify(rulesArray));

        $.post(config.ajax_url || ajaxurl, {
            action: 'stackboost_tg_save_rules',
            nonce: config.nonce,
            enabled: isEnabled ? 'true' : 'false',
            rules: JSON.stringify(rulesArray)
        }, function(res) {
            console.log('[StackBoost TicketGuard] saveConfig AJAX success response:', res);
            if (feedbackMsg) {
                var msgText = typeof feedbackMsg === 'string' ? feedbackMsg : (config.i18n ? config.i18n.saved_success : 'Settings saved successfully.');
                showNotice(msgText, false);
            }
            if (typeof callback === 'function') callback();
        }).fail(function(xhr, status, error) {
            console.error('[StackBoost TicketGuard] saveConfig AJAX error:', status, error);
            var errText = config.i18n ? config.i18n.save_error : 'Failed to save settings.';
            showNotice(errText, true);
            if (typeof callback === 'function') callback();
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery);
