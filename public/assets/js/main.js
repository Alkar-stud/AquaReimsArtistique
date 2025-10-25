//On importe les fonctions d'initialisation des modules.
import { initReservationModal } from './gestion/reservationModal.js';
import { initReservationList } from './gestion/reservationList.js';
import { initStatusToggles } from './gestion/statusToggle.js';


document.addEventListener('DOMContentLoaded', () => {
    /* ----------------------------------------------------

     Initialisations globales (pour les pages de gestion)

     ---------------------------------------------------- */

    initReservationModal();
    initReservationList();
    initStatusToggles();

});
