jQuery(document).ready(function($) {
    // Export JSON
    $('#stackboost-json-export-button').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        $button.prop('disabled', true).text('Exporting...');

        var includeImages = $('#stackboost-export-include-images').is(':checked') ? 1 : 0;

        // Create a hidden form to trigger the download
        var form = $('<form></form>').attr('action', stackboostManagementAjax.ajax_url).attr('method', 'post');
        form.append($('<input></input>').attr('type', 'hidden').attr('name', 'action').attr('value', 'stackboost_directory_export_json'));
        form.append($('<input></input>').attr('type', 'hidden').attr('name', 'nonce').attr('value', stackboostManagementAjax.export_nonce)); // Use specific export nonce
        form.append($('<input></input>').attr('type', 'hidden').attr('name', 'include_images').attr('value', includeImages));

        $('body').append(form);
        form.submit();
        form.remove();

        // Reset button after a short delay (since we can't easily detect download completion)
        setTimeout(function() {
            $button.prop('disabled', false).text('Export JSON');
        }, 3000);
    });

    // Import JSON
    $('#stackboost-json-import-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this); // Capture form reference

        stackboostConfirm(
            'WARNING: This will replace ALL existing directory data (Departments, Locations, Staff). This action cannot be undone. Are you sure you want to proceed?',
            'Confirm Import',
            function() {
                var formData = new FormData($form[0]);
                var fileInput = $('#json_file')[0];
                var messageDiv = $('#json-import-progress');
                var $submitButton = $form.find('input[type="submit"]');

                if (fileInput.files.length === 0) {
                    messageDiv.html('<p style="color: red;">Please select a file.</p>');
                    return;
                }

                formData.append('action', 'stackboost_directory_import_json');
                formData.append('nonce', stackboostManagementAjax.import_nonce); // Use specific import nonce

                $submitButton.prop('disabled', true);
                messageDiv.html('<p style="color: blue;">Importing data... This may take a while.</p>');

                $.ajax({
                    url: stackboostManagementAjax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            var html = '<p style="color: green;">' + response.data.message + '</p>';
                            if (response.data.failures && response.data.failures.length > 0) {
                                html += '<p style="color: orange;"><strong>Import completed with warnings:</strong></p>';
                                html += '<ul style="color: red; max-height: 200px; overflow-y: auto;">';
                                $.each(response.data.failures, function(index, value) {
                                    html += '<li>' + value + '</li>';
                                });
                                html += '</ul>';
                            }
                            messageDiv.html(html);
                            // Optional: reload to show new data, but maybe just show success message is enough.
                            // window.location.reload();
                        } else {
                            messageDiv.html('<p style="color: red;">Error: ' + response.data.message + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        messageDiv.html('<p style="color: red;">An error occurred: ' + error + '</p>');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false);
                    }
                });
            },
            null, // No action on cancel
            'Yes, Replace Data',
            'Cancel',
            true // isDanger
        );
    });
});
