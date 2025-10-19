document.addEventListener('DOMContentLoaded', () => {
    // Utiliser le ScrollManager global
    // Restaure la position au chargement
    ScrollManager.restore();


    // On sélectionne la liste déroulante, peu importe l'onglet actif
    // en se basant sur le début de son ID "event-selector-".
    const eventSelector = document.querySelector('[id^="event-selector-"]');

    // S'il n'y a pas d'événements, le sélecteur n'existe pas.
    if (eventSelector) {
        eventSelector.addEventListener('change', (event) => {
            const selectedSessionId = event.target.value;

            // Si une session est sélectionnée (et pas l'option vide)
            if (selectedSessionId) {
                // On recharge la page en ajoutant l'ID de la session dans l'URL.
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('s', selectedSessionId);
                window.location.href = currentUrl.toString();
            }
        });
    }

    //Gérer le changement de page
    const itemsPerPageSelector = document.getElementById('items-per-page-selector');
    if (itemsPerPageSelector) {
        itemsPerPageSelector.addEventListener('change', function() {
            const perPage = this.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', perPage);
            // On retourne à la première page pour éviter les incohérences
            currentUrl.searchParams.set('page', '1');
            window.location.href = currentUrl.toString();
        });
    }

    const reservationDetailModal = document.getElementById('reservationDetailModal');
    if (reservationDetailModal) {
        reservationDetailModal.addEventListener('show.bs.modal', async (event) => {
            const button = event.relatedTarget; // Le bouton qui a déclenché la modale
            const reservationId = button.getAttribute('data-reservation-id');

            // On récupère l'état de lecture seule depuis l'attribut de la modale
            const isReadOnly = reservationDetailModal.dataset.isReadonly === 'true';

            // Afficher un loader pendant le chargement
            const modalBody = reservationDetailModal.querySelector('.modal-body');
            // On sauvegarde le contenu HTML original de la modale avant de mettre le spinner
            const originalModalBodyHtml = modalBody.innerHTML;
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

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

                // On restaure le contenu HTML original
                modalBody.innerHTML = originalModalBodyHtml;

                // Reconstruire le contenu de la modale avec les données
                document.getElementById('modal_reservation_id').value = reservation.id;
                document.getElementById('modal_contact_name').value = reservation.name;
                document.getElementById('modal_contact_firstname').value = reservation.firstName;
                document.getElementById('modal_contact_email').value = reservation.email;
                document.getElementById('modal_contact_phone').value = reservation.phone || '';

                // --- Remplissage des informations de paiement ---
                const totalAmount = reservation.totalAmount || 0;
                const amountPaid = reservation.totalAmountPaid || 0;
                const amountDue = totalAmount - amountPaid;

                // Calcul du total des dons
                const totalDonation = reservation.payments.reduce((acc, payment) => {
                    return acc + (payment.partOfDonation || 0);
                }, 0);

                document.getElementById('modal-total-cost').textContent = (totalAmount / 100).toFixed(2).replace('.', ',');
                document.getElementById('modal-amount-paid').textContent = (amountPaid / 100).toFixed(2).replace('.', ',');
                document.getElementById('modal-amount-due').textContent = (amountDue / 100).toFixed(2).replace('.', ',');
                document.getElementById('modal-don-amount').textContent = (totalDonation / 100).toFixed(2).replace('.', ',');

                // --- Remplissage du détail des paiements ---
                const paymentDetailsContainer = document.getElementById('modal-payment-details-container');
                const togglePaymentDetailsLink = document.getElementById('toggle-payment-details');
                paymentDetailsContainer.innerHTML = ''; // On vide le contenu précédent
                togglePaymentDetailsLink.style.display = 'none'; // On cache le lien par défaut

                if (reservation.payments && reservation.payments.length > 0) {
                    togglePaymentDetailsLink.style.display = 'block'; // On affiche le lien s'il y a des paiements

                    let paymentDetailsHtml = '<ul class="list-group list-group-flush text-start">';
                    reservation.payments.forEach(payment => {
                        const paymentDateTime = payment.createdAt ? new Date(payment.createdAt).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
                        const paymentAmount = (payment.amountPaid / 100).toFixed(2).replace('.', ',');
                        const paymentStatus = payment.status === 'succeeded' ? '<span class="badge bg-success">Réussi</span>' : `<span class="badge bg-warning">${payment.status}</span>`;

                        let donationText = '';
                        if (payment.partOfDonation && payment.partOfDonation > 0) {
                            const donationAmount = (payment.partOfDonation / 100).toFixed(2).replace('.', ',');
                            donationText = ` <small class="text-info">(dont don ${donationAmount} €)</small>`;
                        }

                        paymentDetailsHtml += `
                             <li class="list-group-item d-flex justify-content-between align-items-center">
                                 <div>
                                     <strong>${payment.type.toUpperCase()}</strong> - ${paymentAmount} €${donationText}
                                     <small class="text-muted d-block">Le ${paymentDateTime}</small>
                                 </div>
                                 ${paymentStatus}
                             </li>
                         `;
                    });
                    paymentDetailsHtml += '</ul>';
                    paymentDetailsContainer.innerHTML = paymentDetailsHtml;
                }


                // --- Remplissage des participants ---
                const participantsList = document.getElementById('modal-participants-list');
                participantsList.innerHTML = ''; // On vide la liste

                // On groupe les participants par tarif
                const participantsByTarif = reservation.details.reduce((acc, detail) => {
                    if (!acc[detail.tarifId]) {
                        acc[detail.tarifId] = {
                            tarifName: detail.tarifName,
                            tarifDescription: detail.tarifDescription || '',
                            tarifPrice: detail.tarifPrice || 0,
                            participants: []
                        };
                    }
                    acc[detail.tarifId].participants.push(detail);
                    return acc;
                }, {});

                for (const tarifId in participantsByTarif) {
                    const group = participantsByTarif[tarifId];
                    const groupTotal = group.participants.length * group.tarifPrice;

                    let participantsHtml = '';
                    group.participants.forEach(p => {
                        const fullPlaceName = p.fullPlaceName || p.placeNumber || 'N/A';

                        participantsHtml += `
                             <div class="row g-2 mb-2">
                                 <div class="col-md-6">
                                     <div class="input-group input-group-sm">
                                         <span class="input-group-text">Nom</span>
                                         <input type="text" class="form-control" value="${p.name || ''}" ${isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="name">
                                      </div>
                                 </div>
                                 <div class="col-md-6">
                                     <div class="input-group input-group-sm">
                                         <span class="input-group-text">Prénom</span>
                                         <input type="text" class="form-control" value="${p.firstname || ''}" ${isReadOnly ? 'readonly' : ''} data-detail-id="${p.id}" data-field="firstname">
                                      </div>
                                 </div>
                                 <div class="col-12">
                                     <small class="text-muted">Place : ${fullPlaceName}</small>
                                 </div>
                             </div>
                         `;
                    });

                    participantsList.innerHTML += `
                         <div class="list-group-item d-flex justify-content-between align-items-start">
                             <div class="me-auto">
                                 <strong>${group.participants.length} × ${group.tarifName}</strong>
                                 ${group.tarifDescription ? `<div class="text-muted small">${group.tarifDescription}</div>` : ''}
                             </div>
                             <div class="text-end">
                                 <strong>${(groupTotal / 100).toFixed(2).replace('.', ',')} €</strong>
                                 <div class="text-muted small">${group.participants.length} × ${(group.tarifPrice / 100).toFixed(2).replace('.', ',')} €</div>
                             </div>
                         </div>
                         <div class="list-group-item">
                             <div class="mt-2">${participantsHtml}</div>
                         </div>
                     `;
                }

                // --- Remplissage des compléments (s'il y en a) ---
                const complementsSection = document.getElementById('modal-complements-section');
                const complementsList = document.getElementById('modal-complements-list');
                complementsList.innerHTML = '';


                if (reservation.complements && reservation.complements.length > 0) {
                    complementsSection.style.display = 'block';
                    reservation.complements.forEach(complement => {
                        complementsList.innerHTML += `
                             <div class="list-group-item d-flex justify-content-between align-items-center">
                                 ${complement.tarifName}
                                 <span class="badge bg-primary rounded-pill">Qté: ${complement.quantity}</span>
                             </div>
                         `;
                    });
                } else {
                    complementsSection.style.display = 'none';
                }

                const saveToggleBtn = document.getElementById('modal-save-and-toggle-checked-btn');
                if (saveToggleBtn) {
                    // Si la réservation est déjà vérifiée, on propose de la marquer comme non vérifiée, sinon comme vérifiée
                    const nextLabel = reservation.isChecked
                        ? '<i class="bi bi-x"></i>&nbsp;Enregistrer et marquer comme non vérifié'
                        : '<i class="bi bi-check"></i>&nbsp;Enregistrer et marquer comme vérifié';

                    saveToggleBtn.innerHTML = nextLabel;

                    saveToggleBtn.dataset.targetChecked = reservation.isChecked ? '0' : '1';
                }

            } catch (error) {
                // Correction de l'affichage de l'erreur : utilisation des backticks (`)
                modalBody.innerHTML = `<div class="alert alert-danger"><strong>Erreur de communication avec le serveur :</strong><pre>${error.message}</pre></div>`;
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
                    // Si le serveur renvoie un nouveau token dans le body, on met la meta à jour
                    if (data && data.csrfToken) {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) meta.content = String(data.csrfToken);
                    }
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