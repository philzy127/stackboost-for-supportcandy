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

    // --- Form Submission ---
    $('form[action="options.php"]').on('submit', function() {
        // Select all options in the "Selected Fields" list so they get submitted
        $selectedList.find('option').prop('selected', true);
    });

});
