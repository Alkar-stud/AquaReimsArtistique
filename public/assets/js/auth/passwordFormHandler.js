'use strict';

/**
 * Initialise la validation côté client pour un formulaire de changement de mot de passe.
 * @param {object} config - L'objet de configuration.
 * @param {string} config.formSelector - Le sélecteur du formulaire.
 * @param {string} config.currentPasswordSelector - Sélecteur pour le champ du mot de passe actuel.
 * @param {string} config.newPasswordSelector - Sélecteur pour le champ du nouveau mot de passe.
 * @param {string} config.confirmPasswordSelector - Sélecteur pour le champ de confirmation.
 * @param {string} config.submitButtonSelector - Sélecteur pour le bouton de soumission.
 */
export function initPasswordFormHandler(config) {
    const form = document.querySelector(config.formSelector);
    if (!form) return;

    const current = form.querySelector(config.currentPasswordSelector);
    const newPwd = form.querySelector(config.newPasswordSelector);
    const confirm = form.querySelector(config.confirmPasswordSelector);
    const submit = form.querySelector(config.submitButtonSelector);

    if (!current || !newPwd || !confirm || !submit) {
        console.error("Un ou plusieurs champs du formulaire de mot de passe sont introuvables.");
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

        // Feedback visuel Bootstrap
        if (newVal.length === 0 && confVal.length === 0) {
            newPwd.classList.remove('is-valid', 'is-invalid');
            confirm.classList.remove('is-valid', 'is-invalid');
        } else {
            newPwd.classList.toggle('is-invalid', !match);
            confirm.classList.toggle('is-invalid', !match);
            newPwd.classList.toggle('is-valid', match && newVal.length > 0);
            confirm.classList.toggle('is-valid', match && confVal.length > 0);
        }

        // Messages de validité pour l'UI HTML5
        confirm.setCustomValidity(match ? '' : 'Les mots de passe ne correspondent pas.');

        submit.disabled = !(allFilled && match && differentFromCurrent);
    };

    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        form.addEventListener(evt, validate);
    });

    validate(); // Validation initiale au chargement
}