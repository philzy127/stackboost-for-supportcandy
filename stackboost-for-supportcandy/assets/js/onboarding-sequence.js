jQuery(document).ready(function($) {
    // Ensure the elements exist before initializing
    if ($("#stkb-sequence-list").length === 0 && $("#stkb-available-list").length === 0) {
        return;
    }

    $("#stkb-sequence-list, #stkb-available-list").sortable({
        connectWith: ".connectedSortable",
        placeholder: "ui-state-highlight",
        cursor: "move",
        helper: "clone",
        forcePlaceholderSize: true,
        tolerance: "pointer",
        receive: function(event, ui) {
            if (this.id === 'stkb-sequence-list') {
                // Item moved into Sequence list
                // Ensure we don't have duplicate inputs first
                $(ui.item).find('input[type="hidden"]').remove();
                // Add the input field so it submits
                $(ui.item).append('<input type="hidden" name="onboarding_sequence[]" value="' + $(ui.item).data('post-id') + '">');
            } else {
                // Item moved into Available list
                // Remove the input field so it doesn't submit
                $(ui.item).find('input[type="hidden"]').remove();
            }
        },
        stop: function(event, ui) {
            // Optional: Add any logic needed after drag stops
            // The hidden inputs in #stkb-sequence-list will be submitted in order automatically.
        }
    }).disableSelection();
});
