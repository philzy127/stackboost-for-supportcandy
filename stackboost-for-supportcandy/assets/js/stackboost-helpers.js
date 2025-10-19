/**
 * StackBoost Global Helpers
 *
 * This file contains reusable helper functions for the StackBoost plugin.
 */
var StackBoost = StackBoost || {};

(function($) {
    'use strict';

    StackBoost.helpers = {
        /**
         * Initializes a Select2 field with our standard settings.
         *
         * This acts as a centralized manager for all Select2 fields, ensuring
         * they have a consistent look and feel and are easy to maintain.
         *
         * @param {jQuery} $element - The jQuery object for the <select> element.
         * @param {object} customOptions - Any specific options to override the defaults.
         */
        initializeSelect2: function($element, customOptions) {

            // Define our standard, default options for all Select2 fields
            var defaultOptions = {
                theme: 'default',
                width: '100%',
                placeholder: 'Select an option...'
            };

            // Merge the defaults with any custom options for this specific instance
            var finalOptions = $.extend({}, defaultOptions, customOptions);

            // Initialize Select2
            $element.select2(finalOptions);
        }
    };

})(jQuery);