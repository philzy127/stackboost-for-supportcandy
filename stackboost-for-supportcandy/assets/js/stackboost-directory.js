jQuery(document).ready(function($) {
    // Initialize DataTables
    $('#stackboostStaffDirectoryTable').DataTable({
        "pageLength": 25,
        "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
        "responsive": true,
        "language": {
            "search": "Filter results:",
            "lengthMenu": "Show _MENU_ entries"
        }
    });

    // Function to show a toast notification
    function showToast(message) {
        var toast = $('<div class="stackboost-toast"></div>').text(message);
        $('body').append(toast);
        toast.fadeIn(400).delay(3000).fadeOut(400, function() {
            $(this).remove();
        });
    }

    // Function to handle the copy action
    function copyToClipboard(text, $icon) {
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();

        $icon.addClass('copied');
        setTimeout(function() {
            $icon.removeClass('copied');
        }, 500);
    }

    // Copy to clipboard functionality for email
    $(document).on('click', '.stackboost-copy-email-icon', function() {
        var email = $(this).data('email');
        copyToClipboard(email, $(this));
        showToast('Email copied: ' + email);
    });

    // Copy to clipboard functionality for phone
    $(document).on('click', '.stackboost-copy-phone-icon', function() {
        var phone = $(this).data('phone');
        var extension = $(this).data('extension');
        var fullNumber = phone + (extension ? 'x' + extension : '');
        copyToClipboard(fullNumber, $(this));
        showToast('Phone copied: ' + fullNumber);
    });
});