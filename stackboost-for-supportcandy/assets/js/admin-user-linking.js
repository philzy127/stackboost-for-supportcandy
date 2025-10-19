jQuery(document).ready(function($) {
    if (typeof StackBoost === 'undefined' || typeof StackBoost.helpers === 'undefined') {
        return;
    }

    var $userSearchContainer = $('#stackboost-user-search-container');
    var $userInfoContainer = $('#stackboost-user-info');
    var $userSearchSelect = $('#stackboost-user-search');
    var $userIdInput = $('#stackboost-user-id');
    var $unlinkFlagInput = $('#stackboost-unlink-user-flag');

    // Initialize the Select2 field
    var select2Options = {
        placeholder: 'Search for a user by name or email...',
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'stackboost_search_users',
                    term: params.term,
                    _ajax_nonce: stackboostUserLinking.nonce
                };
            },
            processResults: function(data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        minimumInputLength: 3
    };
    StackBoost.helpers.initializeSelect2($userSearchSelect, select2Options);

    // Handle user selection
    $userSearchSelect.on('select2:select', function(e) {
        var data = e.params.data;
        $userIdInput.val(data.id);
        $userInfoContainer.find('strong').text(data.text);

        $userSearchContainer.hide();
        $userInfoContainer.show();
        $unlinkFlagInput.val('0');
    });

    // Handle the "Change" button
    $('#stackboost-change-user').on('click', function(e) {
        e.preventDefault();
        $userInfoContainer.hide();
        $userSearchContainer.show();
        $userSearchSelect.val(null).trigger('change');
    });

    // Handle the "Remove" button
    $('#stackboost-remove-user').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to unlink this user?')) {
            $userIdInput.val('');
            $unlinkFlagInput.val('1');
            $userInfoContainer.find('strong').text('');
            $userInfoContainer.hide();
            $userSearchContainer.show();
            $userSearchSelect.val(null).trigger('change');
        }
    });
});