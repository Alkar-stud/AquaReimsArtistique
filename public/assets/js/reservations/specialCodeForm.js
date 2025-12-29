import { apiPost } from '../components/apiClient.js';

/**
 * Initialise la logique pour le formulaire d'ajout par code spécial.
 * @param {object} options - Options de configuration.
 * @param {string} options.reservationToken - Le token de la réservation.
 */
export function initSpecialCodeForm(options) {
    const validateCodeBtn = document.getElementById('validateCodeBtn');
    const specialCodeInput = document.getElementById('specialCode');
    const specialCodeFeedback = document.getElementById('specialCodeFeedback');

    if (!validateCodeBtn || !specialCodeInput || !specialCodeFeedback) {
        return;
    }

    validateCodeBtn.addEventListener('click', async () => {
        const code = specialCodeInput.value.trim();
        if (!code) {
            specialCodeFeedback.textContent = 'Veuillez saisir un code.';
            return;
        }

        validateCodeBtn.disabled = true;
        specialCodeFeedback.textContent = 'Validation en cours...';
        specialCodeFeedback.classList.remove('text-danger', 'text-success');

        try {
            const response = await apiPost('/modifData/add-code', {
                token: options.reservationToken,
                code: code
            });

            if (response.success) {
                if (response.reload) {
                    window.location.reload();
                } else {
                    specialCodeFeedback.classList.add('text-success');
                    specialCodeFeedback.textContent = 'Article ajouté avec succès !';
                    specialCodeInput.value = '';
                }
            } else {
                specialCodeFeedback.classList.add('text-danger');
                specialCodeFeedback.textContent = response.message || 'Code invalide ou erreur.';
            }
        } catch (error) {
            specialCodeFeedback.classList.add('text-danger');

            specialCodeFeedback.textContent = error.userMessage || 'Erreur de communication.';
        } finally {
            validateCodeBtn.disabled = false;
        }
    });
}