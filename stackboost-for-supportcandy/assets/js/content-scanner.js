(function($) {
    'use strict';

    const ContentScanner = {
        init: function() {
            // Use namespaced event handler to avoid conflict with other scripts
            $(document).on('click.stackboost_scanner', '.wpsc-submit-reply, .wpsc-submit-note, button[name="wpsc_submit_reply"]', this.handleSubmission.bind(this));
        },

        handleSubmission: function(e) {
            const $btn = $(e.currentTarget);

            // Check if we should bypass the scan (user clicked "Send Anyway")
            if ($btn.data('stackboost-bypass-scan')) {
                // Reset the flag immediately so next time it scans again
                $btn.data('stackboost-bypass-scan', false);
                return true;
            }

            const settings = stackboost_agent_protection;

            // Get content from TinyMCE
            let content = '';

            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                content = tinymce.activeEditor.getContent({format: 'text'}); // Get plain text for scanning
            } else {
                // Fallback to textarea
                const $textarea = $('textarea.wpsc_textarea_reply, textarea[name="reply_body"], textarea[name="wpsc_reply_body"]');
                if ($textarea.length > 0) {
                    content = $textarea.val();
                }
            }

            if (!content) return true; // Empty content, let core validation handle it

            // Normalize content
            const contentLower = content.toLowerCase();

            // 1. Attachment Scan
            const attKeywords = settings.scanner_keywords.attachment; // Array
            let hasAttKeyword = attKeywords.some(keyword => contentLower.includes(keyword.toLowerCase()));

            if (hasAttKeyword) {
                // Check if file is attached
                const $fileInput = $('input[type="file"]');
                const hasFiles = ($fileInput.length > 0 && $fileInput[0].files.length > 0) || $('.wpsc-uploaded-file').length > 0;

                if (!hasFiles) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    this.showAlert('attachment', $btn);
                    return false;
                }
            }

            // 2. Credential Scan
            const credKeywords = settings.scanner_keywords.credential; // Array
            let hasCredKeyword = credKeywords.some(keyword => contentLower.includes(keyword.toLowerCase()));

            if (hasCredKeyword) {
                e.preventDefault();
                e.stopImmediatePropagation();
                this.showAlert('credential', $btn);
                return false;
            }

            return true;
        },

        showAlert: function(type, $triggerBtn) {
            let title, message, icon;
            const i18n = stackboost_agent_protection.i18n;

            if (type === 'attachment') {
                title = i18n.scanner_att_title;
                message = i18n.scanner_att_msg;
                icon = 'dashicons-paperclip';
            } else {
                title = i18n.scanner_cred_title;
                message = i18n.scanner_cred_msg;
                icon = 'dashicons-lock';
            }

            // Remove existing modal
            $('#stackboost-scanner-modal').remove();

            const modalHtml = `
                <div id="stackboost-scanner-modal" style="position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;">
                    <div style="background-color: #fefefe; padding: 20px; border: 1px solid #888; width: 400px; max-width: 90%; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <span class="dashicons ${icon}" style="font-size: 48px; width: 48px; height: 48px; color: #d63638;"></span>
                            <h2 style="margin-top: 10px;">${title}</h2>
                            <p>${message}</p>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <button id="stackboost-scanner-cancel" class="button button-secondary button-large">${i18n.scanner_btn_cancel}</button>
                            <button id="stackboost-scanner-proceed" class="button button-primary button-large">${i18n.scanner_btn_proceed}</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Bind buttons
            $('#stackboost-scanner-cancel').on('click', function() {
                $('#stackboost-scanner-modal').remove();
            });

            $('#stackboost-scanner-proceed').on('click', function() {
                $('#stackboost-scanner-modal').remove();

                // Set the bypass flag on the original button
                $triggerBtn.data('stackboost-bypass-scan', true);

                // Trigger the click again
                $triggerBtn.click();
            });
        }
    };

    $(document).ready(function() {
        if (typeof stackboost_agent_protection !== 'undefined' && stackboost_agent_protection.content_scanner_enabled) {
            ContentScanner.init();
        }
    });

})(jQuery);
