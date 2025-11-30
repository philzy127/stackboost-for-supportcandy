(function($) {
    'use strict';

    const AutoSave = {
        TICKET_ID: null,
        STORAGE_KEY: null,
        editorId: null,

        init: function() {
            // Wait for TinyMCE to be initialized
            const checkTinyMCE = setInterval(() => {
                if (typeof tinymce !== 'undefined' && tinymce.editors.length > 0) {
                    // Try to find the active editor for the reply box
                    // SupportCandy usually uses 'wpsc_reply_editor' or similar ID
                    // We iterate to find the one that is likely the reply body
                    for (let i = 0; i < tinymce.editors.length; i++) {
                        const ed = tinymce.editors[i];
                        if (ed.id.includes('reply') || ed.id.includes('body') || ed.id.includes('wpsc')) {
                            this.editorId = ed.id;
                            this.setupAutoSave(ed);
                            break;
                        }
                    }
                    clearInterval(checkTinyMCE);
                }
            }, 1000);

            // Stop checking after 10 seconds to save resources
            setTimeout(() => clearInterval(checkTinyMCE), 10000);

            // Handle form submission to clear draft
            // We listen to both the form submit and the specific button clicks that trigger AJAX
            $(document).on('submit', '.wpsc-frm-reply, form[name="reply_ticket"]', () => {
                this.clearDraft();
            });

            // Also clear on click of submit buttons (in case form submit isn't standard)
            $(document).on('click', '.wpsc-submit-reply, .wpsc-submit-note, button[name="wpsc_submit_reply"]', () => {
                // We clear it, assuming validation passes.
                // If validation fails, the user might lose the draft if they reload immediately.
                // However, clearing on successful AJAX return is harder to hook into reliably without modifying SC core JS.
                // A balanced approach is to clear it here. If they don't reload, the content is still in the editor.
                this.clearDraft();
            });
        },

        getTicketId: function() {
             // Reuse the logic from LiveMonitor or duplicate it for independence
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('ticket_id')) return urlParams.get('ticket_id');
            if (urlParams.has('id')) return urlParams.get('id');
            const $hiddenInput = $('input[name="ticket_id"]');
            if ($hiddenInput.length > 0) return $hiddenInput.val();
            if (typeof wpsc_ticket_id !== 'undefined') return wpsc_ticket_id;
            return null;
        },

        setupAutoSave: function(editor) {
            this.TICKET_ID = this.getTicketId();
            if (!this.TICKET_ID) return;

            this.STORAGE_KEY = 'stackboost_draft_ticket_' + this.TICKET_ID;

            // Restore Draft
            const savedContent = localStorage.getItem(this.STORAGE_KEY);
            if (savedContent && savedContent.trim() !== '') {
                // Only restore if editor is currently empty or user confirms
                const currentContent = editor.getContent();
                if (!currentContent || currentContent.trim() === '') {
                    editor.setContent(savedContent);
                    this.showRestoredNotice(editor);
                }
            }

            // Bind Save Events
            editor.on('keyup change', () => {
                const content = editor.getContent();
                localStorage.setItem(this.STORAGE_KEY, content);
            });
        },

        showRestoredNotice: function(editor) {
            // Find editor container
            const $container = $(editor.getContainer());
            const noticeHtml = `
                <div class="stackboost-autosave-notice" style="font-size: 12px; color: #46b450; margin-bottom: 5px;">
                    <span class="dashicons dashicons-yes" style="font-size: 14px; vertical-align: middle;"></span>
                    ${stackboost_agent_protection.i18n.draft_restored}
                </div>
            `;
            $container.before(noticeHtml);

            // Fade out after a few seconds
            setTimeout(() => {
                $('.stackboost-autosave-notice').fadeOut();
            }, 5000);
        },

        clearDraft: function() {
            if (this.STORAGE_KEY) {
                localStorage.removeItem(this.STORAGE_KEY);
            }
        }
    };

    $(document).ready(function() {
        if (typeof stackboost_agent_protection !== 'undefined' && stackboost_agent_protection.auto_save_enabled) {
            AutoSave.init();
        }
    });

})(jQuery);
