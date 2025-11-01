'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';
import { initTarifInputHandler } from './tarifInputHandler.js';
import { initSpecialTarifHandler } from './specialTarifHandler.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationPlacesForm');
    if (!form) return;

    const container = form;
    const alertDiv = document.getElementById('reservationStep3Alert');
    const placesRestantesSpan = document.getElementById('placesRestantes');
    const dejaReserveesSpan = document.getElementById('dejaReservees');
    const submitButton = document.getElementById('submitButton');
    const eventIdInput = document.getElementById('event_id');

    if (!eventIdInput) {
        console.error("L'ID de l'événement est manquant.");
        return;
    }
    const eventId = eventIdInput.value;

    const toIntOr = (v, fb) => {
        if (v === undefined || v === null) return fb;
        const s = String(v).trim();
        if (s === '' || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') return fb;
        const n = parseInt(s, 10);
        return Number.isFinite(n) ? n : fb;
    };

    // Utiliser les valeurs de la limite nageuse (alignées avec l'encart)
    const limitation = toIntOr(form.dataset.limitation, null);
    const dejaReservees = toIntOr(form.dataset.dejaReservees, 0);
    const initialSpecialTarifSession = form.dataset.specialTarifSession ? JSON.parse(form.dataset.specialTarifSession) : null;

    const allTarifsSeats = form.dataset.allTarifsSeats ? JSON.parse(form.dataset.allTarifsSeats) : {};

    const getInputs = () => container.querySelectorAll('.place-input');

    let specialTarifHandler;
    let tarifInputHandler;

    const seatCountResolver = (input, tarifId) => {
        const id = tarifId || (input.name.match(/^tarifs\[(\d+)]$/)?.[1] ?? null);
        if (!id) return parseInt(input.dataset.nbPlace, 10) || 1;
        const raw = allTarifsSeats[id];
        const seatCount = typeof raw === 'object' && raw !== null ? raw.seat_count : raw;
        const n = parseInt(seatCount, 10);
        return Number.isFinite(n) && n > 0 ? n : (parseInt(input.dataset.nbPlace, 10) || 1);
    };

    function updateSubmitState() {
        if (!submitButton) return;
        const totalDemanded = tarifInputHandler.totalDemanded();
        const hasSpecial = specialTarifHandler ? specialTarifHandler.hasSpecialSelection() : false;

        let remaining = Infinity;
        if (limitation !== null) {
            remaining = Math.max(0, limitation - dejaReservees - totalDemanded);
            tarifInputHandler.refreshRemainingUi(remaining);
            if (dejaReserveesSpan) dejaReserveesSpan.textContent = String(dejaReservees + totalDemanded);
        }

        const enable = (totalDemanded > 0 || hasSpecial) && (limitation === null || remaining > 0 || hasSpecial);
        submitButton.disabled = !enable;
    }

    tarifInputHandler = initTarifInputHandler({
        container: container,
        alertDiv: alertDiv,
        placesRestantesSpan: placesRestantesSpan,
        limitation: limitation,
        dejaReservees: dejaReservees,
        seatCountResolver,
        showFlash: (type, msg) => showFlashMessage(type, msg),
        updateSubmitState: updateSubmitState
    });

    specialTarifHandler = initSpecialTarifHandler({
        container: container,
        validateCodeBtn: document.getElementById('validateCodeBtn'),
        specialCodeInput: document.getElementById('specialCode'),
        specialCodeFeedback: document.getElementById('specialCodeFeedback'),
        specialTarifContainer: document.getElementById('specialTarifContainer'),
        eventId: eventId,
        initialSpecialTarifSession: initialSpecialTarifSession,
        updateSubmitState: updateSubmitState
    });

    if (limitation !== null) {
        getInputs().forEach(tarifInputHandler.clampInput);
        const remaining = tarifInputHandler.remainingSeats();
        tarifInputHandler.refreshRemainingUi(remaining);
        if (dejaReserveesSpan) dejaReserveesSpan.textContent = String(dejaReservees + tarifInputHandler.totalDemanded());
    } else {
        tarifInputHandler.clearAlert();
    }
    updateSubmitState();

    function buildReservationPayload() {
        const event_id = parseInt(eventId, 10) || 0;
        const tarifs = {};

        getInputs().forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            if (qty <= 0) return;
            const match = input.name.match(/^tarifs\[(\d+)]$/);
            const tarifId = match ? match[1] : null;
            if (!tarifId) return;
            tarifs[tarifId] = (tarifs[tarifId] || 0) + qty;
        });

        let special = null;
        const s = specialTarifHandler.getSpecialTarifSession();
        if (specialTarifHandler.hasSpecialSelection() && s) {
            const sid = String(s.id);
            tarifs[sid] = (tarifs[sid] || 0) + 1;
            special = { [sid]: s.code || null };
        }

        return { event_id, tarifs, special };
    }

    async function submitEtape3(payload) {
        apiPost('/reservation/valid/3', payload)
            .then((data) => {
                if (data.success) {
                    window.location.href = '/reservation/etape4Display';
                } else {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showFlashMessage('danger', data.error || 'Erreur lors de la validation de l’étape 3.');
                }
            })
            .catch((err) => {
                showFlashMessage('danger', err.userMessage || err.message);
                submitButton && (submitButton.disabled = false);
            });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        submitButton && (submitButton.disabled = true);

        try {
            const payload = buildReservationPayload();
            await submitEtape3(payload);
        } catch (err) {
            showFlashMessage('danger', err.userMessage || err.message || 'Erreur lors de la validation de l’étape 3.');
            submitButton && (submitButton.disabled = false);
        }
    });
});