jQuery(document).ready(function($) {
    $('#stackboost-upload-default-photo').on('click', function(e) {
        e.preventDefault();

        var frame = wp.media({
            title: 'Select or Upload Default Staff Photo',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#stackboost-default-photo-id').val(attachment.id);
            $('#stackboost-default-photo-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" />');
        });

        frame.open();
    });
});
