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

    if ($addRuleBtn.length) {
        $addRuleBtn.on('click', function() {
            var newIndex = new Date().getTime();
            var rowHtml = ruleTemplate.replace(/__INDEX__/g, newIndex);
            $rulesContainer.append(rowHtml);
        });

        $rulesContainer.on('click', '.stackboost-odb-remove-rule', function() {
            $(this).closest('.stackboost-odb-rule-row').remove();
        });
    }

    // --- Phone Configuration Logic ---

    // 1. Single vs Multiple Mode
    var $phoneModeSelect = $('#stkb_phone_mode');
    function togglePhoneMode() {
        var mode = $phoneModeSelect.val();
        $('.stkb-phone-logic-container').hide();
        if (mode === 'single') {
            $('#stkb_phone_single_container').show();
        } else if (mode === 'multiple') {
            $('#stkb_phone_multi_container').show();
        }
    }
    if ($phoneModeSelect.length) {
        $phoneModeSelect.on('change', togglePhoneMode);
        togglePhoneMode(); // Init
    }

    // 2. Single Mode: Type Field Toggle
    var $phoneHasTypeSelect = $('#stkb_phone_has_type');
    function togglePhoneTypeLogic() {
        if ($phoneHasTypeSelect.val() === 'yes') {
            $('.stkb-phone-type-logic').show();
        } else {
            $('.stkb-phone-type-logic').hide();
        }
    }
    if ($phoneHasTypeSelect.length) {
        $phoneHasTypeSelect.on('change', togglePhoneTypeLogic);
        togglePhoneTypeLogic(); // Init
    }

    // 3. Multiple Mode: Dynamic List
    var $multiPhoneList = $('#stkb-phone-multi-list');
    var $addPhoneRowBtn = $('#stkb-add-phone-row');
    var phoneRowTemplate = $('#stkb-phone-multi-template').html();

    if ($addPhoneRowBtn.length && phoneRowTemplate) {
        $addPhoneRowBtn.on('click', function() {
            var newIndex = new Date().getTime();
            var rowHtml = phoneRowTemplate.replace(/__INDEX__/g, newIndex);
            $multiPhoneList.append(rowHtml);
        });

        $multiPhoneList.on('click', '.stkb-remove-phone-row', function() {
            $(this).closest('.stkb-phone-multi-row').remove();
        });
    }


    // --- AJAX Field Logic ---
    function loadFieldOptions($fieldSelector, $optionSelector) {
        var fieldSlug = $fieldSelector.val();
        var rawSelected = $optionSelector.data('selected');
        var selectedOptions = [];

        // Normalize selected data to array
        if (Array.isArray(rawSelected)) {
            selectedOptions = rawSelected;
        } else if (rawSelected !== undefined && rawSelected !== null && rawSelected !== '') {
            selectedOptions = [rawSelected];
        }

        // Ensure all are strings for comparison
        selectedOptions = selectedOptions.map(String);

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
            console.log('[StackBoost] Field Options Loaded:', response);
            if (response.success) {
                var optionsHtml = '';
                $.each(response.data, function(index, item) {
                    var isSelected = ($.inArray(String(item.id), selectedOptions) !== -1) ? 'selected' : '';
                    optionsHtml += '<option value="' + item.id + '" ' + isSelected + '>' + item.name + '</option>';
                });

                // Destroy existing Select2 instance if it exists to allow clean re-init
                if ($optionSelector.data('select2')) {
                    console.log('[StackBoost] Destroying existing select2 instance');
                    $optionSelector.select2('destroy');
                }

                $optionSelector.html(optionsHtml).prop('disabled', false);

                // Initialize SelectWoo/Select2 if available and the element has the class
                // SelectWoo aliases itself as select2, but we check both to be safe
                var select2Func = $.fn.selectWoo || $.fn.select2;

                if (select2Func) {
                    if ($optionSelector.hasClass('stackboost-selectwoo')) {
                        console.log('[StackBoost] Initializing SelectWoo on', $optionSelector);
                        select2Func.call($optionSelector, {
                            width: '100%',
                            placeholder: stackboost_admin_ajax.i18n_select_option || 'Select Options'
                        });
                    } else {
                        console.warn('[StackBoost] Element missing .stackboost-selectwoo class');
                    }
                } else {
                    console.error('[StackBoost] SelectWoo/Select2 library NOT found');
                }
            } else {
                // Destroy existing Select2 instance before showing error
                if ($optionSelector.data('select2')) {
                    $optionSelector.select2('destroy');
                }
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

    // --- Initialize SelectWoo on Static Fields ---
    var select2Func = $.fn.selectWoo || $.fn.select2;
    if (select2Func) {
        // Initialize any selectwoo fields that are NOT the dynamic one (stkb_req_id)
        // (stkb_req_id is handled in loadFieldOptions after AJAX)
        $('.stackboost-selectwoo').not('#stkb_req_id').each(function() {
            select2Func.call($(this), {
                width: '100%',
                placeholder: stackboost_admin_ajax.i18n_select_option || 'Select Options'
            });
        });
    }

    // --- Form Submission ---
    $('form[action="options.php"]').on('submit', function() {
        // Select all options in the "Selected Fields" list so they get submitted
        $selectedList.find('option').prop('selected', true);
    });

});
