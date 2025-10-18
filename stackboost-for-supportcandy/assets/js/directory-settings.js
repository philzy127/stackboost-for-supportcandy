jQuery(document).ready(function($) {
    var wrap = $('.wrap');

    // Tab switching logic
    wrap.on('click', 'h2.nav-tab-wrapper a', function(e) {
        e.preventDefault();
        var url = new URL($(this).attr('href'));
        var subTab = url.searchParams.get("sub-tab");

        $('h2.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').hide();
        $('#tab-' + subTab).show();

        window.history.pushState({path: url.href}, '', url.href);
    });

    // On page load, show the correct tab if specified in URL
    if (typeof stackboostDirSettings !== 'undefined' && stackboostDirSettings.activeSubTab) {
        $('.tab-content').hide();
        $('#tab-' + stackboostDirSettings.activeSubTab).show();
    }

    // Dual-list sortable logic
    function updateHiddenField() {
        var fieldKeys = $('#displayed-fields').sortable('toArray', { attribute: 'data-key' });
        $('#stackboost-display-fields').val(fieldKeys.join(','));
    }

    if ($('.sortable-list').length > 0) {
        $('.sortable-list').sortable({
            connectWith: '.sortable-list',
            placeholder: 'ui-sortable-placeholder',
            receive: function(event, ui) {
                updateHiddenField();
            },
            stop: function(event, ui) {
                updateHiddenField();
            }
        }).disableSelection();

        // Initial update
        updateHiddenField();
    }
});