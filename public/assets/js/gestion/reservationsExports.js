/**
 * Initialise les interactions de la page d'extraction des réservations.
 * - Gère la sélection d'une session pour recharger la page avec les bonnes options.
 * - Met à jour le lien de génération de PDF dynamiquement.
 */
function init() {
    const eventSelector = document.getElementById('event-selector-extracts');
    const optionsContainer = document.getElementById('export-options-container');

    if (!eventSelector || !optionsContainer) {
        return; // Ne rien faire si les éléments ne sont pas sur la page
    }

    // On récupère l'ID de la session depuis le selecteur, car il est déjà chargé par le PHP
    const selectedSessionId = eventSelector.value;

    /**
     * Met à jour le lien du bouton PDF en fonction du type sélectionné.
     * @param {string} sessionId L'ID de la session sélectionnée.
     */
    function initPdfGenerator(sessionId) {
        const pdfTypeSelector = document.getElementById('pdf-type-selector');
        const pdfSortRadios = document.querySelectorAll('input[name="pdf-sort-selector"]');
        const generatePdfBtn = document.getElementById('generate-pdf-btn');

        // --- Logique pour le PDF ---
        if (pdfTypeSelector && pdfSortRadios.length > 0 && generatePdfBtn) {
            const updatePdfLink = () => {
                const pdfType = pdfTypeSelector.value;
                const sortOrder = document.querySelector('input[name="pdf-sort-selector"]:checked').value;
                // Construit l'URL exacte demandée pour la génération du PDF
                const url = new URL('/gestion/reservations/exports', window.location.origin);
                url.searchParams.set('s', sessionId);
                url.searchParams.set('pdf', pdfType);
                url.searchParams.set('tri', sortOrder);
                generatePdfBtn.href = url.toString();
            };

            pdfTypeSelector.addEventListener('change', updatePdfLink);
            pdfSortRadios.forEach(radio => radio.addEventListener('change', updatePdfLink));
            // Mettre à jour le lien une première fois au chargement
            updatePdfLink();
        }
    }

    /**
     * Initialise le bouton CSV pour afficher une alerte.
     */
    function initCsvGenerator() {
        const generateCsvBtn = document.getElementById('generate-csv-btn');
        // --- Logique pour le CSV ---
        if (generateCsvBtn) {
            generateCsvBtn.addEventListener('click', () => {
                // Pour le moment, affiche juste une alerte comme demandé.
                alert('La génération de CSV sera bientôt disponible !');
            });
        }
    }

    // Gérer le changement de sélection de la session
    eventSelector.addEventListener('change', (event) => {
        const selectedSessionId = event.target.value;
        const url = new URL(window.location);
        url.searchParams.set('s', selectedSessionId);
        window.location.href = url.toString();
    });

    // Initialiser les générateurs si les options sont déjà affichées au chargement de la page
    initPdfGenerator(selectedSessionId);
    initCsvGenerator();
}

export { init as initReservationExtracts };