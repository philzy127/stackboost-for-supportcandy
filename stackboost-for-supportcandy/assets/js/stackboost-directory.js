jQuery(document).ready(function($) {
    console.log('StackBoost Directory: Script loaded. Secure Context: ' + window.isSecureContext);

    // 1. Move Event Listeners FIRST so they attach even if DataTables crashes.

    // Copy to clipboard functionality for email
    $(document).on('click', '.stackboost-copy-email-icon', function() {
        console.log('StackBoost Directory: Email icon clicked');
        var email = $(this).data('email');
        if (email) {
            copyToClipboard(email, $(this), 'email');
        } else {
            console.error('StackBoost Directory: No email data found on icon');
        }
    });

    // Copy to clipboard functionality for phone
    $(document).on('click', '.stackboost-copy-phone-icon', function() {
        console.log('StackBoost Directory: Phone icon clicked');
        var phone = $(this).data('phone');
        var extension = $(this).data('extension');

        console.log('StackBoost Directory: Raw Data', { phone: phone, extension: extension });

        // Ensure we treat them as strings to avoid weird addition
        phone = String(phone);

        var fullNumber = phone;
        if (extension) {
            fullNumber = phone + ' x' + extension;
        }

        if (fullNumber && fullNumber !== "undefined") {
            copyToClipboard(fullNumber, $(this), 'phone');
        } else {
             console.error('StackBoost Directory: Failed to construct full number');
        }
    });

    // 2. Robust DataTables Initialization
    // Check if table exists and is visible to avoid cloneNode errors
    var $table = $('#stackboostStaffDirectoryTable');
    if ($table.length > 0) {

        // Define common options to avoid duplication
        var getDtOptions = function(responsive) {
            var opts = {
                "pageLength": 25,
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
                "responsive": responsive,
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
            };

            // If fallback (non-responsive), disable autoWidth to prevent calculation errors on hidden elements
            if (!responsive) {
                opts.autoWidth = false;
            }
            return opts;
        };

        var attemptInit = function() {
            try {
                // Attempt 1: Responsive
                // Note: We deliberately don't assign the instance to a variable to avoid potential GC issues in catches
                $table.DataTable(getDtOptions(true));
                console.log('StackBoost Directory: Responsive DataTables initialized.');
            } catch (e) {
                console.warn('StackBoost Directory: Responsive DataTables init failed. Retrying with standard configuration.');
                // Attempt 2: Non-responsive fallback
                try {
                     if ($.fn.DataTable.isDataTable('#stackboostStaffDirectoryTable')) {
                        // Use the static selector to destroy, in case the jQuery object reference is stale
                        $('#stackboostStaffDirectoryTable').DataTable().destroy();
                     }
                    $table.DataTable(getDtOptions(false));
                    console.log('StackBoost Directory: Standard DataTables initialized (Fallback).');
                } catch (e2) {
                    console.error('StackBoost Directory: DataTables fallback init failed.', e2);
                }
            }
        };

        // Robust init logic
        if ($table.is(':visible')) {
            attemptInit();
        } else {
            // Poll for visibility (common in tabs)
            var checkVis = setInterval(function() {
                if ($table.is(':visible')) {
                    clearInterval(checkVis);
                    attemptInit();
                }
            }, 200);
        }
    }

    // Function to show a toast notification
    function showToast(message) {
        var toast = $('<div class="stackboost-toast"></div>').text(message);
        $('body').append(toast);
        toast.fadeIn(400).delay(3000).fadeOut(400, function() {
            $(this).remove();
        });
    }

    // Function to handle the copy action
    function copyToClipboard(text, $icon, type) {
        console.log('StackBoost Directory: Attempting to copy', text);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                handleCopySuccess($icon, text, type);
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
                // Fallback to execCommand if async fails
                fallbackCopyTextToClipboard(text, $icon, type);
            });
        } else {
            fallbackCopyTextToClipboard(text, $icon, type);
        }
    }

    // Fallback using execCommand
    function fallbackCopyTextToClipboard(text, $icon, type) {
        console.log('StackBoost Directory: Using fallback copy');
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
                handleCopySuccess($icon, text, type);
            } else {
                console.error('Fallback: Copying text command was unsuccessful');
            }
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }

        document.body.removeChild(textArea);
    }

    // Success UI Feedback
    function handleCopySuccess($icon, text, type) {
        console.log('StackBoost Directory: Copy successful');
        $icon.addClass('copied');

        var msg = type === 'email' ? 'Email copied: ' + text : 'Phone copied: ' + text;
        showToast(msg);

        setTimeout(function() {
            $icon.removeClass('copied');
        }, 1500); // 1.5s delay to be visible
    }

});