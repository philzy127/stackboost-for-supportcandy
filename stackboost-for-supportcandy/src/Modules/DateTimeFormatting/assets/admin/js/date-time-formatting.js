(function ($) {
  "use strict";

  $(function () {
    window.stackboost_log("[StackBoost] Date & Time Formatting script loaded.");

    /**
     * Handles the dynamic behavior of the date format rule builder.
     */
    function initializeDateFormatRuleBuilder() {
      function toggleDateOptions($rule) {
        var formatType = $rule.find(".stackboost-date-format-type").val();
        var $bottomRow = $rule.find(".stackboost-date-rule-row-bottom");
        var $customFormatWrapper = $rule.find(".stackboost-custom-format-wrapper");

        // Handle visibility of the custom format input.
        if (formatType === "custom") {
          $customFormatWrapper.show();
        } else {
          $customFormatWrapper.hide();
        }

        // Handle visibility of the checkboxes row.
        if (formatType === "date_only" || formatType === "date_and_time") {
          $bottomRow.show();
        } else {
          $bottomRow.hide();
          // Uncheck the checkboxes when they are hidden to prevent saving unwanted values.
          $bottomRow.find('input[type="checkbox"]').prop("checked", false);
        }
      }

      // Initial setup on page load.
      $(".stackboost-date-rule-wrapper").each(function () {
        toggleDateOptions($(this));
      });

      // Handle change event for the format type dropdown.
      $("#stackboost-date-rules-container").on(
        "change",
        ".stackboost-date-format-type",
        function () {
          toggleDateOptions($(this).closest(".stackboost-date-rule-wrapper"));
        }
      );

      // Add a new rule.
      $("#stackboost-add-date-rule").on("click", function () {
        var ruleTemplate = $("#stackboost-date-rule-template").html();
        var newIndex = new Date().getTime(); // Use a timestamp for a unique index.
        var newRule = ruleTemplate.replace(/__INDEX__/g, newIndex);
        $("#stackboost-no-date-rules-message").hide();
        $("#stackboost-date-rules-container").append(newRule);
      });

      // Remove a rule.
      $("#stackboost-date-rules-container").on(
        "click",
        ".stackboost-remove-date-rule",
        function () {
          $(this).closest(".stackboost-date-rule-wrapper").remove();
          if ($("#stackboost-date-rules-container .stackboost-date-rule-wrapper").length === 0) {
            $("#stackboost-no-date-rules-message").show();
          }
        }
      );
    }

    // Initialize the rule builder if we are on the correct page.
    if ($("#stackboost-date-rules-container").length) {
      window.stackboost_log("[StackBoost] Date & Time settings container found. Initializing...");

      // Critical Dependency Check
      if (typeof stackboost_admin_ajax === 'undefined') {
          console.error("[StackBoost] CRITICAL ERROR: stackboost_admin_ajax is undefined. Nonce missing. Form save will fail.");
          // We can try to alert the user, or just disable the form.
          // For diagnostics, alert is annoying but effective.
          // Let's rely on console for now unless we are sure it's failing silently.
      } else {
          window.stackboost_log("[StackBoost] stackboost_admin_ajax is defined. Nonce available.");
      }

      initializeDateFormatRuleBuilder();

      // Intercept the form submission for AJAX saving
      $('form[action="options.php"]').on('submit', function (e) {
          e.preventDefault();
          window.stackboost_log("[StackBoost] Form submission intercepted.");

          var $form = $(this);
          var $submitButton = $form.find('input[type="submit"], button[type="submit"]');
          var originalButtonText = $submitButton.val() || $submitButton.text();

          // Check dependency again at submit time
          if (typeof stackboost_admin_ajax === 'undefined' || !stackboost_admin_ajax.nonce) {
              console.error("[StackBoost] Cannot save: stackboost_admin_ajax.nonce is missing.");
              alert("Error: Security token missing. Please reload the page. If the issue persists, check the console.");
              return;
          }

          // Disable button and change text
          $submitButton.prop('disabled', true).val('Saving...');
          if ($submitButton.is('button')) {
              $submitButton.text('Saving...');
          }

          var formData = $form.serialize();

          // Append action and nonce for CUSTOM AJAX handler
          formData += '&action=stackboost_save_date_time_settings';
          formData += '&nonce=' + stackboost_admin_ajax.nonce;

          window.stackboost_log("[StackBoost] Serialized Form Data:", formData);
          window.stackboost_log("[StackBoost] Sending AJAX request to:", stackboost_admin_ajax.ajax_url);

          $.post(stackboost_admin_ajax.ajax_url, formData, function (response) {
              window.stackboost_log("[StackBoost] AJAX Response:", response);
              if (response.success) {
                  window.stackboost_show_toast(response.data, 'success');
              } else {
                  if (typeof stackboostAlert === 'function') {
                      stackboostAlert('Error: ' + (response.data || 'Unknown error'), 'Error');
                  } else {
                      alert('Error: ' + (response.data || 'Unknown error'));
                  }
              }
          }).fail(function (xhr, status, error) {
              console.error("[StackBoost] AJAX Fail:", status, error);
              console.error(xhr.responseText);
              if (typeof stackboostAlert === 'function') {
                  stackboostAlert('An unexpected error occurred. Check console for details.', 'Error');
              } else {
                  alert('An unexpected error occurred. Check console for details.');
              }
          }).always(function () {
              // Restore button state
              $submitButton.prop('disabled', false).val(originalButtonText);
              if ($submitButton.is('button')) {
                  $submitButton.text(originalButtonText);
              }
          });
      });
    }
  });
})(jQuery);
