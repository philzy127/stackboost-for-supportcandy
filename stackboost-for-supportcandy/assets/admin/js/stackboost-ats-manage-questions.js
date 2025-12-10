jQuery(document).ready(function($) {
    var modal = $('#stackboost-ats-question-modal');
    var form = $('#stackboost-ats-question-form');
    var tableBody = $('#stackboost-ats-questions-list tbody');

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
        }
    });

    // Initialize Sortable
    tableBody.sortable({
        handle: '.stackboost-ats-sort-handle',
        update: function(event, ui) {
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
                    alert('Failed to save order: ' + response.data);
                }
            });
        }
    });

    // Toggle Dropdown Options Visibility
    $('#question_type').on('change', function() {
        if ($(this).val() === 'dropdown') {
            $('#ats_dropdown_options_group').show();
        } else {
            $('#ats_dropdown_options_group').hide();
        }
    });

    // Open Modal for New Question
    $('#stackboost-ats-add-question').on('click', function(e) {
        e.preventDefault();
        modal.dialog('option', 'title', 'Add New Question');
        modal.dialog('open');
    });

    // Open Modal for Edit
    $(document).on('click', '.stackboost-ats-edit-question', function(e) {
        e.preventDefault();
        var questionId = $(this).data('id');

        $.post(stackboost_ats_manage.ajax_url, {
            action: 'stackboost_ats_get_question',
            nonce: stackboost_ats_manage.nonce,
            question_id: questionId
        }, function(response) {
            if (response.success) {
                var q = response.data;
                $('#question_id').val(q.id);
                $('#question_text').val(q.question_text);
                $('#question_type').val(q.question_type).trigger('change');
                $('#ats_sort_order').val(q.sort_order);
                $('#ats_is_required').prop('checked', q.is_required == 1);
                $('#ats_dropdown_options').val(q.options_str);

                modal.dialog('option', 'title', 'Edit Question');
                modal.dialog('open');
            } else {
                alert('Error fetching question: ' + response.data);
            }
        });
    });

    // Delete Question
    $(document).on('click', '.stackboost-ats-delete-question', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this question?')) return;

        var row = $(this).closest('tr');
        var questionId = $(this).data('id');

        $.post(stackboost_ats_manage.ajax_url, {
            action: 'stackboost_ats_delete_question',
            nonce: stackboost_ats_manage.nonce,
            question_id: questionId
        }, function(response) {
            if (response.success) {
                row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Error deleting question: ' + response.data);
            }
        });
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
            dropdown_options: $('#ats_dropdown_options').val()
        };

        $.post(stackboost_ats_manage.ajax_url, data, function(response) {
            if (response.success) {
                modal.dialog("close");
                location.reload(); // Reload to refresh list simply and robustly
            } else {
                alert('Error saving question: ' + response.data);
            }
        });
    }
});
