jQuery(document).ready(function($) {
    // Helper function for conditional logging using standardized wrapper
    var sbLog = function(message, data) {
        if (typeof window.stackboostLog === 'function') {
            window.stackboostLog('[Directory] ' + message, data, 'log');
        }
    };

    var sbError = function(message, data) {
        if (typeof window.stackboostLog === 'function') {
            window.stackboostLog('[Directory Error] ' + message, data, 'error');
        }
    };

    sbLog('Script loaded. Secure Context: ' + window.isSecureContext);

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
                // Note: Column indices can shift if columns are hidden!
                // We must map visual indices to data types or iterate all.

                // Let's iterate all data columns available
                var found = false;

                data.forEach(function(colData, idx) {
                    if (found) return;

                    var rawData = (colData || '').replace(/<[^>]+>/g, "").toLowerCase();
                    var rawDigits = rawData.replace(/\D/g, '');

                    // Simple text check
                    if (rawData.includes(termLower)) {
                        found = true;
                    }

                    // Digits check (for phone numbers mainly)
                    if (termDigits.length > 0 && rawDigits.includes(termDigits)) {
                        found = true;
                    }
                });

                return found;
            }
        );

        // Define common options to avoid duplication
        var getDtOptions = function(responsive) {
            // Read configuration from data attributes
            var searching = $table.data('search-enabled');
            if (searching === undefined) searching = true; // Default true if not set

            var pageLength = $table.data('page-length');
            if (pageLength === undefined) pageLength = 25;

            var lengthChange = $table.data('length-change-enabled');
            if (lengthChange === undefined) lengthChange = true;

            // Ensure custom pageLength is in the lengthMenu
            var customPageLength = parseInt(pageLength, 10);
            var lengthMenuValues = [10, 25, 50, -1];
            var lengthMenuLabels = [10, 25, 50, "All"];

            if (lengthMenuValues.indexOf(customPageLength) === -1 && customPageLength !== -1) {
                // Add value and label
                lengthMenuValues.push(customPageLength);
                lengthMenuLabels.push(customPageLength);

                // Sort both arrays based on values to keep them in order (keeping -1/"All" at end)
                // Combine into objects for sorting
                var combined = [];
                for (var i = 0; i < lengthMenuValues.length; i++) {
                    combined.push({ val: lengthMenuValues[i], label: lengthMenuLabels[i] });
                }

                combined.sort(function(a, b) {
                    // Handle -1 (All) to always be last
                    if (a.val === -1) return 1;
                    if (b.val === -1) return -1;
                    return a.val - b.val;
                });

                // Unpack
                lengthMenuValues = combined.map(function(o) { return o.val; });
                lengthMenuLabels = combined.map(function(o) { return o.label; });
            }

            var opts = {
                "dom": '<"stackboost-directory-header"lf>rtip',
                "searching": searching,
                "pageLength": customPageLength,
                "lengthChange": lengthChange,
                "lengthMenu": [ lengthMenuValues, lengthMenuLabels ],
                "responsive": responsive,
                "language": {
                    "search": "Filter results:",
                    "lengthMenu": "Show _MENU_ entries"
                },
                "columnDefs": [
                    {
                        "targets": "_all", // Apply to all columns that might have data-search
                        "render": function ( data, type, row, meta ) {
                            if ( type === 'filter' ) {
                                var cellNode = meta.settings.aoData[meta.row].anCells[meta.col];
                                // Check if cellNode exists (it might not if responsive hidden)
                                if (cellNode) {
                                    var dataSearch = $(cellNode).data('search');
                                    if (dataSearch) {
                                        return data + ' ' + dataSearch;
                                    }
                                }
                            }
                            return data;
                        }
                    }
                ],
                "initComplete": function(settings, json) {
                    var api = this.api();
                    var $searchInput = $(api.table().container()).find('.dataTables_filter input');

                    if ($searchInput.length > 0) {
                        // Unbind default DataTables search event to prevent it from filtering out our custom matches
                        $searchInput.unbind();

                        // Bind custom event to trigger redraw (which calls ext.search)
                        $searchInput.bind('keyup change input', function() {
                            api.draw();
                        });
                    }
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
                sbLog('Responsive DataTables initialized.');
            } catch (e) {
                // We keep this warn as it's useful even in non-debug mode for developers inspecting console
                sbLog('Responsive DataTables init failed (likely hidden). Retrying standard config.');
                // Attempt 2: Non-responsive fallback
                try {
                     if ($.fn.DataTable.isDataTable('#stackboostStaffDirectoryTable')) {
                        // Use the static selector to destroy, in case the jQuery object reference is stale
                        $('#stackboostStaffDirectoryTable').DataTable().destroy();
                     }
                    $table.DataTable(getDtOptions(false));
                    sbLog('Standard DataTables initialized (Fallback).');
                } catch (e2) {
                    sbError('DataTables fallback init failed.', e2);
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
});
