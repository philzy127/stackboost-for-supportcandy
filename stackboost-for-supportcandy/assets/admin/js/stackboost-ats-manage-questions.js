jQuery(document).ready(function($) {
    var modal = $('#stackboost-ats-question-modal');
    var form = $('#stackboost-ats-question-form');
    var tableBody = $('#stackboost-ats-questions-list tbody');

    // Central Logging Helper - Wraps global utility
    function log(message, context) {
        if (typeof stackboost_log === 'function') {
            stackboost_log('[StackBoost ATS] ' + message, context);
        } else if (stackboost_ats_manage.diagnostic_log_enabled) {
            console.log('[StackBoost ATS] ' + message, context || '');
        }
    }

    log('Manage Questions script initialized.');

    // Helper to show a temporary status message
    function showStatus(message, type) {
        // Remove existing notices
        $('.stackboost-ats-status-notice').remove();

        var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        var notice = $('<div class="notice ' + noticeClass + ' stackboost-ats-status-notice is-dismissible"><p>' + message + '</p></div>');

        $('.stackboost-ats-questions-list').before(notice);

        if (type !== 'error') {
            setTimeout(function() {
                notice.fadeOut(function() { $(this).remove(); });
            }, 3000);
        }
    }

    // Initialize Dialog
    modal.dialog({
        autoOpen: false,
        modal: true,
        width: 500,
        buttons: {
            "Save": function() {
                saveQuestion();
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        },
        close: function() {
            form[0].reset();
            $('#question_id').val('');
            $('#ats_dropdown_options_group').hide();
            // Reset visibility
            $('#question_type').trigger('change');
        }
    });

    // Initialize Sortable
    tableBody.sortable({
        handle: '.stackboost-ats-sort-handle',
        update: function(event, ui) {
            log('Order updated via sortable.');
            showStatus('Saving order...', 'info');

            var order = [];
            tableBody.find('tr').each(function() {
                order.push($(this).data('id'));
            });

            $.post(stackboost_ats_manage.ajax_url, {
                action: 'stackboost_ats_reorder_questions',
                nonce: stackboost_ats_manage.nonce,
                order: order
            }, function(response) {
                if (!response.success) {
                    log('Reorder failed', response);
                    showStatus('Failed to save order: ' + response.data, 'error');
                } else {
                    log('Reorder successful');
                    showStatus('Order saved!', 'success');
                }
            });
        }
    });

    // Toggle Fields Visibility & Highlander Rule Check
    $('#question_type').on('change', function() {
        var type = $(this).val();
        var dropdownGroup = $('#ats_dropdown_options_group');
        var currentId = $('#question_id').val(); // ID of question being edited (if any)

        // Highlander Rule for 'ticket_number'
        if (type === 'ticket_number') {
            var existingTicketNum = false;
            // Iterate over existing rows to find if one exists
            $('#stackboost-ats-questions-list tbody tr').each(function() {
                var rowId = $(this).data('id');
                // Check 3rd column (Type) for "Ticket Number"
                var rowType = $(this).find('td:nth-child(3)').text().trim();

                // If it's a ticket number question AND it's not the one we are currently editing
                // We use toLowerCase() for robust comparison against 'ticket number', 'Ticket number', etc.
                if (rowType.toLowerCase() === 'ticket number' && rowId != currentId) {
                    existingTicketNum = true;
                    return false; // break loop
                }
            });

            if (existingTicketNum) {
                // Reset to previous or default (short_text)
                // Since we don't track 'previous', we just default to 'short_text'
                $(this).val('short_text');

                // Show toast
                stackboostToast('Only one "Ticket Number" question is allowed.', 'warning');

                // Re-trigger change to update UI for the new value (short_text)
                $(this).trigger('change');
                return;
            }
        }

        // Prefill key is now available for all types, so no logic needed for it

        // Dropdown options logic
        if (type === 'dropdown') {
            dropdownGroup.show();
        } else {
            dropdownGroup.hide();
        }
    });

    // Open Modal for New Question
    $('#stackboost-ats-add-question').on('click', function(e) {
        e.preventDefault();
        log('Add Question button clicked.');
        $('#question_type').trigger('change');
        modal.dialog('option', 'title', 'Add New Question');
        modal.dialog('open');
    });

    // Open Modal for Edit
    $(document).on('click', '.stackboost-ats-edit-question', function(e) {
        e.preventDefault();
        var questionId = $(this).data('id');
        log('Edit Question button clicked.', questionId);

        $.post(stackboost_ats_manage.ajax_url, {
            action: 'stackboost_ats_get_question',
            nonce: stackboost_ats_manage.nonce,
            question_id: questionId
        }, function(response) {
            if (response.success) {
                log('Question data retrieved.', response.data);
                var q = response.data;
                $('#question_id').val(q.id);
                $('#question_text').val(q.question_text);
                $('#question_type').val(q.question_type).trigger('change');
                $('#ats_sort_order').val(q.sort_order);
                $('#ats_is_required').prop('checked', q.is_required == 1);
            $('#ats_is_readonly_if_prefilled').prop('checked', q.is_readonly_if_prefilled == 1);
                $('#ats_dropdown_options').val(q.options_str);

                // Set Prefill Key
                $('#ats_prefill_key').val(q.prefill_key || '');

                modal.dialog('option', 'title', 'Edit Question');
                modal.dialog('open');
            } else {
                log('Error fetching question.', response);
                stackboostAlert('Error fetching question: ' + response.data, 'Error');
            }
        });
    });

    // Delete Question
    $(document).on('click', '.stackboost-ats-delete-question', function(e) {
        e.preventDefault();
        var row = $(this).closest('tr');
        var questionId = $(this).data('id');

        stackboostConfirm(
            'Are you sure you want to delete this question?',
            'Confirm Delete',
            function() {
                log('Delete Question requested.', questionId);

                $.post(stackboost_ats_manage.ajax_url, {
                    action: 'stackboost_ats_delete_question',
                    nonce: stackboost_ats_manage.nonce,
                    question_id: questionId
                }, function(response) {
                    if (response.success) {
                        log('Question deleted successfully.');
                        row.fadeOut(300, function() { $(this).remove(); });
                        showStatus('Question deleted.', 'success');
                    } else {
                        log('Error deleting question.', response);
                        stackboostAlert('Error deleting question: ' + response.data, 'Error');
                    }
                });
            },
            null, // No action on cancel
            'Yes, Delete',
            'Cancel',
            true // isDanger
        );
    });

    function saveQuestion() {
        var data = {
            action: 'stackboost_ats_save_question',
            nonce: stackboost_ats_manage.nonce,
            question_id: $('#question_id').val(),
            question_text: $('#question_text').val(),
            question_type: $('#question_type').val(),
            sort_order: $('#ats_sort_order').val(),
            is_required: $('#ats_is_required').is(':checked') ? 1 : 0,
            is_readonly_if_prefilled: $('#ats_is_readonly_if_prefilled').is(':checked') ? 1 : 0,
            dropdown_options: $('#ats_dropdown_options').val(),
            prefill_key: $('#ats_prefill_key').val() // Include new field
        };

        log('Saving question...', data);

        $.post(stackboost_ats_manage.ajax_url, data, function(response) {
            if (response.success) {
                log('Question saved successfully.');
                modal.dialog("close");
                location.reload(); // Reload to refresh list simply and robustly
            } else {
                log('Error saving question.', response);
                stackboostAlert('Error saving question: ' + response.data, 'Error');
            }
        });
    }
});
