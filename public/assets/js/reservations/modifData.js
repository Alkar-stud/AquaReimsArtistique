'use strict';

import { initPaymentHandler } from './paymentHandler.js';
import { initContactForm } from './contactForm.js';
import { initParticipantsForm } from './participantsForm.js';
import { initComplementsForm } from './complementsForm.js';
import { initSpecialCodeForm } from './specialCodeForm.js';
import { initCancelButtons } from './cancelReservation.js';
import { initPaymentManager } from './paymentManager.js';
import { initPaymentCheckHandler } from './paymentCheckHandler.js'; // Import du nouveau gestionnaire

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOMContentLoaded in modifData.js');
    const reservationDataContainer = document.getElementById('reservation-data-container');

    // Si ce conteneur n'existe pas, on n'est pas sur la bonne page. On ne fait rien.
    if (!reservationDataContainer) {
        console.log('reservationDataContainer not found. Exiting modifData.js');
        return;
    }
    console.log('reservationDataContainer found.');

    const paymentCheckContainer = document.getElementById('payment-check-container');
    console.log('paymentCheckContainer:', paymentCheckContainer);

    if (paymentCheckContainer) {
        console.log('Payment check container found. Initializing payment check handler.');
        initPaymentCheckHandler({
            containerSelector: '#payment-check-container',
            spinnerSelector: '#payment-check-spinner',
            messageSelector: '#payment-check-message',
            errorSelector: '#payment-check-error',
            successSelector: '#payment-check-success',
            pollIntervalMs: 5000, // Vérification toutes les 5 secondes
            initialPollAttempts: 5, // 5 tentatives locales (25s)
            maxPollAttempts: 3 // 3 tentatives forcées (15s de plus)
        });
        return; // Arrête l'initialisation des autres composants pour cette page
    }
    console.log('Payment check container NOT found. Initializing normal modifData components.');

    // Si ce n'est pas une page de vérification de paiement, on procède aux initialisations normales
    const reservationToken = reservationDataContainer.dataset.token;
    const baseDueCents = parseInt(reservationDataContainer.dataset.baseDueCents || '0', 10);

    // Initialisation des composants du formulaire de contact
    initContactForm({
        apiUrl: '/modifData/update',
        reservationIdentifier: reservationToken,
        identifierType: 'token'
    });

    // Initialisation des composants du formulaire des participants
    initParticipantsForm({
        apiUrl: '/modifData/update',
        reservationIdentifier: reservationToken,
        identifierType: 'token'
    });

    // Initialisation des composants des compléments
    initComplementsForm({
        apiUrl: '/modifData/update',
        reservationIdentifier: reservationToken,
        identifierType: 'token'
    });

    // Gestion du code spécial
    initSpecialCodeForm({
        reservationToken: reservationToken
    });

    // Gestion du bouton d'annulation
    initCancelButtons('.cancel-button', {
        apiUrl: '/modifData/update',
        reservationIdentifier: reservationToken,
        identifierType: 'token'
    });

    // Initialisation du gestionnaire de l'interface du don et des totaux
    initPaymentManager();

    // Initialisation du gestionnaire de clic pour le bouton de paiement (si présent)
    const payButton = document.getElementById('pay-balance-btn');
    const donationInput = document.getElementById('donation-amount-input');
    if (payButton && donationInput) {
        initPaymentHandler({
            buttonSelector: '#pay-balance-btn',
            token: reservationToken,
            baseDueCents: baseDueCents,
            donationInputSelector: '#donation-amount-input'
        });
    } else {
        console.warn('Payment button or donation input not found on normal modifData page. Payment initiation will not work.');
    }
});