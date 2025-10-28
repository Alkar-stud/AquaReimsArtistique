'use strict';

import { initPaymentCheckHandler } from './paymentCheckHandler.js';

document.addEventListener('DOMContentLoaded', () => {
    const paymentCheckContainer = document.getElementById('payment-check-container');

    if (paymentCheckContainer) {
        // On initialise le gestionnaire de vérification de paiement
        // en lui passant la bonne URL de redirection pour une nouvelle réservation.
        initPaymentCheckHandler({
            containerSelector: '#payment-check-container',
            spinnerSelector: '.spinner-border',
            messageSelector: '.message',
            errorSelector: '#merci-error',
            successSelector: '#payment-check-success', // Un nouvel ID pour le message de succès
            successRedirectUrl: '/reservation/merci', // URL de redirection finale
            pollIntervalMs: 5000,
            initialPollAttempts: 5,
            maxPollAttempts: 3
        });
    } else {
        const errorContainer = document.getElementById('merci-error');
        if(errorContainer) {
            errorContainer.textContent = "Erreur : Impossible de trouver les informations de paiement pour la vérification.";
        }
    }
});