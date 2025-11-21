jQuery(document).ready(function($) {

    var $useSCOrderCheckbox = $('#stackboost_use_sc_order');
    var $sortingButtons = $('#stackboost_utm_move_top, #stackboost_utm_move_up, #stackboost_utm_move_down, #stackboost_utm_move_bottom');
	var $enableCheckbox = $('#stackboost_utm_enabled');

    // Function to toggle the sorting buttons
    function toggleSortingButtons() {
        var isChecked = $useSCOrderCheckbox.is(':checked');
        $sortingButtons.prop('disabled', isChecked);
    }

    // Initial state on page load
    toggleSortingButtons();

    // Toggle on checkbox change
    $useSCOrderCheckbox.on('change', function() {
        toggleSortingButtons();
    });

    // Move Up
    $('#stackboost_utm_move_up').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            var $first = $selected.first();
            var $before = $first.prev();
            if ($before.length) {
                $selected.insertBefore($before);
            }
        }
    });

    // Move Down
    $('#stackboost_utm_move_down').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            var $last = $selected.last();
            var $after = $last.next();
            if ($after.length) {
                $selected.insertAfter($after);
            }
        }
    });

    // Move to Top
    $('#stackboost_utm_move_top').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            $('#stackboost_utm_selected_fields').prepend($selected);
        }
    });

    // Move to Bottom
    $('#stackboost_utm_move_bottom').on('click', function() {
        var $selected = $('#stackboost_utm_selected_fields option:selected');
        if ($selected.length) {
            $('#stackboost_utm_selected_fields').append($selected);
        }
    });

    // Add selected
    $('#stackboost_utm_add').click(function() {
        $('#stackboost_utm_available_fields option:selected').appendTo('#stackboost_utm_selected_fields');
    });

    // Remove selected
    $('#stackboost_utm_remove').click(function() {
        $('#stackboost_utm_selected_fields option:selected').appendTo('#stackboost_utm_available_fields');
    });

    // Add all
    $('#stackboost_utm_add_all').click(function() {
        $('#stackboost_utm_available_fields option').appendTo('#stackboost_utm_selected_fields');
    });

    // Remove all
    $('#stackboost_utm_remove_all').click(function() {
        $('#stackboost_utm_selected_fields option').appendTo('#stackboost_utm_available_fields');
    });

	// Add Rule
	$('#stackboost-utm-add-rule').on('click', function() {
		var container = $('#stackboost-utm-rules-container');
		var newIndex = container.find('.stackboost-utm-rule-row').length;
		var template = $('#stackboost-utm-rule-template').html();

		// Replace placeholder index with the new index
		template = template.replace(/__INDEX__/g, newIndex);

		container.append(template);
	});


    // Remove Rule (using event delegation)
    $('#stackboost-utm-rules-container').on('click', '.stackboost-utm-remove-rule', function() {
        $(this).closest('.stackboost-utm-rule-row').remove();
    });

	// On form submission, select all options in the "Selected Fields" list so they get submitted.
	$('form[action="options.php"]').on('submit', function() {
		$('#stackboost_utm_selected_fields option').prop('selected', true);
	});
});
