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

    /**
     * Show a standardized toast notification.
     * @param {string} message The message to display.
     * @param {string} type Optional. 'success' or 'error'. Defaults to generic dark gray.
     */
    window.stackboost_show_toast = function(message, type) {
        // Create toast element if it doesn't exist
        if ($('#stackboost-global-toast').length === 0) {
            $('body').append('<div id="stackboost-global-toast" class="stackboost-toast"></div>');
        }

        const toast = $('#stackboost-global-toast');

        // Reset classes
        toast.removeClass('show success error');

        // Set content and type
        toast.text(message);
        if (type) {
            toast.addClass(type);
        }

        // Show it
        setTimeout(function() {
            toast.addClass('show');
        }, 100); // Slight delay to ensure DOM is ready and transition triggers

        // Hide after 3 seconds
        setTimeout(function() {
            toast.removeClass('show');
        }, 3000);
    };

    // Check for URL parameters to trigger a toast on page load
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('stackboost_log_cleared')) {
        window.stackboost_show_toast('Diagnostic log cleared successfully.', 'success');

        // Clean up the URL
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace(/[?&]stackboost_log_cleared=[^&]+/, "").replace(/^&/, "?");
        window.history.replaceState({path: newUrl}, '', newUrl);
    }
});