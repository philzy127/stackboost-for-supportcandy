/*
 * File: js/onboarding-dashboard.js
 * Handles the frontend logic for the onboarding dashboard, including
 * checklist functionality, local storage management, navigation, and
 * the new completion stage as a virtual step.
 */

jQuery(document).ready(function($) {

    // --- Pre-Onboarding View Logic ---
    const $preSessionView = $('.onboarding-pre-session-view');
    if ($preSessionView.length) {
        // We are in the pre-onboarding view.

        // 1. Populate Attendee List
        const $attendeesPreview = $('#attendees-list-preview');
        if ($attendeesPreview.length) {
            if (odbDashboardVars && typeof odbDashboardVars.thisWeekAttendees !== 'undefined') {
                if (odbDashboardVars.thisWeekAttendees.length > 0) {
                    let attendeesList = '<ul style="list-style: none; padding: 0;">';
                    odbDashboardVars.thisWeekAttendees.forEach(function(attendee) {
                        attendeesList += '<li style="padding: 4px 0;">' + attendee.name + '</li>';
                    });
                    attendeesList += '</ul>';
                    $attendeesPreview.html(attendeesList);
                } else {
                    $attendeesPreview.html('<p>No attendees scheduled for today.</p>');
                }
            } else {
                $attendeesPreview.html('<p style="color: red;">Error: Could not load attendee data.</p>');
            }
        }

        // 2. Reset Button Functionality
        $('#reset-checkboxes').on('click', function(e) {
            e.preventDefault();

            if (confirm('This will clear all saved progress for ALL onboarding steps. Are you sure?')) {
                let keysToRemove = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith('odb_checklist_step_')) {
                        keysToRemove.push(key);
                    }
                }
                keysToRemove.forEach(key => localStorage.removeItem(key));

                const $button = $(this);
                const originalText = $button.text();
                $button.text('Progress Reset!').css('background-color', '#27ae60').prop('disabled', true);
                setTimeout(function() {
                    $button.text(originalText).css('background-color', '').prop('disabled', false);
                }, 2500);
            }
        });

        return; // Stop further execution, as we are not in a step view.
    }
    // --- End Pre-Onboarding View Logic ---

    // Basic check to ensure localization data is available.
    if (typeof odbDashboardVars === 'undefined' || !odbDashboardVars.fullSequence || odbDashboardVars.fullSequence.length === 0) {
        console.warn('ODB: Localization data (odbDashboardVars) is missing or incomplete. Checklist and navigation may not function correctly.');
        return;
    }

    const currentStepId = odbDashboardVars.currentStepId;
    const currentStepIndex = odbDashboardVars.currentStepIndex;
    const completionStepId = odbDashboardVars.completionStepId; // Get the virtual completion step ID
    const isCurrentlyOnCompletionStage = (currentStepId === completionStepId);

    const $nextButton = $('.onboarding-next-button');
    const $checklistContainer = $('.onboarding-checklist');
    const $backButton = $('.onboarding-back-button');
    const $mainStepContent = $('.onboarding-main-step-content'); // Select the main content wrapper (includes CPT content, checklist, notes)
    const $completionStage = $('.onboarding-completion-stage'); // Select the completion stage container
    const $attendeesSelection = $('#onboarding-attendees-selection'); // Select attendees container
    const $sendCertificatesButton = $('#send-certificates-button'); // Select send certificates button
    const $completionStatusMessage = $('.onboarding-completion-status-message'); // Select status message
    const $navigationContainer = $('.onboarding-navigation'); // Select navigation container


    // --- Initial Display Logic based on current step ---
    if (isCurrentlyOnCompletionStage) {
        // If we are on the completion stage, the PHP has rendered its HTML.
        // We ensure regular step content is hidden and completion stage is explicitly shown.
        $mainStepContent.hide(); // Hide the step-specific content sections
        $completionStage.removeClass('onboarding-completion-stage--hidden').show(); // Ensure it's visible by removing class and showing
        $navigationContainer.show(); // Ensure navigation buttons are shown for the completion page

        populateAttendeesSelection(odbDashboardVars.thisWeekAttendees);

        // Adjust back/next button visibility for the completion stage
        $backButton.show(); // Always show back button on completion stage
        // The $nextButton with class 'onboarding-next-button' is NOT rendered on the completion stage by PHP.
        // So, we don't need to explicitly hide it here.

    } else {
        // If we are on a regular onboarding step, PHP has rendered main step content.
        // We need to explicitly hide the completion stage.
        $completionStage.addClass('onboarding-completion-stage--hidden').hide(); // Ensure it's hidden by adding class and hiding
        $mainStepContent.show(); // Show step-specific content
        $navigationContainer.show(); // Ensure navigation buttons are shown

        $backButton.show();
        $nextButton.show(); // Always show the next button for regular steps
    }
    // --- End Initial Display Logic ---


    /**
     * Loads the checklist item status for the current step from local storage.
     * Applies 'completed' class and checks checkboxes based on saved status.
     */
    function loadChecklistStatus() {
        // Only load checklist status if it's a regular step, not the completion stage
        if (isCurrentlyOnCompletionStage || !currentStepId || !$checklistContainer.length) {
            console.log('ODB: Skipping loadChecklistStatus. On completion stage, no currentStepId, or no checklist container.');
            return;
        }
        const localStorageKey = `odb_checklist_step_${currentStepId}`;
        const savedStatus = JSON.parse(localStorage.getItem(localStorageKey)) || {};
        console.log(`ODB: Loading checklist status for step ID '${currentStepId}' from key '${localStorageKey}'. Status found:`, savedStatus);


        $checklistContainer.find('li').each(function() {
            const itemText = $(this).find('label span').text().trim();
            if (savedStatus[itemText]) {
                $(this).addClass('completed');
                $(this).find('input[type="checkbox"]').prop('checked', true);
            } else {
                $(this).removeClass('completed');
                $(this).find('input[type="checkbox"]').prop('checked', false);
            }
        });
        updateNextButtonState(); // Update button state after loading status
    }

    /**
     * Saves the current checklist item status for the current step to local storage.
     */
    function saveChecklistStatus() {
        // Only save checklist status if it's a regular step, not the completion stage
        if (isCurrentlyOnCompletionStage || !currentStepId || !$checklistContainer.length) {
            console.log('ODB: Skipping saveChecklistStatus. On completion stage, no currentStepId, or no checklist container.');
            return;
        }
        const savedStatus = {};
        $checklistContainer.find('li').each(function() {
            const itemText = $(this).find('label span').text().trim();
            savedStatus[itemText] = $(this).hasClass('completed');
        });
        const localStorageKey = `odb_checklist_step_${currentStepId}`;
        localStorage.setItem(localStorageKey, JSON.stringify(savedStatus));
        console.log(`ODB: Saved checklist status for step ID '${currentStepId}' to key '${localStorageKey}'.`, savedStatus);
    }

    /**
     * Updates the disabled state of the "Next Step" or "Final Step" button
     * based on the completion status of checklist items.
     */
    function updateNextButtonState() {
        // Only update button state if we are on a regular step
        if (isCurrentlyOnCompletionStage) {
            return; // Buttons on completion stage have different logic
        }

        // Count total items from the DOM to match the checked items count source
        const totalItems = $checklistContainer.find('li').length;
        const checkedItems = $checklistContainer.find('li.completed').length;

        // Determine if this is the last *real* step before the virtual completion step
        const isLastRealStep = (parseInt(currentStepIndex, 10) === odbDashboardVars.fullSequence.length - 2);

        if (isLastRealStep) { // If clicking the "Final Step" button
            if (totalItems > 0) {
                if (checkedItems === totalItems) {
                    $nextButton.prop('disabled', false); // Enable "Final Step" button
                } else {
                    $nextButton.prop('disabled', true); // Disable "Final Step" button
                }
            } else { // No checklist items, enable 'Final Step' button immediately
                $nextButton.prop('disabled', false);
            }
        } else { // Regular 'Next Step' button (not the last real step)
            if (totalItems > 0 && checkedItems === totalItems) {
                $nextButton.prop('disabled', false);
            } else if (totalItems === 0) { // No checklist items, enable 'Next' button
                $nextButton.prop('disabled', false);
            } else {
                $nextButton.prop('disabled', true);
            }
        }
    }

    /**
     * Renders the checklist items dynamically based on the localized data.
     * This function is mostly redundant now as PHP renders the checklist directly,
     * but it includes the tooltip logic and re-applies status.
     * Kept for potential future dynamic updates if needed.
     */
    function renderChecklist() {
        // The PHP output already generates the list items. We just need to load their state.
        loadChecklistStatus(); // Ensure status is loaded after the DOM is ready and list is present
    }

    /**
     * Populates the attendee selection area in the completion stage.
     * @param {Array} attendees An array of attendee objects ({id: ..., name: ...}).
     */
    function populateAttendeesSelection(attendees) {
        $attendeesSelection.empty(); // Clear "Loading attendees..." or previous content

        if (!attendees || attendees.length === 0) {
            $attendeesSelection.append('<p>No attendees found for this week\'s onboarding.</p>');
            $sendCertificatesButton.prop('disabled', true);
            return;
        }

        // Added margin-bottom to the container to space it from the button
        let html = '<div class="onboarding-attendees-list-container" style="margin-bottom: 20px;">';
        attendees.forEach(function(attendee) {
            // Added margin-bottom to each item for vertical spacing
            html += '<div class="onboarding-attendee-item" style="margin-bottom: 8px;">';
            html += '<label>';
            // Added margin-right to the checkbox for spacing from the name
            html += '<input type="checkbox" name="not_present_attendee" value="' + attendee.id + '" data-name="' + attendee.name + '" style="margin-right: 8px;">';
            html += '<span>' + attendee.name + '</span>'; // Only display name
            html += '</label>';
            html += '</div>';
        });
        html += '</div>';
        $attendeesSelection.append(html);

        $sendCertificatesButton.prop('disabled', false); // Enable the send certificates button

        // Add a change listener to the checkboxes in the attendee list
        $attendeesSelection.on('change', 'input[type="checkbox"]', function() {
            // This handler is currently empty, but kept for potential future logic.
            console.log(`ODB: Attendee checkbox changed for ID: ${$(this).val()}, Name: ${$(this).data('name')}, Checked: ${$(this).is(':checked')}`);
        });
    }

    // Event handler for checkbox changes within the checklist.
    $(document).on('change', '.onboarding-checklist input[type="checkbox"]', function() {
        $(this).closest('li').toggleClass('completed', this.checked);
        saveChecklistStatus(); // Save status whenever a checkbox changes.
        updateNextButtonState(); // Update button state after status changes.
    });

    // Handle Next/Final Step button click.
    $nextButton.on('click', function(e) {
        e.preventDefault();

        // Determine if this is the last *real* step (before the virtual completion step)
        const isLastRealStep = (parseInt(currentStepIndex, 10) === odbDashboardVars.fullSequence.length - 2);

        if (isLastRealStep) { // If clicking the "Final Step" button
            // Navigate to the virtual completion step URL
            const completionStepData = odbDashboardVars.fullSequence.find(step => step.id === completionStepId);
            if (completionStepData && completionStepData.permalink) {
                const correctedPermalink = completionStepData.permalink.replace(/&#038;/g, '&');
                window.location.href = correctedPermalink;
            } else {
                console.error('ODB: Completion step permalink not found or invalid.');
            }
        } else {
            // Logic for 'Next Step' button (regular navigation)
            const nextStepIndex = parseInt(currentStepIndex, 10) + 1;

            if (nextStepIndex < odbDashboardVars.fullSequence.length) {
                const nextStep = odbDashboardVars.fullSequence[nextStepIndex];

                if (nextStep && nextStep.permalink) {
                    const correctedPermalink = nextStep.permalink.replace(/&#038;/g, '&');
                    window.location.href = correctedPermalink;
                } else {
                    console.error('ODB: Next step permalink not found or invalid for step index:', nextStepIndex);
                }
            } else {
                console.warn('ODB: Attempted to navigate past the last available step.');
            }
        }
    });

    // Handle Back button click.
    $backButton.on('click', function(e) {
        e.preventDefault();

        if (isCurrentlyOnCompletionStage) {
            // If on the completion stage, go back to the last real step.
            const lastRealStepIndex = odbDashboardVars.fullSequence.length - 2;
            if (lastRealStepIndex >= 0) {
                const prevStep = odbDashboardVars.fullSequence[lastRealStepIndex];
                window.location.href = prevStep.permalink.replace(/&#038;/g, '&');
            } else {
                window.location.href = window.location.pathname;
            }
        } else {
            // If on a regular step, determine where to go.
            const prevStepIndex = parseInt(currentStepIndex, 10) - 1;
            if (prevStepIndex >= 0) {
                // If there's a previous step in the sequence, go to it.
                const prevStep = odbDashboardVars.fullSequence[prevStepIndex];
                if (prevStep && prevStep.permalink) {
                    const correctedPermalink = prevStep.permalink.replace(/&#038;/g, '&');
                    window.location.href = correctedPermalink;
                }
            } else {
                // If on the first step (index 0), go back to the pre-onboarding view.
                window.location.href = window.location.pathname;
            }
        }
    });

    // Handle "Send Completion Certificates" button click
    $sendCertificatesButton.on('click', function() {
        const notPresentAttendees = [];
        const presentAttendees = [];

        // Collect attendees based on checkbox state
        $('#onboarding-attendees-selection input[name="not_present_attendee"]').each(function() {
            if ($(this).is(':checked')) {
                notPresentAttendees.push({
                    id: $(this).val(),
                    name: $(this).data('name'),
                });
            } else {
                presentAttendees.push({
                    id: $(this).val(),
                    name: $(this).data('name'),
                });
            }
        });

        // Display status message
        $completionStatusMessage.text('Processing certificates...').removeClass('notice-error notice-success').css('display', 'block');
        $sendCertificatesButton.prop('disabled', true).text('Sending...');

        // Make AJAX call to WordPress backend
        $.ajax({
            url: odbDashboardVars.ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'stackboost_onboarding_send_certificates', // Our custom AJAX action
                nonce: odbDashboardVars.sendCertificatesNonce, // Security nonce
                present_attendees: JSON.stringify(presentAttendees),
                not_present_attendees: JSON.stringify(notPresentAttendees) // Send not present for logging/future use
            },
            success: function(response) {
                if (response.success) {
                    let successCount = 0;
                    let errorCount = 0;
                    let messages = [];

                    if (response.data.results && response.data.results.length > 0) {
                        response.data.results.forEach(function(result) {
                            if (result.status === 'success') {
                                successCount++;
                            } else {
                                errorCount++;
                            }
                            messages.push(`${result.attendee}: ${result.message}`);
                        });
                    }

                    // --- Open HTML Preview in a new tab if URL is provided ---
                    if (response.data.preview_url) {
                        window.open(response.data.preview_url, '_blank');
                    }
                    // --- End HTML Preview Logic ---

                    if (successCount > 0 && errorCount === 0) {
                        $completionStatusMessage.text(`Certificates sent for ${successCount} attendees!`).removeClass('notice-error').addClass('notice-success').fadeIn();
                    } else if (successCount > 0 && errorCount > 0) {
                        $completionStatusMessage.html(`Certificates sent for ${successCount} attendees, but ${errorCount} had errors.<br>${messages.join('<br>')}`).removeClass('notice-success').addClass('notice-error').fadeIn();
                    } else if (successCount === 0 && errorCount > 0) {
                        $completionStatusMessage.html(`Failed to send certificates for all attendees.<br>${messages.join('<br>')}`).removeClass('notice-success').addClass('notice-error').fadeIn();
                    } else {
                        // Handle case where no attendees were marked present, but the API returned success (e.g., just informational)
                        $completionStatusMessage.text(response.data.message || 'Certificate processing complete. No certificates sent as no attendees were marked present.').removeClass('notice-error').addClass('notice-success').fadeIn();
                    }

                    // Clear local storage for all steps after successful processing
                    let keysToRemove = [];
                    console.log('ODB: Attempting to clear all onboarding checklist local storage keys.');
                    for (let i = 0; i < localStorage.length; i++) {
                        const key = localStorage.key(i);
                        if (key && key.startsWith('odb_checklist_step_')) { // Added 'key' existence check
                            keysToRemove.push(key);
                        }
                    }
                    console.log('ODB: Keys identified for removal:', keysToRemove);
                    keysToRemove.forEach(key => {
                        localStorage.removeItem(key);
                        console.log(`ODB: Removed local storage key: ${key}`);
                    });

                    // --- IMPORTANT: To ensure checkboxes are visually cleared immediately if user navigates back,
                    // you might need to force a reload or re-evaluate checklist states.
                    // For now, relying on browser refresh/navigation to trigger loadChecklistStatus.
                    // If a user navigates back *without* a full page reload, the state might not update.
                    // A potential enhancement here would be to navigate to the first step of the dashboard
                    // (or a dashboard overview) after successful completion and clearing,
                    // to ensure a fresh load of the first step.
                    // window.location.href = odbDashboardVars.fullSequence[0].permalink.replace(/&#038;/g, '&');
                    // This is commented out as it changes navigation flow, but is a common pattern for "completion".

                } else {
                    console.error('ODB: AJAX response success was false:', response.data);
                    $completionStatusMessage.text('Error sending certificates: ' + (response.data || 'Unknown error.')).removeClass('notice-success').addClass('notice-error').fadeIn();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('ODB: AJAX request failed:', textStatus, errorThrown, jqXHR);
                $completionStatusMessage.text('AJAX request failed: ' + textStatus).removeClass('notice-success').addClass('notice-error').fadeIn();
            },
            complete: function() {
                $sendCertificatesButton.text('Send Completion Certificates').prop('disabled', false); // Always re-enable button
            }
        });
    });

    // Initialize the checklist rendering and status loading when the document is ready.
    // These only apply to actual onboarding steps, not the completion stage.
    if (!isCurrentlyOnCompletionStage) {
        renderChecklist();
        // loadChecklistStatus(); // renderChecklist already calls loadChecklistStatus
    }

    // Initial update of button state on page load for regular steps.
    // If on completion stage, button states are set by the initial display logic.
    // No redundant hide call at the end of document.ready.
    if (!isCurrentlyOnCompletionStage) {
        updateNextButtonState();
    }
});