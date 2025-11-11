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
        const participants = [];
        const nameInputs = form.querySelectorAll('input[name="names[]"]');
        const firstnameInputs = form.querySelectorAll('input[name="firstnames[]"]');
        const justificatifInputs = form.querySelectorAll('input[name="justificatifs[]"]');

        nameInputs.forEach((input, i) => {
            const participantData = {
                name: input.value.trim(),
                firstname: firstnameInputs[i].value.trim(),
            };

            if (justificatifInputs[i] && justificatifInputs[i].files && justificatifInputs[i].files[0]) {
                formData.append(`justificatif_${i}`, justificatifInputs[i].files[0]);
                // On ajoute le nom du fichier au payload JSON pour garder le nom original le temps de la transaction
                // Car le fichier n'est pas stocké sous son nom original (doublon, sécurité...)
                participantData.justificatif = justificatifInputs[i].files[0].name;
            }
            participants.push(participantData);
        });

        formData.append('participants', JSON.stringify(participants));

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
