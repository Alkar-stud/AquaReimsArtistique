//On importe les fonctions d'initialisation des modules.
import { initReservationModal } from './gestion/reservationModal.js';
import { initReservationList } from './gestion/reservationList.js';
import { initStatusToggles } from './gestion/statusToggle.js';
import { initReservationExtracts } from './gestion/reservationsExports.js';
import {initSearchReservation} from "./gestion/reservationSearch.js";
import { initReservationTemp } from './gestion/reservationTemp.js';
import { initEventPresentations } from './gestion/event_presentations.js';
import { initOccupationPlan } from "./gestion/occupationPlan.js";



document.addEventListener('DOMContentLoaded', () => {
    /* ----------------------------------------------------

     Initialisations globales (pour les pages de gestion)

     ---------------------------------------------------- */

    initSearchReservation();
    initReservationList();

    initReservationModal();
    initStatusToggles();
    initReservationExtracts();
    initReservationTemp();
    initEventPresentations();
    initOccupationPlan();

});
