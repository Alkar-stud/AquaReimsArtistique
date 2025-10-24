document.addEventListener('DOMContentLoaded', () => {
    const reservationDetailModal = document.getElementById('reservationDetailModal');
    if (reservationDetailModal) {
        reservationDetailModal.addEventListener('show.bs.modal', async (event) => {
            const canUpdate = reservationDetailModal.dataset.canUpdate === 'true';

            const button = event.relatedTarget; // Le bouton qui a déclenché la modale
            const reservationId = button.getAttribute('data-reservation-id');

            // On récupère l'état de lecture seule depuis l'attribut de la modale
            const isReadOnly = reservationDetailModal.dataset.isReadonly === 'true';

            // Afficher un loader pendant le chargement
            const modalBody = reservationDetailModal.querySelector('.modal-body');
            // On sauvegarde le contenu HTML original de la modale avant de mettre le spinner
            const originalModalBodyHtml = modalBody.innerHTML;
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

            // On prépare la fonction de gestion du clic sur "Marquer comme payé"
            // On la définit ici pour avoir accès à la variable `reservation` qui sera chargée plus bas.
            const handleMarkAsPaidClick = async () => {
                if (!confirm("Êtes-vous sûr de vouloir marquer cette réservation comme entièrement payée ?")) {
                    return;
                }
                try {
                    const response = await apiPost('/gestion/reservations/mark-as-paid', {
                        reservationId: reservation.id
                    });
                    if (response.success) {
                        alert('La réservation a été marquée comme payée.');
                        // On recharge la page pour voir les changements
                        window.location.reload();
                    }
                } catch (error) {
                    alert(error.userMessage || 'Une erreur est survenue.');
                }
            };


            try {
                const response = await fetch(`/gestion/reservations/details/${reservationId}`);

                // On récupère toujours la réponse en texte pour pouvoir la déboguer
                const responseText = await response.text();

                if (!response.ok) {
                    // Si la réponse n'est pas OK, le texte est le message d'erreur du serveur
                    throw new Error(`Erreur ${response.status}: ${responseText}`);
                }

                let reservation;
                try {
                    // On essaie de parser le texte en JSON
                    reservation = JSON.parse(responseText);
                } catch (e) {
                    // Si le parsing échoue, on affiche le texte brut dans la console et on lance une erreur claire
                    console.error("Erreur de parsing JSON. Réponse brute du serveur :", responseText);
                    throw new Error("La réponse du serveur n'est pas un JSON valide.");
                }
                console.log('reservation : ', reservation);
                // On restaure le contenu HTML original
                modalBody.innerHTML = originalModalBodyHtml;

                // Construire le contenu de la modale avec les données
                // --- Initialisation du composant Contact ---
                // On lui passe le formulaire qui contient les champs de contact
                const contactForm = reservationDetailModal.querySelector('#reservationDetailForm');
                if (contactForm) App.Components.Contact.init(contactForm);

                document.getElementById('modal_reservation_id').value = reservation.id;
                document.getElementById('modal_reservation_token').value = reservation.token;

                const reservationTokenToDisplay = document.getElementById('modal-reset-token');
                if (reservationTokenToDisplay) {
                    reservationTokenToDisplay.innerText = reservation.token;
                }
                const tokenExpireAtEl = document.getElementById('modal-modification-token-expire-at');
                if (tokenExpireAtEl) {
                    const toDatetimeLocal = (raw) => {
                        if (!raw) return '';
                        const d = new Date(raw);
                        if (isNaN(d.getTime())) return '';
                        const pad = n => String(n).padStart(2, '0');
                        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                    };
                    tokenExpireAtEl.value = toDatetimeLocal(reservation.tokenExpireAt);
                }

                // --- Mise à jour de l'UI via les composants ---
                App.Components.Contact.updateUI(reservation);
                const participantsSection = reservationDetailModal.querySelector('#modal-participants-section');
                if (participantsSection) App.Components.Participants.init(participantsSection, isReadOnly);

                // --- Remplissage des informations de paiement ---
                const totalAmount = reservation.totalAmount || 0;
                const amountPaid = reservation.totalAmountPaid || 0;
                const amountDue = totalAmount - amountPaid;

                const markAsPaidDiv = document.getElementById('div-modal-mark-as-paid');
                //Si amountDue est > 0, on affiche la coche pour noter comme payé
                if (amountDue > 0) {
                    if (markAsPaidDiv) {
                        if (amountDue > 0) {
                            markAsPaidDiv.classList.remove('d-none');
                        } else {
                            markAsPaidDiv.classList.add('d-none');
                        }
                        // On attache l'écouteur de clic seulement si la div est visible.
                        if (amountDue > 0 && !markAsPaidDiv.dataset.listenerAttached) {
                            markAsPaidDiv.addEventListener('click', handleMarkAsPaidClick);
                            markAsPaidDiv.dataset.listenerAttached = 'true';
                        }
                    }
                }

                // Calcul du total des dons
                const totalDonation = reservation.payments.reduce((acc, payment) => {
                    return acc + (payment.partOfDonation || 0);
                }, 0);

                document.getElementById('modal-total-cost').textContent = (totalAmount / 100).toFixed(2).replace('.', ',');
                document.getElementById('modal-amount-paid').textContent = (amountPaid / 100).toFixed(2).replace('.', ',');
                const amountDueEl = document.getElementById('modal-amount-due');
                if (amountDueEl) {
                    amountDueEl.textContent = (amountDue / 100).toFixed(2).replace('.', ',');
                    // On change la couleur en fonction du solde
                    amountDueEl.classList.toggle('text-danger', amountDue > 0);
                    amountDueEl.classList.toggle('text-info', amountDue <= 0);
                }
                document.getElementById('modal-don-amount').textContent = (totalDonation / 100).toFixed(2).replace('.', ',');

                // --- Remplissage du détail des paiements ---
                const paymentDetailsContainer = document.getElementById('modal-payment-details-container');
                const togglePaymentDetailsLink = document.getElementById('toggle-payment-details');
                paymentDetailsContainer.innerHTML = ''; // On vide le contenu précédent
                togglePaymentDetailsLink.style.display = 'none'; // On cache le lien par défaut

                if (reservation.payments && reservation.payments.length > 0) {
                    togglePaymentDetailsLink.style.display = 'block'; // On affiche le lien s'il y a des paiements

                    // Génération des boutons d'action des paiements (refresh / refund) avec permissions
                    let paymentDetailsHtml = '<ul class="list-group list-group-flush text-start">';
                    reservation.payments.forEach(payment => {
                        const paymentDateTime = payment.createdAt ? new Date(payment.createdAt).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
                        const paymentAmount = (payment.amountPaid / 100).toFixed(2).replace('.', ',');

                        let paymentStatus = '';
                        if (payment.status === 'Authorized' || payment.status === 'Processed') {
                            paymentStatus = '<span class="badge bg-success payment-status-badge">Réussi</span>';
                        } else if (payment.status === 'Refunded') {
                            paymentStatus = '<span class="badge bg-danger payment-status-badge">Remboursé</span>';
                        } else {
                            paymentStatus = `<span class="badge bg-warning payment-status-badge">${payment.status}</span>`;
                        }

                        let donationText = '';
                        if (payment.partOfDonation && payment.partOfDonation > 0) {
                            const donationAmount = (payment.partOfDonation / 100).toFixed(2).replace('.', ',');
                            donationText = ` <small class="text-info">(dont don ${donationAmount} €)</small>`;
                        }

                        let refundBtnHtml = '';
                        if (canUpdate && (payment.status === 'Authorized' || payment.status === 'Processed') && payment.type !== 'ref') {
                            refundBtnHtml = `
                                  <button class="btn btn-warning refund-btn" data-payment-id="${payment.paymentId}">
                                     Remboursement
                                 </button>`;
                        }

                        let refreshBtnHtml = '';
                        // On n'affiche le bouton de rafraîchissement que si le paiement n'est pas déjà remboursé
                        if (canUpdate && payment.status !== 'Refunded' && payment.type !== 'ref') {
                            refreshBtnHtml = `<button class="btn refresh-btn" data-payment-id="${payment.paymentId}">
                                    <i class="bi bi-arrow-clockwise"></i>
                                 </button>`;
                        }

                        paymentDetailsHtml += `
                             <li class="list-group-item d-flex justify-content-between align-items-center">
                                 <div>
                                     <strong>${payment.type.toUpperCase()}</strong> - ${paymentAmount} €${donationText}
                                     <small class="text-muted d-block">${payment.paymentId} - Le ${paymentDateTime}</small>
                                 </div>

                                 <div>
                                 ${refreshBtnHtml}
                                 ${paymentStatus}
                                 ${refundBtnHtml}
                                 </div>
                             </li>
                         `;
                    });
                    paymentDetailsHtml += '</ul>';
                    paymentDetailsContainer.innerHTML = paymentDetailsHtml;
                }

                // --- Remplissage des participants via le composant ---
                App.Components.Participants.updateUI(reservation);

                // --- Remplissage des compléments via le composant ---
                const complementsSection = document.getElementById('modal-complements-section');
                if (reservation.complements && reservation.complements.length > 0) {
                    complementsSection.style.display = 'block';
                    // On initialise le composant ici, juste avant de l'utiliser
                    App.Components.Complements.init(complementsSection, {
                        isModal: true,
                        token: reservation.token,
                        reservationId: reservation.id
                    });
                    App.Components.Complements.updateUI(reservation);
                } else {
                    complementsSection.style.display = 'none';
                }

                const saveToggleBtn = document.getElementById('modal-save-and-toggle-checked-btn');
                if (saveToggleBtn) {
                    // Si la réservation est déjà vérifiée, on propose de la marquer comme non vérifiée, sinon comme vérifiée
                    saveToggleBtn.innerHTML = reservation.isChecked
                        ? '<i class="bi bi-x"></i>&nbsp;Enregistrer et marquer comme non vérifié'
                        : '<i class="bi bi-check"></i>&nbsp;Enregistrer et marquer comme vérifié';

                    saveToggleBtn.dataset.targetChecked = reservation.isChecked ? '0' : '1';
                }

            } catch (error) {
                modalBody.innerHTML = `<div class="alert alert-danger"><strong>Erreur de communication avec le serveur :</strong><pre>${error.message}</pre></div>`;
            }
        });

        // Nettoyage des écouteurs d'événements à la fermeture de la modale pour éviter les fuites de mémoire
        reservationDetailModal.addEventListener('hidden.bs.modal', () => {
            const markAsPaidDiv = document.getElementById('div-modal-mark-as-paid');
            if (markAsPaidDiv) {
                delete markAsPaidDiv.dataset.listenerAttached;
            }
        });

    }
    // Gestion du clic sur le lien pour afficher/masquer les détails de paiement
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'toggle-payment-details') {
            e.preventDefault();
            const link = e.target;
            const containerDetail = document.getElementById('modal-payment-details-container');

            const isHidden = containerDetail.style.display === 'none';
            containerDetail.style.display = isHidden ? 'block' : 'none';
            link.textContent = isHidden ? 'Masquer le détail des paiements' : 'Voir le détail des paiements';
        }
    });

    //Gestion du clic sur Refresh
    document.addEventListener('click', function(e) {
        const refreshButton = e.target.closest('.refresh-btn');
        if (refreshButton) {
            e.preventDefault();
            const paymentId = refreshButton.dataset.paymentId;

            // Exemple d'utilisation de apiGet
            apiGet('/reservation/checkPaymentState', { id: paymentId })
                .then(data => {
                    const newStatus = data.state;
                    const newTotalAmountPaid = data.totalAmountPaid;

                    // --- Mise à jour des montants globaux de la réservation ---
                    if (newTotalAmountPaid !== undefined) {
                        const amountPaidElement = document.getElementById('modal-amount-paid');
                        const amountDueElement = document.getElementById('modal-amount-due');
                        const totalCostElement = document.getElementById('modal-total-cost');

                        if (amountPaidElement && amountDueElement && totalCostElement) {
                            // On récupère le coût total (qui ne change pas) pour recalculer le reste à payer.
                            // On convertit la chaîne "123,45" en nombre.
                            const totalCostInCents = parseFloat(totalCostElement.textContent.replace(',', '.')) * 100;

                            // On met à jour le montant payé
                            amountPaidElement.textContent = (newTotalAmountPaid / 100).toFixed(2).replace('.', ',');
                            // On met à jour le reste à payer
                            amountDueElement.textContent = ((totalCostInCents - newTotalAmountPaid) / 100).toFixed(2).replace('.', ',');
                        }
                    }

                    // On trouve la ligne (<li>) correspondant au paiement pour cibler nos modifications
                    const listItem = refreshButton.closest('.list-group-item');
                    if (!listItem) return;

                    const statusBadge = listItem.querySelector('.payment-status-badge');

                    // On masque le bouton de rafraîchissement si le nouveau statut est "Refunded".
                    if (newStatus === 'Refunded') {
                        refreshButton.style.display = 'none';
                    }

                    const refundButton = listItem.querySelector('.refund-btn');

                    if (statusBadge) {
                        statusBadge.textContent = newStatus;
                        // On retire toutes les classes de couleur pour en mettre une nouvelle
                        statusBadge.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-info');

                        if (newStatus === 'Refunded') {
                            statusBadge.textContent = 'Remboursé';
                            statusBadge.classList.add('bg-danger');
                            // Si le paiement est remboursé, on supprime le bouton de remboursement
                            if (refundButton) {
                                refundButton.remove();
                            }
                        } else if ((newStatus === 'Authorized' || newStatus === 'Processed')) {
                            statusBadge.textContent = 'Réussi';
                            statusBadge.classList.add('bg-success');
                            // Si le statut est autorisé et que le bouton n'existe pas, on le recrée
                            if (!refundButton) {
                                const newRefundButton = document.createElement('button');
                                newRefundButton.className = 'btn btn-warning refund-btn';
                                newRefundButton.dataset.paymentId = paymentId;
                                newRefundButton.textContent = 'Remboursement';
                                // On l'insère après le badge de statut
                                statusBadge.parentNode.insertBefore(newRefundButton, statusBadge.nextSibling);
                            }
                        } else {
                            // Pour tout autre statut (Pending, etc.)
                            statusBadge.classList.add('bg-info');
                        }
                    }
                })
                .catch(err => {
                    console.error('Erreur lors du refresh :', err);
                    alert(err.userMessage || 'Une erreur est survenue.');
                });
        }
    });

    //Gestion du clic sur Remboursement
    document.addEventListener('click', function(e) {
        const refundButton = e.target.closest('.refund-btn');
        if (refundButton) {
            e.preventDefault();
            const paymentId = refundButton.dataset.paymentId;
            console.log('Clic sur remboursement pour le paiement ID :', paymentId);
            alert('L\'éventuel don n\'est pas remboursé');


        }
    });

    // Gestion du clic sur les boutons d'action du footer de la modale
    document.addEventListener('click', function(e) {
        const closeButton = e.target.closest('#modal-close-btn'); // Le bouton "Fermer" du footer
        const deleteButton = e.target.closest('#modal-reservation-delete-btn');
        const cancelButton = e.target.closest('#modal-reservation-cancel-btn');
        const saveAndToggleButton = e.target.closest('#modal-save-and-toggle-checked-btn');
        const actionButton = saveAndToggleButton || closeButton || deleteButton || cancelButton;

        if (actionButton) {
            e.preventDefault();

            // On s'assure que la position est bien sauvegardée avant l'action
            ScrollManager.save();

            const reservationId = document.getElementById('modal_reservation_id').value;
            const isCheckedTarget = actionButton.dataset.targetChecked || null; // '1', '0', ou null

            // Ici, logique d'appel API pour sauvegarder les données
            // Pour le bouton "Fermer", il n'y a pas d'action, on recharge directement.
            // Pour les autres, on attend la réussite de l'action.
            if (saveAndToggleButton) {
                console.log(`Sauvegarde pour la réservation ${reservationId}. Cible isChecked : ${isCheckedTarget}`);
                // Simuler un appel API qui réussit
                Promise.resolve({ success: true })
                    .then(() => {
                        //window.location.reload();
                    });
            } else {
                // Si c'est juste le bouton "Fermer", on recharge sans attendre.
                window.location.reload();
            }
        }
    });

    //Gestion du clic sur le toogle pour que la réservation soit en statut vérifié
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const el = this;
            const itemId = Number(this.dataset.id);
            const newStatus = Boolean(this.checked);
            el.disabled = true;

            window.apiPost('/gestion/reservation/toggle-status', { id: itemId, status: newStatus }, {
                headers: { 'X-CSRF-Context': '/gestion/accueil' }
            })
                .then(data => {
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    el.disabled = false;
                    alert('Une erreur de communication est survenue. Veuillez réessayer.');
                });
        });
    });

});