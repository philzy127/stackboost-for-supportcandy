jQuery(document).ready(function($) {
    var modal = $('#stackboost-clear-db-confirm-modal');
    var btn = $('#stackboost-clear-db-button');
    var span = $('.stackboost-modal-close-button');
    var confirmInput = $('#stackboost-modal-confirm-input');
    var confirmYes = $('#stackboost-modal-confirm-yes');
    var confirmNo = $('#stackboost-modal-confirm-no');
    var messageDiv = $('#stackboost-clear-db-message');

    // When the user clicks the button, open the modal
    btn.on('click', function() {
        messageDiv.empty(); // Clear previous messages
        confirmInput.val(''); // Clear input field
        confirmYes.prop('disabled', true); // Disable button
        modal.show();
    });

    // When the user clicks on <span> (x), close the modal
    span.on('click', function() {
        modal.hide();
    });

    // When the user clicks anywhere outside of the modal, close it
    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });

    // Enable/disable "Yes" button based on input
    confirmInput.on('keyup', function() {
        if ($(this).val() === 'DELETE') {
            confirmYes.prop('disabled', false);
        } else {
            confirmYes.prop('disabled', true);
        }
    });

    // When the user clicks "Yes, Delete All Data"
    confirmYes.on('click', function() {
        if (confirmInput.val() !== 'DELETE') {
            return; // Should be disabled, but as a safeguard
        }
        modal.hide(); // Hide the modal
        messageDiv.html('<p style="color: blue;">' + stackboostClearDbAjax.clearingMessage + '</p>'); // Show clearing message

        $.ajax({
            url: stackboostClearDbAjax.ajax_url, // WordPress AJAX URL from wp_localize_script
            type: 'POST',
            data: {
                action: 'stackboost_directory_clear_db', // The AJAX action hook
                nonce: stackboostClearDbAjax.nonce // The nonce for security
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                } else {
                    messageDiv.html('<p style="color: red;">' + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                messageDiv.html('<p style="color: red;">' + stackboostClearDbAjax.errorMessage + ' ' + error + '</p>');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    });

    // When the user clicks "No, Cancel"
    confirmNo.on('click', function() {
        modal.hide();
        messageDiv.html('<p style="color: grey;">' + stackboostClearDbAjax.cancelMessage + '</p>');
    });

    // Add basic modal styling directly via JavaScript for immediate effect
    // In a real plugin, this would be in a CSS file.
    $('head').append(`
        <style>
            .stackboost-modal {
                display: none; /* Hidden by default */
                position: fixed; /* Stay in place */
                z-index: 100000; /* Sit on top */
                left: 0;
                top: 0;
                width: 100%; /* Full width */
                height: 100%; /* Full height */
                overflow: auto; /* Enable scroll if needed */
                background-color: rgba(0,0,0,0.7); /* Black w/ opacity */
                padding-top: 60px;
            }
            .stackboost-modal-content {
                background-color: #fefefe;
                margin: 5% auto; /* 15% from the top and centered */
                padding: 30px;
                border: 1px solid #888;
                width: 80%; /* Could be more responsive */
                max-width: 500px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                position: relative;
                text-align: center;
            }
            .stackboost-modal-content h2 {
                color: #d63638; /* WordPress red */
                margin-top: 0;
                margin-bottom: 20px;
            }
            .stackboost-modal-content p {
                margin-bottom: 15px;
                font-size: 16px;
                line-height: 1.5;
            }
            .stackboost-modal-close-button {
                color: #aaa;
                position: absolute;
                top: 10px;
                right: 15px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .stackboost-modal-close-button:hover,
            .stackboost-modal-close-button:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            }
            .stackboost-modal-content .button {
                margin: 0 10px;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
            .stackboost-modal-content .button-danger {
                background-color: #d63638;
                color: white;
                border: none;
            }
            .stackboost-modal-content .button-danger:hover {
                background-color: #c62828;
            }
            .stackboost-modal-content .button-secondary {
                background-color: #f0f0f0;
                color: #333;
                border: 1px solid #ccc;
            }
            .stackboost-modal-content .button-secondary:hover {
                background-color: #e0e0e0;
            }
        </style>
    `);
});