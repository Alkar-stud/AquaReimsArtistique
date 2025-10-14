document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        return; // Ne rien faire si le conteneur principal n'est pas trouvé
    }

    // Récupération des éléments du DOM
    const donationSlider = document.getElementById('donation-slider');
    const donationAmountDisplay = document.getElementById('donation-amount-display');
    const totalToPayWithDonationEl = document.getElementById('total-to-pay-with-donation');
    const totalWithDonationContainer = document.getElementById('total-with-donation');
    const amountDueEl = document.getElementById('amount-due');
    const dueLine = document.getElementById('due-line');
    const creditMsg = document.getElementById('credit-message');
    const paySection = document.getElementById('pay-balance-section');

    // Récupération du montant dû initial depuis l'attribut data
    const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);

    // Fonction pour formater un nombre en euros
    const formatEuro = (amount) => {
        return amount.toFixed(2).replace('.', ',') + ' €';
    };

    /**
     * Met à jour l'interface utilisateur en fonction de la valeur du don.
     */
    function updateDonationUI() {
        if (!donationSlider) return;

        const donationEuros = parseFloat(donationSlider.value) || 0;
        const donationCents = Math.round(donationEuros * 100);

        // Le total à payer est le montant dû de base + le don. Ne peut pas être négatif.
        const totalToPayCents = Math.max(0, baseDueCents + donationCents);
        const totalToPayEuros = totalToPayCents / 100;

        // Mettre à jour l'affichage du montant du don
        if (donationAmountDisplay) {
            donationAmountDisplay.textContent = formatEuro(donationEuros);
        }

        // Gérer l'affichage du "Total à régler (avec don)"
        if (totalWithDonationContainer && totalToPayWithDonationEl) {
            if (donationCents > 0) {
                totalWithDonationContainer.classList.remove('d-none');
                totalToPayWithDonationEl.textContent = formatEuro(totalToPayEuros);
            } else {
                totalWithDonationContainer.classList.add('d-none');
            }
        }

        // Mettre à jour le "Reste à payer" ou le message de crédit
        if (baseDueCents <= 0) {
            // Cas où la réservation est soldée ou en crédit
            if (totalToPayCents > 0) {
                // Un don a été fait, créant un montant à payer
                if (creditMsg) creditMsg.classList.add('d-none');
                if (dueLine) dueLine.classList.remove('d-none');
                if (amountDueEl) amountDueEl.textContent = formatEuro(totalToPayEuros);
            } else {
                // Pas de don ou don insuffisant pour créer un dû
                if (creditMsg) creditMsg.classList.remove('d-none');
                if (dueLine) dueLine.classList.add('d-none');
            }
        } else {
            // Cas où il y a un reste à payer initial
            if (amountDueEl) {
                amountDueEl.textContent = formatEuro(totalToPayEuros);
            }
        }

        // 4. Afficher ou masquer le bouton de paiement
        if (paySection) {
            if (totalToPayCents > 0) {
                paySection.classList.remove('d-none');
            } else {
                paySection.classList.add('d-none');
            }
        }
    }

    // S'assurer que tous les éléments nécessaires existent avant d'ajouter l'écouteur
    if (donationSlider && donationAmountDisplay && amountDueEl) {
        // Mettre à jour l'affichage une première fois au chargement
        updateDonationUI();
        // Ajouter l'écouteur pour les changements sur le slider
        donationSlider.addEventListener('input', updateDonationUI);
    }
});