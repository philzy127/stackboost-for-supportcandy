jQuery(document).ready(function($) {

    // Check if our localized settings object exists.
    if (typeof stackboostWidgetSettings === 'undefined') {
        return;
    }

    const myAddonSettings = {
        targetWidgetSlug: stackboostWidgetSettings.targetWidget,
        position: stackboostWidgetSettings.position,
        widgetId: '#stackboost-directory-pseudo-widget'
    };

    function positionMyWidget() {
        const myWidget = $(myAddonSettings.widgetId);
        if (!myWidget.length) return false;

        const targetSelector = '.wpsc-itw-' + myAddonSettings.targetWidgetSlug;
        const targetWidgets = $(targetSelector);

        if (targetWidgets.length > 0) {
            // Move the widget into position relative to each target.
            // SupportCandy duplicates the widget area for mobile, so we may have multiple targets.
            targetWidgets.each(function() {
                const myWidgetClone = myWidget.clone().show(); // Clone for each instance
                if (myAddonSettings.position === 'before') {
                    myWidgetClone.insertBefore($(this));
                } else {
                    myWidgetClone.insertAfter($(this));
                }
            });

            myWidget.remove(); // Remove the original hidden widget
            return true;
        }
        return false;
    }

    // Use a MutationObserver to wait for SupportCandy's AJAX content to load.
    const mainContentArea = document.querySelector('#wpsc-container') || document.body;

    const observer = new MutationObserver(function(mutations, obs) {
        // When the DOM changes, try to position our widget.
        if (positionMyWidget()) {
            obs.disconnect(); // Stop observing once the widget is placed.
        }
    });

    observer.observe(mainContentArea, {
        childList: true,
        subtree: true
    });
});