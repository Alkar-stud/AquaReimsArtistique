'use strict';

import { apiPost } from '../components/apiClient.js';
import { buttonLoading } from '../components/utils.js';
import { showFlashMessage } from '../components/ui.js';
import { formatEuro } from '../components/utils.js'; // Formatter pour euro

/**
 * Initialise la logique du code spécial pour l'étape 3.
 * @param {object} config - Configuration pour le gestionnaire.
 * @param {HTMLElement} config.container - Le conteneur principal des éléments du formulaire.
 * @param {HTMLElement} config.validateCodeBtn - Le bouton de validation du code.
 * @param {HTMLElement} config.specialCodeInput - L'input du code spécial.
 * @param {HTMLElement} config.specialCodeFeedback - L'élément pour le feedback du code.
 * @param {HTMLElement} config.specialTarifContainer - Le conteneur pour afficher le tarif spécial.
 * @param {string} config.eventId - L'ID de l'événement.
 * @param {object|null} config.initialSpecialTarifSession - Les données du tarif spécial pré-remplies.
 * @param {function} config.updateSubmitState - Fonction pour mettre à jour l'état du bouton de soumission.
 * @param {boolean} config.withSeat - Indique si le tarif spécial est pour des places assises (true pour etape3, false pour etape6).
 */
export function initSpecialTarifHandler(config) {
    const {
        container,
        validateCodeBtn,
        specialCodeInput,
        specialCodeFeedback,
        specialTarifContainer,
        eventId,
        initialSpecialTarifSession,
        updateSubmitState
        , withSeat
    } = config; // Ajout de withSeat

    let currentSpecialTarifSession = initialSpecialTarifSession;

    function renderSpecialTarifBlock(t) {
        if (!specialTarifContainer || !t) return;
        const seatInfo = t.seat_count ? ` (${t.seat_count} place${t.seat_count > 1 ? 's' : ''} incluse${t.seat_count > 1 ? 's' : ''})` : '';
        const priceInfo = typeof t.price !== 'undefined' ? ` - ${formatEuro(t.price)}` : '';
        const desc = t.description ? `<div class="text-muted small mb-1">${String(t.description).replace(/\n/g, '<br>')}</div>` : '';

        specialTarifContainer.innerHTML = `
          <div class="alert alert-success mb-2">
            Tarif spécial reconnu : <strong>${(t.name || 'Tarif spécial')}</strong>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="specialTarifCheck" name="specialTarif[${t.id}]" checked>
            <label class="form-check-label" for="specialTarifCheck">
              Utiliser ce tarif spécial${seatInfo}${priceInfo}
            </label>
            <input type="hidden"
                   id="tarif_${t.id}"
                   name="tarifs[${t.id}]"
                   value="1">
          </div>
          ${desc}
        `;

        const cb = document.getElementById('specialTarifCheck');
        const hidden = document.getElementById(`tarif_${t.id}`);
        if (cb && hidden) hidden.disabled = !cb.checked;

        updateSubmitState();
    }

    // Préremplissage si déjà en session
    if (currentSpecialTarifSession) {
        const t = currentSpecialTarifSession;
        if (specialCodeInput) specialCodeInput.value = t.code || '';
        if (specialCodeInput) specialCodeInput.disabled = true;
        if (validateCodeBtn) validateCodeBtn.disabled = true;
        if (specialCodeFeedback) {
            specialCodeFeedback.classList.remove('text-danger');
            specialCodeFeedback.classList.add('text-success');
            specialCodeFeedback.textContent = 'Code validé.';
        }
        renderSpecialTarifBlock(t);
    }

    // Validation du code spécial (AJAX)
    if (validateCodeBtn && specialCodeInput) {
        validateCodeBtn.addEventListener('click', async () => {
            const code = specialCodeInput.value.trim();
            if (!code || !eventId) {
                if (specialCodeFeedback) {
                    specialCodeFeedback.classList.remove('text-success');
                    specialCodeFeedback.classList.add('text-danger');
                    specialCodeFeedback.textContent = 'Veuillez saisir un code et avoir un événement sélectionné.';
                }
                return;
            }

            buttonLoading(validateCodeBtn, true);
            specialCodeFeedback.textContent = '';

            try {
                const res = await apiPost('/reservation/validate-special-code', { event_id: eventId, code, with_seat: withSeat });
                if (!res || !res.success) {
                    throw new Error(res?.error || 'Code invalide.');
                }

                currentSpecialTarifSession = {
                    id: res.tarif.id,
                    name: res.tarif.name,
                    description: res.tarif.description,
                    seat_count: res.tarif.seat_count,
                    price: res.tarif.price,
                    code
                };

                specialCodeInput.disabled = true;
                specialCodeFeedback.classList.remove('text-danger');
                specialCodeFeedback.classList.add('text-success');
                specialCodeFeedback.textContent = 'Code validé.';

                renderSpecialTarifBlock(currentSpecialTarifSession);
            } catch (e) {
                specialCodeFeedback.classList.remove('text-success');
                specialCodeFeedback.classList.add('text-danger');
                specialCodeFeedback.textContent = e.message || 'Erreur lors de la validation du code.';
            } finally {
                buttonLoading(validateCodeBtn, false);
            }
        });
    }

    // Écoute le (dé)cochage du tarif spécial
    container.addEventListener('change', (e) => {
        if (e.target && e.target.id === 'specialTarifCheck') {
            const tarifId = currentSpecialTarifSession?.id;
            if (!tarifId) return;

            apiPost('/reservation/remove-special-tarif', { tarif_id: tarifId })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showFlashMessage('danger', data.error || "Erreur lors de la suppression du tarif spécial.");
                    }
                })
                .catch((err) => {
                    showFlashMessage('danger', err.userMessage || err.message);
                    window.location.reload();
                });

            const hidden = document.querySelector('#specialTarifContainer input[type="hidden"][id^="tarif_"]');
            if (hidden) hidden.disabled = !e.target.checked;
            updateSubmitState();
        }
    });

    // Expose currentSpecialTarifSession for buildReservationPayload
    return {
        getSpecialTarifSession: () => currentSpecialTarifSession,
        hasSpecialSelection: () => {
            const cb = document.getElementById('specialTarifCheck');
            return !!(cb && cb.checked);
        }
    };
}