jQuery(document).ready(function($) {
    $('#stackboost-csv-import-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        var fileInput = $('#csv_file')[0];
        var messageDiv = $('#import-progress'); // Use the existing div for messages.
        var progressDiv = $('#stackboost-import-progress');
        var progressBar = $('#stackboost-progress-bar');
        var progressText = $('#stackboost-progress-text');

        if (fileInput.files.length === 0) {
            messageDiv.html('<p style="color: red;">' + stackboostImportAjax.no_file_selected + '</p>');
            return;
        }

        formData.append('action', 'stackboost_directory_import_csv');
        formData.append('nonce', stackboostImportAjax.nonce);

        $.ajax({
            url: stackboostImportAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                messageDiv.html('<p style="color: blue;">' + stackboostImportAjax.uploading_message + '</p>');
                progressDiv.show();
            },
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        progressBar.val(percentComplete);
                        progressText.text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                progressDiv.hide();
                if (response.success) {
                    var message = '<p style="color: green;">' + response.data.message + '</p>';
                    if (response.data.skipped_count > 0) {
                        message += '<p><strong>' + response.data.skipped_count + ' skipped entries:</strong></p><ul>';
                        response.data.skipped_details.forEach(function(item) {
                            message += '<li>' + item.reason + ': ' + item.data + '</li>';
                        });
                        message += '</ul>';
                    }
                    messageDiv.html(message);
                } else {
                    messageDiv.html('<p style="color: red;">' + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                progressDiv.hide();
                messageDiv.html('<p style="color: red;">' + stackboostImportAjax.error_message + ' ' + error + '</p>');
                if (typeof window.stackboostLog === 'function') {
                    window.stackboostLog('AJAX Error: ' + xhr.responseText, null, 'error');
                }
            }
        });
    });
});