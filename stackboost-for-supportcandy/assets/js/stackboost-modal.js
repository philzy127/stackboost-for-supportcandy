jQuery(document).ready(function($) {
    var modal = $('#stackboost-staff-modal');
    var modalBody = modal.find('.stackboost-modal-body');
    var closeModal = modal.find('.stackboost-modal-close');

    // Function to open the modal and load content
    function openModalWithPost(postId) {
        if (!modal.length || !postId) {
            return;
        }

        // Show modal and loader
        modal.show();
        modalBody.html('<div class="stackboost-modal-loader"></div>');

        // AJAX request to get post content
        $.ajax({
            url: stackboostPublicAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'stackboost_get_staff_details',
                post_id: postId,
                nonce: stackboostPublicAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    modalBody.html(response.data.html);
                } else {
                    modalBody.html('<p>Error: Could not load content.</p>');
                    console.error('AJAX Error:', response);
                }
            },
            error: function(xhr, status, error) {
                modalBody.html('<p>Error: Could not load content.</p>');
                console.error('AJAX Failure:', error);
            }
        });
    }

    // Event listener for modal trigger links
    $(document).on('click', '.stackboost-modal-trigger', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        openModalWithPost(postId);
    });

    // Close modal when 'x' is clicked
    closeModal.on('click', function() {
        modal.hide();
        modalBody.html(''); // Clear content
    });

    // Close modal when clicking outside of the modal content
    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
            modalBody.html(''); // Clear content
        }
    });
});