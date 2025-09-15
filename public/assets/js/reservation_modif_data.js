document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        console.error("Le conteneur de données de réservation est introuvable.");
        return;
    }

    const { reservationId, token } = container.dataset;

    // --- Gestion des détails des participants ---
    const editableDetails = document.querySelectorAll('.editable-detail');
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

    // --- Gestion des infos du contact principal ---
    const editableContacts = document.querySelectorAll('.editable-contact');
    editableContacts.forEach(input => {
        input.addEventListener('blur', function () {
            const feedbackSpan = this.parentElement.querySelector('.feedback-span');
            const field = this.dataset.field;
            const value = this.value;

            // Validation de l'email
            if (field === 'email' && !validateEmail(value)) {
                feedbackSpan.textContent = '✗';
                feedbackSpan.classList.add('text-danger');
                feedbackSpan.title = 'Adresse e-mail invalide.';
                return; // On arrête l'exécution
            }

            // Validation du téléphone (s'il n'est pas vide)
            if (field === 'phone' && value.trim() !== '' && !validateTel(value)) {
                feedbackSpan.textContent = '✗';
                feedbackSpan.classList.add('text-danger');
                feedbackSpan.title = 'Format de téléphone invalide.';
                return; // On arrête l'exécution
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

    // --- Gestion des quantités des compléments ---
    document.querySelectorAll('.complement-qty-btn').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.dataset.action;
            const complementId = this.dataset.complementId;
            const qtyInput = document.getElementById(`qty-complement-${complementId}`);
            let currentQty = parseInt(qtyInput.value, 10);

            if (action === 'minus') {
                if (currentQty <= 0) return;
                const confirmationMessage = "Souhaitez-vous vraiment retirer 1 ticket de cet item de votre commande ?\nLe trop perçu ne sera pas remboursé !!\nMais il peut servir à prendre autre chose en ligne uniquement !";
                if (!confirm(confirmationMessage)) {
                    return;
                }
                currentQty--;
            } else {
                const confirmationMessage = "Confirmez-vous l'ajout de cet article ?\nLe montant total de votre réservation sera mis à jour.";
                if (!confirm(confirmationMessage)) {
                    return;
                }
                currentQty++;
            }

            qtyInput.value = currentQty;

            const data = {
                typeField: 'complement',
                token: token,
                id: complementId,
                qty: currentQty
            };

            // On utilise un feedback "global" pour les totaux
            const totalFeedbackSpan = document.createElement('span'); // Span virtuel
            updateField(totalFeedbackSpan, data, (result) => {
                // Callback de succès pour mettre à jour les totaux
                if (result.success && typeof result.newTotalAmount !== 'undefined') {
                    updateTotals(result.newTotalAmount);
                }
            });
        });
    });

    // --- Gestion de l'AJOUT de nouveaux compléments ---
    document.querySelectorAll('.add-complement-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tarifId = this.dataset.tarifId;

            const confirmationMessage = "Confirmez-vous l'ajout de cet article ?\nLe montant total de votre réservation sera mis à jour.";
            if (!confirm(confirmationMessage)) {
                return;
            }

            const data = {
                typeField: 'complement',
                token: token,
                tarifId: tarifId, // On envoie l'ID du tarif, pas l'ID du complément
                qty: 1 // On ajoute toujours 1
            };

            // On utilise un feedback "global" pour les totaux
            const totalFeedbackSpan = document.createElement('span'); // Span virtuel
            updateField(totalFeedbackSpan, data, (result) => {
                // En cas de succès, on recharge la page pour voir le nouvel article et les totaux mis à jour.
                if (result.success) window.location.reload();
            });
        });
    });

    function updateTotals(newTotalAmount) {
        const totalPaid = parseFloat(document.getElementById('total-paid-amount').textContent.replace(',', '.'));
        const newTotal = newTotalAmount / 100;

        document.getElementById('new-total-amount').textContent = newTotal.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });

        const amountDue = newTotal - totalPaid;
        const amountDueContainer = document.getElementById('amount-due-container');
        const amountDueSpan = document.getElementById('amount-due');

        if (amountDue > 0) {
            amountDueContainer.innerHTML = `Reste à payer : <span class="text-danger" id="amount-due">${amountDue.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}</span>`;
        } else if (amountDue < 0) {
            amountDueContainer.innerHTML = `Crédit disponible : <span class="text-info" id="amount-due">${Math.abs(amountDue).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}</span>`;
        } else {
            amountDueContainer.innerHTML = '';
        }
    }



    // --- Fonction générique de mise à jour ---
    function updateField(feedbackSpan, data, successCallback = null) {
        feedbackSpan.textContent = '...';
        feedbackSpan.classList.remove('text-success', 'text-danger');
        feedbackSpan.title = ''; // On réinitialise l'infobulle

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
                    feedbackSpan.textContent = '✓';
                    feedbackSpan.classList.add('text-success');
                    if (successCallback) {
                        successCallback(result);
                    }
                } else {
                    feedbackSpan.textContent = '✗';
                    feedbackSpan.classList.add('text-danger');
                    // On affiche l'erreur dans l'infobulle au lieu d'une alerte
                    feedbackSpan.title = result.message || 'Une erreur est survenue.';
                    console.error('Erreur lors de la mise à jour:', result.message);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour:', error);
                feedbackSpan.textContent = '✗';
                feedbackSpan.classList.add('text-danger');
                feedbackSpan.title = 'Une erreur de communication est survenue. Veuillez réessayer.';
                console.error('Erreur de communication:', error);
            });
    }
});