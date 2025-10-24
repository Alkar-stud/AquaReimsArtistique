import { apiPost } from '../components/apiClient.js';
import ScrollManager from '../components/scrollManager.js';

/**
 * Gère la logique d'annulation d'une réservation.
 * @param {object} options - Les options de configuration.
 * @param {string} options.apiUrl - L'URL de l'API à appeler.
 * @param {string|number} options.reservationIdentifier - Le token ou l'ID de la réservation.
 * @param {string} options.identifierType - 'token' ou 'reservationId'.
 * @param {boolean} options.newStatus - Le nouvel état (true pour annulé, false pour réactivé).
 * @param {HTMLElement} options.button - Le bouton qui a déclenché l'action.
 */
export async function toggleCancelStatus(options) {
    const { apiUrl, reservationIdentifier, identifierType, newStatus, button } = options;

    // Message de confirmation adapté à l'action
    const confirmationMessage = newStatus
        ? "Êtes-vous sûr de vouloir annuler cette réservation ?\nCette action est irréversible."
        : "Êtes-vous sûr de vouloir réactiver cette réservation ?";

    if (!confirm(confirmationMessage)) {
        return;
    }
    // Double confirmation uniquement pour l'annulation
    if (newStatus && !confirm("Êtes-vous toujours sûr ?\nVous ne pourrez prétendre à aucun remboursement !")) {
        return;
    }

    const payload = {
        typeField: 'cancel',
        value: newStatus // On envoie le nouvel état au backend
    };

    if (identifierType === 'token') {
        payload.token = reservationIdentifier;
    } else if (identifierType === 'reservationId') {
        payload.reservationId = reservationIdentifier;
    }

    button.disabled = true;

    try {
        const response = await apiPost(apiUrl, payload);
        if (response.success) {
            alert(response.message || 'Le statut de la réservation a été mis à jour.');
            ScrollManager.save();
            window.location.reload();
        } else {
            alert(response.message || "L'opération a échoué.");
            button.disabled = false;
        }
    } catch (error) {
        alert(error.userMessage || 'Erreur de communication.');
        button.disabled = false;
    }
}

/**
 * Initialise les écouteurs pour les boutons d'annulation.
 * @param {string} buttonSelector - Le sélecteur CSS du bouton d'annulation.
 * @param {object} options - Les options à passer à performCancellation.
 */
export function initCancelButtons(buttonSelector, options) {
    const button = document.querySelector(buttonSelector);
    if (button) {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            // Pour la page modif_data, le statut cible est toujours 'true' (annuler)
            const finalOptions = { ...options, newStatus: true, button };
            toggleCancelStatus(finalOptions);
        });
    }
}