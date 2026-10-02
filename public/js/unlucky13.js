(() => {
    const body = document.body;
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');

    if (!openButton) {
        return;
    }

    const setSidebar = (open) => {
        body.classList.toggle('sidebar-open', open);
        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    openButton.addEventListener('click', () => setSidebar(true));

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => setSidebar(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebar(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 960) {
            setSidebar(false);
        }
    });
})();
