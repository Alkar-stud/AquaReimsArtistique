/**
 * Affiche un feedback visuel à côté d'un champ.
 * @param {HTMLElement|null} feedbackSpan - L'élément où afficher le feedback.
 * @param {'success'|'error'|'loading'} status - Le statut à afficher.
 * @param {string} [message=''] - Le message à afficher au survol.
 */
function showFeedback(feedbackSpan, status, message = '') {
    if (!feedbackSpan) return;

    feedbackSpan.textContent = status === 'success' ? '✓' : status === 'error' ? '✗' : '...';
    feedbackSpan.className = 'input-group-text feedback-span'; // Reset classes
    feedbackSpan.classList.add(
        status === 'success' ? 'text-success' :
            status === 'error' ? 'text-danger' : 'text-muted'
    );
    feedbackSpan.title = message;
}

/**
 * Fonction générique pour mettre à jour un champ de réservation via AJAX.
 * @param {object} data - Les données à envoyer (typeField, reservationId, field, value, etc.).
 * @returns {Promise<any>} - La promesse résolue avec le résultat JSON.
 */
export async function updateReservationField(data) {
    const feedbackSpan = data.feedbackSpan; // Récupérer le span depuis l'objet data

    try {
        if (feedbackSpan) {
            showFeedback(feedbackSpan, 'loading');
        }

        const response = await fetch('/gestion/reservations/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (feedbackSpan) {
            showFeedback(feedbackSpan, result.success ? 'success' : 'error', result.message || '');
        }

        return result;
    } catch (error) {
        console.error('Erreur lors de la mise à jour:', error);
        if (feedbackSpan) {
            showFeedback(feedbackSpan, 'error', 'Erreur de communication.');
        }
        // Renvoyer une promesse rejetée pour que l'appelant puisse gérer l'erreur
        return Promise.reject(error);
    }
}