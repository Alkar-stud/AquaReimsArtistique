import {apiGet} from "../components/apiClient.js";

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
console.log('data : ', data);
            setModalContent(modal, {
                name: `${data.name} ${data.firstName}`,
                email: data.email,
                phone: data.phone,
                amount: `${(data.totalAmount ?? 0).toFixed(2)} €`,
                details: data.details.map(d => `<li>${d.name} ${d.firstname} <div class="text-muted small">${d.tarifObject.name} (${(d.tarifObject.price /100).toFixed(2)} €)</div></li>`).join(''),
                complements: data.complements.map(c => `<li>${c.name} : ${c.value ?? ''}</li>`).join('')
            });
        } else {
            throw new Error(data.error || 'Données de réservation non trouvées.');
        }
    } catch (error) {
        console.error('Erreur lors du chargement des détails de la réservation temporaire:', error);
        setModalContent(modal, { error: 'Impossible de charger les détails.' });
    }
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
console.log('content : ', content);
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
 * Gère la suppression d'une réservation temporaire.
 * @param {Event} event
 */
async function handleDeleteReservationTemp(event) {
    const button = event.currentTarget;
    const reservationId = button.dataset.id;

    if (confirm(`Êtes-vous sûr de vouloir supprimer la réservation temporaire TEMP-${reservationId.padStart(5, '0')} ?`)) {
        // Logique de suppression à implémenter (fetch avec méthode DELETE)
        console.log(`Suppression de la réservation ${reservationId}`);
        // Après suppression, recharger la page ou supprimer la ligne du tableau
    }
}