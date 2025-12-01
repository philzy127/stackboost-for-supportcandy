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

        // 1. Check for pre-formatted copy text (Primary Method)
        var copyText = $(this).data('copy-text');
        if (copyText) {
             console.log('StackBoost Directory: Using pre-formatted copy text', copyText);
             copyToClipboard(copyText, $(this), 'phone');
             return;
        }

        // 2. Fallback to legacy construction (Secondary Method)
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

        // Define Custom Search Function for "Numbers to Numbers" Logic
        $.fn.dataTable.ext.search.push(
            function( settings, data, dataIndex ) {
                // Only filter OUR table
                if ( settings.nTable.id !== 'stackboostStaffDirectoryTable' ) {
                    return true;
                }

                // Get value from input (since we hijacked the built-in search)
                var term = $('#stackboostStaffDirectoryTable_filter input').val();
                if (!term) return true;

                var termLower = term.toLowerCase();
                var termDigits = term.replace(/\D/g, '');

                // Iterate columns (Name=0, Phone=1, Dept=2, Title=3)
                // Return true if ANY column matches

                // Phone Column (Index 1) - Special "Numbers Only" Logic
                var phoneData = data[1] || '';
                var phoneDigits = phoneData.replace(/\D/g, '');
                if (termDigits.length > 0 && phoneDigits.includes(termDigits)) {
                    return true;
                }

                // Other Columns - Standard Logic
                // We check 0, 2, 3 against the standard term
                // Strip HTML from data just in case
                var otherIndices = [0, 2, 3];
                for (var i = 0; i < otherIndices.length; i++) {
                    var idx = otherIndices[i];
                    var colData = (data[idx] || '').replace(/<[^>]+>/g, "").toLowerCase();
                    if (colData.includes(termLower)) {
                        return true;
                    }
                }

                return false;
            }
        );

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
                ],
                "initComplete": function(settings, json) {
                    var api = this.api();
                    var $searchInput = $(api.table().container()).find('.dataTables_filter input');

                    // Unbind default DataTables search event to prevent it from filtering out our custom matches
                    $searchInput.unbind();

                    // Bind custom event to trigger redraw (which calls ext.search)
                    $searchInput.bind('keyup change input', function() {
                        api.draw();
                    });
                }
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