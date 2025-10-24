//On importe les fonctions d'initialisation des modules.
import { initContactForm } from './reservations/contactForm.js';
import { initParticipantsForm } from './reservations/participantsForm.js';
import { initReservationModal } from './gestion/reservationModal.js';
import { initReservationList } from './gestion/reservationList.js';
import { initComplementsForm } from './reservations/complementsForm.js';
import { initSpecialCodeForm } from './reservations/specialCodeForm.js';
import { initPaymentManager } from './reservations/paymentManager.js';
import { initCancelButtons } from './reservations/cancelReservation.js';
import { initStatusToggles } from './gestion/statusToggle.js';


document.addEventListener('DOMContentLoaded', () => {
    // Pour la page de gestion : ouverture de la modale
    initReservationModal();

    // Vérification et actions sur le formulaire de contact
    const reservationDataContainer = document.getElementById('reservation-data-container');
    if (reservationDataContainer) {
        initContactForm({
            apiUrl: '/modifData/update',
            reservationIdentifier: reservationDataContainer.dataset.token,
            identifierType: 'token'
        });
    }

    // Vérification et actions sur le formulaire des participants
    const participantsContainer = document.getElementById('participants-container');
    if (participantsContainer && reservationDataContainer) {
        initParticipantsForm({
            apiUrl: '/modifData/update',
            reservationIdentifier: reservationDataContainer.dataset.token,
            identifierType: 'token'
        });
    }

    // Vérification et actions sur les compléments
    const complementsContainer = document.getElementById('complements-container');
    if (complementsContainer && reservationDataContainer) {
        initComplementsForm({
            apiUrl: '/modifData/update',
            reservationIdentifier: reservationDataContainer.dataset.token,
            identifierType: 'token'
        });
    }

    // Pour la page de modification : gestion du code spécial
    if (reservationDataContainer) {
        initSpecialCodeForm({
            reservationToken: reservationDataContainer.dataset.token
        });
    }

    // Pour la page de modification : gestion du bouton d'annulation
    if (reservationDataContainer) {
        initCancelButtons('.cancel-button', {
            apiUrl: '/modifData/update',
            reservationIdentifier: reservationDataContainer.dataset.token,
            identifierType: 'token'
        });
    }

    // Pour la page de modification : gestion des totaux et du paiement
    initPaymentManager();

    // Filtres de la liste des réservations
    initReservationList();

    // Interrupteurs "Vérifié"
    initStatusToggles();

});
