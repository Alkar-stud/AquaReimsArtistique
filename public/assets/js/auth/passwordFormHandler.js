'use strict';

/**
 * Initialise la validation côté client pour un formulaire de changement de mot de passe.
 * @param {object} config - L'objet de configuration.
 * @param {string} config.formSelector - Le sélecteur du formulaire.
 * @param {string} config.currentPasswordSelector - Sélecteur pour le champ du mot de passe actuel.
 * @param {string} config.newPasswordSelector - Sélecteur pour le champ du nouveau mot de passe.
 * @param {string} config.confirmPasswordSelector - Sélecteur pour le champ de confirmation.
 * @param {string} config.submitButtonSelector - Sélecteur pour le bouton de soumission.
 * @param {string} config.newPasswordFeedbackSelector - Sélecteur pour le message de feedback du nouveau mot de passe.
 * @param {string} config.confirmPasswordFeedbackSelector - Sélecteur pour le message de feedback de la confirmation du mot de passe.
 */
export function initPasswordFormHandler(config) {
    const form = document.querySelector(config.formSelector);
    if (!form) return;

    const current = form.querySelector(config.currentPasswordSelector);
    const newPwd = form.querySelector(config.newPasswordSelector);
    const confirm = form.querySelector(config.confirmPasswordSelector);
    const submit = form.querySelector(config.submitButtonSelector);
    const newPwdFeedback = form.querySelector(config.newPasswordFeedbackSelector);
    const confirmFeedback = form.querySelector(config.confirmPasswordFeedbackSelector);


    if (!current || !newPwd || !confirm || !submit || !newPwdFeedback || !confirmFeedback) {
        console.error("Un ou plusieurs champs du formulaire de mot de passe ou éléments de feedback sont introuvables.");
        return;
    }

    const validate = () => {
        const curVal = current.value.trim();
        const newVal = newPwd.value;
        const confVal = confirm.value;

        // Conditions de validation
        const allFilled = curVal.length > 0 && newVal.length > 0 && confVal.length > 0;
        const match = newVal === confVal;
        const differentFromCurrent = newVal !== curVal;

        // Reset all feedback states
        newPwd.classList.remove('is-valid', 'is-invalid');
        confirm.classList.remove('is-valid', 'is-invalid');
        newPwdFeedback.textContent = '';
        confirmFeedback.textContent = '';

        let newPwdHasError = false;
        let confirmHasError = false;

        // Validate new password against current password
        if (newVal.length > 0 && !differentFromCurrent) {
            newPwd.classList.add('is-invalid');
            newPwdFeedback.textContent = 'Le nouveau mot de passe doit être différent de l\'ancien.';
            newPwdHasError = true;
        }

        // Validate new password and confirm password match
        if (newVal.length > 0 && confVal.length > 0 && !match) {
            confirm.classList.add('is-invalid');
            newPwd.classList.add('is-invalid'); // Both should be invalid if they don't match
            confirmFeedback.textContent = 'Les mots de passe ne correspondent pas.';
            confirmHasError = true;
            newPwdHasError = true; // Mark newPwd as having an error due to mismatch
        }

        // Apply 'is-valid' if no errors and fields are filled
        if (newVal.length > 0 && !newPwdHasError) {
            newPwd.classList.add('is-valid');
        }
        if (confVal.length > 0 && !confirmHasError) {
            confirm.classList.add('is-valid');
        }

        // Enable/disable submit button
        submit.disabled = !(allFilled && match && differentFromCurrent);
    };

    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        form.addEventListener(evt, validate);
    });

    validate(); // Validation initiale au chargement
}