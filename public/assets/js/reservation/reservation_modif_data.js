App.ready.then(async () => {
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        return; // Ne rien faire si le conteneur principal n'est pas trouvé
    }

    // Utiliser le ScrollManager global
    // Restaure la position au chargement
    ScrollManager.restore();


    // Récupération des éléments du DOM
    const donationSlider = document.getElementById('donation-slider');
    const donationAmountInput = document.getElementById('donation-amount-input');
    const totalToPayWithDonationEl = document.getElementById('total-to-pay-with-donation');
    const totalWithDonationContainer = document.getElementById('total-with-donation');
    const amountDueEl = document.getElementById('amount-due');
    const dueLine = document.getElementById('due-line');
    const creditMsg = document.getElementById('credit-message');
    const paySection = document.getElementById('pay-balance-section');
    const reservationToken = container.dataset.token;
    const roundUpBtn = document.getElementById('round-up-donation-btn');

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
        if (!donationSlider) {
            return;
        }

        // La source de vérité est l'input, pour permettre des dons supérieurs au max du slider.
        const donationEuros = parseFloat(donationAmountInput.value) || 0;
        const donationCents = Math.round(donationEuros * 100);

        // Le total à payer est le montant dû de base + le don. Ne peut pas être négatif.
        const totalToPayCents = Math.max(0, baseDueCents + donationCents);
        const totalToPayEuros = totalToPayCents / 100;

        if (donationAmountInput && document.activeElement !== donationAmountInput) {
            donationAmountInput.value = donationEuros.toFixed(2);
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
                if (creditMsg) {
                    creditMsg.classList.add('d-none');
                }
                if (dueLine) {
                    dueLine.classList.remove('d-none');
                }
                if (amountDueEl) {
                    amountDueEl.textContent = formatEuro(totalToPayEuros);
                }
            } else {
                // Pas de don ou don insuffisant pour créer un dû
                if (creditMsg) {
                    creditMsg.classList.remove('d-none');
                }
                if (dueLine) {
                    dueLine.classList.add('d-none');
                }
            }
        } else {
            // Cas où il y a un reste à payer initial
            if (amountDueEl) {
                amountDueEl.textContent = formatEuro(totalToPayEuros);
            }
        }

        // Afficher ou masquer le bouton de paiement
        if (paySection) {
            if (totalToPayCents > 0) {
                paySection.classList.remove('d-none');
            } else {
                paySection.classList.add('d-none');
            }
        }
    }

    /**
     * Met à jour la visibilité du bouton "Arrondir".
     * Cette fonction est séparée pour n'être appelée que sur certains événements (change, click).
     */
    function updateRoundUpButtonVisibility() {
        if (roundUpBtn) {
            const donationEuros = parseFloat(donationAmountInput.value) || 0;
            const donationCents = Math.round(donationEuros * 100);
            const totalToPayCents = Math.max(0, baseDueCents + donationCents);
            const centsPart = totalToPayCents % 100;
            const maxSliderEuros = parseFloat(donationSlider.max);
            if (centsPart > 0 && donationEuros < maxSliderEuros) {
                roundUpBtn.classList.remove('d-none');
            } else {
                roundUpBtn.classList.add('d-none');
            }
        }
    }

    // S'assurer que tous les éléments nécessaires existent avant d'ajouter l'écouteur
    if (donationSlider && donationAmountInput && amountDueEl) {
        // Mettre à jour l'affichage une première fois au chargement pour initialiser les valeurs
        updateDonation();
        updateRoundUpButtonVisibility(); // Vérifier une fois au chargement
        // Ajouter l'écouteur pour le bouton "Arrondir"
        if (roundUpBtn) {
            roundUpBtn.addEventListener('click', function () {
                const currentDonationCents = Math.round(parseFloat(donationAmountInput.value) * 100);
                const currentTotalCents = baseDueCents + currentDonationCents;
                const centsPart = currentTotalCents % 100;

                if (centsPart > 0) {
                    const donationToAddCents = 100 - centsPart;
                    const newDonationEuros = (currentDonationCents + donationToAddCents) / 100;
                    donationAmountInput.value = newDonationEuros.toFixed(2);
                    donationSlider.value = newDonationEuros; // Mettre à jour le slider aussi pour la cohérence visuelle
                    // Déclencher manuellement la mise à jour de l'interface
                    updateDonation();
                    updateRoundUpButtonVisibility(); // Mettre à jour la visibilité du bouton après avoir arrondi
                }
            });
        }

        // Ajouter l'écouteur pour les changements sur le slider
        donationSlider.addEventListener('input', function() {
            // Le slider met à jour l'input, qui est la source de vérité.
            if (donationAmountInput) {
                donationAmountInput.value = parseFloat(this.value).toFixed(2);
            }
            updateDonation();
        });

        // Mettre à jour le bouton "Arrondir" uniquement à la fin du mouvement du slider
        donationSlider.addEventListener('change', updateRoundUpButtonVisibility);

        // Ajouter l'écouteur pour les changements sur l'input du don
        donationAmountInput.addEventListener('input', function() {
            let donationEuros = parseFloat(this.value) || 0;
            if (donationEuros < 0) {
                donationEuros = 0;
                this.value = '0.00';
            }
            // Mettre à jour la position du slider. S'il dépasse le max, le slider se bloquera visuellement
            // à son max, mais la valeur de l'input (et donc du calcul) restera celle saisie.
            donationSlider.value = donationEuros;
            updateDonation();
        });

        // Mettre à jour le bouton "Arrondir" quand on quitte le champ input
        donationAmountInput.addEventListener('change', updateRoundUpButtonVisibility);
    }

    // --- Gestion des infos du contact principal via le composant ---
    const contactFieldsContainer = document.getElementById('contact-fields-container');
    if (contactFieldsContainer) {
        App.Components.Contact.init(contactFieldsContainer);
    }

    // --- Gestion des participants ---
    // La logique est maintenant gérée par le composant Participants.
    const participantsContainer = document.getElementById('participants-container');
    if (participantsContainer) {
        App.Components.Participants.init(participantsContainer);
    }

    // --- Gestion des compléments via le composant ---
    // On initialise sur le conteneur principal pour capturer tous les clics
    // sur les boutons de compléments (modification ET ajout).
    if (container) {
        App.Components.Complements.init(container, {
            token: reservationToken,
            reservationId: container.dataset.reservationId
        });
    }

    // --- Gestion du code spécial pour ajouter un complément ---
    const validateCodeBtn = document.getElementById('validateCodeBtn');
    const specialCodeInput = document.getElementById('specialCode');
    const specialCodeFeedback = document.getElementById('specialCodeFeedback');
    const specialTarifContainer = document.getElementById('specialTarifContainer');

    if (validateCodeBtn && specialCodeInput && specialCodeFeedback && specialTarifContainer) {
        validateCodeBtn.addEventListener('click', function () {
            const code = specialCodeInput.value.trim();
            if (!code) {
                specialCodeFeedback.textContent = 'Veuillez saisir un code.';
                return;
            }

            validateCodeBtn.disabled = true;
            specialCodeFeedback.textContent = 'Validation en cours...';

            const data = {
                token: reservationToken,
                code: code
            };

            apiPost('/modifData/add-code', data)
                .then(result => {
                    if (result.success) {
                        // Si le backend demande un rechargement, on le fait.
                        if (result.reload) {
                            ScrollManager.save(); // Sauvegarde de la position avant de recharger
                            window.location.reload();
                        } else {
                            // Alternative sans recharger
                            specialCodeFeedback.classList.remove('text-danger');
                            specialCodeFeedback.classList.add('text-success');
                            specialCodeFeedback.textContent = 'Article ajouté avec succès !';
                            specialCodeInput.value = ''; // Vider le champ
                        }
                    } else {
                        // Afficher l'erreur retournée par le backend
                        specialCodeFeedback.classList.remove('text-success');
                        specialCodeFeedback.classList.add('text-danger');
                        specialCodeFeedback.textContent = result.message || 'Code invalide ou erreur.';
                    }
                })
                .catch(err => {
                    specialCodeFeedback.classList.remove('text-success');
                    specialCodeFeedback.classList.add('text-danger');
                    specialCodeFeedback.textContent = err.userMessage || 'Une erreur de communication est survenue.';
                })
                .finally(() => {
                    // On réactive le bouton seulement si l'opération n'a pas entraîné de rechargement
                    const shouldReload = document.querySelector('#ajax_flash_container.alert-success');
                    if (!shouldReload) {
                        validateCodeBtn.disabled = false;
                    }
                });
        });
    }

    // --- Gestion de l'annulation de la réservation ---
    const cancelBtn = document.querySelector('.cancel-button');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (confirm("Êtes-vous sûr de vouloir annuler cette réservation ?\nCette action est irréversible.")) {
                if (confirm("Êtes-vous toujours sûr ?\n Vous ne pourrez prétendre à aucun remboursement !")) {
                    const data = {
                        typeField: 'cancel',
                        token: reservationToken
                    };

                    updateField(null, data);
                }
            }
        });
    }

    // Initialisation du bouton de paiement
    const payBalanceBtn = document.getElementById('pay-balance-btn');
    if (payBalanceBtn) {
        payBalanceBtn.addEventListener('click', handlePayBalance);
    }

    // Détecter si nous sommes en mode vérification de paiement
    const paymentCheckContainer = document.getElementById('payment-check-container');
    if (paymentCheckContainer) {
        const checkoutIntentId = paymentCheckContainer.dataset.checkoutId;
        if (checkoutIntentId) {
            await checkPaymentStatus(checkoutIntentId, 0);
        }
    }


    async function handlePayBalance(event) {
        event.preventDefault(); // Empêche le lien de naviguer vers "#"

        const payBalanceBtn = document.getElementById('pay-balance-btn');
        const reservationToken = container.dataset.token;
        const payBalanceImg = payBalanceBtn.querySelector('img');

        const amountDueEl = document.getElementById('amount-due');
        const donationAmountInput = document.getElementById('donation-amount-input');

        // Convertir le texte "12,34 €" en nombre de centimes
        const amountDueInCents = Math.round(parseFloat(amountDueEl.textContent.replace(',', '.').replace('€', '').trim()) * 100);
        const donationInCents = Math.round(parseFloat(donationAmountInput.value) * 100);

        if (amountDueInCents <= 0) {
            showFlash('danger', 'Le montant à payer est nul ou invalide.');
            return;
        }

        // Désactiver le lien et montrer un état de chargement
        payBalanceBtn.style.pointerEvents = 'none';
        if (payBalanceImg) {
            payBalanceImg.style.opacity = '0.5';
        }

        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm ms-2';
        spinner.setAttribute('role', 'status');
        payBalanceBtn.parentNode.appendChild(spinner);

        const data = {
            token: reservationToken,
            amountToPay: amountDueInCents,
            containsDonation: donationInCents > 0
        };

        apiPost('/modifData/createPayment', data)
            .then(result => {
                if (result.success && result.redirectUrl) {
                    // Rediriger l'utilisateur vers la page de paiement HelloAsso.
                    // Pas besoin de nettoyer l'UI (spinner, etc.) car la page va changer.
                    window.location.href = result.redirectUrl;
                } else {
                    // Erreur applicative retournée par le backend (ex: "montant invalide")
                    showFlash('danger', result.message || 'Une erreur est survenue lors de la création du paiement.');
                    window.scrollTo(0, 0); // Remonter en haut pour voir le message flash
                }
            })
            .catch(error => {
                // Erreur réseau ou HTTP (gérée par apiPost)
                console.error('Erreur lors de la création du paiement:', error);
                showFlash('danger', error.userMessage || 'Une erreur technique est survenue.');
                window.scrollTo(0, 0); // Remonter en haut pour voir le message flash
            })
            .finally(() => {
                // Ce bloc s'exécute toujours, sauf en cas de redirection.
                payBalanceBtn.style.pointerEvents = 'auto';
                if (payBalanceImg) {
                    payBalanceImg.style.opacity = '1';
                }
                spinner.remove();
                window.scrollTo(0, 0);
            });
    }

    /**
     * Interroge le backend pour connaître le statut du paiement.
     * @param {string} checkoutIntentId L'ID de l'intention de paiement.
     * @param {number} attempt Le numéro de la tentative actuelle.
     */
    async function checkPaymentStatus(checkoutIntentId, attempt) {
        const maxAttempts = 1; // Tenter pendant 30 secondes max
        const delay = 1000; // 5 secondes entre chaque tentative


        const data = {
            token: reservationToken,
            checkoutIntentId: checkoutIntentId
        };

        if (attempt >= maxAttempts) {
            // Le polling a échoué, on passe au plan B : la vérification forcée.
            await forceCheckPayment(data);
            return;
        }

        apiPost('/reservation/checkPayment', data)
            .then(result => {
                if (result.success) {
                    // Paiement confirmé !
                    const spinner = document.getElementById('payment-check-spinner');
                    const msg = document.getElementById('payment-check-message');
                    const successMsg = document.getElementById('payment-check-success');

                    if(spinner) {
                        spinner.style.display = 'none';
                    }
                    if(msg) {
                        msg.style.display = 'none';
                    }
                    if(successMsg) {
                        successMsg.style.display = 'block';
                    }

                    // Recharger la page de modification pour voir le solde mis à jour.
                    setTimeout(() => {
                        // On retire les paramètres GET de l'URL pour éviter une boucle
                        const url = new URL(window.location.href);
                        url.searchParams.delete('status');
                        url.searchParams.delete('checkout_intent_id');
                        url.searchParams.delete('id');
                        window.location.href = url.toString();
                    }, 3000);

                } else {
                    // Le paiement est en attente, on réessaie.
                    setTimeout(() => checkPaymentStatus(checkoutIntentId, attempt + 1), delay);
                }
            })
            .catch(error => {
                console.error("Erreur lors de la vérification du paiement :", error);
                displayPaymentError("Une erreur technique est survenue lors de la vérification. Nous allons vérifier manuellement.", checkoutIntentId);
            });

    }

    /**
     * Interroge directement HelloAsso via le backend si le polling simple a échoué.
     * @param data
     */
    async function forceCheckPayment(data) {
        const msg = document.getElementById('payment-check-message');
        if (msg) {
            msg.textContent = "La confirmation automatique prend du temps. Nous lançons une vérification manuelle...";
        }

        apiPost('/reservation/checkPayment', data)
            .then(result => {
                if (result.success) {
                    // La vérification forcée a fonctionné ! On affiche le succès.
                    // On appelle la même logique que si le polling avait réussi.
                    const successMsg = document.getElementById('payment-check-success');
                    document.getElementById('payment-check-spinner').style.display = 'none';
                    document.getElementById('payment-check-message').style.display = 'none';
                    successMsg.style.display = 'block';
                    // Rediriger en nettoyant l'URL
                    setTimeout(() => {
                        const url = new URL(window.location.href);
                        url.searchParams.delete('status');
                        url.searchParams.delete('checkout_intent_id');
                        window.location.href = url.toString();
                    }, 3000);            } else {
                    // Même la vérification forcée a échoué.
                    displayPaymentError(result.message || "La vérification du paiement a échoué. Nous allons vérifier manuellement. Votre réservation est bien enregistrée.", data.checkoutIntentId);
                }
            })
            .catch(error => {
                displayPaymentError("Une erreur technique est survenue lors de la vérification finale. Nous allons vérifier manuellement.", data.checkoutIntentId);
            });


    }


    /**
     * Affiche un message d'erreur en cas d'échec de la vérification.
     * @param {string} message Le message à afficher.
     * @param {string} checkoutIntentId
     */
    function displayPaymentError(message, checkoutIntentId) {
        const spinner = document.getElementById('payment-check-spinner');
        const msg = document.getElementById('payment-check-message');
        const errorContainer = document.getElementById('payment-check-error');

        if(spinner) {
            spinner.style.display = 'none';
        }
        if(msg) {
            msg.style.display = 'none';
        }
        if(errorContainer) {
            errorContainer.innerHTML = message + `<br><small>ID de transaction pour référence : ${checkoutIntentId}</small>`;
            errorContainer.style.display = 'block';
        }
    }

});