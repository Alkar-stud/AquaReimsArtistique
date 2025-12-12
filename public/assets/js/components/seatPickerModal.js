import { apiGet, apiPost } from './apiClient.js';
import { createBleacherGrid, applySeatStates } from './bleacherGrid.js';

let modalInstance = null;
let currentDetailId = null;
let currentOriginalPlaceId = null;
let eventSessionId = null;
let piscineId = null;
let onSeatUpdated = null; // Callback pour rafraîchir la modale parente

/**
 * Crée le DOM de la modale si elle n'existe pas.
 * @returns {HTMLElement} L'élément de la modale.
 */
function ensureModalDOM() {
    if (document.getElementById('seat-picker-modal')) {
        return document.getElementById('seat-picker-modal');
    }

    const modalEl = document.createElement('div');
    modalEl.id = 'seat-picker-modal';
    modalEl.className = 'modal fade';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-labelledby', 'seat-picker-modal-label');
    modalEl.setAttribute('aria-hidden', 'true');

    modalEl.innerHTML = `
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="seat-picker-modal-label">Changer la place</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="seat-picker-modal-body" style="overflow-y: auto;">
                    <div class="text-center p-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modalEl);

    // Initialiser l'instance Bootstrap Modal
    modalInstance = new bootstrap.Modal(modalEl);

    return modalEl;
}

/**
 * Gère le clic sur un siège disponible dans la modale.
 * @param {object} seat - L'objet représentant le siège cliqué.
 */
async function handleSeatClick(seat) {
    // Pour l'instant, on affiche seulement en console.
    console.log(`Place cliquée : ID ${seat.seatId}, pour le participant (detailId) : ${currentDetailId}`);

    try {
        await apiPost('/gestion/reservations/update-seat', {
            detailId: currentDetailId,
            newPlaceId: seat.seatId
        });

        // On ferme la modale de sélection
        modalInstance.hide();

        // On exécute le callback pour rafraîchir la modale de gestion
        if (typeof onSeatUpdated === 'function') {
            onSeatUpdated();
        }
    } catch (error) {
        alert(`Erreur lors de la mise à jour de la place : ${error.message}`);
    }
}

/**
 * Charge et affiche toutes les zones de gradins.
 * @param {HTMLElement} container - Le conteneur où afficher les gradins.
 */
async function loadAllZones(container) {
    container.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement des zones...</span></div></div>';

    try {
        // 1. Récupérer la liste des zones pour la piscine
        const zonesResponse = await apiGet(`/piscine/${piscineId}/zones`);
        if (!zonesResponse.success || !Array.isArray(zonesResponse.zones)) {
            throw new Error('Impossible de récupérer la liste des zones.');
        }

        // 2. Récupérer l'état de toutes les places pour la session
        const seatStatesResponse = await apiGet(`/reservation/seat-states/${eventSessionId}`);
        const seatStates = seatStatesResponse.success ? seatStatesResponse.seatStates : {};

        // 3. Charger le plan de chaque zone et le rendre
        const zonePromises = zonesResponse.zones.map(zone => apiGet(`/piscine/gradins/${piscineId}/${zone.id}`));
        const structurePlanResponses = await Promise.all(zonePromises);

        container.innerHTML = ''; // Vider le spinner

        structurePlanResponses.forEach(response => {
            if (response.success && response.plan) {
                const zoneContainer = document.createElement('div');
                zoneContainer.className = 'mb-5';

                // Ajout du titre de la zone
                const title = document.createElement('h4');
                title.className = 'zone-title mb-3';
                title.textContent = response.plan.zone.zoneName;
                zoneContainer.appendChild(title);

                const gridContainer = document.createElement('div');
                gridContainer.dataset.bleacherSeats = 'true';
                zoneContainer.appendChild(gridContainer);

                container.appendChild(zoneContainer);

                // Créer la grille
                createBleacherGrid(gridContainer, response.plan, {
                    mode: 'reservation',
                    onSeatClick: handleSeatClick
                });

                // Appliquer les états (réservé, etc.)
                applySeatStates(gridContainer, seatStates);

                // Mettre en évidence la place d'origine
                if (currentOriginalPlaceId) {
                    const originalSeatBtn = gridContainer.querySelector(`button[data-seat-id="${currentOriginalPlaceId}"]`);
                    if (originalSeatBtn) {
                        const td = originalSeatBtn.closest('td');
                        td.classList.add('tdplaceOriginal');
                        // On s'assure que le bouton reste cliquable s'il était dans la sélection de la session
                        if (originalSeatBtn.dataset.status === 'in_cart_session') {
                            originalSeatBtn.disabled = false;
                        }
                    }
                }
            }
        });

    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

/**
 * Ouvre et initialise la modale de sélection de place.
 * @param {object} options
 * @param {number} options.piscineId - ID de la piscine.
 * @param {number} options.eventSessionId - ID de la session de l'événement.
 * @param {number} options.detailId - ID du détail de réservation (participant).
 * @param {number} options.placeId - ID de la place actuelle du participant.
 * @param {function} options.onSuccess - Callback à exécuter après la mise à jour.
 */
export function openSeatPickerModal(options) {
    piscineId = options.piscineId;
    eventSessionId = options.eventSessionId;
    currentDetailId = options.detailId;
    currentOriginalPlaceId = options.placeId;
    onSeatUpdated = options.onSuccess;

    if (!piscineId || !eventSessionId || !currentDetailId) {
        console.error("Informations manquantes pour ouvrir le sélecteur de places.", options);
        alert("Une erreur est survenue : impossible d'ouvrir le sélecteur de places.");
        return;
    }

    const modalEl = ensureModalDOM();
    const modalBody = modalEl.querySelector('#seat-picker-modal-body');

    modalInstance.show();

    // Charger le contenu
    loadAllZones(modalBody);
}

/**
 * Initialise le composant global.
 * Principalement pour s'assurer que le DOM est prêt.
 */
export function initSeatPicker() {
    ensureModalDOM();
}