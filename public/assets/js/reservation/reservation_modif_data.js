document.addEventListener('DOMContentLoaded', function () {
    // Variable pour stocker la position de défilement actuelle
    let currentScrollPosition = 0;
    const formatEuro = (n) => n.toFixed(2).replace('.', ',') + ' €';

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

    // Récupération du conteneur principal
    const container = document.getElementById('reservation-data-container');
    const baseDueCents = container ? parseInt(container.dataset.baseDueCents || '0', 10) : 0;
    const baseDueEuros = baseDueCents / 100;


    // --- Gestion du don et du paiement ---
    const donationSlider = document.getElementById('donation-slider');
    const donationAmountDisplay = document.getElementById('donation-amount-display');
    const creditMsg = document.getElementById('credit-message');
    const dueLine = document.getElementById('due-line');
    const amountDueEl = document.getElementById('amount-due');
    const totalWithDonationContainer = document.getElementById('total-with-donation');
    const totalToPayWithDonationEl = document.getElementById('total-to-pay-with-donation');
    const paySection = document.getElementById('pay-balance-section');

    function updateDonation() {
        if (!donationSlider) return;
        const donationEuros = parseFloat(donationSlider.value) || 0;
        const donationCents = Math.round(donationEuros * 100);
        const totalToPayCents = Math.max(0, baseDueCents,  donationCents);
        const totalToPayEuros = totalToPayCents / 100;

        if (donationAmountDisplay) {
            donationAmountDisplay.textContent = formatEuro(donationEuros);
        }

        // Total avec don: visible si don>0, ou si baseDue<=0 et le don crée un dû
        if (totalWithDonationContainer && totalToPayWithDonationEl) {
            if (donationCents > 0 || (baseDueCents <= 0 && totalToPayCents > 0)) {
                totalWithDonationContainer.classList.remove('d-none');
                totalToPayWithDonationEl.textContent = formatEuro(totalToPayEuros);
            } else {
                totalWithDonationContainer.classList.add('d-none');
            }
        }

        // Bloc montant dû: si baseDue<=0 et don crée un dû, on bascule du crédit au reste à payer
        if (baseDueCents <= 0) {
            if (totalToPayCents > 0) {
                if (creditMsg) creditMsg.classList.add('d-none');
                if (dueLine) dueLine.classList.remove('d-none');
                if (amountDueEl) amountDueEl.textContent = formatEuro(totalToPayEuros);
            } else {
                if (creditMsg) creditMsg.classList.remove('d-none');
                if (dueLine) dueLine.classList.add('d-none');
            }
        } else {
            // Base dû > 0: on garde "reste à payer" sur le dû initial
            if (dueLine) dueLine.classList.remove('d-none');
            if (amountDueEl) amountDueEl.textContent = formatEuro(Math.max(0, baseDueEuros));
        }

        // Bouton paiement visible si total à payer (dû  don) > 0
        if (paySection) {
            if (totalToPayCents > 0) paySection.classList.remove('d-none');
            else paySection.classList.add('d-none');
        }

        // Persistance locale du don
        if (container && container.dataset.reservationId) {
            localStorage.setItem(`donation_reservation_${container.dataset.reservationId}`, donationEuros);
        }
    }

    if (donationSlider) {
        const saved = container && container.dataset.reservationId
            ? localStorage.getItem(`donation_reservation_${container.dataset.reservationId}`)
            : null;
        if (saved !== null && !isNaN(parseFloat(saved))) donationSlider.value = String(saved);
        updateDonation();
        donationSlider.addEventListener('input', updateDonation);
    }


    // Toute la logique d'interaction avec le formulaire ne s'exécute que si le conteneur existe.
    if (container) {
        const {reservationId, token} = container.dataset;

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
                    token: token,
                    field: this.dataset.field,
                    value: this.value
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
                    const data = {
                        typeField: 'detail',
                        token: token,
                        id: this.dataset.detailId,
                        field: this.dataset.field,
                        value: this.value
                    };

                    if (feedbackSpan) {
                        updateField(feedbackSpan, data);
                    }
                });
            });
        }

        // --- Gestion des quantités de compléments ---
        const complementBtns = document.querySelectorAll('.complement-qty-btn');
        if (complementBtns.length > 0) {
            complementBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const complementId = this.dataset.complementId;
                    const qtyInput = document.getElementById(`qty-complement-${complementId}`);

                    if (action === 'minus') {
                        //Selon si la diminution de 1 entre la suppression complète
                        let confirmationMessage;
                        if (qtyInput.value <= 1) {
                            confirmationMessage = "Souhaitez-vous vraiment supprimer cet élément de votre commande ?\nLe trop perçu ne sera pas remboursé !!\nMais il peut servir à prendre autre chose en ligne uniquement !";
                        } else {
                            confirmationMessage = "Souhaitez-vous vraiment retirer 1 ticket de cet élément de votre commande ?\nLe trop perçu ne sera pas remboursé !!\nMais il peut servir à prendre autre chose en ligne uniquement !";
                        }
                        if (!confirm(confirmationMessage)) {
                            return;
                        }
                    } else {
                        const confirmationMessage = "Confirmez-vous l'ajout de cet article ?\nLe montant total de votre réservation sera mis à jour.";
                        if (!confirm(confirmationMessage)) {
                            return;
                        }
                    }

                    const data = {
                        typeField: 'complement',
                        token: token,
                        id: complementId,
                        action: action
                    };
                    updateField(null, data, function(result) {
                        if (result.success && result.newQty !== undefined) {
                            qtyInput.value = result.newQty;
                            if (result.newSubtotal !== undefined) {
                                document.getElementById(`subtotal-complement-${complementId}`).textContent = result.newSubtotal;
                            }
                            if (result.newTotalAmount !== undefined) {
                                document.getElementById('new-total-amount').textContent = result.newTotalAmount;
                            }
                            if (result.newAmountDue !== undefined) {
                                document.getElementById('amount-due').textContent = result.newAmountDue;
                            }
                        }
                    });
                });
            });
        }

        // --- Gestion de l'ajout de compléments ---
        const addComplementBtns = document.querySelectorAll('.add-complement-btn');
        if (addComplementBtns.length > 0) {
            addComplementBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const confirmationMessage = "Confirmez-vous l'ajout de cet article ?\nLe montant total de votre réservation sera mis à jour.";
                    if (!confirm(confirmationMessage)) {
                        return;
                    }
                    const tarifId = this.dataset.tarifId;

                    const data = {
                        typeField: 'complement',
                        token: token,
                        tarifId: tarifId,
                        qty: 1
                    };

                    updateField(null, data);
                });
            });
        }

        // --- Gestion de l'annulation de la réservation ---
        const cancelBtn = document.querySelector('.cancel-button');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (confirm("Êtes-vous sûr de vouloir annuler cette réservation ?\nCette action est irréversible.")) {
                    if (confirm("Êtes-vous toujours sûr ?\n Vous ne pourrez prétendre à aucun remboursement !")) {
                        const data = {
                            typeField: 'cancel',
                            token: token
                        };

                        updateField(null, data);
                    }
                }
            });
        }


        // --- Gestion du don ---
        const donationSlider = document.getElementById('donation-slider');
        if (donationSlider) {
            const donationAmountDisplay = document.getElementById('donation-amount-display');
            const amountDueEl = document.getElementById('amount-due');
            const totalToPayWithDonationEl = document.getElementById('total-to-pay-with-donation');
            const totalWithDonationContainer = document.getElementById('total-with-donation-container');
            const paySection = document.getElementById('pay-balance-section');
            const dueLine = document.getElementById('due-line');
            const creditMsg = document.getElementById('credit-message');

            const formatEuro = (n) => n.toFixed(2).replace('.', ',') + ' €';

            const updateDonation = () => {
                const donationEuros = parseFloat(donationSlider.value) || 0;
                const baseDueEuros = baseDueCents / 100;
                const totalToPayEuros = Math.max(0, baseDueEuros + donationEuros);

                if (donationAmountDisplay) donationAmountDisplay.textContent = formatEuro(donationEuros);

                if (totalWithDonationContainer && totalToPayWithDonationEl) {
                    if (donationEuros > 0) {
                        totalWithDonationContainer.classList.remove('d-none');
                        totalToPayWithDonationEl.textContent = formatEuro(totalToPayEuros);
                    } else {
                        totalWithDonationContainer.classList.add('d-none');
                    }
                }

                // Met à jour l’affichage "reste à payer" si baseDue <= 0
                if (baseDueEuros <= 0) {
                    if (donationEuros + baseDueEuros > 0) {
                        if (dueLine) dueLine.classList.remove('d-none');
                        if (amountDueEl) amountDueEl.textContent = formatEuro(totalToPayEuros);
                        if (creditMsg) creditMsg.classList.add('d-none');
                    } else {
                        if (dueLine) dueLine.classList.add('d-none');
                        if (creditMsg) creditMsg.classList.remove('d-none');
                    }
                } else {
                    if (dueLine) dueLine.classList.remove('d-none');
                }

                // Affiche/masque la section paiement selon le total à payer (don compris)
                if (paySection) {
                    if (totalToPayEuros > 0) paySection.classList.remove('d-none');
                    else paySection.classList.add('d-none');
                }

                // Persistance locale
                const reservationId = container ? container.dataset.reservationId : 'unknown';
                localStorage.setItem(`donation_reservation_${reservationId}`, donationEuros);
            };

            updateDonation();
            donationSlider.addEventListener('input', updateDonation);
        }

        const payBalanceBtn = document.getElementById('pay-balance-btn');
        if (payBalanceBtn) payBalanceBtn.addEventListener('click', handlePayBalance);
    } // Fin du bloc if (container)

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

        // Appel AJAX pour mettre à jour les données
        fetch('/modifData/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                // Récupérer d'abord le texte brut
                return response.text().then(text => {
                    // Ensuite essayer de parser en JSON si possible
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        console.error("Erreur de parsing JSON:", error);
                        console.log("Contenu non parsable:", text);
                        throw new Error("Réponse non valide du serveur");
                    }
                });
            })
            .then(result => {
                console.log('result : ', result);
                if (result.success) {
                    if (feedbackSpan) {
                        showFeedback(feedbackSpan, 'success');
                    }
                    if (successCallback) {
                        successCallback(result);
                    }
                } else {
                    if (feedbackSpan) {
                        showFeedback(feedbackSpan, 'error', result.message || 'Une erreur est survenue');
                    }
                }
                window.location.reload();
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour:', error);
                if (feedbackSpan) {
                    showFeedback(feedbackSpan, 'error', 'Une erreur est survenue');
                }
            });
    }

    // Détecter si nous sommes en mode vérification de paiement
    const paymentCheckContainer = document.getElementById('payment-check-container');
    if (paymentCheckContainer) {
        const checkoutIntentId = paymentCheckContainer.dataset.checkoutId;
        if (checkoutIntentId) {
            checkPaymentStatus(checkoutIntentId, 0);
        }
    }

    // Bouton de paiement (si une logique existe ailleurs pour lancer HelloAsso, on ne la duplique pas ici)
    const payBtn = document.getElementById('pay-balance-btn');
    if (payBtn) {
        payBtn.addEventListener('click', function (e) {
            // Laisser la logique existante s’exécuter (ce patch gère seulement l’affichage)
        });
    }

});

async function handlePayBalance(event) {
    event.preventDefault(); // Empêche le lien de naviguer vers "#"

    const payBalanceBtn = document.getElementById('pay-balance-btn');
    const container = document.getElementById('reservation-data-container');
    const reservationToken = container.dataset.token;
    const payBalanceImg = payBalanceBtn.querySelector('img');

    const baseDueCents = parseInt(container.dataset.baseDueCents || '0', 10);
    const donationSlider = document.getElementById('donation-slider');
    const donationInCents = Math.round((parseFloat(donationSlider.value) || 0) * 100);

    // total à payer = max(0, baseDue + don)
    const totalToPayInCents = Math.max(0, baseDueCents + donationInCents);
    if (totalToPayInCents <= 0) {
        showToast('Le montant à payer est nul ou invalide.', 'warning');
        return;
    }


    if (totalToPayInCents <= 0) {
        showToast('Le montant à payer est nul ou invalide.', 'warning');
        return;
    }

    // Désactiver le lien et montrer un état de chargement
    payBalanceBtn.style.pointerEvents = 'none';
    if (payBalanceImg) payBalanceImg.style.opacity = '0.5';

    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm ms-2';
    spinner.setAttribute('role', 'status');
    payBalanceBtn.parentNode.appendChild(spinner);

    try {
        const response = await fetch('/modifData/createPayment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                token: reservationToken,
                amountToPay: totalToPayInCents,
                containsDonation: donationInCents > 0
            })
        });

        const result = await response.json();

        if (result.success && result.redirectUrl) {
            // Rediriger l'utilisateur vers la page de paiement HelloAsso
            window.location.href = result.redirectUrl;
        } else {
            showToast(result.message || 'Une erreur est survenue.', 'danger');
            // Réactiver le bouton en cas d'erreur
            payBalanceBtn.style.pointerEvents = 'auto';
            if (payBalanceImg) payBalanceImg.style.opacity = '1';
            spinner.remove();
        }

    } catch (error) {
        console.error('Erreur lors de la création du paiement:', error);
        showToast('Une erreur technique est survenue.', 'danger');
        // Réactiver le bouton en cas d'erreur
        payBalanceBtn.style.pointerEvents = 'auto';
        if (payBalanceImg) payBalanceImg.style.opacity = '1';
        spinner.remove();
    }
}


/**
 * Interroge le backend pour connaître le statut du paiement.
 * @param {string} checkoutIntentId L'ID de l'intention de paiement.
 * @param {number} attempt Le numéro de la tentative actuelle.
 */
function checkPaymentStatus(checkoutIntentId, attempt) {
    const maxAttempts = 5; // Tenter pendant 30 secondes max
    const delay = 5000; // 5 secondes entre chaque tentative

    if (attempt >= maxAttempts) {
        // Le polling a échoué, on passe au plan B : la vérification forcée.
        forceCheckPayment(checkoutIntentId);
        return;
    }

    fetch('/reservation/checkPayment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ checkoutIntentId: checkoutIntentId })
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Paiement confirmé !
                const spinner = document.getElementById('payment-check-spinner');
                const msg = document.getElementById('payment-check-message');
                const successMsg = document.getElementById('payment-check-success');

                if(spinner) spinner.style.display = 'none';
                if(msg) msg.style.display = 'none';
                if(successMsg) successMsg.style.display = 'block';

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
 * @param {string} checkoutIntentId
 */
function forceCheckPayment(checkoutIntentId) {
    const msg = document.getElementById('payment-check-message');
    if (msg) msg.textContent = "La confirmation automatique prend du temps. Nous lançons une vérification manuelle...";

    fetch('/modifData/forceCheckPayment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ checkoutIntentId: checkoutIntentId })
    })
        .then(response => response.json())
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
                displayPaymentError(result.message || "La vérification du paiement a échoué. Nous allons vérifier manuellement. Votre réservation est bien enregistrée.", checkoutIntentId);
            }
        })
        .catch(error => {
            displayPaymentError("Une erreur technique est survenue lors de la vérification finale. Nous allons vérifier manuellement.", checkoutIntentId);
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

    if(spinner) spinner.style.display = 'none';
    if(msg) msg.style.display = 'none';
    if(errorContainer) {
        errorContainer.innerHTML = message + `<br><small>ID de transaction pour référence : ${checkoutIntentId}</small>`;
        errorContainer.style.display = 'block';
    }
}