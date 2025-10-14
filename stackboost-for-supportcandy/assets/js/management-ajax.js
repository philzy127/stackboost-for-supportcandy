jQuery(document).ready(function($) {
    // Clear Staff Data
    $('#stackboost-clear-db-button').on('click', function() {
        if (confirm(stackboostManagementAjax.clearConfirm)) {
            var $button = $(this);
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
        }
    });

    // Fresh Start
    $('#stackboost-fresh-start-button').on('click', function() {
        if (confirm(stackboostManagementAjax.freshStartConfirm) && confirm(stackboostManagementAjax.freshStartConfirmDouble)) {
            var $button = $(this);
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
                    alert(stackboostManagementAjax.freshStartSuccess);
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
        } else {
            alert(stackboostManagementAjax.cancelMessage);
        }
    });
});