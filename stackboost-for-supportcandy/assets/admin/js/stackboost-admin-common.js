jQuery(document).ready(function ($) {
    'use strict';

    // Handle adding new rules for Conditional Views
    $('#stackboost-add-rule').on('click', function () {
        const rulesContainer = $('#stackboost-rules-container');
        const template = $('#stackboost-rule-template').html();
        const newIndex = new Date().getTime();
        const newRuleHtml = template.replace(/__INDEX__/g, newIndex);

        $('#stackboost-no-rules-message').hide();
        rulesContainer.append(newRuleHtml);
    });

    // Handle removing rules using event delegation
    $('#stackboost-rules-container').on('click', '.stackboost-remove-rule', function () {
        $(this).closest('.stackboost-rule').remove();
        if ($('#stackboost-rules-container').find('.stackboost-rule').length === 0) {
            $('#stackboost-no-rules-message').show();
        }
    });

    // Dual list for Queue Macro statuses
    $('#stackboost_add_status').on('click', function () {
        $('#stackboost_available_statuses option:selected').each(function () {
            $(this).remove().appendTo('#stackboost_selected_statuses');
        });
    });

    $('#stackboost_remove_status').on('click', function () {
        $('#stackboost_selected_statuses option:selected').each(function () {
            $(this).remove().appendTo('#stackboost_available_statuses');
        });
    });

    // Before submitting a settings form, select all items in any 'selected' dual-list.
    // The logic is conditional to only run if the dual-list exists on the page.
    $('form[action="options.php"]').on('submit', function () {
        const selectedStatusesList = $('#stackboost_selected_statuses');
        if (selectedStatusesList.length > 0) {
            selectedStatusesList.find('option').prop('selected', true);
        }
    });

    // Test button for Queue Macro
    $('#stackboost_test_queue_macro_button').on('click', function () {
        const resultsContent = $('#stackboost_test_results_content');
        const resultsContainer = $('#stackboost_test_results');
        resultsContent.html('<p>Loading...</p>');
        resultsContainer.show();

        $.post(stackboost_admin_ajax.ajax_url, {
            action: 'stackboost_test_queue_counts', // Updated action name
            nonce: stackboost_admin_ajax.nonce
        }, function (response) {
            if (response.success) {
                let html;
                if (Object.keys(response.data).length === 0) {
                    html = '<p>No tickets found for the specified criteria.</p>';
                } else {
                    html = '<ul>';
                    $.each(response.data, function (key, value) {
                        html += '<li><strong>' + key + ':</strong> ' + value + '</li>';
                    });
                    html += '</ul>';
                }
                resultsContent.html(html);
            } else {
                resultsContent.html('<p>Error: ' + (response.data || 'Unknown error') + '</p>');
            }
        }).fail(function() {
            resultsContent.html('<p>An unexpected error occurred during the AJAX request.</p>');
        });
    });
});