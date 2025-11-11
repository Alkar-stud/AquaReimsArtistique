import { showFeedback } from '../components/ui.js';
import { apiPost } from '../components/apiClient.js';

/**
 * Initialise la logique du formulaire des participants.
 * Sont modifiables noms et prénoms, et le numéro de place pour la partie gestion
 * Trouve les champs et attache les écouteurs d'événements.
 * @param {string} options.apiUrl - L'URL de l'API à appeler pour la mise à jour.
 * @param {string} options.reservationIdentifier - Le token ou l'ID de la réservation.
 * @param {string} options.identifierType - 'token' ou 'reservationId'.
 */
function init(options) {
    const participantsContainer = document.getElementById('participants-container');
    if (!participantsContainer) {
        // Si le conteneur n'est pas sur la page, on ne fait rien.
        return;
    }

    // On cible tous les champs éditables des participants
    const editableInputs = participantsContainer.querySelectorAll('.editable-detail');

    editableInputs.forEach(input => {
        input.addEventListener('blur', (event) => {
            const input = event.target;
            const field = input.dataset.field;
            const value = input.value;
            const detailId = input.dataset.detailId; // ID du détail de la réservation

            // Trouver le span de feedback associé à cet input
            const feedbackSpan = input.closest('.input-group')?.querySelector('.feedback-span');

            // --- Validation conditionnelle (si nécessaire, pour l'instant aucune) ---
            // if (field === 'place_number' && !validatePlaceNumber(value)) {
            //     showFeedback(feedbackSpan, 'error', 'Numéro de place invalide.');
            //     return;
            // }

            // --- Appel API ---
            showFeedback(feedbackSpan, 'pending', 'Enregistrement...');

            const payload = {
                typeField: 'detail', // Indique au backend que c'est une modification de détail
                id: detailId,       // L'ID du détail à modifier
                field: field,       // Le champ spécifique (name, firstname, place_number)
                value: value
            };

            // Ajouter l'identifiant de la réservation au payload
            if (options.identifierType === 'token') {
                payload.token = options.reservationIdentifier;
            } else if (options.identifierType === 'reservationId') {
                payload.reservationId = options.reservationIdentifier;
            }

            apiPost(options.apiUrl, payload)
                .then(response => {
                    if (response.success) {
                        showFeedback(feedbackSpan, 'success', 'Enregistré');
                    } else {
                        showFeedback(feedbackSpan, 'error', response.message || 'Échec de l\'enregistrement');
                    }
                })
                .catch(error => {
                    showFeedback(feedbackSpan, 'error', error.userMessage || 'Erreur de communication');
                });
        });
    });
}

/**
 * Met à jour l'UI avec les données des participants.
 * Cette fonction est utilisée par la modale pour construire la liste dynamiquement.
 * @param {HTMLElement} containerEl - Le conteneur où se trouvent les participants.
 * @param {object} reservationData - Les données complètes de la réservation.
 * @param {boolean} isReadOnly - Indique si les champs doivent être en lecture seule.
 */
function updateUI(containerEl, reservationData, isReadOnly = false) {
    if (!containerEl) {
        return;
    }

    containerEl.innerHTML = ''; // Vider la liste

    const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // On groupe les participants par tarif.
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
            // Préparer le champ place_number
            const placeNumberInput = p.placeNumber ?
                `<div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">Place</span><input type="text" class="form-control editable-detail" value="${esc(p.placeNumber)}" ${isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="place_number"><span class="input-group-text feedback-span"></span></div></div>` :
                ''; // Si pas de place_number, on n'affiche pas le champ

            participantsHtml += `
                 <div class="row g-2 mb-2">
                     <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">Nom</span><input type="text" class="form-control editable-detail" value="${esc(p.name || '')}" ${isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="name"><span class="input-group-text feedback-span"></span></div></div>
                     <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">Prénom</span><input type="text" class="form-control editable-detail" value="${esc(p.firstname || '')}" ${isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="firstname"><span class="input-group-text feedback-span"></span></div></div>
                     ${placeNumberInput}
                 </div>`;
        });

        containerEl.innerHTML += `<div class="list-group-item"><strong>${group.participants.length} × ${esc(group.tarifName)}</strong>${group.tarifDescription ? `<div class="text-muted small">${esc(group.tarifDescription)}</div>` : ''}<div class="mt-2">${participantsHtml}</div></div>`;
    }
}

export { init as initParticipantsForm, updateUI as updateParticipantsUI };
