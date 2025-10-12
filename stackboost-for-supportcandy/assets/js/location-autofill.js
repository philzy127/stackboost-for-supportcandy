jQuery(document).ready(function($) {
    // When the location dropdown changes
    $('#location').on('change', function() {
        var locationId = $(this).val();
        if (locationId) {
            // Set the hidden field with the location name
            var locationName = $(this).find('option:selected').text();
            $('#stackboost_staff_directory_location_name_hidden').val(locationName);
        }
    });
});