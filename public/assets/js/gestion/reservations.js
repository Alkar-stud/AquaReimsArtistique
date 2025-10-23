document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('reservation-data-container');
    if (!container) {
        return; // Ne rien faire si le conteneur principal n'est pas trouvé
    }

    // Restaure la position au chargement
    ScrollManager.restore();

    // On sélectionne la liste déroulante, peu importe l'onglet actif
    // en se basant sur le début de son ID "event-selector-".
    const eventSelector = document.querySelector('[id^="event-selector-"]');

    // S'il n'y a pas d'événements, le sélecteur n'existe pas.
    if (eventSelector) {
        eventSelector.addEventListener('change', (event) => {
            const selectedSessionId = event.target.value;

            // Si une session est sélectionnée (et pas l'option vide).
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

                // Reconstruire le contenu de la modale avec les données
                document.getElementById('modal_reservation_id').value = reservation.id;
                document.getElementById('modal_reservation_token').value = reservation.token;
                document.getElementById('modal_contact_name').value = reservation.name;
                document.getElementById('modal_contact_firstname').value = reservation.firstName;
                document.getElementById('modal_contact_email').value = reservation.email;
                document.getElementById('modal_contact_phone').value = reservation.phone || '';

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

console.log(reservation.complements);
                if (reservation.complements && reservation.complements.length > 0) {
                    complementsSection.style.display = 'block';
                    complementsList.innerHTML = ''; // réinitialise

                    // petit utilitaire pour échapper du HTML injecté
                    const esc = (s) => String(s === null || s === undefined ? '' : s)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

                    reservation.complements.forEach(complement => {
                        const tarifId = complement.tarifId;
                        const complementId =  complement.id;
                        const qty = parseInt(complement.quantity || 0, 10);
                        const priceCents = parseInt(complement.tarifPrice ?? complement.price ?? 0, 10);
                        const priceText = (priceCents / 100).toFixed(2).replace('.', ',') + ' €';
                        const groupTotalText = ((qty * priceCents) / 100).toFixed(2).replace('.', ',') + ' €';

                        // maxForThisPrice peut être null ou un integer
                        const maxForThisPrice = (complement.maxForThisPrice === null || complement.maxForThisPrice === undefined) ? null : parseInt(complement.maxForThisPrice, 10);
                        const showPlus = !(maxForThisPrice !== null && qty >= maxForThisPrice);

                        complementsList.innerHTML += `
            <div class="list-group-item d-flex justify-content-between align-items-center" 
                    data-complement-wrapper-id="${tarifId}"
                    data-complement-row-id="${complementId}">
                <div>
                    <strong>${esc(complement.tarifName)}</strong>
                    ${complement.tarifDescription ? `<div class="text-muted small">${esc(complement.tarifDescription)}</div>` : ''}
                </div>

                <div class="text-end" style="min-width:260px;">
                    <div class="d-flex align-items-center justify-content-end" style="gap:0.5rem;">
                        <!-- Prix unitaire placé avant l'input -->
                        <div class="text-muted small me-2" style="min-width:90px; text-align:right;">${priceText} x </div>

                        <div class="input-group input-group-sm" style="max-width:160px;">
                            <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="minus" data-complement-id="${tarifId}">-</button>
                            <input type="text" class="form-control text-center" id="qty-complement-${tarifId}" value="${qty}" readonly data-max-for-price="${maxForThisPrice !== null ? maxForThisPrice : ''}">
                            ${ showPlus ? `<button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="plus" data-complement-id="${tarifId}">+</button>` : `&nbsp;(max ${maxForThisPrice})` }
                        </div>
                    </div>

                    <div class="mt-1">
                        <strong class="complement-total" data-complement-id="${tarifId}">${groupTotalText}</strong>
                    </div>
                </div>
            </div>
        `;
                    });

                    // Ajoute les écouteurs pour +/- (simple)
                    complementsList.querySelectorAll('.complement-qty-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            e.preventDefault();
                            const action = btn.dataset.action;
                            const tarifId = btn.dataset.complementId;
                            // Récupération de data-complement-row-id sur le wrapper
                            const wrapper = btn.closest('[data-complement-row-id]');
                            const complementRowId = wrapper ? wrapper.dataset.complementRowId : null;
                            const qtyInput = document.getElementById(`qty-complement-${tarifId}`);
                            if (!qtyInput) return;

                            const oldQty = parseInt(qtyInput.value, 10) || 0;
                            const maxAttr = qtyInput.dataset.maxForPrice;
                            const maxForThisPrice = (maxAttr && maxAttr !== '') ? parseInt(maxAttr, 10) : null;

                            // sécurité côté client : si on est au max, bloquer l'ajout
                            if (action === 'plus' && maxForThisPrice !== null && oldQty >= maxForThisPrice) {
                                return;
                            }

                            // confirmation pour les actions (reprise de la logique existante)
                            if (action === 'minus') {
                                let confirmationMessage;
                                if (oldQty <= 1) {
                                    confirmationMessage = "Souhaitez-vous vraiment supprimer cet élément ? Le trop perçu ne sera pas remboursé.";
                                } else {
                                    confirmationMessage = "Souhaitez-vous vraiment retirer 1 ticket de cet élément ?";
                                }
                                if (!confirm(confirmationMessage)) return;
                            } else {
                                if (!confirm("Confirmez-vous l'ajout de cet article ? Le montant total sera mis à jour.")) return;
                            }

                            // Désactiver temporairement les boutons pour éviter double-clic
                            btn.disabled = true;
                            const reservationToken = document.getElementById('modal_reservation_token').value;
console.log('action : ', action);
                            // Appel API de mise à jour (adapter l'URL si nécessaire côté serveur)
                            apiPost('/modifData/update', {
                                typeField: 'complement',
                                token: reservationToken,
                                reservationId: reservation.id,
                                id: complementRowId,
                                action: action
                            })
                                .then(result => {
                                    if (result && result.success) {
                                        const newQty = (typeof result.newQuantity !== 'undefined')
                                            ? parseInt(result.newQuantity, 10)
                                            : (action === 'plus' ? oldQty + 1 : Math.max(0, oldQty - 1));

                                        qtyInput.value = newQty;

                                        // Scope dans la modale
                                        const modal = document.getElementById('reservationDetailModal');

                                        // Mettre à jour le total du groupe si fourni, sinon calcul local depuis le DOM
                                        const totalEl = modal.querySelector(`.complement-total[data-complement-id="${tarifId}"]`);
                                        if (typeof result.groupTotalCents !== 'undefined') {
                                            const euros = (result.groupTotalCents / 100).toFixed(2).replace('.', ',') + ' €';
                                            if (totalEl) totalEl.textContent = euros;
                                        } else {
                                            const item = qtyInput.closest('.list-group-item');
                                            let unitPrice = 0;
                                            if (item) {
                                                // Ex: "12,34 € x" -> on récupère "12,34"
                                                const priceNode = item.querySelector('.text-muted.small');
                                                const priceMatch = priceNode ? priceNode.textContent.match(/([\d,]+)\s*€/) : null;
                                                unitPrice = priceMatch ? parseFloat(priceMatch[1].replace(',', '.')) : 0;
                                            }
                                            const total = (newQty * unitPrice).toFixed(2).replace('.', ',') + ' €';
                                            if (totalEl) totalEl.textContent = total;
                                        }

                                        // Gestion de l'affichage du bouton + selon maxForThisPrice
                                        const complementsListEl = modal.querySelector('#modal-complements-list');
                                        const plusBtn = complementsListEl
                                            ? complementsListEl.querySelector(`button[data-action="plus"][data-complement-id="${tarifId}"]`)
                                            : null;

                                        if (maxForThisPrice !== null && plusBtn) {
                                            plusBtn.style.display = (newQty >= maxForThisPrice) ? 'none' : '';
                                        }

                                        // Mettre à jour les totaux globaux si fournis
                                        if (result.totals) {
                                            if (typeof result.totals.totalAmount !== 'undefined') {
                                                const el = modal.querySelector('#modal-total-cost');
                                                if (el) el.textContent = (result.totals.totalAmount / 100).toFixed(2).replace('.', ',');
                                            }
                                            if (typeof result.totals.totalPaid !== 'undefined') {
                                                const el = modal.querySelector('#modal-amount-paid');
                                                if (el) el.textContent = (result.totals.totalPaid / 100).toFixed(2).replace('.', ',');
                                            }
                                            if (typeof result.totals.amountDue !== 'undefined') {
                                                const el = modal.querySelector('#modal-amount-due');
                                                if (el) el.textContent = (result.totals.amountDue / 100).toFixed(2).replace('.', ',');
                                            }
                                        }
                                    }
                                })
                                .catch(err => {
                                    console.error('Erreur update complément :', err);
                                    alert(err.userMessage || err.message || 'Erreur réseau.');
                                })
                                .finally(() => {
                                    btn.disabled = false;
                                });
                        });
                    });
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

                    // On masque le bouton de rafraîchissement si le nouveau statut est "Refunded"
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
                    // Si le serveur renvoie un nouveau token dans le body, on met la meta à jour
                    if (data && data.csrfToken) {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) {
                            meta.content = String(data.csrfToken);
                        }
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