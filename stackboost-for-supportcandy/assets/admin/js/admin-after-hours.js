(function($) {
    'use strict';

    $(document).ready(function() {
        if ($('.stackboost-timepicker').length) {
            $('.stackboost-timepicker').timepicker({
                timeFormat: 'h:mm tt',
                controlType: 'select',
                oneLine: true
            });
        }
    });

})(jQuery);
