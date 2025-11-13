jQuery(document).ready(function($) {
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
            renameRules.push({ field: field, name: name });
        });

        // To pass this complex data, we'll serialize it as JSON and put it in a hidden field.
        // The PHP side will need to json_decode this.
        var renameRulesInput = '<input type="hidden" name="stackboost_settings[utm_rename_rules]" value="' + esc_attr(JSON.stringify(renameRules)) + '" />';
        $(this).append(renameRulesInput);
    });

    // Helper function to escape attributes
    function esc_attr(str) {
        return str.replace(/"/g, '&quot;');
    }
});
