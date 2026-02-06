(function($) {
    'use strict';

    const modal = $('#sb-cv-modal');
    const modalContent = $('.sb-cv-modal-content');

    // Elements
    const ruleIdInput = $('#sb-cv-rule-id');
    const viewSelector = $('#sb-cv-view-selector');
    const availableFields = $('#sb-cv-available-fields');
    const selectedFields = $('#sb-cv-selected-fields');

    // Open Modal (Add)
    $('#sb-cv-add-rule').on('click', function(e) {
        e.preventDefault();
        resetModal();
        openModal();
    });

    // Open Modal (Edit)
    $(document).on('click', '.sb-cv-edit-rule', function(e) {
        e.preventDefault();
        const ruleData = $(this).data('rule');
        populateModal(ruleData);
        openModal();
    });

    // Close Modal
    $('#sb-cv-cancel').on('click', function(e) {
        e.preventDefault();
        closeModal();
    });

    // Save Rule
    $('#sb-cv-save-rule').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text(sb_cv_admin.i18n_saving || 'Saving...');

        const ruleId = ruleIdInput.val();
        const viewId = viewSelector.val();
        const columns = [];
        selectedFields.find('option').each(function() {
            columns.push($(this).val());
        });

        if (!viewId) {
            alert('Please select a view.');
            $btn.prop('disabled', false).text('Save Workspace');
            return;
        }

        $.post(sb_cv_admin.ajax_url, {
            action: 'stackboost_save_contextual_rule',
            nonce: sb_cv_admin.nonce,
            rule_id: ruleId,
            view_id: viewId,
            columns: columns
        }, function(response) {
            if (response.success) {
                closeModal();
                location.reload(); // Reload to update table
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
                $btn.prop('disabled', false).text('Save Workspace');
            }
        });
    });

    // Migrate Rules
    $('#sb-cv-migrate').on('click', function(e) {
        e.preventDefault();
        if (!confirm('This will convert existing "Hide" rules from the Conditional Views module into new Workspaces. Existing workspaces will be preserved. Continue?')) return;

        const $btn = $(this);
        $btn.prop('disabled', true).text('Migrating...');

        $.post(sb_cv_admin.ajax_url, {
            action: 'stackboost_migrate_contextual_rules',
            nonce: sb_cv_admin.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Migrate Legacy Rules');
        });
    });

    // Delete Rule
    $(document).on('click', '.sb-cv-delete-rule', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this workspace?')) return;

        const ruleId = $(this).data('id');
        const $row = $(this).closest('tr');

        $.post(sb_cv_admin.ajax_url, {
            action: 'stackboost_delete_contextual_rule',
            nonce: sb_cv_admin.nonce,
            rule_id: ruleId
        }, function(response) {
            if (response.success) {
                $row.fadeOut(function() { $(this).remove(); });
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    // Dual List Box Logic
    $('#sb-cv-add').click(function() {
        availableFields.find('option:selected').appendTo(selectedFields);
    });

    $('#sb-cv-remove').click(function() {
        selectedFields.find('option:selected').appendTo(availableFields);
    });

    $('#sb-cv-add-all').click(function() {
        availableFields.find('option').appendTo(selectedFields);
    });

    $('#sb-cv-remove-all').click(function() {
        selectedFields.find('option').appendTo(availableFields);
    });

    // Reordering
    $('#sb-cv-move-up').click(function() {
        const $selected = selectedFields.find('option:selected');
        if ($selected.length) {
            $selected.first().prev().before($selected);
        }
    });

    $('#sb-cv-move-down').click(function() {
        const $selected = selectedFields.find('option:selected');
        if ($selected.length) {
            $selected.last().next().after($selected);
        }
    });

    // Modal Helpers
    function openModal() {
        // Simple overlay implementation if stackboost-util modal isn't used directly
        // Or if we want to use the existing modal structure if hidden in page
        // We defined #sb-cv-modal in PHP.
        // We'll use Thickbox or a custom overlay style.
        // For now, let's just show it as a fixed overlay.

        modal.css({
            'position': 'fixed',
            'top': 0,
            'left': 0,
            'width': '100%',
            'height': '100%',
            'background': 'rgba(0,0,0,0.5)',
            'z-index': 9999,
            'display': 'flex',
            'justify-content': 'center',
            'align-items': 'center'
        }).fadeIn();
    }

    function closeModal() {
        modal.fadeOut();
    }

    function resetModal() {
        ruleIdInput.val('');
        viewSelector.val('');
        selectedFields.empty();

        // Move all items back to available and sort
        // Actually, easiest is to reload available from a cache or clone, but moving back is fine.
        // We need to make sure we don't duplicate.
        // If we are resetting, we should move all selected back to available.
        selectedFields.find('option').appendTo(availableFields);

        // Sort available fields
        const options = availableFields.find('option');
        options.sort(function(a,b) {
            if (a.text > b.text) return 1;
            if (a.text < b.text) return -1;
            return 0;
        });
        availableFields.empty().append(options);
    }

    function populateModal(data) {
        resetModal();
        ruleIdInput.val(data.id);
        viewSelector.val(data.view_id);

        if (data.columns && Array.isArray(data.columns)) {
            data.columns.forEach(function(slug) {
                const option = availableFields.find('option[value="' + slug + '"]');
                if (option.length) {
                    option.appendTo(selectedFields);
                }
            });
        }
    }

})(jQuery);
