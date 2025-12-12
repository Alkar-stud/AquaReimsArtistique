import { showFlashMessage } from "../components/ui.js";
import {apiGet, apiPost} from "../components/apiClient.js";
import {buttonLoading} from "../components/utils.js";

let reservationTempModal = null;

/**
 * Initialise les fonctionnalités liées aux réservations temporaires.
 */
export function initReservationTemp() {
    const modalElement = document.getElementById('reservation-incoming-modal');
    if (!modalElement) return;

    reservationTempModal = new bootstrap.Modal(modalElement);

    // Écouteur pour l'ouverture de la modale
    modalElement.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget;
        const reservationId = button.getAttribute('data-id');
        if (reservationId) {
            await loadReservationTempDetails(reservationId);
        }
    });

    // Écouteur pour les switches de verrouillage dans la liste
    document.querySelectorAll('.js-toggle-lock').forEach(toggle => {
        toggle.addEventListener('change', (event) => handleToggleLock(event.currentTarget));
    })

    // Écouteurs pour les boutons de suppression
    document.querySelectorAll('.js-delete-reservation-temp').forEach(button => {
        button.addEventListener('click', handleDeleteReservationTemp);
    });
}

/**
 * Charge et affiche les détails d'une réservation temporaire dans la modale.
 * @param {string} reservationId - L'ID de la réservation temporaire.
 */
async function loadReservationTempDetails(reservationId) {
    const modal = document.getElementById('reservation-incoming-modal');
    if (!modal) return;

    // Afficher un loader (optionnel, mais recommandé)
    setModalContent(modal, { loading: true });

    try {
        const data = await apiGet(`/gestion/reservations-temp/details/${reservationId}`);

        if (data && !data.error) {
            // Stocke l'ID et le statut de verrouillage dans des champs cachés
            modal.querySelector('#incoming_reservation_id').value = data.id;
            modal.querySelector('#incoming_is_locked').value = data.isLocked ? '1' : '0';
            setModalContent(modal, {
                name: `${data.name} ${data.firstName}`,
                email: data.email,
                phone: data.phone,
                amount: `${((data.totalAmount ?? 0) / 100).toFixed(2).replace('.', ',')} €`,
                details: data.details.map(d => `<li>${d.name} ${d.firstname} <div class="text-muted small">${d.tarifObject.name} (${((d.tarifObject.price ?? 0) /100).toFixed(2)} €)</div></li>`).join(''),
                complements: data.complements.map(c => `<li>${c.name} : ${c.value ?? ''}</li>`).join('')
            });
        } else {
            throw new Error(data.error || 'Données de réservation non trouvées.');
        }
    } catch (error) {
        console.error('Erreur lors du chargement des détails de la réservation temporaire:', error);
        setModalContent(modal, { error: 'Impossible de charger les détails.' });
    }

    // Initialiser les infobulles Bootstrap pour le nouveau contenu
    new bootstrap.Tooltip(modal.querySelector('[data-bs-toggle="tooltip"]'));
}

/**
 * Met à jour le contenu de la modale.
 * @param {HTMLElement} modal - L'élément de la modale.
 * @param {object} content - Le contenu à afficher.
 */
function setModalContent(modal, content) {
    const nameEl = modal.querySelector('#incoming-name');
    const emailEl = modal.querySelector('#incoming-email');
    const phoneEl = modal.querySelector('#incoming-phone');
    const amountEl = modal.querySelector('#incoming-amount');
    const detailsEl = modal.querySelector('#incoming-details');
    const complementsEl = modal.querySelector('#incoming-complements');

    if (content.loading) {
        nameEl.textContent = 'Chargement...';
        // ... vider les autres champs
    } else if (content.error) {
        nameEl.textContent = content.error;
        // ... vider les autres champs
    } else {
        nameEl.textContent = content.name;
        emailEl.textContent = content.email;
        phoneEl.textContent = content.phone;
        amountEl.textContent = content.amount;
        detailsEl.innerHTML = `<ul>${content.details}</ul>`;
        complementsEl.innerHTML = content.complements ? `<ul>${content.complements}</ul>` : '';
    }
}

/**
 * Gère le clic sur le switch de verrouillage.
 * @param {HTMLInputElement} toggleSwitch L'interrupteur qui a été actionné.
 */
async function handleToggleLock(toggleSwitch) {
    const reservationId = toggleSwitch.dataset.id;
    const isLocked = toggleSwitch.checked;

    toggleSwitch.disabled = true;
    try {
        await apiPost('/gestion/reservations-temp/toggle-lock', {
            id: reservationId,
            isLocked: isLocked
        });

        // Mettre à jour le badge visuellement
        const badge = toggleSwitch.closest('td').querySelector('.badge');
        if (badge) {
            badge.textContent = isLocked ? 'Verrouillée' : 'Ouverte';
            badge.classList.toggle('bg-warning', isLocked);
            badge.classList.toggle('bg-success', !isLocked);
        }

        // Activer/désactiver le bouton de suppression correspondant
        const row = toggleSwitch.closest('tr');
        const deleteButton = row.querySelector('.js-delete-reservation-temp');
        if (deleteButton) {
            deleteButton.disabled = isLocked;
        }

        // Afficher un message flash
        const message = isLocked ? 'La réservation a été verrouillée.' : 'La réservation a été déverrouillée.';
        showFlashMessage('success', message);

    } catch (error) {
        console.error("Erreur lors du changement de statut de verrouillage", error);
        showFlashMessage('danger', error.message || 'Une erreur est survenue.');
    } finally {
        toggleSwitch.disabled = false;
    }
}


/**
 * Gère la suppression d'une réservation temporaire.
 * @param {Event} event
 */
async function handleDeleteReservationTemp(event) {
    const button = event.currentTarget;
    const reservationId = button.dataset.id;

    const confirmationMessage = `Êtes-vous sûr de vouloir supprimer la réservation temporaire TEMP-${reservationId.padStart(5, '0')} ?`;
    if (!confirm(confirmationMessage)) {
        return;
    }

    buttonLoading(button, true); // Afficher l'état de chargement
    try {
        const response = await apiPost(`/gestion/reservations-temp/delete/${reservationId}`, {}, 'DELETE');

        if (response.success) {
            // Supprimer la ligne du tableau après suppression réussie
            const row = button.closest('tr');
            if (row) {
                row.remove();
            }
            showFlashMessage('success', 'La réservation temporaire a été supprimée.');
        } else {
            showFlashMessage('danger', response.message || 'Erreur lors de la suppression de la réservation temporaire.');
        }
    } catch (error) {
        console.error("Erreur lors de la suppression de la réservation temporaire", error);
        showFlashMessage('danger', 'Une erreur est survenue lors de la suppression de la réservation temporaire.');
    } finally {
        buttonLoading(button, false); // Masquer l'état de chargement
    }
}