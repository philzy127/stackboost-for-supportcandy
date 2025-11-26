jQuery(document).ready(function($) {
    // Before initializing DataTables, set up the header search inputs
    $('#stackboostStaffDirectoryTable thead .stackboost-directory-filters th').each(function() {
        $(this).html('<input type="text" placeholder="Search..." />');
    });

    // Initialize DataTables
    var table = $('#stackboostStaffDirectoryTable').DataTable({
        "pageLength": 25,
        "lengthMenu": [
            [10, 25, 50, -1],
            [10, 25, 50, "All"]
        ],
        "language": {
            "search": "Filter results:",
            "lengthMenu": "Show _MENU_ entries"
        },
        "columnDefs": [{
            "targets": 1, // The 'Phone' column
            "render": function(data, type, row, meta) {
                if (type === 'filter') {
                    // For filtering, combine the display data and the searchable number
                    var cellNode = meta.settings.aoData[meta.row].anCells[meta.col];
                    var dataSearch = $(cellNode).data('search');
                    return data + ' ' + dataSearch;
                }
                return data;
            }
        }],
        "initComplete": function() {
            // Apply the search
            this.api().columns().every(function(colIdx) {
                var that = this;
                // Find the input in the correct header row
                var input = $('#stackboostStaffDirectoryTable thead .stackboost-directory-filters th').eq(colIdx).find('input');

                input.on('keyup change clear', function() {
                    if (that.search() !== this.value) {
                        that.search(this.value).draw();
                    }
                });
            });
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