import { apiPost } from '../components/apiClient.js';
// La fonction de mise à jour de l'UI des paiements sera importée et utilisée par d'autres modules.
// Pour l'instant, nous laissons le paymentManager gérer cela.

// Fonction utilitaire pour formater les nombres sans le symbole euro, accessible à tout le module
const formatNumber = (cents) => (cents / 100).toFixed(2).replace('.', ',');

/**
 * Initialise la logique de gestion des compléments.
 * Gère l'affichage, les actions +/- et l'ajout de compléments.
 * @param {string} options.apiUrl - L'URL de l'API à appeler pour la mise à jour.
 * @param {string} options.reservationIdentifier - Le token ou l'ID de la réservation.
 * @param {string} options.identifierType - 'token' ou 'reservationId'.
 * @param {boolean} options.isModalContext - Indique si le composant est utilisé dans une modale.
 */
function init(options) {
    // Le conteneur principal dépend du contexte
    const complementsContainer = options.isModalContext
        ? document.getElementById('modal-complements-section') // Pour la modale
        : document.getElementById('complements-section');     // Pour modif_data

    if (!complementsContainer) {
        return;
    }

    // Utilisation de la délégation d'événements sur le conteneur principal
    complementsContainer.addEventListener('click', async (event) => {
        const button = event.target.closest('.complement-qty-btn, .add-complement-btn');
        if (!button) {
            return;
        }

        event.preventDefault();

        const action = button.dataset.action || 'plus'; // L'ajout est une action 'plus'
        const complementId = button.dataset.complementId; // ID de la ligne reservation_complements
        const tarifId = button.dataset.tarifId; // ID du tarif pour un nouvel ajout

        let confirmationMessage = "Confirmez-vous cette action ? Le montant total sera mis à jour.";
        if (action === 'minus') {
            const qtyInput = complementsContainer.querySelector(`#qty-complement-${complementId}`);
            const currentQty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
            confirmationMessage = (currentQty <= 1)
                ? "Souhaitez-vous vraiment supprimer cet élément ? Le trop perçu ne sera pas remboursé."
                : "Souhaitez-vous vraiment retirer 1 ticket de cet élément ?";
        }

        if (!confirm(confirmationMessage)) {
            return;
        }

        const payload = {
            typeField: 'complement',
            id: complementId, // Pour +/-
            tarifId: tarifId,   // Pour l'ajout
            action: action,
        };

        // Ajouter l'identifiant de la réservation au payload
        if (options.identifierType === 'token') {
            payload.token = options.reservationIdentifier;
        } else if (options.identifierType === 'reservationId') {
            payload.reservationId = options.reservationIdentifier;
        }

        button.disabled = true; // Désactiver le bouton pendant l'appel API

        try {
            const response = await apiPost(options.apiUrl, payload);

            if (response.success) {
                if (options.isModalContext) {
                    _updateComplementLineUI(complementsContainer, response, complementId);
                    _updateModalTotals(response);
                } else {
                    // Pour modif_data, on recharge toujours la page pour garantir la cohérence des totaux.
                    // Le backend renvoie déjà reload:true pour l'ajout et la suppression.
                    // On force le rechargement pour les +/-.
                    window.location.reload();
                }
            } else {
                alert(response.message || 'Échec de l\'opération.');
            }
        } catch (error) {
console.log('erreur : ', error);
            alert(error.userMessage || 'Erreur de communication.');
        } finally {
            button.disabled = false; // Réactiver le bouton
        }
    });
}

/**
 * Met à jour une ligne de complément dans l'UI (quantité, total, ou suppression).
 * @param {HTMLElement} complementsContainer - Le conteneur des compléments.
 * @param {object} response - La réponse de l'API.
 * @param {string} complementId - L'ID du complément modifié.
 */
function _updateComplementLineUI(complementsContainer, response, complementId) {
    if (!complementId) return; // Ne s'applique pas à un nouvel ajout qui recharge la page

    // Si la nouvelle quantité est 0, on supprime la ligne du DOM.
    if (response.newQuantity <= 0) {
        const complementRow = complementsContainer.querySelector(`[data-complement-row-id="${complementId}"]`);
        if (complementRow) {
            complementRow.remove();
        }
    } else {
        // Sinon, on met à jour la quantité et le total de la ligne.
        const qtyInput = complementsContainer.querySelector(`#qty-complement-${complementId}`);
        if (qtyInput) {
            qtyInput.value = response.newQuantity;
        }

        const totalEl = complementsContainer.querySelector(`.complement-total[data-complement-id="${complementId}"]`);
        if (totalEl && typeof response.groupTotalCents !== 'undefined') {
            totalEl.textContent = formatNumber(response.groupTotalCents);
        }
    }
}

/**
 * Met à jour les totaux globaux dans la modale.
 * @param {object} response - La réponse de l'API.
 */
function _updateModalTotals(response) {
    if (response.totals) {
        const modal = document.getElementById('reservationDetailModal');
        if (!modal) {
            return;
        }

        const totalCostEl = modal.querySelector('#modal-total-cost');
        const amountPaidEl = modal.querySelector('#modal-amount-paid');
        const amountDueEl = modal.querySelector('#modal-amount-due');
        const markAsPaidDiv = modal.querySelector('#div-modal-mark-as-paid');

        if (totalCostEl) totalCostEl.textContent = formatNumber(response.totals.totalAmount);
        if (amountPaidEl) amountPaidEl.textContent = formatNumber(response.totals.totalPaid);
        if (amountDueEl) {
            const amountDue = response.totals.amountDue;
            amountDueEl.textContent = formatNumber(amountDue);
            // On change la couleur en fonction du solde
            amountDueEl.classList.toggle('text-danger', amountDue > 0);
            amountDueEl.classList.toggle('text-info', amountDue < 0);
            amountDueEl.classList.toggle('text-success', amountDue === 0);
        }
        if (markAsPaidDiv) {
            markAsPaidDiv.classList.toggle('d-none', response.totals.amountDue <= 0);
        }
    }
}


/**
 * Met à jour l'UI avec les données des compléments.
 * Cette fonction est utilisée par la modale pour construire la liste dynamiquement.
 * @param {HTMLElement} containerEl - Le conteneur où se trouvent les compléments.
 * @param {object} reservationData - Les données complètes de la réservation.
 * @param {boolean} isReadOnly - Indique si les champs doivent être en lecture seule.
 */
function updateUI(containerEl, reservationData, isReadOnly = false) {
    if (!containerEl) {
        return;
    }

    const listEl = containerEl.querySelector('#modal-complements-list') || containerEl;

    listEl.innerHTML = ''; // Vider la liste

    const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    (reservationData.complements || []).forEach(c => {
        const qty = parseInt(c.quantity || 0, 10);
        const priceCents = parseInt(c.tarifPrice ?? c.price ?? 0, 10);
        const maxQty = (c.maxForThisPrice === null || c.maxForThisPrice === undefined) ? null : parseInt(c.maxForThisPrice, 10);
        const showPlus = !(maxQty !== null && qty >= maxQty);

        listEl.innerHTML += `
             <div class="list-group-item d-flex justify-content-between align-items-center" data-complement-row-id="${c.id}">
                 <div>
                    <strong>${esc(c.tarifName)}</strong>
                    ${c.tarifDescription ? `<div class="text-muted small">${esc(c.tarifDescription)}</div>` : ''}
                </div>
                <div class="text-end" style="min-width:260px;">
                    <div class="d-flex align-items-center justify-content-end" style="gap:0.5rem;">
                        <div class="text-muted small me-2" style="min-width:90px; text-align:right;">${formatNumber(priceCents)} € x </div>
                        <div class="input-group input-group-sm" style="max-width:160px;">
                            <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="minus" data-complement-id="${c.id}" ${isReadOnly ? 'disabled' : ''}>-</button>
                            <input type="text" class="form-control text-center" id="qty-complement-${c.id}" value="${qty}" readonly>
                            ${showPlus && !isReadOnly ? `<button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="plus" data-complement-id="${c.id}">+</button>` : ''}
                        </div>
                    </div>
                    <div class="mt-1">
                        <strong class="complement-total" data-complement-id="${c.id}">${formatNumber(qty * priceCents)} €</strong>
                    </div>
                </div>
            </div>`;
    });
}

export { init as initComplementsForm, updateUI as updateComplementsUI };
