document.addEventListener('DOMContentLoaded', function() {
    // Lecture et normalisation des données debug
    let debugData = {};
    const holder = document.getElementById('debug-data');
    if (holder) {
        try {
            const raw = JSON.parse(holder.textContent || '{}') || {};
            debugData = raw.debug && typeof raw.debug === 'object' ? raw.debug : raw;
        } catch (e) {
            console.warn('Invalid debug JSON', e);
        }
    }

    // --- Affichage des dimensions du viewport ---
    const dimensionsDisplay = document.getElementById('screen-dimensions-display');
    function updateDimensions() {
        if (dimensionsDisplay) {
            dimensionsDisplay.textContent = `Viewport: ${window.innerWidth}px x ${window.innerHeight}px`;
        }
    }
    if (dimensionsDisplay) {
        updateDimensions();
        window.addEventListener('resize', updateDimensions);
    }

    // --- Compte à rebours de la session ---
    const timeoutDisplay = document.getElementById('session-timeout-display');
    const timeoutDuration = Number(debugData.sessionTimeoutDuration ?? 0); // secondes
    const lastActivity = Number(debugData.sessionLastActivity ?? 0);       // timestamp (s)
    if (timeoutDisplay && timeoutDuration > 0 && lastActivity > 0) {
        const expirationTime = (lastActivity + timeoutDuration) * 1000;
        const intervalId = setInterval(() => {
            const remaining = expirationTime - Date.now();
            if (remaining <= 0) {
                timeoutDisplay.textContent = 'Session: Expirée';
                timeoutDisplay.style.color = '#ffc107';
                clearInterval(intervalId);
            } else {
                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                timeoutDisplay.textContent =
                    `Session: ${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;
            }
        }, 1000);
    }

    // --- Masquer / Afficher le bandeau ---
    const debugContainer = document.getElementById('debug-container');
    const debugToggle = document.getElementById('debug-toggle');
    const storageKey = 'debugBarCollapsed';
    if (debugContainer && debugToggle) {
        if (localStorage.getItem(storageKey) === 'true') {
            debugContainer.classList.add('is-collapsed');
        }
        debugToggle.addEventListener('click', () => {
            const isCollapsed = debugContainer.classList.toggle('is-collapsed');
            localStorage.setItem(storageKey, String(isCollapsed));
        });
    }
});
