import { apiPost } from '../components/apiClient.js';

/**
 * Initialise les interactions de la page d'extraction des réservations.
 */
function init() {
    const eventSelector = document.getElementById('event-selector-extracts');
    const optionsContainer = document.getElementById('export-options-container');

    if (!eventSelector || !optionsContainer) {
        return;
    }

    const selectedSessionId = eventSelector.value;

    function initPdfGenerator(sessionId) {
        const pdfTypeRadios = document.querySelectorAll('input[name="pdf-type-selector"]');
        const pdfSortRadios = document.querySelectorAll('input[name="pdf-sort-selector"]');
        const generatePdfBtn = document.getElementById('generate-pdf-btn');

        if (pdfTypeRadios.length > 0 && pdfSortRadios.length > 0 && generatePdfBtn) {
            const updatePdfLink = () => {
                const pdfType = document.querySelector('input[name="pdf-type-selector"]:checked').value;
                const sortOrder = document.querySelector('input[name="pdf-sort-selector"]:checked').value;
                const url = new URL('/gestion/reservations/exports', window.location.origin);
                url.searchParams.set('s', sessionId);
                url.searchParams.set('pdf', pdfType);
                url.searchParams.set('tri', sortOrder);
                generatePdfBtn.href = url.toString();
            };

            pdfTypeRadios.forEach(radio => radio.addEventListener('change', updatePdfLink));
            pdfSortRadios.forEach(radio => radio.addEventListener('change', updatePdfLink));
            updatePdfLink();
        }
    }

    function initCsvGenerator() {
        const generateCsvBtn = document.getElementById('generate-csv-btn');
        const fieldCheckboxes = document.querySelectorAll('input[id^="csv-field-"]');
        const tarifSelector = document.getElementById('csv-tarif-selector');
        const eventSelector = document.getElementById('event-selector-extracts');

        if (!generateCsvBtn || !eventSelector) return;

        generateCsvBtn.addEventListener('click', async (event) => {
            event.preventDefault();

            const selectedSessionId = eventSelector.value;

            // Champs cochés
            const checkedFields = Array.from(fieldCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => {
                    const labelEl = document.querySelector(`label[for="${cb.id}"]`);
                    return {
                        value: cb.value,
                        label: labelEl ? labelEl.textContent.trim() : cb.value
                    };
                });

            // Tarifs sélectionnés
            const selectedTarif = tarifSelector
                ? Array.from(tarifSelector.selectedOptions).map(option => option.value)
                : [];

            const payload = {
                id: selectedSessionId,
                checkedFields,
                selectedTarif,
            };

            try {
                const resp = await fetch('/gestion/reservations/extract-csv', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/octet-stream' },
                    body: JSON.stringify(payload),
                });

                // Erreurs serveur lisibles
                const ct = resp.headers.get('content-type') || '';
                if (!resp.ok) {
                    const msg = ct.includes('application/json')
                        ? (await resp.json()).message || 'Erreur lors de la génération du CSV.'
                        : await resp.text();
                    throw new Error(msg);
                }

                // Récupérer le nom du fichier depuis Content-Disposition
                let fileName = 'export.csv';
                const dispo = resp.headers.get('content-disposition') || '';
                const match = dispo.match(/filename="?([^"]+)"?/i);
                if (match && match[1]) fileName = match[1];

                // Télécharger via Blob
                const blob = await resp.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (e) {
                console.error(e);
                alert(e.message || 'Erreur lors de la génération du CSV.');
            }
        });
    }

    eventSelector.addEventListener('change', (event) => {
        const selectedSessionId = event.target.value;
        const url = new URL(window.location);
        url.searchParams.set('s', selectedSessionId);
        window.location.href = url.toString();
    });

    initPdfGenerator(selectedSessionId);
    initCsvGenerator();
}

export { init as initReservationExtracts };