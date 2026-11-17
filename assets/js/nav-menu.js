/* OldBear Expansion — collapsible nav menu. Works on the frontend and inside the Elementor preview. */
(() => {
    const init = (root) => {
        root.querySelectorAll('.obx-nav').forEach((nav) => {
            if (nav.dataset.obxReady) { return; }
            nav.dataset.obxReady = '1';
            const toggle = nav.querySelector('.obx-nav__toggle');
            if (!toggle) { return; }
            const setOpen = (open) => { nav.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); };
            toggle.addEventListener('click', () => setOpen(!nav.classList.contains('is-open')));
            nav.addEventListener('keydown', (event) => { if (event.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); } });
            document.addEventListener('click', (event) => { if (!nav.contains(event.target)) { setOpen(false); } });
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', () => init(document)); } else { init(document); }
    window.addEventListener('elementor/frontend/init', () => {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/obx-nav-menu.default', ($scope) => init($scope[0] || $scope));
            window.elementorFrontend.hooks.addAction('frontend/element_ready/nav-menu.default', ($scope) => init($scope[0] || $scope));
        }
    });
})();
