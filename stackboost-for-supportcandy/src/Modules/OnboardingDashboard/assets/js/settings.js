jQuery(document).ready(function($) {

    // --- Dual List Selector Logic ---
    var $addBtn = $('#stackboost_odb_add');
    var $removeBtn = $('#stackboost_odb_remove');
    var $addAllBtn = $('#stackboost_odb_add_all');
    var $removeAllBtn = $('#stackboost_odb_remove_all');
    var $moveUpBtn = $('#stackboost_odb_move_up');
    var $moveDownBtn = $('#stackboost_odb_move_down');
    var $moveTopBtn = $('#stackboost_odb_move_top');
    var $moveBottomBtn = $('#stackboost_odb_move_bottom');
    var $availableList = $('#stackboost_odb_available_fields');
    var $selectedList = $('#stackboost_odb_selected_fields');

    // Add selected
    $addBtn.click(function() {
        $('#stackboost_odb_available_fields option:selected').appendTo($selectedList);
    });

    // Remove selected
    $removeBtn.click(function() {
        $('#stackboost_odb_selected_fields option:selected').appendTo($availableList);
    });

    // Add all
    $addAllBtn.click(function() {
        $('#stackboost_odb_available_fields option').appendTo($selectedList);
    });

    // Remove all
    $removeAllBtn.click(function() {
        $('#stackboost_odb_selected_fields option').appendTo($availableList);
    });

    // Move Up
    $moveUpBtn.click(function() {
        var $selected = $('#stackboost_odb_selected_fields option:selected');
        if ($selected.length) {
            var $first = $selected.first();
            var $before = $first.prev();
            if ($before.length) {
                $selected.insertBefore($before);
            }
        }
    });

    // Move Down
    $moveDownBtn.click(function() {
        var $selected = $('#stackboost_odb_selected_fields option:selected');
        if ($selected.length) {
            var $last = $selected.last();
            var $after = $last.next();
            if ($after.length) {
                $selected.insertAfter($after);
            }
        }
    });

    // Move to Top
    $moveTopBtn.click(function() {
        var $selected = $('#stackboost_odb_selected_fields option:selected');
        if ($selected.length) {
            $selectedList.prepend($selected);
        }
    });

    // Move to Bottom
    $moveBottomBtn.click(function() {
        var $selected = $('#stackboost_odb_selected_fields option:selected');
        if ($selected.length) {
            $selectedList.append($selected);
        }
    });

    // --- Renaming Rules Logic ---
    var $rulesContainer = $('#stackboost-odb-rules-container');
    var $addRuleBtn = $('#stackboost-odb-add-rule');
    var ruleTemplate = $('#stackboost-odb-rule-template').html();

    $addRuleBtn.on('click', function() {
        // Use timestamp to avoid index collisions if rows are deleted
        var newIndex = new Date().getTime();
        var rowHtml = ruleTemplate.replace(/__INDEX__/g, newIndex);
        $rulesContainer.append(rowHtml);
    });

    // Remove Rule (event delegation)
    $rulesContainer.on('click', '.stackboost-odb-remove-rule', function() {
        $(this).closest('.stackboost-odb-rule-row').remove();
    });

    // --- AJAX Field Logic ---
    function loadFieldOptions($fieldSelector, $optionSelector) {
        var fieldSlug = $fieldSelector.val();
        var selectedOption = $optionSelector.data('selected');

        if (!fieldSlug) {
            $optionSelector.prop('disabled', true).html('<option value="">' + stackboost_admin_ajax.i18n_select_option + '</option>');
            return;
        }

        $optionSelector.prop('disabled', true).html('<option value="">' + stackboost_admin_ajax.i18n_loading + '</option>');

        $.post(stackboost_admin_ajax.ajax_url, {
            action: 'stackboost_onboarding_get_field_options',
            nonce: stackboost_admin_ajax.nonce,
            field_slug: fieldSlug
        }, function(response) {
            if (response.success) {
                var optionsHtml = '<option value="">' + stackboost_admin_ajax.i18n_select_option + '</option>';
                $.each(response.data, function(index, item) {
                    var isSelected = (item.id == selectedOption) ? 'selected' : '';
                    optionsHtml += '<option value="' + item.id + '" ' + isSelected + '>' + item.name + '</option>';
                });
                $optionSelector.html(optionsHtml).prop('disabled', false);
            } else {
                $optionSelector.html('<option value="">' + response.data + '</option>'); // Error message
            }
        });
    }

    // Attach listeners to AJAX fields
    $('.stackboost-ajax-field-selector').on('change', function() {
        var $this = $(this);
        var targetId = $this.data('target');
        var $target = $(targetId);
        // Reset selected data attribute on change so we don't re-select old value
        $target.data('selected', '');
        loadFieldOptions($this, $target);
    });

    // Initialize AJAX fields on load
    $('.stackboost-ajax-field-selector').each(function() {
        var $this = $(this);
        var targetId = $this.data('target');
        var $target = $(targetId);
        if ($this.val()) {
            loadFieldOptions($this, $target);
        }
    });

    // --- Mobile Logic Mode Switcher ---
    var $mobileModeSelector = $('#stkb_mobile_logic_mode');
    function toggleMobileSections() {
        var mode = $mobileModeSelector.val();
        $('.stkb-mobile-logic-container').hide();
        if (mode === 'separate_field') {
            $('#stkb_mobile_separate_field_container').show();
        } else if (mode === 'indicator_field') {
            $('#stkb_mobile_indicator_field_container').show();
        }
    }
    $mobileModeSelector.on('change', toggleMobileSections);
    toggleMobileSections(); // Init on load

    // --- Form Submission ---
    $('form[action="options.php"]').on('submit', function() {
        // Select all options in the "Selected Fields" list so they get submitted
        $selectedList.find('option').prop('selected', true);
    });

});
