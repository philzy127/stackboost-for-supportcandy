document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('stackboost-ats-heading-modal');
    if (!modal) {
        return;
    }
    const closeModal = modal.querySelector('.close-modal');
    const questionTextSpan = document.getElementById('stackboost-ats-modal-question-text');
    const questionIdInput = document.getElementById('stackboost-ats-modal-question-id');
    const reportHeadingInput = document.getElementById('stackboost-ats-modal-report-heading');
    const form = document.getElementById('stackboost-ats-heading-form');

    document.querySelectorAll('.stackboost-ats-edit-heading').forEach(editIcon => {
        editIcon.addEventListener('click', function() {
            const questionId = this.dataset.questionId;
            const questionText = this.dataset.questionText;
            const reportHeading = this.dataset.reportHeading;

            questionIdInput.value = questionId;
            questionTextSpan.textContent = questionText;
            reportHeadingInput.value = reportHeading;
            modal.style.display = 'block';
        });
    });

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

        const questionId = questionIdInput.value;
        const newHeading = reportHeadingInput.value;

        const formData = new FormData(this);
        formData.append('action', 'stackboost_ats_update_report_heading');
        formData.append('nonce', stackboost_ats_modal_ajax.nonce);

        fetch(stackboost_ats_modal_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                modal.style.display = 'none';

                // Update the heading in the table
                const headingElement = document.querySelector(`.stackboost-ats-edit-heading[data-question-id="${questionId}"]`).previousSibling;
                headingElement.textContent = newHeading.trim() + ' '; // Add space to separate from icon

                // Update the data attribute on the icon
                const iconElement = document.querySelector(`.stackboost-ats-edit-heading[data-question-id="${questionId}"]`);
                iconElement.dataset.reportHeading = newHeading;

                // Show toast notification
                const toast = document.createElement('div');
                toast.id = 'stackboost-ats-toast';
                toast.className = 'show';
                toast.textContent = 'Heading updated successfully!';
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
        });
    });
});