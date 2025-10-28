'use strict';

/**
 * Gère la logique des inputs de tarifs (calcul des totaux, limites, UI).
 * @param {object} config - Configuration pour le gestionnaire.
 * @param {HTMLElement} config.container - Le conteneur parent des inputs de tarifs.
 * @param {HTMLElement|null} config.alertDiv - L'élément pour afficher les alertes.
 * @param {HTMLElement|null} config.placesRestantesSpan - L'élément pour afficher les places restantes.
 * @param {number|null} config.limitation - La limite de places par nageuse (null si pas de limite, ou si non applicable).
 * @param {number} config.dejaReservees - Le nombre de places déjà réservées.
 * @param {function} config.hasSpecialSelection - Fonction pour vérifier si un tarif spécial est sélectionné.
 * @param {function} config.updateSubmitState - Fonction pour mettre à jour l'état du bouton de soumission.
 */
export function initTarifInputHandler(config) {
    const {
        container,
        alertDiv,
        placesRestantesSpan,
        limitation,
        dejaReservees,
        updateSubmitState
    } = config;

    const getInputs = () => container.querySelectorAll('.place-input');

    function totalDemanded(except = null) {
        let total = 0;
        getInputs().forEach(input => {
            if (input === except) return;
            const nb = parseInt(input.value, 10) || 0;
            const placesParTarif = parseInt(input.dataset.nbPlace, 10) || 1;
            total += nb * placesParTarif;
        });
        return total;
    }

    function refreshRemainingUi(remaining) {
        if (placesRestantesSpan && limitation !== null) { // Seulement si une limitation est définie
            placesRestantesSpan.textContent = Math.max(0, remaining);
        }
    }

    function clearAlert() {
        if (alertDiv) alertDiv.innerHTML = '';
    }

    function showAlert(msg) {
        if (!alertDiv) return;
        alertDiv.innerHTML = `<div class="alert alert-danger">${msg}</div>`;
    }

    function clampInput(input) {
        if (limitation === null) return; // Pas de limitation, pas de clamp
        const placesParTarif = parseInt(input.dataset.nbPlace, 10) || 1;
        const reste = Math.max(0, limitation - dejaReservees - totalDemanded(input));
        const maxPossible = Math.floor(reste / placesParTarif);
        const current = parseInt(input.value, 10) || 0;

        input.setAttribute('min', '0');
        input.setAttribute('step', '1');
        input.setAttribute('max', String(Math.max(0, maxPossible)));

        if (current > maxPossible) {
            input.value = String(Math.max(0, maxPossible));
            showAlert('Votre sélection a été ajustée pour respecter la limite.');
        } else {
            clearAlert();
        }
        const remaining = Math.max(0, limitation - dejaReservees - totalDemanded());
        refreshRemainingUi(remaining);
    }

    // Délégation d’événements pour couvrir les champs dynamiques
    container.addEventListener('focus', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        if (input.value === '0') input.value = '';
    }, true);

    container.addEventListener('blur', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        if (input.value === '') input.value = '0';
        clampInput(input);
        updateSubmitState();
    }, true);

    container.addEventListener('input', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        const v = input.value.trim();
        if (v !== '' && !/^\d+$/.test(v)) {
            input.value = String(parseInt(v.replace(/[^\d]/g, ''), 10) || 0);
        }
        clampInput(input);
        updateSubmitState();
    });

    // Expose totalDemanded for external use (e.g., by updateSubmitState)
    return {
        totalDemanded,
        clampInput,
        refreshRemainingUi,
        showAlert,
        clearAlert
    };
}