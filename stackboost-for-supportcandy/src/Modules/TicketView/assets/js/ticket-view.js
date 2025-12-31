jQuery(document).ready(function($) {

    /**
     * Main Runner for Ticket View Features
     */
    function stackboostRunTicketViewFeatures() {
        if (typeof stackboostTicketView === 'undefined') {
            return;
        }

        // Feature: Hide "Reply & Close" for non-agents
        if (stackboostTicketView.features.hide_reply_close.enabled) {
            stackboostHideReplyCloseButton();
        }
    }

    /**
     * Feature: Hide "Reply & Close" button for non-agents.
     */
    function stackboostHideReplyCloseButton() {
        // 1. Check if user is an agent (passed from PHP)
        if (stackboostTicketView.is_agent) {
            return; // Do nothing if agent
        }

        // 2. Find and hide the specific button
        // Selector targets the button with the specific onclick handler
        var selector = 'button.wpsc-it-editor-submit[onclick*="wpsc_it_reply_and_close"]';
        var $button = $(selector);

        if ($button.length) {
            $button.hide();
            // Optional: Log for debugging if enabled
            // window.stackboostLog('Hiding "Reply & Close" button for non-agent.');
        }
    }

    // Run on initial load
    stackboostRunTicketViewFeatures();

    // Run on AJAX complete (to handle SupportCandy navigation/updates)
    $(document).ajaxComplete(function(event, xhr, settings) {
        stackboostRunTicketViewFeatures();
    });

});
