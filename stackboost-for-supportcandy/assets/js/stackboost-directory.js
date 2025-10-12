jQuery(document).ready(function($) {
    console.log('[DEBUG] stackboost-directory.js: Script loaded and document is ready.');

    // Initialize DataTables
    $('#stackboostStaffDirectoryTable').DataTable({
        "pageLength": 25,
        "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
        "responsive": true,
        "language": {
            "search": "Filter results:",
            "lengthMenu": "Show _MENU_ entries"
        },
        "initComplete": function(settings, json) {
            console.log('[DEBUG] stackboost-directory.js: DataTable initComplete callback fired.');
            var table = this.api();
            var lengthSelect = $(table.table().container()).find('.dataTables_length select');
            console.log('[DEBUG] stackboost-directory.js: Found dropdown element using API:', lengthSelect);
            console.log('[DEBUG] stackboost-directory.js: Number of dropdowns found:', lengthSelect.length);

            if (lengthSelect.length > 0 && !lengthSelect.parent().hasClass('stackboost-select-wrapper')) {
                console.log('[DEBUG] stackboost-directory.js: Applying dropdown fix...');
                lengthSelect.wrap('<div class="stackboost-select-wrapper"></div>');
                lengthSelect.parent().css({
                    'position': 'relative',
                    'display': 'inline-block'
                });
                $('<style>')
                    .text('.stackboost-select-wrapper::after { content: ""; position: absolute; top: 50%; right: 10px; transform: translateY(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 5px solid #555; pointer-events: none; z-index: 999; } .stackboost-select-wrapper select { -webkit-appearance: none; -moz-appearance: none; appearance: none; padding-right: 25px; }')
                    .appendTo('head');
                console.log('[DEBUG] stackboost-directory.js: Dropdown fix applied.');
            } else {
                console.log('[DEBUG] stackboost-directory.js: Dropdown fix not applied. Condition not met. Element already wrapped or not found.');
            }
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