document.addEventListener('DOMContentLoaded', () => {
    // 1. Button Loading State
    const primaryButtons = document.querySelectorAll('.btn:not(.btn-ghost):not(.btn-danger)');
    primaryButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Only trigger if inside a form that is valid
            const form = this.closest('form');
            if (!form || form.checkValidity()) {
                setTimeout(() => {
                    this.classList.add('btn-loading');
                }, 50);
            }
        });
    });

    // 2. Mobile Drawer Auto-Close on Link Click
    const drawerLinks = document.querySelectorAll('.drawer-item');
    drawerLinks.forEach(link => {
        link.addEventListener('click', () => {
            document.querySelector('.drawer')?.classList.remove('open');
            document.querySelector('.drawer-overlay')?.classList.remove('open');
        });
    });

    // 3. Auto-hide Flash Messages after 5 seconds
    const flashes = document.querySelectorAll('.flash');
    flashes.forEach(f => {
        setTimeout(() => {
            f.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            f.style.opacity = '0';
            f.style.transform = 'translateY(-10px)';
            setTimeout(() => f.remove(), 500);
        }, 5000);
    });
});
