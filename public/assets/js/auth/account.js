'use strict';

import { initPasswordFormHandler } from './passwordFormHandler.js';

document.addEventListener('DOMContentLoaded', () => {
    // Initialisation du gestionnaire pour le formulaire de changement de mot de passe.
    // On lui passe les sélecteurs spécifiques à la page `account.tpl`.
    initPasswordFormHandler({
        formSelector: 'form[action="/account/password"]',
        currentPasswordSelector: '#current_password',
        newPasswordSelector: '#new_password',
        confirmPasswordSelector: '#confirm_password',
        submitButtonSelector: '#changePasswordButton',
        newPasswordFeedbackSelector: '#new_password_feedback',
        confirmPasswordFeedbackSelector: '#confirm_password_feedback'
    });


});