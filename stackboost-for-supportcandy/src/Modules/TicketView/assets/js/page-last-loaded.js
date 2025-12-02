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
            // Default to WordPress-like format inference
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
            $('.wpsc-ticket-pagination-header').append($span.clone());
        }

        if (config.placement === 'footer' || config.placement === 'both') {
            // SupportCandy Footer Pagination
            $('.wpsc-ticket-pagination-footer').append($span.clone());
        }
    }

    $(document).ajaxComplete(function(event, xhr, settings) {
        if (!settings.data || settings.data.indexOf('action=wpsc_get_tickets') === -1) {
            return;
        }
        updatePageLastLoaded();
    });

    // Initial run in case the table is already loaded (e.g. static render or early ajax)
    $(document).ready(function() {
        if ($('.wpsc-ticket-pagination-header').length > 0 || $('.wpsc-ticket-pagination-footer').length > 0) {
            updatePageLastLoaded();
        }
    });

})(jQuery);
