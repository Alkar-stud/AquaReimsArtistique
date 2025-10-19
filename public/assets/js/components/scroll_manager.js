(function (global) {
    'use strict';

    const PREFIX = 'scrollpos:';

    function makeKey(suffix) {
        // Par défaut, clé liée à la page + ses paramètres
        const defaultId = global.location.pathname + global.location.search;
        return PREFIX + (suffix || defaultId);
    }

    function getScrollTop(container) {
        if (container && container instanceof Element) {
            return container.scrollTop || 0;
        }
        return global.scrollY || document.documentElement.scrollTop || 0;
    }

    function setScrollTop(container, top, behavior = 'auto') {
        if (container && container instanceof Element) {
            container.scrollTo({ top, behavior });
        } else {
            global.scrollTo({ top, behavior });
        }
    }

    function save(opts = {}) {
        const { keySuffix, container } = opts;
        const key = makeKey(keySuffix);
        const top = getScrollTop(container);
        try {
            global.localStorage.setItem(key, String(top));
        } catch (_) { /* stockage indisponible, ignorer */ }
    }

    function restore(opts = {}) {
        const { keySuffix, container, behavior = 'auto', remove = true } = opts;
        const key = makeKey(keySuffix);
        let pos = null;
        try {
            pos = global.localStorage.getItem(key);
        } catch (_) { /* ignorer */ }
        if (pos !== null) {
            const top = parseInt(pos, 10) || 0;
            setScrollTop(container, top, behavior);
            if (remove) {
                try { global.localStorage.removeItem(key); } catch (_) {}
            }
            return true;
        }
        return false;
    }

    // Active un suivi passif du scroll et sauvegarde automatiquement
    function track(opts = {}) {
        const { keySuffix, container } = opts;
        const target = container instanceof Element ? container : global;
        const handler = () => save({ keySuffix, container });
        target.addEventListener('scroll', handler, { passive: true });
        return () => target.removeEventListener('scroll', handler);
    }

    // Expose global
    global.ScrollManager = { save, restore, track };
})(window);
