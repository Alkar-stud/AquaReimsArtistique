import { apiGet } from '../components/apiClient.js';
import { createBleacherGrid, applySeatStates, showBleacherTooltip } from '../components/bleacherGrid.js';

/**
 * Initialise les fonctionnalités liées au plan d'occupation.
 */
export function initOccupationPlan()
{
    const showPlanButton = document.getElementById('show-occupation-plan');
    const occupationPlanModalEl = document.getElementById('occupationPlanModal');

    if (showPlanButton && occupationPlanModalEl) {
        // On crée une instance de la modale Bootstrap une seule fois.
        const occupationPlanModal = new bootstrap.Modal(occupationPlanModalEl);

        showPlanButton.addEventListener('click', (event) => {
            const button = event.currentTarget;
            const sessionId = button.dataset.sessionId;
            const piscineId = button.dataset.piscineId ?? 0;

            if (sessionId && occupationPlanModal) {
                // On passe les infos nécessaires à la modale avant de l'ouvrir
                occupationPlanModalEl.dataset.sessionId = sessionId;
                occupationPlanModalEl.dataset.piscineId = piscineId;

                // Ouvre la modale.
                occupationPlanModal.show();
            }
        });

        // Écouteur pour charger le contenu lorsque la modale est sur le point de s'afficher
        occupationPlanModalEl.addEventListener('show.bs.modal', async (event) => {
            const modal = event.target;
            const sessionId = modal.dataset.sessionId;
            const piscineId = modal.dataset.piscineId;
            const modalBody = modal.querySelector('#occupation-plan-modal-body');

            if (sessionId && piscineId && modalBody) {
                await loadOccupationPlan(modalBody, piscineId, sessionId);
            }
        });
    }
}

/**
 * Gère le clic sur un siège dans le plan d'occupation.
 * @param {object} seat - L'objet représentant le siège cliqué.
 * @param {HTMLButtonElement} btn - Le bouton du siège.
 */
function handleSeatClick(seat, btn) {
    if (seat.status === 'occupied') {
        // TODO: Pour afficher les vraies infos, le backend (endpoint /reservation/seat-states/)
        // devra renvoyer, pour chaque place 'occupied', un objet contenant:
        // { status: 'occupied', reservationId: '123', reserverName: 'Dupont Jean' }
        const tooltipText = "infos à venir";
        showBleacherTooltip(btn, tooltipText);
    }
    // Ne rien faire pour les autres types de places (libres, etc.)
}

/**
 * Charge et affiche le plan d'occupation complet dans le conteneur de la modale.
 * @param {HTMLElement} container - Le conteneur où afficher les gradins.
 * @param {number} piscineId - L'ID de la piscine.
 * @param {number} eventSessionId - L'ID de la session.
 */
async function loadOccupationPlan(container, piscineId, eventSessionId) {
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

                const title = document.createElement('h4');
                title.className = 'zone-title mb-3';
                title.textContent = response.plan.zone.zoneName;
                zoneContainer.appendChild(title);

                const gridContainer = document.createElement('div');
                zoneContainer.appendChild(gridContainer);
                container.appendChild(zoneContainer);

                createBleacherGrid(gridContainer, response.plan, { onSeatClick: handleSeatClick });
                applySeatStates(gridContainer, seatStates, 'occupation_plan');
            }
        });
    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}