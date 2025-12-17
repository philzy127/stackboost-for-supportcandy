jQuery(document).ready(function($) {
    var wrapper = $('#stackboost-uninstall-wrapper');
    if (!wrapper.length) return;

    // Elements
    var authBtn = $('#stackboost-authorize-uninstall-btn');
    var cancelBtn = $('#stackboost-cancel-uninstall-btn');
    var modal = $('#stackboost-uninstall-modal');
    var closeModalBtns = $('.stackboost-modal-close-button, .stackboost-modal-close-btn');
    var confirmAuthBtn = $('#stackboost-confirm-uninstall-auth');
    var timerSpan = $('#stackboost-uninstall-timer');
    var statusText = wrapper.find('.status-text');
    var timerInterval;

    // --- Modal Logic ---

    authBtn.on('click', function() {
        modal.show();
    });

    closeModalBtns.on('click', function() {
        modal.hide();
    });

    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });

    // --- Authorization Logic ---

    confirmAuthBtn.on('click', function() {
        // Disable button to prevent double clicks
        $(this).prop('disabled', true).text('Authorizing...');

        $.post(ajaxurl, {
            action: 'stackboost_authorize_uninstall',
            nonce: stackboost_admin.nonce
        }, function(response) {
            modal.hide();
            confirmAuthBtn.prop('disabled', false).text('Yes, Authorize Removal');

            if (response.success) {
                activateAuthorizedMode(300); // 5 minutes
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        });
    });

    cancelBtn.on('click', function() {
        if (!confirm('Cancel the data removal authorization?')) return;

        $(this).prop('disabled', true).text('Cancelling...');

        $.post(ajaxurl, {
            action: 'stackboost_cancel_uninstall',
            nonce: stackboost_admin.nonce
        }, function(response) {
            cancelBtn.prop('disabled', false).text('Cancel Authorization');
            if (response.success) {
                deactivateAuthorizedMode();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        });
    });

    // --- State Management ---

    function activateAuthorizedMode(secondsRemaining) {
        wrapper.attr('data-authorized', '1');
        wrapper.removeClass('safe-mode').addClass('authorized-mode');

        // Update UI
        authBtn.hide();
        cancelBtn.show();
        statusText.text('AUTHORIZED FOR REMOVAL');

        // Show timer area if hidden
        if (wrapper.find('.stackboost-uninstall-timer-warning').length === 0) {
            $('<div class="stackboost-uninstall-timer-warning"><p>This authorization expires in: <span id="stackboost-uninstall-timer"></span></p><p class="warning-text">Go to the Plugins page and delete StackBoost now to wipe all data.</p></div>').insertAfter(wrapper.find('.description'));
            timerSpan = $('#stackboost-uninstall-timer');
        }

        startTimer(secondsRemaining);
    }

    function deactivateAuthorizedMode() {
        wrapper.attr('data-authorized', '0');
        wrapper.removeClass('authorized-mode').addClass('safe-mode');

        // Update UI
        authBtn.show();
        cancelBtn.hide();
        statusText.text('Standard Uninstall (Safe)');
        wrapper.find('.stackboost-uninstall-timer-warning').remove();

        clearInterval(timerInterval);
    }

    // --- Timer Logic ---

    function startTimer(duration) {
        var timer = duration, minutes, seconds;

        // Clear existing interval
        clearInterval(timerInterval);

        function updateDisplay() {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            timerSpan.text(minutes + ":" + seconds);

            if (--timer < 0) {
                // Timer expired
                deactivateAuthorizedMode();
                // Optionally verify with server that it's actually expired
            }
        }

        updateDisplay();
        timerInterval = setInterval(updateDisplay, 1000);
    }

    // --- Initial State Check ---

    var initialAuthorized = wrapper.data('authorized') == '1';
    var initialRemaining = parseInt(wrapper.data('remaining'), 10);

    if (initialAuthorized && initialRemaining > 0) {
        startTimer(initialRemaining);
    } else if (initialAuthorized && initialRemaining <= 0) {
        // Expired on load
        deactivateAuthorizedMode();
    }
});
