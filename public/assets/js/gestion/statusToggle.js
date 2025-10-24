import { apiPost } from '../components/apiClient.js';
import ScrollManager from '../components/scrollManager.js';

/**
 * Fonction principale pour basculer le statut "Vérifié" d'une réservation.
 * @param {number} reservationId - L'ID de la réservation.
 * @param {boolean} newStatus - Le nouvel état (true pour vérifié, false pour non vérifié).
 * @param {HTMLElement} [elementToDisable] - L'élément (bouton ou interrupteur) à désactiver pendant l'appel.
 */
export async function toggleReservationStatus(reservationId, newStatus, elementToDisable = null) {
    if (elementToDisable) {
        elementToDisable.disabled = true;
    }

    try {
        const response = await apiPost('/gestion/reservation/toggle-status', {
            id: reservationId,
            status: newStatus
        });

        if (response.success) {
            ScrollManager.save(); // Sauvegarder la position du scroll
            window.location.reload(); // Recharger la page
        } else {
            alert(response.message || 'Échec de la mise à jour du statut.');
            if (elementToDisable) {
                elementToDisable.disabled = false;
            }
        }
    } catch (error) {
        console.error('Erreur lors du changement de statut:', error);
        alert(error.userMessage || 'Une erreur de communication est survenue.');
        if (elementToDisable) {
            elementToDisable.disabled = false;
        }
    }
}

/**
 * Gère le clic sur les interrupteurs de statut "Vérifié".
 * @param {Event} event - L'événement 'change' de l'interrupteur.
 */
async function handleStatusToggle(event) {
    const toggle = event.target;
    await toggleReservationStatus(Number(toggle.dataset.id), Boolean(toggle.checked), toggle);
}

/**
 * Initialise les écouteurs pour tous les interrupteurs de statut.
 */
export function initStatusToggles() {
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', handleStatusToggle);
    });
}