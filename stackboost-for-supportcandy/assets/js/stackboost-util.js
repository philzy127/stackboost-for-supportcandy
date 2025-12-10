jQuery(document).ready(function($) {
    // Helper function for conditional logging
    var sbUtilLog = function(message, data) {
        if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
            if (data) {
                console.log('[StackBoost Util]', message, data);
            } else {
                console.log('[StackBoost Util]', message);
            }
        }
    };

    var sbUtilError = function(message, data) {
        if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
             if (data) {
                console.error('[StackBoost Util]', message, data);
            } else {
                console.error('[StackBoost Util]', message);
            }
        }
    };

    sbUtilLog('Utility script loaded.');

    // Copy to clipboard functionality for email
    $(document).on('click', '.stackboost-copy-email-icon', function() {
        sbUtilLog('Email icon clicked');
        var email = $(this).data('email');
        if (email) {
            copyToClipboard(email, $(this), 'email');
        } else {
            sbUtilError('No email data found on icon');
        }
    });

    // Copy to clipboard functionality for phone
    $(document).on('click', '.stackboost-copy-phone-icon', function() {
        sbUtilLog('Phone icon clicked');

        // 1. Check for pre-formatted copy text (Primary Method)
        var copyText = $(this).data('copy-text');
        if (copyText) {
             sbUtilLog('Using pre-formatted copy text', copyText);
             copyToClipboard(copyText, $(this), 'phone');
             return;
        }

        // 2. Fallback to legacy construction (Secondary Method)
        var phone = $(this).data('phone');
        var extension = $(this).data('extension');

        sbUtilLog('Raw Data', { phone: phone, extension: extension });

        // Ensure we treat them as strings to avoid weird addition
        phone = String(phone);

        var fullNumber = phone;
        if (extension) {
            fullNumber = phone + ' x' + extension;
        }

        if (fullNumber && fullNumber !== "undefined") {
            copyToClipboard(fullNumber, $(this), 'phone');
        } else {
             sbUtilError('Failed to construct full number');
        }
    });

    // Function to show a toast notification
    function showToast(message) {
        var toast = $('<div class="stackboost-toast"></div>').text(message);
        $('body').append(toast);
        toast.fadeIn(400).delay(3000).fadeOut(400, function() {
            $(this).remove();
        });
    }

    // Function to handle the copy action
    function copyToClipboard(text, $icon, type) {
        sbUtilLog('Attempting to copy', text);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                handleCopySuccess($icon, text, type);
            }, function(err) {
                sbUtilError('Async: Could not copy text: ', err);
                // Fallback to execCommand if async fails
                fallbackCopyTextToClipboard(text, $icon, type);
            });
        } else {
            fallbackCopyTextToClipboard(text, $icon, type);
        }
    }

    // Fallback using execCommand
    function fallbackCopyTextToClipboard(text, $icon, type) {
        sbUtilLog('Using fallback copy');
        var textArea = document.createElement("textarea");
        textArea.value = text;

        // Ensure it's not visible but part of the DOM
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                handleCopySuccess($icon, text, type);
            } else {
                sbUtilError('Fallback: Copying text command was unsuccessful');
            }
        } catch (err) {
            sbUtilError('Fallback: Oops, unable to copy', err);
        }

        document.body.removeChild(textArea);
    }

    // Success UI Feedback
    function handleCopySuccess($icon, text, type) {
        sbUtilLog('Copy successful');
        $icon.addClass('copied');

        var msg = type === 'email' ? 'Email copied: ' + text : 'Phone copied: ' + text;
        showToast(msg);

        setTimeout(function() {
            $icon.removeClass('copied');
        }, 1500); // 1.5s delay to be visible
    }

});
