jQuery(document).ready(function($) {
    $("#stkb-sequence-list, #stkb-available-list").sortable({
        connectWith: ".connectedSortable",
        placeholder: "ui-state-highlight",
        receive: function(event, ui) {
            if (this.id === 'stkb-sequence-list') {
                $(ui.item).find('input[type="hidden"]').remove();
                $(ui.item).append('<input type="hidden" name="onboarding_sequence[]" value="' + $(ui.item).data('post-id') + '">');
            } else {
                $(ui.item).find('input[type="hidden"]').remove();
            }
        }
    }).disableSelection();
});
