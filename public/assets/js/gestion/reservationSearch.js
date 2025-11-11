'use strict';

import {showFlashMessage} from "../components/ui.js";
/**
 * Initialise la recherche de réservations.
 */
function init() {
    //On récupère le bouton "Chercher".
    const searchBtn = document.getElementById('search-button');

    if (searchBtn) {
        const searchInput = document.getElementById('search-input');

        const performSearch = () => {
            const rawValue = searchInput.value.trim();
            // On ne fait rien si la recherche est vide
            if (!rawValue) {
                showFlashMessage('warning', 'La zone de recherche est vide', 'ajax_flash_container');
                return;
            }

            // Normalisation ARA-xxxxx -> xxxxx (sans zéros initiaux)
            // Exemples: "ARA-000123" -> "123", "ARA-000000" -> "0"
            let searchValue = rawValue;
            const m = rawValue.match(/^\s*ARA-(\d+)\s*$/i);
            if (m) {
                const digits = m[1];
                const normalized = digits.replace(/^0+/, '') || '0';
                searchValue = normalized;
            }

            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('q', searchValue);
            window.location.href = currentUrl.toString();
        };

        searchBtn.addEventListener('click', performSearch);
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }

}

export { init as initSearchReservation };
