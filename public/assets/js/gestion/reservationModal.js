// On importe les initialisateurs des autres composants que cette modale va utiliser.
import { initContactForm } from '../reservations/contactForm.js';
import { initParticipantsForm, updateParticipantsUI } from '../reservations/participantsForm.js';
import { initComplementsForm, updateComplementsUI } from '../reservations/complementsForm.js';
import ScrollManager from '../components/scrollManager.js';
import { apiDelete, apiPost } from '../components/apiClient.js';
import { toggleReservationStatus } from './statusToggle.js';
import { toggleCancelStatus } from '../reservations/cancelReservation.js';

// Dictionnaire des explications pour les statuts de paiement HelloAsso
const paymentStatusExplanations = {
    'Pending' : 'Paiement à venir (échéances)',
    'Authorized': 'Paiement accepté',
    'Refused': 'Paiement refusé (surement par la banque de l\'utilisateur)',
    'Unknown': 'Paiement dont le statut n\'est pas résolu car incertitude sur celui-ci',
    'Registered': 'Paiements/dons hors ligne (espèces, chèques,..)',
    'Refunded': 'Paiement remboursé',
    'Refunding': 'Paiement en cours de remboursement',
    'Contested': 'L\'utilisateur a contesté le paiement auprès de sa banque',
    'CashedOut': "Somme reversée à l'association",
    'WaitingForCashOutConfirmation': "En attente de la confirmation du versement (état transitoire)",
    'TransferInProgress': "Le paiement est en cours de transfert sur le compte HelloAsso de l'association.",
    'Transfered': "Somme transférée sur le compte HelloAsso de l'association",
    'MoneyIn': "Les fonds sont sur le wallet contributeur",
    'Processed': 'Effectué'
};


async function refreshModalContent(modal, reservationId) {
    const modalBody = modal.querySelector('.modal-body');
    const isReadOnly = modal.dataset.isReadonly === 'true';

    // Sauvegarder le HTML original pour le restaurer plus tard
    const originalModalBodyHtml = modalBody.innerHTML;

    // Afficher un spinner de chargement
    modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

    try {
        // Aller chercher les données de la réservation
        const response = await fetch(`/gestion/reservations/details/${reservationId}`);
        if (!response.ok) {
            throw new Error(`Erreur serveur : ${response.statusText}`);
        }
        const reservation = await response.json();
        console.log('reservation : ', reservation);
        // Restaurer le HTML et remplir la modale
        modalBody.innerHTML = originalModalBodyHtml;

        /*---------------------------------

        Composant contact

         ---------------------------------*/
        initContactForm({
            apiUrl: '/gestion/reservations/update',
            reservationIdentifier: reservation.id,
            identifierType: 'reservationId'
        });
        // Remplir les champs cachés
        modal.querySelector('#modal_reservation_id').value = reservation.id || '';
        modal.querySelector('#modal_reservation_token').value = reservation.token || '';

        // Remplir les champs de l'acheteur
        modal.querySelector('#modal_contact_name').value = reservation.name || '';
        modal.querySelector('#modal_contact_firstname').value = reservation.firstName || '';
        modal.querySelector('#modal_contact_email').value = reservation.email || '';
        modal.querySelector('#modal_contact_phone').value = reservation.phone || '';

        /*---------------------------------

        Composant participants

         ---------------------------------*/
        const participantsSection = modal.querySelector('#modal-participants-section');
        if (participantsSection) {
            // On génère le HTML des participants dans la modale
            updateParticipantsUI(participantsSection.querySelector('#participants-container'), reservation, isReadOnly);

            // On initialise les écouteurs d'événements sur les champs qui viennent d'être créés
            initParticipantsForm({
                apiUrl: '/gestion/reservations/update',
                reservationIdentifier: reservation.id,
                identifierType: 'reservationId'
            });
        }

        /*---------------------------------

         Composant compléments

          ---------------------------------*/
        const complementsSection = modal.querySelector('#modal-complements-section');
        if (complementsSection && reservation.complements && reservation.complements.length > 0) {
            complementsSection.style.display = 'block';
            // On génère le HTML des compléments dans la modale
            updateComplementsUI(complementsSection.querySelector('#complements-container'), reservation, isReadOnly);
            // On initialise les écouteurs d'événements sur les champs qui viennent d'être créés
            initComplementsForm({
                apiUrl: '/gestion/reservations/update',
                reservationIdentifier: reservation.id,
                identifierType: 'reservationId',
                isModalContext: true
            });
        } else if (complementsSection) {
            complementsSection.style.display = 'none';
        }

        /*---------------------------------

         Section Paiements

          ---------------------------------*/
        const totalAmount = reservation.totalAmount || 0;
        const amountPaid = reservation.totalAmountPaid || 0;
        const amountDue = totalAmount - amountPaid;

        // Calcul du total des dons
        const totalDonation = (reservation.payments || []).reduce((acc, payment) => { // Correction de la variable
            return acc + (payment.partOfDonation || 0);
        }, 0);

        // Remplissage des montants
        modal.querySelector('#modal-total-cost').textContent = (totalAmount / 100).toFixed(2).replace('.', ',');
        modal.querySelector('#modal-amount-paid').textContent = (amountPaid / 100).toFixed(2).replace('.', ',');
        modal.querySelector('#modal-don-amount').textContent = (totalDonation / 100).toFixed(2).replace('.', ',');

        const amountDueEl = modal.querySelector('#modal-amount-due');
        if (amountDueEl) {
            amountDueEl.textContent = (amountDue / 100).toFixed(2).replace('.', ',');
            // On change la couleur en fonction du solde
            amountDueEl.classList.toggle('text-danger', amountDue > 0);
            amountDueEl.classList.toggle('text-info', amountDue < 0);
            amountDueEl.classList.toggle('text-success', amountDue === 0);
        }

        // Gestion du bouton "Marquer comme payé"
        const markAsPaidDiv = modal.querySelector('#div-modal-mark-as-paid');
        if (markAsPaidDiv) {
            markAsPaidDiv.classList.toggle('d-none', amountDue <= 0);
            // TODO : Ajouter la logique de clic pour ce bouton
        }


        // --- Section Détails des Paiements ---
        const paymentsDetailsContainer = modal.querySelector('#modal-payment-details-container');
        const toggleDetailsLink = modal.querySelector('#toggle-payment-details');

        if (paymentsDetailsContainer && toggleDetailsLink && reservation.payments && reservation.payments.length > 0) {
            toggleDetailsLink.style.display = 'inline';
            paymentsDetailsContainer.innerHTML = ''; // Vider le conteneur

            reservation.payments.forEach(payment => {
                let refundActionHtml = '';
                const refreshButtonHtml = `
                    <button class="btn btn-sm btn-outline-secondary refresh-payment-btn"
                            data-payment-id="${payment.id}"
                            title="Rafraîchir le statut">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                `;

                // On détermine l'action de remboursement à afficher (bouton ou badge)
                if (payment.status === 'Refunded') {
                    refundActionHtml = '<span class="badge bg-info me-1">Remboursé</span>';
                } else if (payment.type !== 'ref') { // On ne peut pas rembourser une écriture de remboursement
                    const isRefundable = payment.status === 'Authorized' || payment.status === 'Processed';
                    refundActionHtml = `
                        <button class="btn btn-sm btn-outline-warning refund-btn me-1"
                                data-payment-id="${payment.id}"
                                title="Rembourser ce paiement via HelloAsso"
                                ${!isRefundable ? 'disabled' : ''}>
                            <i class="bi bi-currency-euro"></i> Rembourser
                        </button>
                    `;
                }

                const paymentItem = document.createElement('li');
                paymentItem.className = 'list-group-item d-flex justify-content-between align-items-center p-1';
                paymentItem.innerHTML = `
                     <div class="small">
                         <span class="badge bg-secondary me-1">${payment.type || 'N/A'}</span> 
                         ${new Date(payment.createdAt).toLocaleDateString('fr-FR')} -
                         <strong>${(payment.amountPaid / 100).toFixed(2).replace('.', ',')} €</strong>
                         <span class="ms-2 fst-italic text-muted">
                            (${payment.status || 'Inconnu'})
                            <i class="bi bi-question-circle-fill ms-1 text-primary"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="${paymentStatusExplanations[payment.status] || 'Statut non documenté.'}"></i>
                         </span>
                     </div>
                     <div>${refundActionHtml}${refreshButtonHtml}</div>
                 `;
                paymentsDetailsContainer.appendChild(paymentItem);
            });

            toggleDetailsLink.addEventListener('click', (e) => {
                e.preventDefault();
                const isHidden = paymentsDetailsContainer.style.display === 'none';
                paymentsDetailsContainer.style.display = isHidden ? 'block' : 'none';
                e.target.textContent = isHidden
                    ? 'Afficher le détail'
                    : 'Voir le détail des paiements';
            });

            // Attacher les écouteurs pour les nouveaux boutons
            paymentsDetailsContainer.querySelectorAll('.refund-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const paymentId = e.currentTarget.dataset.paymentId;
                    if (confirm(`Êtes-vous sûr de vouloir initier le remboursement pour le paiement #${paymentId} ?`)) {
                        e.currentTarget.disabled = true;
                        e.currentTarget.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        try {
                            await apiPost('/gestion/reservations/refund', { paymentId });
                            alert('La demande de remboursement a été envoyée.');
                            await refreshModalContent(modal, reservationId); // On rafraîchit la modale
                        } catch (error) {
                            alert(`Erreur: ${error.userMessage || error.message}`);
                            e.currentTarget.disabled = false;
                            e.currentTarget.innerHTML = '<i class="bi bi-currency-euro"></i> Rembourser';
                        }
                    }
                });
            });

            paymentsDetailsContainer.querySelectorAll('.refresh-payment-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const button = e.currentTarget;
                    const paymentId = button.dataset.paymentId;
                    const originalIcon = button.innerHTML;

                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    try {
                        // Le contrôleur doit renvoyer {success: true} si le statut a été mis à jour.
                        await apiPost('/gestion/reservations/refresh-payment', { paymentId });
                        // On rafraîchit toute la modale pour afficher le nouveau statut.
                        await refreshModalContent(modal, reservationId);
                    } catch (error) {
                        alert(`Erreur lors du rafraîchissement : ${error.userMessage || error.message}`);
                        button.disabled = false;
                        button.innerHTML = originalIcon;
                    }
                });
            });

            // Initialiser les nouvelles infobulles Bootstrap
            const tooltipTriggerList = [].slice.call(paymentsDetailsContainer.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        } else if (toggleDetailsLink) {
            toggleDetailsLink.style.display = 'none';
        }


        /*---------------------------------

        Boutons du Footer

         ---------------------------------*/
        const saveToggleBtn = modal.querySelector('#modal-save-and-toggle-checked-btn');
        if (saveToggleBtn) {
            // Si la réservation est déjà vérifiée, on propose de la marquer comme non vérifiée
            saveToggleBtn.innerHTML = reservation.isChecked
                ? '<i class="bi bi-x-circle"></i>&nbsp;Enregistrer et marquer comme non vérifié'
                : '<i class="bi bi-check-circle"></i>&nbsp;Enregistrer et marquer comme vérifié';

            saveToggleBtn.dataset.targetChecked = reservation.isChecked ? '0' : '1';
        }

        const cancelBtn = modal.querySelector('#modal-reservation-cancel-btn');
        if (cancelBtn) {
            // Si la réservation est déjà annulée, on propose de la réactiver
            cancelBtn.innerHTML = reservation.isCanceled
                ? '<i class="bi bi-arrow-counterclockwise"></i>&nbsp;Réactiver la réservation'
                : '<i class="bi bi-x-circle"></i>&nbsp;Annuler la réservation';

            cancelBtn.classList.toggle('btn-warning', !reservation.isCanceled);
            cancelBtn.classList.toggle('btn-success', reservation.isCanceled);
            cancelBtn.dataset.targetCanceled = reservation.isCanceled ? '0' : '1';
        }

    } catch (error) {
        modalBody.innerHTML = `<div class="alert alert-danger m-3">Erreur lors du chargement des détails : ${error.message}</div>`;
    }
}

/**
 * Gère l'événement d'ouverture initial de la modale.
 * @param {Event} event - L'événement 'show.bs.modal'
 */
async function onModalOpen(event) {
    // Le bouton "Détail" qui a déclenché l'ouverture
    const button = event.relatedTarget;
    const reservationId = button.getAttribute('data-reservation-id');
    const modal = event.target;

    await refreshModalContent(modal, reservationId);
}

/**
 * Initialise le module de la modale de réservation.
 */
export function initReservationModal() {
    const modal = document.getElementById('reservationDetailModal');
    if (modal) {
        // On attache l'écouteur qui se déclenchera à chaque ouverture
        modal.addEventListener('show.bs.modal', onModalOpen);

        // On gère le clic sur le bouton "Fermer" du footer
        const closeButton = modal.querySelector('#modal-close-btn');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                ScrollManager.save(); // Sauvegarde de la position du scroll
                window.location.reload(); // Rechargement de la page
            });
        }

        // On gère le clic sur le bouton "Enregistrer et marquer..."
        const saveToggleButton = modal.querySelector('#modal-save-and-toggle-checked-btn');
        if (saveToggleButton) {
            saveToggleButton.addEventListener('click', (event) => {
                const button = event.currentTarget;
                const reservationId = modal.querySelector('#modal_reservation_id').value;
                const newStatus = button.dataset.targetChecked === '1';

                // On appelle la fonction partagée depuis statusToggle.js
                toggleReservationStatus(Number(reservationId), newStatus, button);
            });
        }

        // On gère le clic sur le bouton "Annuler/Réactiver".
        const cancelToggleButton = modal.querySelector('#modal-reservation-cancel-btn');
        if (cancelToggleButton) {
            cancelToggleButton.addEventListener('click', (event) => {
                const button = event.currentTarget;
                const reservationId = modal.querySelector('#modal_reservation_id').value;
                const newStatus = button.dataset.targetCanceled === '1';

                toggleCancelStatus({
                    apiUrl: '/gestion/reservations/update',
                    reservationIdentifier: Number(reservationId),
                    identifierType: 'reservationId',
                    newStatus: newStatus,
                    button: button
                });
            });
        }

        // On gère le clic sur le bouton "Annuler/Réactiver".
        const deleteToggleButton = modal.querySelector('#modal-reservation-delete-btn');
        if (deleteToggleButton) {
            deleteToggleButton.addEventListener('click', async (event) => {
                const button = event.currentTarget;
                const reservationId = modal.querySelector('#modal_reservation_id').value;

                if (confirm('Êtes-vous sûr de vouloir supprimer définitivement cette réservation ? Cette action est irréversible.')) {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Suppression...';

                    try {
                        await apiDelete(`/gestion/reservations/delete/${reservationId}`);
                        ScrollManager.save(); // Sauvegarde de la position du scroll pour la retrouver après rechargement
                        window.location.reload(); // Rechargement de la page pour refléter la suppression
                    } catch (error) {
                        alert(`Erreur lors de la suppression : ${error.userMessage || error.message}`);
                        button.disabled = false;
                        button.innerHTML = '<i class="bi bi-trash"></i>&nbsp;Supprimer la réservation';
                    }
                }
            });
        }

    }
}