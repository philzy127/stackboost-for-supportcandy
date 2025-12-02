(function($) {
    'use strict';

    function updatePageLastLoaded() {
        if (typeof stackboostPageLastLoaded === 'undefined') {
            console.log('StackBoost PageLastLoaded: Config object undefined.');
            return;
        }

        var config = stackboostPageLastLoaded;
        console.log('StackBoost PageLastLoaded: Config:', config);

        if (!config.enabled) {
            console.log('StackBoost PageLastLoaded: Feature disabled in config.');
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

        console.log('StackBoost PageLastLoaded: Generated span:', $span[0].outerHTML);

        var added = false;
        if (config.placement === 'header' || config.placement === 'both') {
            // SupportCandy Header Pagination
            var $header = $('.wpsc-ticket-pagination-header');
            console.log('StackBoost PageLastLoaded: Found header:', $header.length);
            if ($header.length > 0) {
                $header.append($span.clone());
                added = true;
            }
        }

        if (config.placement === 'footer' || config.placement === 'both') {
            // SupportCandy Footer Pagination
            var $footer = $('.wpsc-ticket-pagination-footer');
            console.log('StackBoost PageLastLoaded: Found footer:', $footer.length);
            if ($footer.length > 0) {
                $footer.append($span.clone());
                added = true;
            }
        }

        if (!added) {
             console.log('StackBoost PageLastLoaded: Failed to add indicator. Target elements not found.');
        } else {
             console.log('StackBoost PageLastLoaded: Indicator added successfully.');
        }
    }

    $(document).ajaxComplete(function(event, xhr, settings) {
        // Log all AJAX completions to see if we're catching the right one
        // console.log('StackBoost PageLastLoaded: ajaxComplete', settings.data);

        if (!settings.data || typeof settings.data !== 'string' || settings.data.indexOf('action=wpsc_get_tickets') === -1) {
            return;
        }
        console.log('StackBoost PageLastLoaded: wpsc_get_tickets AJAX detected.');
        updatePageLastLoaded();
    });

    // Initial run in case the table is already loaded (e.g. static render or early ajax)
    $(document).ready(function() {
        console.log('StackBoost PageLastLoaded: document ready.');
        if ($('.wpsc-ticket-pagination-header').length > 0 || $('.wpsc-ticket-pagination-footer').length > 0) {
            console.log('StackBoost PageLastLoaded: Pagination elements found on ready. Running update.');
            updatePageLastLoaded();
        } else {
            console.log('StackBoost PageLastLoaded: Pagination elements NOT found on ready.');
        }
    });

})(jQuery);
