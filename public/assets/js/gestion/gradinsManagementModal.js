import { apiGet, apiPost } from '../components/apiClient.js';
import { createBleacherGrid } from '../components/bleacherGrid.js';

let modalInstance = null;
let currentPiscineId = null;

/**
 * Crée le DOM de la modale si elle n'existe pas.
 * @returns {HTMLElement} L'élément de la modale.
 */
function ensureModalDOM() {
    if (document.getElementById('gradins-management-modal')) {
        return document.getElementById('gradins-management-modal');
    }

    const modalEl = document.createElement('div');
    modalEl.id = 'gradins-management-modal';
    modalEl.className = 'modal fade';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-labelledby', 'gradins-management-modal-label');
    modalEl.setAttribute('aria-hidden', 'true');

    modalEl.innerHTML = `
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gradins-management-modal-label">Gestion des Gradins</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="gradins-management-modal-body" style="overflow-y: auto;">
                    <div class="text-center p-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto d-flex flex-wrap align-items-center gap-3 small text-muted">
                        <span>Légende :</span>
                        <span class="legend-item"><span class="legend-color-box placeClosed"></span>Fermé</span>
                        <span class="legend-item"><span class="legend-color-box placePMR"></span>PMR</span>
                        <span class="legend-item"><span class="legend-color-box placeVIP"></span>VIP</span>
                        <span class="legend-item"><span class="legend-color-box placeBenevole"></span>Bénévole</span>
                        <span class="legend-item"><span class="legend-color-box placeVide"></span>Libre</span>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modalEl);
    modalInstance = new bootstrap.Modal(modalEl);
    return modalEl;
}

/**
 * Met à jour l'apparence (classe CSS) d'une cellule de siège en fonction de l'état de ses attributs.
 * @param {number} seatId L'ID du siège à mettre à jour.
 */
function updateSeatAppearance(seatId) {
    const td = document.querySelector(`td[data-seat-id="${seatId}"]`);
    if (!td) return;

    // Récupérer l'état actuel de toutes les checkboxes pour ce siège
    const isClosed = document.getElementById(`seat-${seatId}-is_open`)?.checked;
    const isPmr = document.getElementById(`seat-${seatId}-is_pmr`)?.checked;
    const isVip = document.getElementById(`seat-${seatId}-is_vip`)?.checked;
    const isVolunteer = document.getElementById(`seat-${seatId}-is_volunteer`)?.checked;

    // Déterminer la nouvelle classe en respectant la priorité d'affichage
    let newClass = 'tdplaceVide'; // Classe par défaut si ouvert et sans attribut
    if (isClosed) {
        newClass = 'tdplaceClosed';
    } else if (isPmr) {
        newClass = 'tdplacePMR';
    } else if (isVip) {
        newClass = 'tdplaceVIP';
    } else if (isVolunteer) {
        newClass = 'tdplaceBenevole';
    }

    // Retirer toutes les classes de statut précédentes
    td.classList.remove(
        'tdplaceClosed',
        'tdplacePMR',
        'tdplaceVIP',
        'tdplaceBenevole',
        'tdplaceVide'
    );
    // Ajouter la nouvelle classe
    td.classList.add(newClass);
}

/**
 * Gère le changement d'état d'une case à cocher pour un siège.
 * @param {number} seatId L'ID du siège.
 * @param {string} property La propriété à modifier (ex: 'is_open').
 * @param {boolean} value La nouvelle valeur.
 */
async function handleAttributeChange(seatId, property, value) {
    const checkbox = document.getElementById(`seat-${seatId}-${property}`);
    if (checkbox) checkbox.disabled = true;

    try {
        const response = await apiPost('/gestion/piscines/gradins/update-attribute', {
            seatId: seatId,
            attribute: property,
            value: value
        });

        if (!response.success) {
            throw new Error(response.message || 'Erreur inconnue du serveur.');
        }
        // Le changement est confirmé, on met à jour l'apparence de la cellule.
        updateSeatAppearance(seatId);

    } catch (error) {
        console.error(`Erreur lors de la mise à jour du siège ${seatId}:`, error);
        alert(`La mise à jour a échoué : ${error.message}`);
        // Annuler le changement visuel en cas d'erreur
        if (checkbox) checkbox.checked = !value;
    } finally {
        if (checkbox) checkbox.disabled = false;
    }
}

/**
 * Charge et affiche toutes les zones de gradins pour une piscine.
 * @param {HTMLElement} container - Le conteneur où afficher les gradins.
 */
async function loadAllZones(container) {
    container.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement des zones...</span></div></div>';

    try {
        // 1. Récupérer la liste des zones pour la piscine
        const zonesResponse = await apiGet(`/piscine/${currentPiscineId}/zones`);
        if (!zonesResponse.success || !Array.isArray(zonesResponse.zones)) {
            throw new Error('Impossible de récupérer la liste des zones.');
        }

        // 2. Charger le plan de chaque zone et le rendre
        const zonePromises = zonesResponse.zones.map(zone => apiGet(`/piscine/gradins/${currentPiscineId}/${zone.id}`));
        const structurePlanResponses = await Promise.all(zonePromises);

        container.innerHTML = ''; // Vider le spinner

        structurePlanResponses.forEach(response => {
            if (response.success && response.plan) {
                const zoneContainer = document.createElement('div');
                zoneContainer.className = 'mb-5';

                const title = document.createElement('h4');
                title.className = 'zone-title mb-3';
                title.textContent = `Zone : ${response.plan.zone.zoneName}`;
                zoneContainer.appendChild(title);

                const gridContainer = document.createElement('div');
                gridContainer.dataset.bleacherSeats = 'true';
                gridContainer.classList.add('mode-management'); // Ajout de la classe pour le mode gestion
                zoneContainer.appendChild(gridContainer);

                container.appendChild(zoneContainer);

                // Créer la grille en mode 'management'
                createBleacherGrid(gridContainer, response.plan, {
                    mode: 'management',
                    onAttributeChange: handleAttributeChange
                });
            }
        });

    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

/**
 * Ouvre et initialise la modale de gestion des gradins.
 * @param {number} piscineId - ID de la piscine.
 */
function openGradinsManagementModal(piscineId) {
    currentPiscineId = piscineId;
    if (!currentPiscineId) return;

    const modalEl = ensureModalDOM();
    const modalBody = modalEl.querySelector('#gradins-management-modal-body');
    modalInstance.show();
    loadAllZones(modalBody);
}

/**
 * Initialise les écouteurs d'événements pour les boutons de gestion.
 */
export function initGradinsManagement() {
    ensureModalDOM(); // Prépare la modale au chargement de la page
    document.body.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action="manage-bleachers"]');
        if (target) {
            e.preventDefault(); // Empêche le comportement par défaut du lien
            const piscineId = target.dataset.piscineId;
            openGradinsManagementModal(piscineId);
        }
    });
}