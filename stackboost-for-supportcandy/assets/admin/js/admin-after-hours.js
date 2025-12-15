(function($) {
    'use strict';

    $(document).ready(function() {
        if ($('.stackboost-timepicker').length) {
            $('.stackboost-timepicker').clockpicker({
                autoclose: true,
                twelvehour: true
            });
        }
    });

})(jQuery);
