'use strict';

import { apiPost } from '../components/apiClient.js';

/**
 * Initialise le gestionnaire de paiement pour la page de modification de réservation.
 * @param {object} config
 * * @param {string} config.buttonSelector - Sélecteur du bouton de paiement.
 *   * @param {string} config.token - Le token de la réservation.
 *   * @param {number} config.baseDueCents - Le montant dû de base en centimes.
 *   * @param {string} config.donationInputSelector - Sélecteur de l'input pour le don.
 */
export function initPaymentHandler(config) {
    const payButton = document.querySelector(config.buttonSelector);
    const donationInput = document.querySelector(config.donationInputSelector);

    if (!payButton || !config.token || !donationInput) {
        console.error('Un ou plusieurs éléments nécessaires au paiement sont manquants.');
        return;
    }

    payButton.addEventListener('click', async (event) => {
        event.preventDefault(); // Empêche le comportement par défaut du clic (ex: suivre un href="#")

        const originalButtonText = payButton.innerHTML;
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Préparation...';

        try {
            const reservationToken = config.token;

            // Convertir le don (en euros) en centimes
            const donationValue = parseFloat(donationInput.value.replace(',', '.')) || 0;
            const donationCents = Math.round(donationValue * 100);

            const totalAmountToPay = config.baseDueCents + donationCents;

            if (totalAmountToPay <= 0) {
                throw new Error('Le montant total à payer doit être positif.');
            }

            const payload = {
                token: reservationToken,
                amountToPay: totalAmountToPay,
                containsDonation: donationCents > 0
            };

            const result = await apiPost('/modifData/createPayment', payload);

            if (result.success && result.redirectUrl) {
                // Rediriger l'utilisateur vers la page de paiement
                window.location.href = result.redirectUrl;
            } else {
                throw new Error(result.message || 'Impossible de générer le lien de paiement.');
            }

        } catch (error) {
            alert(`Erreur : ${error.userMessage || error.message}`);
            payButton.disabled = false;
            payButton.innerHTML = originalButtonText;
        }
    });
}