jQuery(document).ready(function($) {
    const $modal = $('#stackboost-ats-heading-modal');
    if (!$modal.length) {
        return;
    }

    const $form = $('#stackboost-ats-heading-form');
    const $openBtn = $('#stackboost-ats-open-headings-modal');
    const $closeModal = $modal.find('.close-modal');

    if ($openBtn.length) {
        $openBtn.on('click', function() {
            $modal.show();
        });
    }

    $closeModal.on('click', function() {
        $modal.hide();
    });

    $(window).on('click', function(event) {
        if (event.target === $modal[0]) {
            $modal.hide();
        }
    });

    $form.on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'stackboost_ats_update_report_headings');
        formData.append('nonce', stackboost_ats_modal_ajax.nonce);

        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Saving...');

        fetch(stackboost_ats_modal_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                $modal.hide();

                // Update the headings in the table
                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('report_headings[')) {
                        const questionId = key.match(/\[(\d+)\]/)[1];
                        const $headingElement = $(`.stackboost-ats-report-heading-${questionId}`);
                        if ($headingElement.length) {
                            // If value is empty, fallback to placeholder (which is the question text)
                            const $inputElem = $(`#report_heading_${questionId}`);
                            $headingElement.text(value.trim() !== '' ? value.trim() : $inputElem.attr('placeholder'));
                        }
                    }
                }

                // Show toast notification
                const $toast = $('<div id="stackboost-ats-toast" class="show">Headings updated successfully!</div>');
                $('body').append($toast);
                setTimeout(() => {
                    $toast.removeClass('show');
                    setTimeout(() => $toast.remove(), 300); // Allow fadeout transition if any
                }, 3000);

            } else {
                alert('Error: ' + result.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        })
        .finally(() => {
            $submitBtn.prop('disabled', false).text(originalText);
        });
    });
});
