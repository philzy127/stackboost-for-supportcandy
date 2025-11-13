jQuery(document).ready(function($) {
    var $useSCOrderCheckbox = $('#stackboost_utm_use_sc_order');
    var $sortingButtons = $('#stackboost_utm_move_top, #stackboost_utm_move_up, #stackboost_utm_move_down, #stackboost_utm_move_bottom');

    // Function to toggle the sorting buttons
    function toggleSortingButtons() {
        var isChecked = $useSCOrderCheckbox.is(':checked');
        $sortingButtons.prop('disabled', isChecked);
    }

    // Initial state on page load
    toggleSortingButtons();

    // Toggle on checkbox change
    $useSCOrderCheckbox.on('change', function() {
        toggleSortingButtons();
    });

    // Logic for the dual-list selector
    $('#stackboost_utm_add').click(function() {
        $('#stackboost_utm_available_fields option:selected').appendTo('#stackboost_utm_selected_fields');
    });

    $('#stackboost_utm_remove').click(function() {
        $('#stackboost_utm_selected_fields option:selected').appendTo('#stackboost_utm_available_fields');
    });

    $('#stackboost_utm_add_all').click(function() {
        $('#stackboost_utm_available_fields option').appendTo('#stackboost_utm_selected_fields');
    });

    $('#stackboost_utm_remove_all').click(function() {
        $('#stackboost_utm_selected_fields option').appendTo('#stackboost_utm_available_fields');
    });

    // Manual Sorting Logic
    $('#stackboost_utm_move_up').on('click', function() {
        $('#stackboost_utm_selected_fields option:selected').each(function() {
            var $this = $(this);
            $this.prev().before($this);
        });
    });

    $('#stackboost_utm_move_down').on('click', function() {
        $($('#stackboost_utm_selected_fields option:selected').get().reverse()).each(function() {
            var $this = $(this);
            $this.next().after($this);
        });
    });

    $('#stackboost_utm_move_top').on('click', function() {
        $('#stackboost_utm_selected_fields').prepend($('#stackboost_utm_selected_fields option:selected'));
    });

    $('#stackboost_utm_move_bottom').on('click', function() {
        $('#stackboost_utm_selected_fields').append($('#stackboost_utm_selected_fields option:selected'));
    });

    // Logic for the renaming rules builder
    $('#stackboost-utm-add-rule').on('click', function() {
        var template = $('#stackboost-utm-rule-template').html();
        $('#stackboost-utm-rules-container').append(template);
    });

    $('#stackboost-utm-rules-container').on('click', '.stackboost-utm-remove-rule', function() {
        $(this).closest('.stackboost-utm-rule-row').remove();
    });

    // Before the form is submitted, select all options in the "selected" list
    // so that they are included in the form data.
    $('form[action="options.php"]').on('submit', function() {
        $('#stackboost_utm_selected_fields option').prop('selected', true);

        // Also, build the rename rules into a hidden input field
        var renameRules = [];
        $('#stackboost-utm-rules-container .stackboost-utm-rule-row').each(function() {
            var field = $(this).find('.stackboost-utm-rule-field').val();
            var name = $(this).find('.stackboost-utm-rule-name').val();
            if (field && name) {
                renameRules.push({ field: field, name: name });
            }
        });

        // To pass this complex data, we'll serialize it as JSON and put it in a hidden field.
        // First, remove any existing field to prevent duplication.
        $(this).find('input[name="stackboost_settings[utm_rename_rules]"]').remove();
        var renameRulesInput = '<input type="hidden" name="stackboost_settings[utm_rename_rules]" value="' + esc_attr(JSON.stringify(renameRules)) + '" />';
        $(this).append(renameRulesInput);
    });

    // Helper function to escape attributes for HTML value attribute.
    function esc_attr(str) {
        return str.replace(/"/g, '&quot;');
    }
});
