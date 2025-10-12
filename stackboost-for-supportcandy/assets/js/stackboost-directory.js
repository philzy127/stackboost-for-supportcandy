jQuery(document).ready(function($) {
    console.log('StackBoost Directory script loaded.');

    var $table = $('#stackboostStaffDirectoryTable');
    console.log('Table element selected:', $table);

    if ($table.length) {
        console.log('Initializing DataTables...');
        var dataTableInstance = $table.DataTable({
            "pageLength": 25,
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
            "responsive": true,
            "language": {
                "search": "Filter results:",
                "lengthMenu": "Show _MENU_ entries"
            },
            "initComplete": function(settings, json) {
                console.log('DataTables initialization complete.');
                var $select = $(this).closest('.dataTables_wrapper').find('.dataTables_length select');

                if ($select.length) {
                    console.log('--- Dropdown Element Debug ---');
                    console.log('Select Element:', $select[0]);
                    console.log('Parent Element:', $select.parent()[0]);

                    var styles = window.getComputedStyle($select[0]);
                    console.log('Computed CSS - z-index:', styles.zIndex);
                    console.log('Computed CSS - position:', styles.position);
                    console.log('Computed CSS - padding-right:', styles.paddingRight);
                    console.log('Computed CSS - appearance:', styles.webkitAppearance || styles.mozAppearance || styles.appearance);
                    console.log('--- End Debug ---');
                } else {
                    console.log('Could not find the length dropdown select element.');
                }
            }
        });
    } else {
        console.log('StackBoost Directory table not found. Skipping DataTables initialization.');
    }

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