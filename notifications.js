(function() {
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.borderColor = type === 'error' ? 'rgba(255,77,109,.4)' : 'rgba(46,233,166,.35)';
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    function setButtonLoading(button, isLoading) {
        if (!button) return;
        if (isLoading) {
            button.classList.add('btn-loading');
            button.disabled = true;
        } else {
            button.classList.remove('btn-loading');
            button.disabled = false;
        }
    }

    document.querySelectorAll('form[data-ajax="true"]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.submitting === '1') return;
            form.dataset.submitting = '1';

            const submitButton = form.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true);

            const formData = new FormData(form);
            formData.set('ajax', '1');

            try {
                const response = await fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                if (data.status === 'success') {
                    showToast(data.message || 'Done', 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    showToast(data.message || 'Something went wrong.', 'error');
                }
            } catch (err) {
                showToast('Unable to process request. Please try again.', 'error');
            } finally {
                setButtonLoading(submitButton, false);
                form.dataset.submitting = '0';
            }
        });
    });
})();
