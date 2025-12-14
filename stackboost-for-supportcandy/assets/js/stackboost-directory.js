jQuery(document).ready(function($) {
    // Helper function for conditional logging
    var sbLog = function(message, data) {
        if (typeof window.stackboost_log === 'function') {
            window.stackboost_log('[Directory] ' + message, data);
        } else if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
            if (data) {
                console.log('[StackBoost Directory]', message, data);
            } else {
                console.log('[StackBoost Directory]', message);
            }
        }
    };

    var sbError = function(message, data) {
        if (typeof window.stackboost_log === 'function') {
            window.stackboost_log('[Directory] ' + message, data);
        } else if (typeof stackboostPublicAjax !== 'undefined' && stackboostPublicAjax.debug_enabled) {
             if (data) {
                console.error('[StackBoost Directory]', message, data);
            } else {
                console.error('[StackBoost Directory]', message);
            }
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
                "dom": "lfrtip",
                "searching": true,
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
                sbLog('Responsive DataTables initialized.');
            } catch (e) {
                // We keep this warn as it's useful even in non-debug mode for developers inspecting console
                console.warn('[StackBoost Directory] Responsive DataTables init failed (likely hidden). Retrying standard config.');
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
