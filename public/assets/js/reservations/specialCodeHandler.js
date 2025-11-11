'use strict';

import { apiPost } from '../components/apiClient.js';
import { buttonLoading } from '../components/utils.js';

/**
 * Initialise la logique de validation du code d'accès pour un événement.
 * @param {HTMLElement} eventCard - La carte de l'événement contenant les éléments.
 */
export function initAccessCodeHandler(eventCard) {
    const eventId = eventCard.dataset.eventId;
    const codeInput = eventCard.querySelector(`#access_code_input_${eventId}`);
    const validateBtn = eventCard.querySelector(`#validate_code_btn_${eventId}`);
    const statusEl = eventCard.querySelector(`#access_code_status_${eventId}`);
    const reserveBtn = eventCard.querySelector(`#btn_reserver_${eventId}`);

    if (!codeInput || !validateBtn || !statusEl || !reserveBtn) return;

    validateBtn.addEventListener('click', async () => {
        const code = codeInput.value.trim();
        if (!code) {
            statusEl.textContent = "Veuillez saisir un code.";
            statusEl.className = 'text-danger ms-2';
            return;
        }

        buttonLoading(validateBtn, true);
        statusEl.textContent = '';

        try {
            const response = await apiPost('/reservation/validate-access-code', { event_id: eventId, code });
            if (response.success) {
                statusEl.textContent = "Code valide !";
                statusEl.className = 'text-success ms-2';
                codeInput.disabled = true;
                validateBtn.disabled = true;
                reserveBtn.disabled = false; // On active le bouton "Réserver"
            } else {
                throw new Error(response.error || "Code invalide ou une erreur est survenue.");
            }
        } catch (error) {
            statusEl.textContent = error.message || 'Erreur de validation.';
            statusEl.className = 'text-danger ms-2';
        } finally {
            buttonLoading(validateBtn, false);
        }
    });
}