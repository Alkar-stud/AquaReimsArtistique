//On importe les fonctions d'initialisation des modules.
import { initReservationModal } from './gestion/reservationModal.js';
import { initReservationList } from './gestion/reservationList.js';
import { initStatusToggles } from './gestion/statusToggle.js';
import { initReservationExtracts } from './gestion/reservationsExports.js';


document.addEventListener('DOMContentLoaded', () => {
    /* ----------------------------------------------------

     Initialisations globales (pour les pages de gestion)

     ---------------------------------------------------- */

    initReservationModal();
    initReservationList();
    initStatusToggles();
    initReservationExtracts();

});
