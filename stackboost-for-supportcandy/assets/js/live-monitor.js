(function($) {
    'use strict';

    const LiveMonitor = {
        intervalId: null,
        ticketId: null,
        currentUpdatedTimestamp: 0,

        init: function() {
            const $ticketContainer = $('.wpsc-ticket-page');
            if ($ticketContainer.length === 0) return;

            this.ticketId = this.getTicketId();
            if (!this.ticketId) return;

            // Sync state with server to get the initial baseline timestamp
            this.syncState(() => {
                // Start polling if enabled
                if (stackboost_agent_protection.live_monitor_enabled) {
                    const interval = parseInt(stackboost_agent_protection.live_monitor_interval) * 1000;
                    this.intervalId = setInterval(this.checkUpdates.bind(this), interval);
                }
            });
        },

        getTicketId: function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('ticket_id')) return urlParams.get('ticket_id');
            if (urlParams.has('id')) return urlParams.get('id');
            const $hiddenInput = $('input[name="ticket_id"]');
            if ($hiddenInput.length > 0) return $hiddenInput.val();
            if (typeof wpsc_ticket_id !== 'undefined') return wpsc_ticket_id;
            return null;
        },

        syncState: function(callback) {
            $.ajax({
                url: stackboost_agent_protection.ajax_url,
                method: 'POST',
                data: {
                    action: 'stackboost_check_ticket_updates',
                    nonce: stackboost_agent_protection.nonce,
                    ticket_id: this.ticketId,
                    current_updated_timestamp: 0 // Signal to just get the current time
                },
                success: (response) => {
                    if (response.success) {
                        this.currentUpdatedTimestamp = response.data.current_updated_timestamp;
                        if (callback) callback();
                    }
                }
            });
        },

        checkUpdates: function() {
            if (!this.currentUpdatedTimestamp) return;

            $.ajax({
                url: stackboost_agent_protection.ajax_url,
                method: 'POST',
                data: {
                    action: 'stackboost_check_ticket_updates',
                    nonce: stackboost_agent_protection.nonce,
                    ticket_id: this.ticketId,
                    current_updated_timestamp: this.currentUpdatedTimestamp
                },
                success: (response) => {
                    if (response.success && response.data.modified) {
                        this.showWarning();
                        clearInterval(this.intervalId);
                    }
                }
            });
        },

        showWarning: function() {
            if ($('#stackboost-monitor-warning').length > 0) return;

            const warningHtml = `
                <div id="stackboost-monitor-warning" class="notice notice-warning inline" style="display: block; margin: 10px 0; padding: 10px; border-left: 4px solid #ffba00; background: #fff;">
                    <p style="margin: 0; font-weight: bold; color: #d63638;">
                        <span class="dashicons dashicons-warning" style="color: #d63638; vertical-align: text-bottom;"></span>
                        ${stackboost_agent_protection.i18n.heads_up}
                    </p>
                    <p style="margin: 5px 0 0;">${stackboost_agent_protection.i18n.ticket_updated_msg}</p>
                    <p style="margin: 5px 0 0;">
                        <a href="javascript:location.reload();" class="button button-secondary">${stackboost_agent_protection.i18n.refresh_page}</a>
                    </p>
                </div>
            `;

            const $editorContainer = $('.wpsc-ticket-reply-section, .wpsc-reply-box, #wpsc-reply-editor').first();

            if ($editorContainer.length > 0) {
                $editorContainer.before(warningHtml);
            } else {
                $('.wpsc-ticket-page').prepend(warningHtml);
            }
        }
    };

    $(document).ready(function() {
        if (typeof stackboost_agent_protection !== 'undefined') {
            LiveMonitor.init();
        }
    });

})(jQuery);
