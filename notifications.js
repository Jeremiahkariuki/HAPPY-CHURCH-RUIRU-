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

            const submitButton = event.submitter || form.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true);

            const formData = new FormData(form);
            formData.set('ajax', '1');
            if (event.submitter && event.submitter.name) {
                formData.set(event.submitter.name, event.submitter.value || '1');
            }

            try {
                // Submit to current page - use a simple relative path
                const submitUrl = form.action || '.';
                const response = await fetch(submitUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                });

                // Read raw text first to handle non-JSON responses (e.g., login redirect HTML or error output)
                const raw = await response.text();

                if (!response.ok) {
                    const isLoginPage = (response.redirected && response.url && response.url.includes('login.php'))
                        || /<title>\s*Login\s*•\s*HAPPY CHURCH/i.test(raw)
                        || raw.toLowerCase().includes('login.php');
                    if (isLoginPage) {
                        showToast('Session expired. Redirecting to login...', 'error');
                        setTimeout(() => window.location.href = 'login.php', 800);
                        return;
                    }
                    const snippet = raw.replace(/\s+/g, ' ').slice(0, 300);
                    showToast(`HTTP ${response.status}: ${snippet || response.statusText}`, 'error');
                    return;
                }

                let data = null;
                try {
                    data = JSON.parse(raw);
                } catch (parseErr) {
                    const isLoginPage = /<title>\s*Login\s*•\s*HAPPY CHURCH/i.test(raw)
                        || raw.toLowerCase().includes('login.php');
                    if (isLoginPage) {
                        showToast('Session expired. Redirecting to login...', 'error');
                        setTimeout(() => window.location.href = 'login.php', 800);
                        return;
                    }

                    // Otherwise show a helpful snippet of the raw response for debugging
                    const snippet = raw.replace(/\s+/g, ' ').slice(0, 300);
                    showToast('Server response unexpected: ' + snippet, 'error');
                    return;
                }

                if (data && data.status === 'success') {
                    showToast(data.message || 'Done', 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    showToast((data && data.message) ? data.message : 'Something went wrong.', 'error');
                }
            } catch (err) {
                console.error('AJAX error', err);
                showToast('Unable to process request. Please try again.', 'error');
            } finally {
                setButtonLoading(submitButton, false);
                form.dataset.submitting = '0';
            }
        });
    });
})();
