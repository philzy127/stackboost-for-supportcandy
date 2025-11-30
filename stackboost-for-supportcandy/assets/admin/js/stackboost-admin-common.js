(function ($) {
    'use strict';

    /**
     * Central JS Logger.
     * Checks if debug mode is enabled (passed via stackboost_admin_ajax) before logging.
     * Defined immediately to be available for other scripts.
     */
    window.stackboost_log = function(message, data) {
        if (typeof stackboost_admin_ajax !== 'undefined' && stackboost_admin_ajax.debug_enabled) {
            if (data) {
                console.log('[StackBoost]', message, data);
            } else {
                console.log('[StackBoost]', message);
            }
        }
    };

    jQuery(document).ready(function ($) {

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

        // Handle clearing the diagnostic log via AJAX
        $('#stackboost-clear-log-btn').on('click', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const originalText = $btn.text();

            if (!confirm('Are you sure you want to clear the diagnostic log?')) {
                return;
            }

            $btn.text('Clearing...').prop('disabled', true);

            $.post(stackboost_admin_ajax.ajax_url, {
                action: 'stackboost_clear_log',
                nonce: stackboost_admin_ajax.nonce
            }, function (response) {
                if (response.success) {
                    // Show a simple toast-like notice
                    const notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                    $('.wrap > h1').after(notice);
                    // Auto-dismiss after 3 seconds
                    setTimeout(function () {
                        notice.fadeOut(function () {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            }).fail(function () {
                alert('An unexpected error occurred.');
            }).always(function () {
                $btn.text(originalText).prop('disabled', false);
            });
        });
    });
})(jQuery);
