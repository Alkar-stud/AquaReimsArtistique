import { apiPost } from '../components/apiClient.js';
import ScrollManager from '../components/scrollManager.js';

/**
 * Gère le clic sur les interrupteurs de statut "Vérifié".
 * @param {Event} event - L'événement 'change' de l'interrupteur.
 */
async function handleStatusToggle(event) {
    const toggle = event.target;
    const reservationId = Number(toggle.dataset.id);
    const newStatus = Boolean(toggle.checked);

    toggle.disabled = true; // Désactiver pour éviter les double-clics

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
            toggle.checked = !newStatus; // Revenir à l'état précédent
            toggle.disabled = false;
        }
    } catch (error) {
        console.error('Erreur lors du changement de statut:', error);
        alert(error.userMessage || 'Une erreur de communication est survenue.');
        toggle.checked = !newStatus; // Revenir à l'état précédent
        toggle.disabled = false;
    }
}

/**
 * Initialise les écouteurs pour tous les interrupteurs de statut.
 */
export function initStatusToggles() {
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', handleStatusToggle);
    });
}