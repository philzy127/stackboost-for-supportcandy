jQuery(document).ready(function($) {
    // Clear Staff Data
    $('#stackboost-clear-db-button').on('click', function() {
        var $button = $(this);

        stackboostConfirm(
            stackboostManagementAjax.clearConfirm,
            'Clear Data',
            function() {
                var $progress = $('#clear-progress');
                $button.prop('disabled', true);
                $progress.text(stackboostManagementAjax.clearingMessage);

                $.post(stackboostManagementAjax.ajax_url, {
                    action: 'stackboost_directory_clear_data',
                    nonce: stackboostManagementAjax.clear_nonce,
                    _ajax_nonce: stackboostManagementAjax.clear_nonce
                })
                .done(function(response) {
                    if (response.success) {
                        $progress.text(response.data);
                        window.location.reload();
                    } else {
                        $progress.text(stackboostManagementAjax.errorMessage + ' ' + response.data);
                    }
                })
                .fail(function() {
                    $progress.text(stackboostManagementAjax.errorMessage);
                })
                .always(function() {
                    $button.prop('disabled', false);
                });
            },
            null, // No action on cancel
            'Yes, Clear Data',
            'Cancel',
            true // isDanger
        );
    });

    // Fresh Start
    $('#stackboost-fresh-start-button').on('click', function() {
        var $button = $(this);

        // First Confirmation
        stackboostConfirm(
            stackboostManagementAjax.freshStartConfirm,
            'Fresh Start',
            function() {
                // Second Confirmation (Double Check)
                stackboostConfirm(
                    stackboostManagementAjax.freshStartConfirmDouble,
                    'Are you absolutely sure?',
                    function() {
                        var $progress = $('#fresh-start-progress');
                        $button.prop('disabled', true);
                        $progress.text(stackboostManagementAjax.clearingMessage);

                        $.post(stackboostManagementAjax.ajax_url, {
                            action: 'stackboost_directory_fresh_start',
                            nonce: stackboostManagementAjax.fresh_start_nonce
                        })
                        .done(function(response) {
                            if (response.success) {
                                $progress.text(response.data);
                                stackboostAlert(
                                    stackboostManagementAjax.freshStartSuccess,
                                    'Success',
                                    function() {
                                        window.location.reload();
                                    }
                                );
                            } else {
                                $progress.text(stackboostManagementAjax.errorMessage + ' ' + response.data);
                            }
                        })
                        .fail(function() {
                            $progress.text(stackboostManagementAjax.errorMessage);
                        })
                        .always(function() {
                            $button.prop('disabled', false);
                        });
                    },
                    function() {
                        stackboostAlert(stackboostManagementAjax.cancelMessage, 'Cancelled');
                    },
                    'Yes, Delete Everything',
                    'Cancel',
                    true // isDanger
                );
            },
            function() {
                stackboostAlert(stackboostManagementAjax.cancelMessage, 'Cancelled');
            },
            'Continue',
            'Cancel',
            true // isDanger
        );
    });
});
