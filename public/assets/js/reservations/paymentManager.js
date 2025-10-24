'use strict';

const formatEuro = (amount) => amount.toFixed(2).replace('.', ',') + ' €';

/**
 * Met à jour l'interface utilisateur des totaux et du paiement.
 */
function updatePaymentUI() {
    const container = document.getElementById('reservation-data-container');
    if (!container) return;

    const donationAmountInput = document.getElementById('donation-amount-input');
    const amountDueEl = document.getElementById('amount-due');
    const paySection = document.getElementById('pay-balance-section');
    const testEnvInfo = document.getElementById('test-env-info'); // Assurez-vous que cet ID existe dans le tpl

    const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);
    const donationEuros = parseFloat(donationAmountInput.value) || 0;
    const donationCents = Math.round(donationEuros * 100);

    // Le total à payer est le montant dû de base + le don. Ne peut pas être négatif.
    const totalToPayCents = Math.max(0, baseDueCents + donationCents);

    // Mettre à jour le "Reste à payer"
    if (amountDueEl) {
        const parentContainer = amountDueEl.closest('#due-line');
        if (parentContainer) {
            parentContainer.classList.toggle('d-none', totalToPayCents <= 0);
        }
        amountDueEl.textContent = formatEuro(totalToPayCents / 100);
    }

    // Gérer le message de crédit
    const creditMessageEl = document.getElementById('credit-message');
    if (creditMessageEl) {
        creditMessageEl.classList.toggle('d-none', totalToPayCents > 0);
        if (totalToPayCents < 0) {
            const creditAmountEl = creditMessageEl.querySelector('#credit-amount');
            if (creditAmountEl) creditAmountEl.textContent = formatEuro(Math.abs(totalToPayCents) / 100);
        }
    }

    // Afficher ou masquer le bouton de paiement et les infos de test
    const shouldShowPaymentSection = totalToPayCents > 0;
    if (paySection) {
        paySection.classList.toggle('d-none', !shouldShowPaymentSection);
    }
    if (testEnvInfo) {
        testEnvInfo.classList.toggle('d-none', !shouldShowPaymentSection);
    }
}

/**
 * Initialise le gestionnaire de paiement pour la page modif_data.
 */
export function initPaymentManager() {
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        // On n'est pas sur la bonne page
        return;
    }

    const donationSlider = document.getElementById('donation-slider');
    const donationAmountInput = document.getElementById('donation-amount-input');
    const roundUpBtn = document.getElementById('round-up-donation-btn');

    if (!donationSlider || !donationAmountInput) {
        return;
    }

    // Écouteurs pour le slider et l'input de don
    donationSlider.addEventListener('input', () => {
        donationAmountInput.value = parseFloat(donationSlider.value).toFixed(2);
        updatePaymentUI();
    });

    donationAmountInput.addEventListener('input', () => {
        let donationEuros = parseFloat(donationAmountInput.value) || 0;
        if (donationEuros < 0) {
            donationEuros = 0;
            donationAmountInput.value = '0.00';
        }
        donationSlider.value = donationEuros;
        updatePaymentUI();
    });

    // Logique pour le bouton "Arrondir"
    if (roundUpBtn) {
        const updateRoundUpButtonVisibility = () => {
            const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);
            const donationEuros = parseFloat(donationAmountInput.value) || 0;
            const totalToPayCents = Math.max(0, baseDueCents + Math.round(donationEuros * 100));
            const centsPart = totalToPayCents % 100;
            const maxSliderEuros = parseFloat(donationSlider.max);
            roundUpBtn.classList.toggle('d-none', centsPart === 0 || donationEuros >= maxSliderEuros);
        };

        roundUpBtn.addEventListener('click', () => {
            const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);
            const currentDonationCents = Math.round(parseFloat(donationAmountInput.value) * 100);
            const currentTotalCents = baseDueCents + currentDonationCents;
            const centsPart = currentTotalCents % 100;

            if (centsPart > 0) {
                const donationToAddCents = 100 - centsPart;
                const newDonationEuros = (currentDonationCents + donationToAddCents) / 100;
                donationAmountInput.value = newDonationEuros.toFixed(2);
                donationSlider.value = newDonationEuros;
                updatePaymentUI();
                updateRoundUpButtonVisibility();
            }
        });

        donationSlider.addEventListener('change', updateRoundUpButtonVisibility);
        donationAmountInput.addEventListener('change', updateRoundUpButtonVisibility);
        updateRoundUpButtonVisibility(); // Appel initial
    }

    // Appel initial pour définir le bon état au chargement de la page
    updatePaymentUI();
}