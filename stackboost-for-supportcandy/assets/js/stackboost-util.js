jQuery(document).ready(function($) {
    // Helper function for conditional logging
    window.sbUtilLog = function(message, data) {
        if (typeof window.stackboost_log === 'function') {
            window.stackboost_log('[Util] ' + message, data);
        } else if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
            if (data) {
                console.log('[StackBoost Util]', message, data);
            } else {
                console.log('[StackBoost Util]', message);
            }
        }
    };

    window.sbUtilError = function(message, data) {
        if (typeof window.stackboost_log === 'function') {
            window.stackboost_log('[Util Error] ' + message, data);
        } else if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
             if (data) {
                console.error('[StackBoost Util]', message, data);
            } else {
                console.error('[StackBoost Util]', message);
            }
        }
    };

    sbUtilLog('Utility script loaded.');

    // --- Modal System Implementation (Standard) ---
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

    function closeModal() {
        $modalOverlay.removeClass('active');
    }
    $modalClose.on('click', closeModal);

    window.stackboostAlert = function(message, title = 'Alert', callback = null) {
        $modalTitle.text(title);
        $modalBody.html(message);
        $modalFooter.empty();
        var $okBtn = $('<button class="stackboost-btn stackboost-btn-primary">OK</button>');
        $okBtn.on('click', function() {
            closeModal();
            if (typeof callback === 'function') callback();
        });
        $modalFooter.append($okBtn);
        $modalOverlay.addClass('active');
        $okBtn.focus();
    };

    window.stackboostConfirm = function(message, title = 'Confirm', onConfirm, onCancel, confirmText = 'Yes', cancelText = 'No', isDanger = false) {
        $modalTitle.text(title);
        $modalBody.html(message);
        $modalFooter.empty();
        var $cancelBtn = $('<button class="stackboost-btn stackboost-btn-secondary">' + cancelText + '</button>');
        var $confirmBtn = $('<button class="stackboost-btn ' + (isDanger ? 'stackboost-btn-danger' : 'stackboost-btn-primary') + '">' + confirmText + '</button>');
        $cancelBtn.on('click', function() {
            closeModal();
            if (typeof onCancel === 'function') onCancel();
        });
        $confirmBtn.on('click', function() {
            closeModal();
            if (typeof onConfirm === 'function') onConfirm();
        });
        $modalFooter.append($cancelBtn);
        $modalFooter.append($confirmBtn);
        $modalOverlay.addClass('active');
        $confirmBtn.focus();
    };

    // --- Image Lightbox Implementation (Ported from Directory/WordPress.php) ---
    // This allows images to be opened in a simple, full-screen overlay.

    // 1. Inject Modal HTML into Body (idempotent)
    if (!document.getElementById('stackboost-widget-modal')) {
        var lightboxHtml = '<div id="stackboost-widget-modal"><span id="stackboost-widget-modal-close">&times;</span><img class="stackboost-modal-content" id="stackboost-widget-modal-content"></div>';
        document.body.insertAdjacentHTML('beforeend', lightboxHtml);

        // 2. Attach Close Listeners
        var lightbox = document.getElementById('stackboost-widget-modal');
        var span = document.getElementById("stackboost-widget-modal-close");

        if (span) {
            span.onclick = function(e) {
                e.stopPropagation(); // Prevent bubbling to Tippy/other listeners
                lightbox.style.display = "none";
            };
            // Prevent interaction with the close button from closing the Tippy
            // Added pointerdown and stopImmediatePropagation for stronger isolation
            ['mousedown', 'touchstart', 'click', 'pointerdown'].forEach(function(evt) {
                span.addEventListener(evt, function(e) { e.stopImmediatePropagation(); });
            });
        }
        if (lightbox) {
            lightbox.onclick = function(e) {
                e.stopImmediatePropagation(); // Prevent bubbling
                if (e.target === lightbox) {
                    lightbox.style.display = "none";
                }
            };
            // Prevent interaction with the overlay from closing the Tippy
            // Added pointerdown and stopImmediatePropagation for stronger isolation
            ['mousedown', 'touchstart', 'click', 'pointerdown'].forEach(function(evt) {
                lightbox.addEventListener(evt, function(e) { e.stopImmediatePropagation(); });
            });
        }
    }

    // 3. Define Global Open Function (idempotent)
    if (typeof window.stackboostOpenWidgetModal === 'undefined') {
        window.stackboostOpenWidgetModal = function(event, imageUrl) {
            if (event) event.preventDefault();
            var lightbox = document.getElementById('stackboost-widget-modal');
            var lightboxImg = document.getElementById('stackboost-widget-modal-content');
            if (lightbox && lightboxImg) {
                lightbox.style.display = "block";
                lightboxImg.src = imageUrl;
            }
        };
    }

    // --- Utility Functions ---

    // Copy to clipboard functionality for email
    $(document).on('click', '.stackboost-copy-email-icon', function() {
        var email = $(this).data('email');
        if (email) copyToClipboard(email, $(this), 'email');
    });

    // Copy to clipboard functionality for phone
    $(document).on('click', '.stackboost-copy-phone-icon', function() {
        var copyText = $(this).data('copy-text');
        if (copyText) {
             copyToClipboard(copyText, $(this), 'phone');
             return;
        }
        var phone = $(this).data('phone');
        var extension = $(this).data('extension');
        phone = String(phone);
        var fullNumber = phone;
        if (extension) fullNumber = phone + ' x' + extension;
        if (fullNumber) copyToClipboard(fullNumber, $(this), 'phone');
    });

    function showToast(message) {
        var toast = $('<div class="stackboost-toast"></div>').text(message);
        $('body').append(toast);
        toast.fadeIn(400).delay(3000).fadeOut(400, function() { $(this).remove(); });
    }

    function copyToClipboard(text, $icon, type) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                handleCopySuccess($icon, text, type);
            }, function() {
                fallbackCopyTextToClipboard(text, $icon, type);
            });
        } else {
            fallbackCopyTextToClipboard(text, $icon, type);
        }
    }

    function fallbackCopyTextToClipboard(text, $icon, type) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            var successful = document.execCommand('copy');
            if (successful) handleCopySuccess($icon, text, type);
        } catch (err) {}
        document.body.removeChild(textArea);
    }

    function handleCopySuccess($icon, text, type) {
        $icon.addClass('copied');
        var msg = type === 'email' ? 'Email copied: ' + text : 'Phone copied: ' + text;
        showToast(msg);
        setTimeout(function() { $icon.removeClass('copied'); }, 1500);
    }

});
