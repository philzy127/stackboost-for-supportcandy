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

    /**
     * Central Toast Notification Helper.
     * Displays a dismissible admin notice that auto-hides.
     *
     * @param {string} message The message to display.
     * @param {string} type    'success' or 'error' (default: 'success').
     */
    window.stackboost_show_toast = function(message, type) {
        type = type || 'success';
        var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        // Manually include the dismissal button markup
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible stackboost-toast"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');

        // Insert after the page title
        $('.wrap > h1').after(notice);

        // Make it dismissible using WordPress standard JS if available, but manual dismiss is safer for dynamic content
        notice.on('click', '.notice-dismiss', function() {
            notice.remove();
        });

        // Auto-dismiss after 3 seconds for success messages
        if (type === 'success') {
            setTimeout(function () {
                notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
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

            stackboostConfirm(
                'Are you sure you want to clear the diagnostic log?',
                'Clear Log',
                function() {
                    $btn.text('Clearing...').prop('disabled', true);

                    $.post(stackboost_admin_ajax.ajax_url, {
                        action: 'stackboost_clear_log',
                        nonce: stackboost_admin_ajax.nonce
                    }, function (response) {
                        if (response.success) {
                            window.stackboost_show_toast(response.data, 'success');
                        } else {
                            stackboostAlert('Error: ' + (response.data || 'Unknown error'), 'Error');
                        }
                    }).fail(function () {
                        stackboostAlert('An unexpected error occurred.', 'Error');
                    }).always(function () {
                        $btn.text(originalText).prop('disabled', false);
                    });
                },
                null, // Cancel callback
                'Yes, Clear It',
                'Cancel',
                true // isDanger
            );
        });

        /**
         * Save Theme Preference
         * @param {string} newTheme The CSS class name of the theme.
         * @param {string} themeName The display name of the theme (for UI updates).
         * @param {object} $btn Optional button element to show loading state.
         */
        function stackboost_save_theme(newTheme, themeName, $btn) {
            var $dashboard = $('.stackboost-dashboard');
            var $previewName = $('#stackboost-preview-theme-name');
            var originalBtnText = $btn ? $btn.text() : '';

            // 1. Live Preview: Remove old theme classes and add new one
            $dashboard.removeClass(function (index, css) {
                return (css.match(/(^|\s)sb-theme-\S+/g) || []).join(' ');
            });
            $dashboard.addClass(newTheme);

            // Update preview text
            if ($previewName.length && themeName) {
                $previewName.text(themeName);
            }

            // Show loading state on button if provided
            if ($btn) {
                $btn.text('Saving...').prop('disabled', true);
            }

            // 2. AJAX Save
            $.post(stackboost_admin_ajax.ajax_url, {
                action: 'stackboost_save_theme_preference',
                theme: newTheme,
                nonce: stackboost_admin_ajax.nonce
            }, function(response) {
                if (response.success) {
                    window.stackboost_show_toast('Theme Updated Successfully', 'success');
                } else {
                    window.stackboost_show_toast('Error saving theme: ' + (response.data || 'Unknown error'), 'error');
                }
            }).fail(function() {
                window.stackboost_show_toast('Communication error while saving theme.', 'error');
            }).always(function() {
                if ($btn) {
                    $btn.text(originalBtnText).prop('disabled', false);
                }
            });
        }

        // Appearance: Theme Switching (Auto-Save)
        $('#stackboost_admin_theme').on('change', function() {
            var newTheme = $(this).val();
            var themeName = $(this).find('option:selected').text();
            stackboost_save_theme(newTheme, themeName);
        });

        // Appearance: Manual Save Button
        $('#stackboost_save_theme_btn').on('click', function() {
            var $select = $('#stackboost_admin_theme');
            var newTheme = $select.val();
            var themeName = $select.find('option:selected').text();
            stackboost_save_theme(newTheme, themeName, $(this));
        });
    });
})(jQuery);
