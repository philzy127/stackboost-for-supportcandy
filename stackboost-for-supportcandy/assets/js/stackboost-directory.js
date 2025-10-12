jQuery(document).ready(function($) {
    // Initialize DataTables
    $('#stackboostStaffDirectoryTable').DataTable({
        "pageLength": 25,
        "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
        "responsive": true,
        "language": {
            "search": "Filter results:"
        }
    });

    // Copy to clipboard functionality for email
    $(document).on('click', '.stackboost-copy-email-icon', function() {
        var email = $(this).data('email');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(email).select();
        document.execCommand('copy');
        tempInput.remove();
        alert('Email address copied to clipboard: ' + email);
    });

    // Copy to clipboard functionality for phone
    $(document).on('click', '.stackboost-copy-phone-icon', function() {
        var phone = $(this).data('phone');
        var extension = $(this).data('extension');
        var fullNumber = phone + (extension ? 'x' + extension : '');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(fullNumber).select();
        document.execCommand('copy');
        tempInput.remove();
        alert('Phone number copied to clipboard: ' + fullNumber);
    });
});