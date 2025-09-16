document.addEventListener('DOMContentLoaded', function () {
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

    // Récupération du conteneur principal
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        console.error("Le conteneur de données de réservation est introuvable.");
        return;
    }

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

    // Ajoutez ici les gestionnaires pour les autres éléments (participants, compléments, etc.)

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
});
