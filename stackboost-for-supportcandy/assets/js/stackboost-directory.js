jQuery(document).ready(function($) {
    // Initialize DataTables
    $('#stackboostStaffDirectoryTable').DataTable({
        "pageLength": 25,
        "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
        "responsive": true,
        "language": {
            "search": "Filter results:",
            "lengthMenu": "Show _MENU_ entries"
        },
        "columnDefs": [
            {
                "targets": 1, // The 'Phone' column
                "render": function ( data, type, row, meta ) {
                    if ( type === 'filter' ) {
                        var cellNode = meta.settings.aoData[meta.row].anCells[meta.col];
                        var dataSearch = $(cellNode).data('search');
                        return data + ' ' + dataSearch;
                    }
                    return data;
                }
            }
        ]
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
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                handleCopySuccess($icon);
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
                // Fallback to execCommand if async fails
                fallbackCopyTextToClipboard(text, $icon);
            });
        } else {
            fallbackCopyTextToClipboard(text, $icon);
        }
    }

    // Fallback using execCommand
    function fallbackCopyTextToClipboard(text, $icon) {
        var textArea = document.createElement("textarea");
        textArea.value = text;

        // Ensure it's not visible but part of the DOM
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                handleCopySuccess($icon);
            } else {
                console.error('Fallback: Copying text command was unsuccessful');
            }
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }

        document.body.removeChild(textArea);
    }

    // Success UI Feedback
    function handleCopySuccess($icon) {
        $icon.addClass('copied');
        setTimeout(function() {
            $icon.removeClass('copied');
        }, 1500); // 1.5s delay to be visible
    }

    // Copy to clipboard functionality for email
    $(document).on('click', '.stackboost-copy-email-icon', function() {
        var email = $(this).data('email');
        if (email) {
            copyToClipboard(email, $(this));
            showToast('Email copied: ' + email);
        }
    });

    // Copy to clipboard functionality for phone
    $(document).on('click', '.stackboost-copy-phone-icon', function() {
        var phone = $(this).data('phone');
        var extension = $(this).data('extension');

        // Ensure we treat them as strings to avoid weird addition
        phone = String(phone);

        var fullNumber = phone;
        if (extension) {
            fullNumber = phone + ' x' + extension;
        }

        if (fullNumber) {
            copyToClipboard(fullNumber, $(this));
            showToast('Phone copied: ' + fullNumber);
        }
    });
});