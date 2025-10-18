jQuery(document).ready(function($) {
    function formatPhoneNumber(input) {
        // 1. Strip all non-numeric characters from the input.
        let digits = input.value.replace(/\D/g, '');

        // 2. Truncate to 10 digits.
        digits = digits.substring(0, 10);

        // 3. Apply the formatting.
        let formatted = '';
        if (digits.length > 0) {
            formatted = '(' + digits.substring(0, 3);
        }
        if (digits.length > 3) {
            formatted += ') ' + digits.substring(3, 6);
        }
        if (digits.length > 6) {
            formatted += '-' + digits.substring(6, 10);
        }

        // 4. Set the formatted value back to the input.
        input.value = formatted;
    }

    var phone_fields = $('#office_phone, #mobile_phone');

    // Format on page load
    phone_fields.each(function() {
        formatPhoneNumber(this);
    });

    // Format on input
    phone_fields.on('input', function() {
        formatPhoneNumber(this);
    });
});