import { validateEmail, validateTel } from '../components/validators.js';
import { showFeedback } from '../components/ui.js';
import { apiPost } from '../components/apiClient.js';

/**
 * Initialise la logique du formulaire de contact.
 * Trouve les champs et attache les écouteurs d'événements.
 * @param {string} options.apiUrl - L'URL de l'API à appeler pour la mise à jour.
 * @param {string} options.reservationIdentifier - Le token ou l'ID de la réservation.
 * @param {string} options.identifierType - 'token' ou 'reservationId'.
 */
function init(options) {
    const contactContainer = document.getElementById('contact-fields-container');
    if (!contactContainer) {
        // Si le conteneur n'est pas sur la page, on ne fait rien.
        return;
    }

    // On cible tous les champs éditables du conteneur
    const editableInputs = contactContainer.querySelectorAll('.editable-contact');

    editableInputs.forEach(input => {
        input.addEventListener('blur', (event) => {
            const input = event.target;
            const field = input.dataset.field;
            const value = input.value;

            // Trouver le span de feedback associé à cet input
            const feedbackSpan = input.closest('.input-group').querySelector('.feedback-span');

            showFeedback(feedbackSpan, 'pending', 'Enregistrement...');

            // --- Validation conditionnelle ---
            if (field === 'email' && !validateEmail(value)) {
                showFeedback(feedbackSpan, 'error', 'Format de l\'email invalide.');
                return;
            }

            if (field === 'phone' && value.trim() !== '' && !validateTel(value)) {
                showFeedback(feedbackSpan, 'error', 'Format de téléphone invalide.');
                return;
            }

            // Récupérer le token de la réservation
            const reservationToken = document.querySelector('[data-token]')?.dataset.token || document.getElementById('modal_reservation_token')?.value;

            const data = {
                typeField: 'contact',
                field: field,
                value: value
            };
            // Ajouter l'identifiant de la réservation au payload
            if (options.identifierType === 'token') {
                data.token = options.reservationIdentifier;
            } else if (options.identifierType === 'reservationId') {
                data.reservationId = options.reservationIdentifier;
            }

            apiPost(options.apiUrl, data)
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


export { init as initContactForm };