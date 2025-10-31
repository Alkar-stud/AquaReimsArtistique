//On importe les fonctions d'initialisation des modules.
import { initReservationModal } from './gestion/reservationModal.js';
import { initReservationList } from './gestion/reservationList.js';
import { initStatusToggles } from './gestion/statusToggle.js';
import { initReservationExtracts } from './gestion/reservationsExports.js';
import {initSearchReservation} from "./gestion/reservationSearch.js";


document.addEventListener('DOMContentLoaded', () => {
    /* ----------------------------------------------------

     Initialisations globales (pour les pages de gestion)

     ---------------------------------------------------- */

    initSearchReservation();
    initReservationList();

    initReservationModal();
    initStatusToggles();
    initReservationExtracts();

});
