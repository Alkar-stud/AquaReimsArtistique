document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const jsVars = body.dataset.jsVars ? JSON.parse(body.dataset.jsVars) : {};
    const debugData = jsVars.debug || {};

    // --- Affichage des dimensions du viewport ---
    const dimensionsDisplay = document.getElementById('screen-dimensions-display');
    function updateDimensions() {
        if(dimensionsDisplay) {
            dimensionsDisplay.textContent = `Viewport: ${window.innerWidth}px x ${window.innerHeight}px`;
        }
    }
    if (dimensionsDisplay) {
        updateDimensions();
        window.addEventListener('resize', updateDimensions);
    }

    // --- Compte à rebours de la session ---
    const timeoutDisplay = document.getElementById('session-timeout-display');
    if (timeoutDisplay && debugData.sessionTimeoutDuration > 0) {
        const timeoutDuration = debugData.sessionTimeoutDuration; // en secondes
        const lastActivity = debugData.sessionLastActivity;   // timestamp
        const expirationTime = (lastActivity + timeoutDuration) * 1000;

        const intervalId = setInterval(() => {
            const now = new Date().getTime();
            const remaining = expirationTime - now;

            if (remaining <= 0) {
                timeoutDisplay.textContent = 'Session: Expirée';
                timeoutDisplay.style.color = '#ffc107';
                clearInterval(intervalId);
            } else {
                const minutes = Math.floor(remaining / (1000 * 60));
                const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
                timeoutDisplay.textContent = `Session: ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }, 1000);
    }

    // --- Masquer / Afficher le bandeau ---
    const debugContainer = document.getElementById('debug-container');
    const debugToggle = document.getElementById('debug-toggle');
    const storageKey = 'debugBarCollapsed';

    if (debugContainer && debugToggle) {
        // Appliquer l'état initial au chargement de la page
        if (localStorage.getItem(storageKey) === 'true') {
            debugContainer.classList.add('is-collapsed');
        }

        // Gérer le clic sur le bouton
        debugToggle.addEventListener('click', () => {
            const isCollapsed = debugContainer.classList.toggle('is-collapsed');
            localStorage.setItem(storageKey, isCollapsed);
        });
    }
});