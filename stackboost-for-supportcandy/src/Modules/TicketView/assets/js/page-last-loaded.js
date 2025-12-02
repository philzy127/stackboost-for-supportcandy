(function($) {
    'use strict';

    function updatePageLastLoaded() {
        if (typeof stackboostPageLastLoaded === 'undefined') {
            return;
        }

        var config = stackboostPageLastLoaded;
        if (!config.enabled) {
            return;
        }

        var now = new Date();
        var timeString = '';

        if (config.format === '12') {
            timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true});
        } else if (config.format === '24') {
            timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: false});
        } else {
            var is12Hour = (config.wp_time_format.indexOf('a') !== -1 || config.wp_time_format.indexOf('A') !== -1);
            timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: is12Hour});
        }

        var label = config.label || 'Page Last Loaded: ';

        // Remove existing indicators to prevent duplicates
        $('.stackboost-last-loaded').remove();

        var $span = $('<span>')
            .addClass('stackboost-last-loaded')
            .css({
                'margin-left': '10px',
                'font-weight': 'bold'
            })
            .text(label + timeString);

        if (config.placement === 'header' || config.placement === 'both') {
            // SupportCandy Header Pagination
            var $header = $('.wpsc-ticket-pagination-header');
            if ($header.length > 0) {
                $header.append($span.clone());
            } else {
                console.log('StackBoost PageLastLoaded: Header target not found.');
            }
        }

        if (config.placement === 'footer' || config.placement === 'both') {
            // SupportCandy Footer Pagination
            var $footer = $('.wpsc-ticket-pagination-footer');
            if ($footer.length > 0) {
                $footer.append($span.clone());
            } else {
                console.log('StackBoost PageLastLoaded: Footer target not found.');
            }
        }
    }

    function isTargetAjax(settings) {
        if (!settings || !settings.data) {
            return false;
        }

        var action = '';

        // Handle string data (standard jQuery.ajax serialization)
        if (typeof settings.data === 'string') {
            // Parse query string to be safe
            var urlParams = new URLSearchParams(settings.data);
            action = urlParams.get('action');
        }
        // Handle object data (if processData is false or interception happens early)
        else if (typeof settings.data === 'object') {
            if (settings.data instanceof FormData) {
                action = settings.data.get('action');
            } else {
                action = settings.data.action;
            }
        }

        console.log('StackBoost PageLastLoaded: AJAX Action detected:', action);

        return action === 'wpsc_get_tickets' || action === 'wpsc_get_ticket_list';
    }

    $(document).ajaxComplete(function(event, xhr, settings) {
        if (isTargetAjax(settings)) {
            // Wait a brief moment for DOM updates to settle if SC uses internal callbacks
            setTimeout(updatePageLastLoaded, 50);
        }
    });

    // Initial run in case the table is already loaded
    $(document).ready(function() {
        // Retry a few times in case of race conditions with SC's own init scripts
        setTimeout(updatePageLastLoaded, 100);
        setTimeout(updatePageLastLoaded, 1000);
    });

})(jQuery);
