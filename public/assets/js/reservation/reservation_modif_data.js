document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        return; // Ne rien faire si le conteneur principal n'est pas trouvé
    }

    // Variable pour stocker la position de défilement actuelle
    let currentScrollPosition = 0;

    // Gestionnaire de défilement
    const scrollManager = {
        // Obtenir la position actuelle de manière fiable
        getPosition: function() {
            return currentScrollPosition;
        },
        // Sauvegarder la position
        savePosition: function() {
            const pos = this.getPosition();
            localStorage.setItem('scrollpos', pos);
        },
        // Restaurer la position
        restorePosition: function() {
            const pos = localStorage.getItem('scrollpos');
            if (pos) {
                window.scrollTo(0, parseInt(pos, 10));
                localStorage.removeItem('scrollpos');
            }
        }
    };

    // Suivre en temps réel la position de défilement
    window.addEventListener('scroll', function() {
        currentScrollPosition = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
    });

    // Restaurer la position au chargement initial
    scrollManager.restorePosition();


    // Récupération des éléments du DOM
    const donationSlider = document.getElementById('donation-slider');
    const donationAmountDisplay = document.getElementById('donation-amount-display');
    const totalToPayWithDonationEl = document.getElementById('total-to-pay-with-donation');
    const totalWithDonationContainer = document.getElementById('total-with-donation');
    const amountDueEl = document.getElementById('amount-due');
    const dueLine = document.getElementById('due-line');
    const creditMsg = document.getElementById('credit-message');
    const paySection = document.getElementById('pay-balance-section');
    const reservationToken = container.dataset.token;

    // Récupération du montant dû initial depuis l'attribut data
    const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);

    // Fonction pour formater un nombre en euros
    const formatEuro = (amount) => {
        return amount.toFixed(2).replace('.', ',') + ' €';
    };

    /**
     * Met à jour l'interface utilisateur en fonction de la valeur du don.
     */
    function updateDonation() {
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
        updateDonation();
        // Ajouter l'écouteur pour les changements sur le slider
        donationSlider.addEventListener('input', updateDonation);
    }

    // --- Gestion des infos du contact principal ---
    const editableContacts = document.querySelectorAll('.editable-contact');
    editableContacts.forEach(input => {
        input.addEventListener('blur', function () {
            const feedbackSpan = this.parentElement.querySelector('.feedback-span');
            const field = this.dataset.field;
            const value = this.value;

            // Validation de l'email
            if (field === 'email' && !validateEmail(value)) {
                showFeedback(feedbackSpan, 'error', 'Adresse e-mail invalide.');
                return;
            }

            // Validation du téléphone (s'il n'est pas vide)
            if (field === 'phone' && value.trim() !== '' && !validateTel(value)) {
                showFeedback(feedbackSpan, 'error', 'Format de téléphone invalide.');
                return;
            }

            const data = {
                typeField: 'contact',
                token: reservationToken, //token de la réservation
                field: field,
                value: value
            };

            if (feedbackSpan) {
                updateField(feedbackSpan, data);
            }
        });
    });

    // --- Gestion des participants ---
    const editableDetails = document.querySelectorAll('.editable-detail');
    if (editableDetails.length > 0) {
        editableDetails.forEach(input => {
            input.addEventListener('blur', function () {
                const feedbackSpan = this.parentElement.querySelector('.feedback-span');
                const field = this.dataset.field;
                const value = this.value;

                const data = {
                    typeField: 'detail',
                    token: reservationToken,
                    id: this.dataset.detailId,
                    field: field,
                    value: value
                };

                if (feedbackSpan) {
                    updateField(feedbackSpan, data);
                }
            });
        });
    }

    // --- Fonction pour afficher les feedbacks ---
    function showFeedback(feedbackSpan, status, message = '') {
        if (!feedbackSpan) return;

        feedbackSpan.textContent = status === 'success' ? '✓' : status === 'error' ? '✗' : '...';
        feedbackSpan.className = 'input-group-text feedback-span';
        feedbackSpan.classList.add(status === 'success' ? 'text-success' :
            status === 'error' ? 'text-danger' : 'text-muted');
        feedbackSpan.title = message;
    }

    // --- Fonction générique de mise à jour ---
    function updateField(feedbackSpan, data, successCallback = null) {
        // Sauvegarde de la position de défilement avant l'action
        scrollManager.savePosition();

        if (feedbackSpan) {
            showFeedback(feedbackSpan, 'loading');
        }

        apiPost('/modifData/update', data)
            .then((result) => {
                if (result.success) {
                    if (feedbackSpan) {
                        showFeedback(feedbackSpan, 'success');
                    }
                    if (successCallback) {
                        successCallback(result);
                    }
                } else {
                    showFlash('danger', result.message);
                    if (feedbackSpan) {
                        showFeedback(feedbackSpan, 'error', result.message || 'Une erreur est survenue');
                    }
                }
            })
            .catch((err) => {
                showFlash('danger', err.userMessage || err.message);
            });

    }

});