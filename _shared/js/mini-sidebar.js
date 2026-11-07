// Sidebar mini mode enhancer (no generated PHPMaker file changes)
(function() {
    const DESKTOP_MIN_WIDTH = 992;
    const miniTooltipInstances = new WeakMap();
    let isApplyingSidebarPatch = false;
    let refreshQueued = false;

    function isDesktop() {
        return window.innerWidth >= DESKTOP_MIN_WIDTH;
    }

    function hasNavbar() {
        return document.querySelector('#ew-navbar') !== null;
    }

    function ensureSidebarMiniBodyClass() {
        if (!isDesktop()) return;
        // Required by AdminLTE so collapsed sidebar shows mini icons instead of fully hiding.
        if (!document.body.classList.contains('sidebar-mini')) {
            document.body.classList.add('sidebar-mini');
        }
    }

    function getLabelText(link) {
        const p = link.querySelector('p');
        if (!p) return '';
        return p.textContent.replace(/\s+/g, ' ').trim();
    }

    function ensureFallbackIcons() {
        // Intentionally no auto-generated fallback icon.
        // If a menu item has no icon, we keep it as-is.
    }

    function buildInitials(name) {
        if (!name) return 'U';
        const parts = name.trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) return 'U';
        if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
        return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    }

    function ensureUserPanelAvatar() {
        const panel = document.querySelector('.main-sidebar .user-panel');
        if (!panel) return;
        const hasImage = panel.querySelector('.image img') !== null;
        if (hasImage) return;

        let avatarWrap = panel.querySelector('.image.ew-user-avatar-wrap');
        if (!avatarWrap) {
            avatarWrap = document.createElement('div');
            avatarWrap.className = 'image ew-user-avatar-wrap';
            panel.insertBefore(avatarWrap, panel.firstChild);
        }

        let avatar = avatarWrap.querySelector('.ew-user-avatar');
        if (!avatar) {
            avatar = document.createElement('span');
            avatar.className = 'ew-user-avatar';
            avatarWrap.appendChild(avatar);
        }

        const name = panel.querySelector('.info a, .info .d-block')?.textContent || '';
        avatar.textContent = buildInitials(name);
        avatar.setAttribute('aria-label', name ? `User ${name}` : 'User');
        avatar.removeAttribute('title');
    }

    function applyCollapsedTitles() {
        const collapsed = document.body.classList.contains('sidebar-collapse') && isDesktop();
        const links = document.querySelectorAll('#ew-menu .nav-sidebar .nav-link');
        links.forEach((link) => {
            const label = getLabelText(link);
            if (!label) return;
            if (collapsed) {
                link.setAttribute('title', label);
            } else if (link.getAttribute('title') === label) {
                link.removeAttribute('title');
            }
        });
    }

    function disposeMiniTooltips() {
        const links = document.querySelectorAll('#ew-menu .nav-sidebar .nav-link');
        links.forEach((link) => {
            const tip = miniTooltipInstances.get(link);
            if (tip) {
                tip.dispose();
                miniTooltipInstances.delete(link);
            }
            link.removeAttribute('data-bs-toggle');
            link.removeAttribute('data-bs-placement');
            link.removeAttribute('data-bs-trigger');
        });
    }

    function applyMiniTooltips() {
        const collapsed = document.body.classList.contains('sidebar-collapse') && isDesktop();
        const hasBootstrapTooltip = window.bootstrap && typeof window.bootstrap.Tooltip === 'function';
        if (!collapsed || !hasBootstrapTooltip) {
            disposeMiniTooltips();
            return;
        }

        const links = document.querySelectorAll('#ew-menu .nav-sidebar > .nav-item > .nav-link');
        links.forEach((link) => {
            const label = getLabelText(link);
            if (!label) return;

            link.setAttribute('data-bs-toggle', 'tooltip');
            link.setAttribute('data-bs-placement', 'right');
            link.setAttribute('data-bs-trigger', 'hover focus');

            const current = miniTooltipInstances.get(link);
            if (current) {
                try {
                    current.setContent({ '.tooltip-inner': label });
                } catch (_) {
                    // Ignore; fallback title handles text.
                }
                return;
            }

            const instance = new window.bootstrap.Tooltip(link, {
                title: label,
                placement: 'right',
                trigger: 'hover focus',
                container: 'body',
                boundary: document.body,
                customClass: 'ew-mini-menu-tooltip'
            });
            miniTooltipInstances.set(link, instance);
        });
    }

    function refreshSidebarMiniNow() {
        if (!hasNavbar()) return;
        ensureSidebarMiniBodyClass();
        ensureFallbackIcons();
        ensureUserPanelAvatar();
        applyCollapsedTitles();
        applyMiniTooltips();
    }

    function refreshSidebarMini() {
        if (refreshQueued) return;
        refreshQueued = true;
        window.requestAnimationFrame(() => {
            refreshQueued = false;
            if (isApplyingSidebarPatch) return;
            isApplyingSidebarPatch = true;
            try {
                refreshSidebarMiniNow();
            } finally {
                isApplyingSidebarPatch = false;
            }
        });
    }

    function scheduleRefresh() {
        window.setTimeout(refreshSidebarMini, 30);
        window.setTimeout(refreshSidebarMini, 180);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshSidebarMini);
    } else {
        refreshSidebarMini();
    }

    // React to AdminLTE pushmenu events
    document.addEventListener('collapsed.lte.pushmenu', scheduleRefresh);
    document.addEventListener('shown.lte.pushmenu', scheduleRefresh);
    window.addEventListener('resize', refreshSidebarMini);

    // React to class changes and lazy menu render
    const bodyObserver = new MutationObserver(() => {
        if (isApplyingSidebarPatch) return;
        refreshSidebarMini();
    });
    bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

    const menuContainer = document.getElementById('ew-menu');
    if (menuContainer) {
        const menuObserver = new MutationObserver(() => {
            if (isApplyingSidebarPatch) return;
            refreshSidebarMini();
        });
        menuObserver.observe(menuContainer, { childList: true, subtree: true });
    }
})();