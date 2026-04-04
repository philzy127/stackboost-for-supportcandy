document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('stackboost-ats-heading-modal');
    if (!modal) {
        return;
    }
    const closeModal = modal.querySelector('.close-modal');
    const form = document.getElementById('stackboost-ats-heading-form');
    const openBtn = document.getElementById('stackboost-ats-open-headings-modal');

    if (openBtn) {
        openBtn.addEventListener('click', function() {
            modal.style.display = 'block';
        });
    }

    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'stackboost_ats_update_report_headings');
        formData.append('nonce', stackboost_ats_modal_ajax.nonce);

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        fetch(stackboost_ats_modal_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                modal.style.display = 'none';

                // Update the headings in the table
                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('report_headings[')) {
                        const questionId = key.match(/\[(\d+)\]/)[1];
                        const headingElement = document.querySelector(`.stackboost-ats-report-heading-${questionId}`);
                        if (headingElement) {
                            // If value is empty, fallback to placeholder (which is the question text)
                            const inputElem = document.getElementById(`report_heading_${questionId}`);
                            headingElement.textContent = value.trim() !== '' ? value.trim() : inputElem.placeholder;
                        }
                    }
                }

                // Show toast notification
                const toast = document.createElement('div');
                toast.id = 'stackboost-ats-toast';
                toast.className = 'show';
                toast.textContent = 'Headings updated successfully!';
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.className = toast.className.replace('show', '');
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
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
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
});
