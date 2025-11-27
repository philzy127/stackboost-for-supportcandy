jQuery(document).ready(function($) {

    function initializeDataTablesWhenVisible() {
        var tableElement = $('#stackboostStaffDirectoryTable');

        // Check if the table exists and is visible.
        // The ':visible' selector checks that an element and its ancestors have a display property other than 'none'
        // and a width and height greater than 0.
        if (tableElement.length && tableElement.is(':visible')) {
            // If visible, clear the interval and initialize DataTables.
            clearInterval(visibilityCheckInterval);

            tableElement.DataTable({
                "pageLength": 25,
                "lengthMenu": [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                "responsive": true,
                "autoWidth": false,
                "language": {
                    "search": "Filter results:",
                    "lengthMenu": "Show _MENU_ entries"
                },
                "columnDefs": [{
                    "targets": 1, // The 'Phone' column
                    "render": function(data, type, row, meta) {
                        if (type === 'filter') {
                            // Defensive check: Ensure the row data exists before accessing it.
                            if (
                                meta.settings.aoData[meta.row] &&
                                meta.settings.aoData[meta.row].anCells &&
                                meta.settings.aoData[meta.row].anCells[meta.col]
                            ) {
                                var cellNode = meta.settings.aoData[meta.row].anCells[meta.col];
                                var dataSearch = $(cellNode).data('search');
                                return data + ' ' + (dataSearch || '');
                            }
                        }
                        return data;
                    }
                }]
            });
        }
    }

    // Set an interval to check for the table's visibility every 100 milliseconds.
    // This is robust against themes or other scripts that might hide/show the container after the initial page load.
    var visibilityCheckInterval = setInterval(initializeDataTablesWhenVisible, 100);


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