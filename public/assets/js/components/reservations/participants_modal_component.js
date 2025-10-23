(function (global, App) {
    'use strict';

    App.Components = App.Components || {};

    // --- Variables privées ---
    let _containerEl, _listEl;
    let _isInitialized = false;
    let _isReadOnly = false;

    // --- Fonctions privées ---

    /**
     * Met en cache les éléments DOM pertinents.
     * @param {HTMLElement} containerEl - Le conteneur principal du composant.
     */
    function _cacheDOMElements(containerEl) {
        _containerEl = containerEl;
        // On vérifie si le conteneur lui-même est la liste que l'on cherche.
        // C'est le cas pour modif_data.tpl où on passe directement l'élément avec id="participants-container".
        if (containerEl.matches('#participants-container, #modal-participants-list')) {
            _listEl = containerEl;
        } else {
            // Sinon (cas de la modale), on cherche la liste à l'intérieur du conteneur.
            _listEl = _containerEl.querySelector('#modal-participants-list');
        }
    }

    /**
     * Gère l'événement 'blur' sur les champs des participants (délégation).
     * @param {Event} event
     */
    function _handleBlur(event) {
        const input = event.target;
        // On s'assure que l'événement vient bien d'un champ de participant
        if (!input.matches('.editable-detail')) {
            return;
        }

        const feedbackSpan = input.parentElement.querySelector('.feedback-span');
        const field = input.dataset.field;
        const value = input.value;
        const detailId = input.dataset.detailId;

        // Le token est soit dans un input caché (modale), soit sur un conteneur parent (modif_data)
        const tokenEl = document.querySelector('#modal_reservation_token') || document.querySelector('[data-token]');
        const reservationToken = tokenEl ? (tokenEl.value || tokenEl.dataset.token) : null;

        if (!reservationToken || !detailId) {
            console.error("Token de réservation ou ID de détail manquant.");
            return;
        }

        const data = {
            typeField: 'detail',
            token: reservationToken,
            id: detailId,
            field: field,
            value: value
        };

        global.updateField(feedbackSpan, data);
    }

    // --- API Publique du composant ---
    App.Components.Participants = {
        /**
         * Initialise le composant.
         * @param {HTMLElement} containerEl - Le conteneur où se trouvent les participants.
         * @param {boolean} isReadOnly - Indique si les champs doivent être en lecture seule.
         */
        init: function(containerEl, isReadOnly = false) {
            if (_isInitialized) return;

            _cacheDOMElements(containerEl);
            _isReadOnly = isReadOnly;

            if (_listEl) {
                // On utilise la délégation d'événement pour gérer les 'blur'
                _listEl.addEventListener('blur', _handleBlur, true); // 'true' pour la phase de capture
            }

            _isInitialized = true;
        },

        /**
         * Met à jour l'UI avec les données des participants.
         * (Cette fonction est surtout utile pour la modale qui se remplit dynamiquement)
         * @param {object} reservationData - Les données complètes de la réservation.
         */
        updateUI: function(reservationData) {
            if (!_listEl) return;

            _listEl.innerHTML = ''; // Vider la liste

            // On groupe les participants par tarif (logique reprise de reservations.js)
            const participantsByTarif = (reservationData.details || []).reduce((acc, detail) => {
                const tarifId = detail.tarifId || detail.tarif; // Gère les deux formats de données
                if (!acc[tarifId]) {
                    acc[tarifId] = {
                        tarifName: detail.tarifName,
                        tarifDescription: detail.tarifDescription || '',
                        participants: []
                    };
                }
                acc[tarifId].participants.push(detail);
                return acc;
            }, {});

            for (const tarifId in participantsByTarif) {
                const group = participantsByTarif[tarifId];
                let participantsHtml = '';
                group.participants.forEach(p => {
                    participantsHtml += `
                         <div class="row g-2 mb-2">
                             <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">Nom</span><input type="text" class="form-control editable-detail" value="${p.name || ''}" ${_isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="name"><span class="input-group-text feedback-span"></span></div></div>
                             <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">Prénom</span><input type="text" class="form-control editable-detail" value="${p.firstname || ''}" ${_isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="firstname"><span class="input-group-text feedback-span"></span></div></div>
                         </div>`;
                });

                _listEl.innerHTML += `<div class="list-group-item"><strong>${group.participants.length} × ${group.tarifName}</strong>${group.tarifDescription ? `<div class="text-muted small">${group.tarifDescription}</div>` : ''}<div class="mt-2">${participantsHtml}</div></div>`;
            }
        }
    };

})(window, window.App || (window.App = {}));