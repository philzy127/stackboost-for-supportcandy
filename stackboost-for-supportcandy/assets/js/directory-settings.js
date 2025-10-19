jQuery(document).ready(function($) {
    // Dual-list sortable logic
    function updateHiddenField() {
        var fieldKeys = $('#displayed-fields').sortable('toArray', { attribute: 'data-key' });
        $('#stackboost-display-fields').val(fieldKeys.join(','));
    }

    if ($('.sortable-list').length > 0) {
        $('.sortable-list').sortable({
            connectWith: '.sortable-list',
            placeholder: 'ui-sortable-placeholder',
            receive: function(event, ui) {
                updateHiddenField();
            },
            stop: function(event, ui) {
                updateHiddenField();
            }
        }).disableSelection();

        // Initial update
        updateHiddenField();
    }
});