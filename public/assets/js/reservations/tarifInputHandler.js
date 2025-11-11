'use strict';

/**
 * Gère la logique des inputs de tarifs (calcul des totaux, limites, UI).
 * @param {object} config
 * @param {HTMLElement} config.container
 * @param {HTMLElement|null} config.alertDiv
 * @param {HTMLElement|null} config.placesRestantesSpan
 * @param {number|null} config.limitation
 * @param {number} config.dejaReservees
 * @param {function} [config.seatCountResolver] - (input, tarifId) => number (fallback sur data-nb-place si absent)
 * @param {function} [config.showFlash] - (type, msg) => void
 * @param {function} config.updateSubmitState
 */
export function initTarifInputHandler(config) {
    const {
        container,
        alertDiv,
        placesRestantesSpan,
        limitation,
        dejaReservees,
        seatCountResolver,
        showFlash,
        updateSubmitState
    } = config;

    const toInt = (v, fb = 0) => {
        const s = String(v ?? '').trim();
        if (s === '' || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') return fb;
        const n = parseInt(s, 10);
        return Number.isFinite(n) ? n : fb;
    };

    const getInputs = () => container.querySelectorAll('.place-input');

    const getTarifIdFromInput = (input) => {
        const m = input?.name?.match(/^tarifs\[(\d+)]$/);
        return m ? m[1] : null;
    };

    const resolveSeatCount = (input) => {
        const fallback = toInt(input.dataset.nbPlace, 1);
        if (typeof seatCountResolver !== 'function') return fallback;
        const tid = getTarifIdFromInput(input);
        const n = seatCountResolver(input, tid);
        const nn = toInt(n, fallback);
        return nn > 0 ? nn : fallback;
    };

    function totalDemanded(except = null) {
        let total = 0;
        getInputs().forEach(input => {
            if (input === except) return;
            const nb = toInt(input.value, 0);
            const placesParTarif = resolveSeatCount(input);
            total += nb * placesParTarif;
        });
        return total;
    }

    function remainingSeats() {
        if (limitation === null) return Infinity;
        return Math.max(0, limitation - dejaReservees - totalDemanded());
    }

    function refreshRemainingUi(remaining) {
        if (placesRestantesSpan && limitation !== null) {
            const rem = toInt(remaining, 0);
            placesRestantesSpan.textContent = Math.max(0, rem);
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
        if (limitation === null) return;
        const placesParTarif = resolveSeatCount(input);
        const reste = Math.max(0, limitation - dejaReservees - totalDemanded(input));
        const maxPossible = Math.floor(reste / placesParTarif);
        const current = toInt(input.value, 0);

        input.setAttribute('min', '0');
        input.setAttribute('step', '1');
        input.setAttribute('max', String(Math.max(0, maxPossible)));
        if (current > maxPossible && current !== 0) {
            input.value = String(Math.max(0, maxPossible));
            showAlert('Votre sélection a été ajustée pour respecter la limite.');
        } else {
            clearAlert();
        }
        const rem = remainingSeats();
        refreshRemainingUi(rem);
    }

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
            input.value = String(toInt(v, 0));
        }
        clampInput(input);
        updateSubmitState();
    });

    return {
        totalDemanded,
        remainingSeats,
        clampInput,
        refreshRemainingUi,
        showAlert,
        clearAlert
    };
}
