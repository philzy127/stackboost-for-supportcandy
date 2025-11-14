jQuery(document).ready(function($) {

    var $useSCOrderCheckbox = $('#stackboost_use_sc_order');
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

    // Helper function to show toast messages
    function showToast(message, isError) {
        var $container = $('#stackboost-utm-toast-container');
        var $toast = $('<div class="stackboost-utm-toast"></div>').text(message);

        if (isError) {
            $toast.addClass('error');
        }

        $container.append($toast);

        // Show the toast
        setTimeout(function() {
            $toast.addClass('show');
        }, 100);

        // Hide and remove the toast after 3 seconds
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    }

    // Move Up
    $('#stackboost_utm_move_up').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            var $first = $selected.first();
            var $before = $first.prev();
            if ($before.length) {
                $selected.insertBefore($before);
            }
        }
    });

    // Move Down
    $('#stackboost_utm_move_down').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            var $last = $selected.last();
            var $after = $last.next();
            if ($after.length) {
                $selected.insertAfter($after);
            }
        }
    });

    // Move to Top
    $('#stackboost_utm_move_top').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            $('#stackboost_utm_selected_fields').prepend($selected);
        }
    });

    // Move to Bottom
    $('#stackboost_utm_move_bottom').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            $('#stackboost_utm_selected_fields').append($selected);
        }
    });

    // Add selected
    $('#stackboost_utm_add').click(function() {
        $('#stackboost_utm_available_fields option:selected').appendTo('#stackboost_utm_selected_fields');
    });

    // Remove selected
    $('#stackboost_utm_remove').click(function() {
        $('#stackboost_utm_selected_fields option:selected').appendTo('#stackboost_utm_available_fields');
    });

    // Add all
    $('#stackboost_utm_add_all').click(function() {
        $('#stackboost_utm_available_fields option').appendTo('#stackboost_utm_selected_fields');
    });

    // Remove all
    $('#stackboost_utm_remove_all').click(function() {
        $('#stackboost_utm_selected_fields option').appendTo('#stackboost_utm_available_fields');
    });


    // Save settings via AJAX
    $('#stackboost-utm-save-settings').on('click', function() {
        var $enableCheckbox = $('#stackboost_utm_enabled');
        var selectedFields = [];
        $('#stackboost_utm_selected_fields option').each(function() {
            selectedFields.push($(this).val());
        });

        // Collect rename rules
        var renameRules = [];
        var validationError = false;
        $('#stackboost-utm-rules-container .stackboost-utm-rule-row').each(function() {
            var $row = $(this);
            var field = $row.find('.stackboost-utm-rule-field').val();
            var name = $row.find('.stackboost-utm-rule-name').val().trim();

            if (name === '') {
                showToast('Rule name cannot be blank. Please provide a name or remove the rule.', true);
                validationError = true;
                return false; // Exit the .each() loop
            }

            renameRules.push({
                'field': field,
                'name': name
            });
        });

        if (validationError) {
            return; // Stop the save process
        }

        var data = {
            'action': 'stackboost_utm_save_settings',
            'nonce': stackboost_utm_admin_params.nonce,
            'is_enabled': $enableCheckbox.is(':checked'),
            'selected_fields': selectedFields,
            'rename_rules': renameRules,
            'use_sc_order': $useSCOrderCheckbox.is(':checked')
        };

        $('.spinner').addClass('is-active');

        $.post(ajaxurl, data, function(response) {
            $('.spinner').removeClass('is-active');
            if (response.success) {
                showToast(response.data.message, false);
            } else {
                showToast(response.data.message, true);
            }
        });
        return false;
    });

    // Add Rule
    $('#stackboost-utm-add-rule').on('click', function() {
        var template = $('#stackboost-utm-rule-template').html();
        $('#stackboost-utm-rules-container').append(template);
    });

    // Remove Rule (using event delegation)
    $('#stackboost-utm-rules-container').on('click', '.stackboost-utm-remove-rule', function() {
        $(this).closest('.stackboost-utm-rule-row').remove();
    });
});
