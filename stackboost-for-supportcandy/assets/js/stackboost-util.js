jQuery(document).ready(function($) {
    // Helper function for conditional logging
    window.sbUtilLog = function(message, data) {
        if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
            if (data) {
                console.log('[StackBoost Util]', message, data);
            } else {
                console.log('[StackBoost Util]', message);
            }
        }
    };

    window.sbUtilError = function(message, data) {
        if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
             if (data) {
                console.error('[StackBoost Util]', message, data);
            } else {
                console.error('[StackBoost Util]', message);
            }
        }
    };

    sbUtilLog('Utility script loaded.');

    // --- Modal System Implementation ---

    // Inject Modal HTML into body if not present
    if ($('#stackboost-modal-overlay').length === 0) {
        var modalHtml = `
            <div id="stackboost-modal-overlay" class="stackboost-modal-overlay">
                <div class="stackboost-modal-box">
                    <div class="stackboost-modal-header">
                        <h3 class="stackboost-modal-title"></h3>
                        <button class="stackboost-modal-close">&times;</button>
                    </div>
                    <div class="stackboost-modal-body"></div>
                    <div class="stackboost-modal-footer"></div>
                </div>
            </div>
        `;
        $('body').append(modalHtml);
    }

    var $modalOverlay = $('#stackboost-modal-overlay');
    var $modalTitle = $modalOverlay.find('.stackboost-modal-title');
    var $modalBody = $modalOverlay.find('.stackboost-modal-body');
    var $modalFooter = $modalOverlay.find('.stackboost-modal-footer');
    var $modalClose = $modalOverlay.find('.stackboost-modal-close');

    // Close modal event
    function closeModal() {
        $modalOverlay.removeClass('active');
    }
    $modalClose.on('click', closeModal);

    // Global Alert Function
    window.stackboostAlert = function(message, title = 'Alert', callback = null) {
        $modalTitle.text(title);
        $modalBody.html(message); // Allow HTML in message
        $modalFooter.empty();

        var $okBtn = $('<button class="stackboost-btn stackboost-btn-primary">OK</button>');
        $okBtn.on('click', function() {
            closeModal();
            if (typeof callback === 'function') {
                callback();
            }
        });

        $modalFooter.append($okBtn);
        $modalOverlay.addClass('active');
        $okBtn.focus();
    };

    // Global Confirm Function
    window.stackboostConfirm = function(message, title = 'Confirm', onConfirm, onCancel, confirmText = 'Yes', cancelText = 'No', isDanger = false) {
        $modalTitle.text(title);
        $modalBody.html(message); // Allow HTML in message
        $modalFooter.empty();

        var $cancelBtn = $('<button class="stackboost-btn stackboost-btn-secondary">' + cancelText + '</button>');
        var $confirmBtn = $('<button class="stackboost-btn ' + (isDanger ? 'stackboost-btn-danger' : 'stackboost-btn-primary') + '">' + confirmText + '</button>');

        $cancelBtn.on('click', function() {
            closeModal();
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });

        $confirmBtn.on('click', function() {
            closeModal();
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        $modalFooter.append($cancelBtn);
        $modalFooter.append($confirmBtn);
        $modalOverlay.addClass('active');
        $confirmBtn.focus();
    };

    // --- End Modal System ---


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
