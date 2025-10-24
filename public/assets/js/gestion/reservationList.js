/**
 * Initialise les interactions de la page de liste des réservations.
 */
function init() {
    // On sélectionne la liste déroulante, peu importe l'onglet actif
    // en se basant sur le début de son ID "event-selector-".
    const eventSelector = document.querySelector('[id^="event-selector-"]');

    if (eventSelector) {
        eventSelector.addEventListener('change', (event) => {
            const selectedSessionId = event.target.value;

            if (selectedSessionId) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('s', selectedSessionId);
                window.location.href = currentUrl.toString();
            }
        });
    }

    // Gérer le changement de page
    const itemsPerPageSelector = document.getElementById('items-per-page-selector');
    if (itemsPerPageSelector) {
        itemsPerPageSelector.addEventListener('change', function() {
            const perPage = this.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', perPage);
            // On retourne à la première page pour éviter les incohérences
            currentUrl.searchParams.set('page', '1');
            window.location.href = currentUrl.toString();
        });
    }
}

export { init as initReservationList };