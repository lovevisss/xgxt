// Presentation only: follow the visible viewport when the on-screen keyboard opens.
export function installMobileViewport() {
    const viewport = window.visualViewport;
    const media = window.matchMedia('(max-width: 767px)');
    const root = document.documentElement;
    const update = () => {
        if (!media.matches) {
            root.style.removeProperty('--mobile-viewport-height');
            root.style.removeProperty('--mobile-viewport-top');
            return;
        }
        root.style.setProperty('--mobile-viewport-height', `${viewport?.height ?? window.innerHeight}px`);
        root.style.setProperty('--mobile-viewport-top', `${viewport?.offsetTop ?? 0}px`);
    };
    viewport?.addEventListener('resize', update);
    viewport?.addEventListener('scroll', update);
    window.addEventListener('resize', update);
    update();
}
