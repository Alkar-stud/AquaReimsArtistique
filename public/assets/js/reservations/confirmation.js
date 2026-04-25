'use strict';

import { initPaymentManager } from './paymentManager.js';

document.addEventListener('DOMContentLoaded', () => {

    // Initialisation du gestionnaire de l'interface du don et des totaux
    initPaymentManager();

    // Logique supplémentaire pour confirmation
    const container = document.getElementById('reservation-data-container');
    const donationInput = document.getElementById('donation-amount-input');
    const totalAmountEl = document.getElementById('total-amount');
    const donationSlider = document.getElementById('donation-slider');

    if (container && donationInput && totalAmountEl) {
        const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);
        // Met à jour l'affichage du total quand le don change
        const updateTotal = () => {
            const donationEuros = parseFloat(donationInput.value) || 0;
            const totalCents = baseDueCents + Math.round(donationEuros * 100);
            totalAmountEl.textContent = (totalCents / 100).toFixed(2).replace('.', ',') + ' €';
        };

        donationInput.addEventListener('input', updateTotal);

        if (donationSlider) {
            donationSlider.addEventListener('input', updateTotal);
        }


        updateTotal();
    }

});