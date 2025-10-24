// On importe les initialisateurs des autres composants que cette modale va utiliser.
import { initContactForm } from '../reservations/contactForm.js';
import { initParticipantsForm, updateParticipantsUI } from '../reservations/participantsForm.js';
import { initComplementsForm, updateComplementsUI } from '../reservations/complementsForm.js';

/**
 * Gère l'événement d'ouverture de la modale.
 * @param {Event} event - L'événement 'show.bs.modal'
 */
async function onModalOpen(event) {
    // Le bouton "Détail" qui a déclenché l'ouverture
    const button = event.relatedTarget;
    const reservationId = button.getAttribute('data-reservation-id');

    // On récupère l'état de lecture seule depuis l'attribut de la modale
    const isReadOnly = event.target.dataset.isReadonly === 'true';

    const modal = event.target; // La modale elle-même
    const modalBody = modal.querySelector('.modal-body');

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
        const totalDonation = (reservation.payments || []).reduce((acc, payment) => {
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
            // Ici, il faudra ajouter la logique de clic pour ce bouton si nécessaire
        }


    } catch (error) {
        modalBody.innerHTML = `<div class="alert alert-danger m-3">Erreur lors du chargement des détails : ${error.message}</div>`;
    }
}

/**
 * Initialise le module de la modale de réservation.
 */
export function initReservationModal() {
    const modal = document.getElementById('reservationDetailModal');
    if (modal) {
        // On attache l'écouteur qui se déclenchera à chaque ouverture
        modal.addEventListener('show.bs.modal', onModalOpen);
    }
}