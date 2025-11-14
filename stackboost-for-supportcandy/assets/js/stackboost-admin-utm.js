jQuery(document).ready(function($) {
    var $useSCOrderCheckbox = $('#utm_use_sc_order');
    var $sortingButtons = $('#utm-move-top, #utm-move-up, #utm-move-down, #utm-move-bottom');

    function toggleSortingButtons() {
        var isChecked = $useSCOrderCheckbox.is(':checked');
        $sortingButtons.prop('disabled', isChecked);
    }

    toggleSortingButtons();

    $useSCOrderCheckbox.on('change', function() {
        toggleSortingButtons();
    });

    $('#utm-add-field').click(function() {
        $('#utm-available-fields option:selected').appendTo('#utm-selected-fields-list');
    });

    $('#utm-remove-field').click(function() {
        $('#utm-selected-fields-list option:selected').appendTo('#utm-available-fields');
    });

    $('#utm-add-all').click(function() {
        $('#utm-available-fields option').appendTo('#utm-selected-fields-list');
    });

    $('#utm-remove-all').click(function() {
        $('#utm-selected-fields-list option').appendTo('#utm-available-fields');
    });

    $('#utm-move-up').on('click', function() {
        $('#utm-selected-fields-list option:selected').each(function() {
            var $this = $(this);
            $this.prev().before($this);
        });
    });

    $('#utm-move-down').on('click', function() {
        $($('#utm-selected-fields-list option:selected').get().reverse()).each(function() {
            var $this = $(this);
            $this.next().after($this);
        });
    });

    $('#utm-move-top').on('click', function() {
        $('#utm-selected-fields-list').prepend($('#utm-selected-fields-list option:selected'));
    });

    $('#utm-move-bottom').on('click', function() {
        $('#utm-selected-fields-list').append($('#utm-selected-fields-list option:selected'));
    });

    $('.add-rule').on('click', function() {
        var newIndex = $('.rule-group').length;
        var template = $('#utm-rule-template').html().replace(/__INDEX__/g, newIndex);
        $('.rules-container').append(template);
    });

    $('.rules-container').on('click', '.remove-rule', function() {
        $(this).closest('.rule-group').remove();
    });

    $('#stackboost-utm-form').on('submit', function() {
        $('#utm-selected-fields-list option').prop('selected', true);
    });
});
