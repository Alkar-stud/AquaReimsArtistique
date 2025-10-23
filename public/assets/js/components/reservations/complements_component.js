(function (global, App) {
    'use strict';

    App.Components = App.Components || {};

    // --- Variables privées ---
    let _containerEl, _listEl;
    let _isInitialized = false;
    let _isModalContext = false;
    let _reservationToken = null;
    let _reservationId = null;

    // --- Fonctions privées ---
    function _cacheDOMElements(containerEl) {
        _containerEl = containerEl;
        _listEl = _containerEl.querySelector('#modal-complements-list') || _containerEl.querySelector('.list-group.mb-3');
    }

    function _handleActionClick(event) {
        const button = event.target.closest('.complement-qty-btn, .add-complement-btn');
        if (!button) return;

        event.preventDefault();

        const action = button.dataset.action;
        const complementId = button.dataset.complementId; // Pour +/-
        const tarifId = button.dataset.tarifId; // Pour ajout

        let confirmationMessage = "Confirmez-vous cette action ? Le montant total sera mis à jour.";
        if (action === 'minus') {
            const qtyInput = _containerEl.querySelector(`#qty-complement-${complementId}`);
            const currentQty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
            confirmationMessage = (currentQty <= 1)
                ? "Souhaitez-vous vraiment supprimer cet élément ? Le trop perçu ne sera pas remboursé."
                : "Souhaitez-vous vraiment retirer 1 ticket de cet élément ?";
        }

        if (!confirm(confirmationMessage)) {
            return;
        }

        const data = {
            typeField: 'complement',
            token: _reservationToken,
            reservationId: _reservationId,
            id: complementId, // ID de la ligne reservation_complements
            tarifId: tarifId, // ID du tarif pour un nouvel ajout
            action: action
        };

        button.disabled = true;

        // On choisit la bonne méthode d'appel API en fonction du contexte.
        // global.updateField est un wrapper qui gère le rechargement de page pour modifData.
        // global.apiPost est utilisé pour la modale pour permettre la mise à jour dynamique.
        const apiPromise = _isModalContext
            ? global.apiPost('/modifData/update', data)
            : global.updateField(null, data);

        apiPromise.then(result => {
            if (result && result.success) {
                // La mise à jour dynamique ne se fait que dans la modale
                // et seulement si le serveur ne demande pas un rechargement.
                // (Avec la modification du backend, result.reload ne sera plus envoyé pour les compléments)
                if (_isModalContext && !result.reload) {
                    _updateDynamicUI(result, complementId);
                }
            } else if (!result || !result.success) {
                // Gérer le cas où updateField ne renvoie pas d'erreur mais un échec
                alert(result.message || 'L\'opération a échoué.');
            } else {
                // Si c'est modifData et que l'opération a réussi, updateField aura déjà rechargé la page.
                // Si c'est la modale et qu'il n'y a pas de mise à jour dynamique, on ne fait rien ici.
            }
        })
            .catch(err => {
                alert(err.userMessage || 'Une erreur est survenue.');
            })
            .finally(() => {
                // Pour la modale, on le réactive.
                button.disabled = false;
            });
    }

    function _updateDynamicUI(result, complementId) {
        // Si la nouvelle quantité est 0, on supprime la ligne du DOM.
        if (result.newQuantity <= 0) {
            const complementRow = _listEl.querySelector(`[data-complement-row-id="${complementId}"]`);
            if (complementRow) {
                complementRow.remove();
            }
        } else {
            // Sinon, on met à jour la quantité et le total de la ligne.
            const qtyInput = _listEl.querySelector(`#qty-complement-${complementId}`);
            if (qtyInput) {
                qtyInput.value = result.newQuantity;
            }

            const totalEl = _listEl.querySelector(`.complement-total[data-complement-id="${complementId}"]`);
            if (totalEl && typeof result.groupTotalCents !== 'undefined') {
                totalEl.textContent = global.formatEuroCents(result.groupTotalCents);
            }
        }

        // Mettre à jour les totaux globaux de la modale
        if (result.totals) {
            const modal = document.getElementById('reservationDetailModal');
            if (!modal) return;

            const totalCostEl = modal.querySelector('#modal-total-cost');
            const amountPaidEl = modal.querySelector('#modal-amount-paid');
            const amountDueEl = modal.querySelector('#modal-amount-due');
            const markAsPaidDiv = modal.querySelector('#div-modal-mark-as-paid');

            // Fonction locale pour formater les nombres sans le symbole euro
            const formatNumber = (cents) => (cents / 100).toFixed(2).replace('.', ',');

            if (totalCostEl) totalCostEl.textContent = formatNumber(result.totals.totalAmount);
            if (amountPaidEl) amountPaidEl.textContent = formatNumber(result.totals.totalPaid);
            if (amountDueEl) {
                const amountDue = result.totals.amountDue;
                amountDueEl.textContent = formatNumber(amountDue);
                // On change la couleur en fonction du solde
                amountDueEl.classList.toggle('text-danger', amountDue > 0);
                amountDueEl.classList.toggle('text-info', amountDue < 0);
                amountDueEl.classList.toggle('text-success', amountDue === 0);
            }
            if (markAsPaidDiv) {
                markAsPaidDiv.classList.toggle('d-none', result.totals.amountDue <= 0);
            }
        }
    }

    // --- API Publique ---
    App.Components.Complements = {
        init: function(containerEl, options = {}) {
            if (_isInitialized) return;

            _cacheDOMElements(containerEl);
            _isModalContext = options.isModal || false;
            _reservationToken = options.token;
            _reservationId = options.reservationId;

            if (_containerEl) {
                _containerEl.addEventListener('click', _handleActionClick);
            }

            _isInitialized = true;
        },

        updateUI: function(reservationData) {
            if (!_listEl) return;
            _listEl.innerHTML = ''; // Vider

            const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

            (reservationData.complements || []).forEach(c => {
                const qty = parseInt(c.quantity || 0, 10);
                const priceCents = parseInt(c.tarifPrice ?? c.price ?? 0, 10);
                const maxQty = (c.maxForThisPrice === null || c.maxForThisPrice === undefined) ? null : parseInt(c.maxForThisPrice, 10);
                const showPlus = !(maxQty !== null && qty >= maxQty);

                _listEl.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center" data-complement-wrapper-id="${c.tarifId}" data-complement-row-id="${c.id}">
                        <div>
                            <strong>${esc(c.tarifName)}</strong>
                            ${c.tarifDescription ? `<div class="text-muted small">${esc(c.tarifDescription)}</div>` : ''}
                        </div>
                        <div class="text-end" style="min-width:260px;">
                            <div class="d-flex align-items-center justify-content-end" style="gap:0.5rem;">
                                <div class="text-muted small me-2" style="min-width:90px; text-align:right;">${global.formatEuroCents(priceCents)} x </div>
                                <div class="input-group input-group-sm" style="max-width:160px;">
                                    <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="minus" data-complement-id="${c.id}">-</button>
                                    <input type="text" class="form-control text-center" id="qty-complement-${c.id}" value="${qty}" readonly>
                                    ${showPlus ? `<button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="plus" data-complement-id="${c.id}">+</button>` : ''}
                                </div>
                            </div>
                            <div class="mt-1">
                                <strong class="complement-total" data-complement-id="${c.id}">${global.formatEuroCents(qty * priceCents)}</strong>
                            </div>
                        </div>
                    </div>`;
            });
        }
    };

})(window, window.App || (window.App = {}));
