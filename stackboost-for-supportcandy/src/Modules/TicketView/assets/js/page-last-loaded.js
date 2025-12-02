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
        $('.stackboost-last-loaded-row').remove();

        var $row = $('<div>')
            .addClass('stackboost-last-loaded-row')
            .css({
                'width': '100%',
                'text-align': 'right',
                'padding': '0 15px 5px',
                'box-sizing': 'border-box',
                'font-size': '12px',
                'color': '#777',
                'clear': 'both' // Ensure it breaks to a new line if floating elements exist
            })
            .text(label + timeString);

        if (config.placement === 'header' || config.placement === 'both') {
            // Place AFTER the bulk actions container (which contains top pagination)
            // but BEFORE the ticket list table.
            var $bulkActions = $('.wpsc-bulk-actions');
            if ($bulkActions.length > 0) {
                $bulkActions.after($row.clone());
            } else {
                // Fallback: if bulk actions hidden/missing, try prepending to the main container
                var $container = $('.wpsc-tickets-container');
                if ($container.length > 0) {
                    $container.prepend($row.clone());
                }
            }
        }

        if (config.placement === 'footer' || config.placement === 'both') {
            // Place AFTER the footer pagination
            var $footer = $('.wpsc-ticket-pagination-footer');
            if ($footer.length > 0) {
                $footer.after($row.clone());
            } else {
                // Fallback: append to main container
                var $container = $('.wpsc-tickets-container');
                if ($container.length > 0) {
                    $container.append($row.clone());
                }
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
            var urlParams = new URLSearchParams(settings.data);
            action = urlParams.get('action');
        }
        // Handle object data
        else if (typeof settings.data === 'object') {
            if (settings.data instanceof FormData) {
                action = settings.data.get('action');
            } else {
                action = settings.data.action;
            }
        }

        return action === 'wpsc_get_tickets' || action === 'wpsc_get_ticket_list';
    }

    $(document).ajaxComplete(function(event, xhr, settings) {
        if (isTargetAjax(settings)) {
            setTimeout(updatePageLastLoaded, 50);
        }
    });

    $(document).ready(function() {
        setTimeout(updatePageLastLoaded, 100);
        setTimeout(updatePageLastLoaded, 1000);
    });

})(jQuery);
