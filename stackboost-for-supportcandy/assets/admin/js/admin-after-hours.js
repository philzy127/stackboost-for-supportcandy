(function($) {
    'use strict';

    $(document).ready(function() {
        if ($('.stackboost-timepicker').length) {
            $('.stackboost-timepicker').flatpickr({
                enableTime: true,
                noCalendar: true,
                dateFormat: "h:i K",
                time_24hr: false
            });
        }
    });

})(jQuery);
