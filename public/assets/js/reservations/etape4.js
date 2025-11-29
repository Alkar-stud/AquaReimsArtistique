'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';
import { validateAllParticipants, initParticipantFieldListeners } from './participantFormValidator.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationPlacesForm');
    if (!form) return;

    // Initialise la validation en direct sur les champs
    initParticipantFieldListeners(form);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitButton = document.getElementById('submitButton');
        if (submitButton) submitButton.disabled = true;

        // Utilise le nouveau validateur
        const validationResult = validateAllParticipants(form);

        if (!validationResult.isValid || !form.checkValidity()) {
            showFlashMessage('danger', 'Veuillez corriger les erreurs dans le formulaire.<br>' + validationResult.errors.join('<br>'));
            if (submitButton) submitButton.disabled = false;
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
            return;
        }

        // Construction du FormData
        const formData = new FormData();
        const participantsData = {};
        const participantRows = form.querySelectorAll('.participant-row');

        participantRows.forEach((row, index) => {
            const detailId = row.querySelector('input[name="detail_ids[]"]').value;
            const name = row.querySelector('input[name="names[]"]').value.trim();
            const firstname = row.querySelector('input[name="firstnames[]"]').value.trim();
            const justificatifInput = row.querySelector('input[name="justificatifs[]"]');

            participantsData[detailId] = { name, firstname };

            if (justificatifInput && justificatifInput.files && justificatifInput.files[0]) {
                // On associe le fichier à l'ID du détail
                formData.append(`justificatifs[${detailId}]`, justificatifInput.files[0]);
            }
        });

        formData.append('participants', JSON.stringify(participantsData));

        try {
            const data = await apiPost('/reservation/valid/4', formData);
            if (data.success) {
                if (data.numerated_seat === true) {
                    window.location.href = '/reservation/etape5Display';
                } else {
                    window.location.href = '/reservation/etape6Display';
                }
            } else {
                throw new Error(data.error || 'Une erreur est survenue.');
            }
        } catch (err) {
            showFlashMessage('danger', err.userMessage || err.message);
            if (submitButton) submitButton.disabled = false;
        }
    });
});
