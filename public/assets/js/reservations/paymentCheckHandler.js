'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';

/**
 * Handles the payment verification process after returning from HelloAsso.
 * @param {object} config
 * @param {string} config.containerSelector - Selector for the container holding payment check info.
 * @param {string} config.spinnerSelector - Selector for the loading spinner.
 * @param {string} config.messageSelector - Selector for the message display area.
 * @param {string} config.errorSelector - Selector for the error display area.
 * @param {string} config.successSelector - Selector for the success display area.
 * @param {number} config.pollIntervalMs - Interval for polling in milliseconds.
 * @param {string} config.successRedirectUrl - URL de base pour la redirection en cas de succès (ex: '/reservation/merci').
 * @param {number} config.initialPollAttempts - Nombre de tentatives de vérification locale.
 * @param {number} config.maxPollAttempts - Maximum number of polling attempts.
 */
export function initPaymentCheckHandler(config) {
    const container = document.querySelector(config.containerSelector);
    if (!container) {
        console.error('Payment check container not found.');
        showFlashMessage('error', 'Erreur interne : Conteneur de vérification de paiement manquant.', 'ajax_flash_container');
        return;
    }

    // On récupère le token directement depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const reservationToken = urlParams.get('token');

    const checkoutIntentId = container.dataset.checkoutId;
    if (!checkoutIntentId) {
        console.error('checkoutIntentId not found in data attribute.');
        showFlashMessage('error', 'Impossible de vérifier le paiement : ID de transaction manquant.', 'ajax_flash_container');
        return;
    }

    const spinner = document.querySelector(config.spinnerSelector);
    const messageEl = document.querySelector(config.messageSelector);
    const errorEl = document.querySelector(config.errorSelector);
    const successEl = document.querySelector(config.successSelector);

    let pollAttempts = 0;

    const forceCheckPaymentStatus = async () => {
        try {
            // On appelle la route qui force la vérification auprès de HelloAsso
            const result = await apiPost('/reservation/checkPaymentState', { checkoutIntentId });
            if (result.success === true) {
                // Si HelloAsso confirme, on relance la vérification locale pour récupérer le token
                pollAttempts = 0; // repart à zéro pour ne pas repasser par le mode "force"
                // Appel immédiat pour obtenir le bon retour avec le token
                await checkPaymentStatus();

            } else {
                handlePollingFailure('La vérification du paiement a pris trop de temps. Veuillez vérifier votre boîte mail pour la confirmation.');
            }
        } catch (error) {
            handlePollingFailure(error.userMessage || 'Erreur de communication avec le serveur lors de la vérification forcée.');
        }
    };

    const checkPaymentStatus = async () => {
        pollAttempts++;
        // Logique en deux étapes
        if (pollAttempts > config.initialPollAttempts) {
            console.warn("Le webhook tarde, passage à la vérification forcée.");
            if (messageEl) {
                messageEl.textContent = "La réponse tarde à arriver, nous vérifions directement auprès du service de paiement...";
            }
            // On réinitialise le compteur pour la deuxième phase de polling
            pollAttempts = 0;

            await forceCheckPaymentStatus();
            return;
        }

        try {
            const result = await apiPost('/reservation/checkPayment', { checkoutIntentId });
            if (result.success) {
                handleSuccess(result.token);
            } else if (result.status === 'pending') {
                setTimeout(checkPaymentStatus, config.pollIntervalMs);
            } else {
                handlePollingFailure(result.error || 'Une erreur est survenue lors de la vérification du paiement.');
            }
        } catch (error) {
            handlePollingFailure(error.userMessage || 'Erreur de communication avec le serveur.');
        }
    };

    function handleSuccess(reservationToken) {
        if (successEl) successEl.style.display = 'block';
        if (spinner) spinner.style.display = 'none';
        if (messageEl) messageEl.style.display = 'none';
        showFlashMessage('success', 'Paiement confirmé !', 'ajax_flash_container');

        if (reservationToken) {
            setTimeout(() => {
                window.location.href = `${config.successRedirectUrl}?token=${reservationToken}`;
            }, 2000); // Redirection après 2 secondes
        } else {
            console.error("Token de réservation manquant pour la redirection.");
            handlePollingFailure("Impossible de vous rediriger, le token de réservation est manquant.");
        }
    }

    function handlePollingFailure(message) {
        console.error('Payment check failed:', message);
        if (spinner) spinner.style.display = 'none';
        if (messageEl) messageEl.style.display = 'none';
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
        showFlashMessage('danger', message, 'ajax_flash_container');
    }

    checkPaymentStatus();
}