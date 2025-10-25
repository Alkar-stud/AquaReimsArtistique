'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';
import { initTarifInputHandler } from './tarifInputHandler.js';
import { initSpecialTarifHandler } from './specialTarifHandler.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationPlacesForm');
    if (!form) return;

    const container = form; // Utiliser le formulaire comme conteneur principal pour la délégation
    const alertDiv = document.getElementById('reservationStep3Alert');
    const placesRestantesSpan = document.getElementById('placesRestantes');
    const submitButton = document.getElementById('submitButton');
    const eventIdInput = document.getElementById('event_id');

    if (!eventIdInput) {
        console.error("L'ID de l'événement est manquant.");
        return;
    }
    const eventId = eventIdInput.value;

    // Récupération des données depuis les data-attributes du formulaire
    const limitation = form.dataset.limitation ? parseInt(form.dataset.limitation, 10) : null;
    const dejaReservees = form.dataset.dejaReservees ? parseInt(form.dataset.dejaReservees, 10) : 0;
    const initialSpecialTarifSession = form.dataset.specialTarifSession ? JSON.parse(form.dataset.specialTarifSession) : null;

    // --- Fonctions utilitaires locales ---
    const getInputs = () => container.querySelectorAll('.place-input');

    // --- Initialisation des gestionnaires ---
    // Déclaration anticipée pour la dépendance mutuelle
    let specialTarifHandler;
    let tarifInputHandler;

    function updateSubmitState() {
        if (!submitButton) return;
        const totalDemanded = tarifInputHandler.totalDemanded();
        const hasSpecial = specialTarifHandler ? specialTarifHandler.hasSpecialSelection() : false;
        submitButton.disabled = !(totalDemanded > 0 || hasSpecial);
    }

    tarifInputHandler = initTarifInputHandler({
        container: container,
        alertDiv: alertDiv,
        placesRestantesSpan: placesRestantesSpan,
        limitation: limitation,
        dejaReservees: dejaReservees,
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

    // --- Initialisation UI au chargement ---
    if (limitation !== null) {
        getInputs().forEach(tarifInputHandler.clampInput);
        const remaining = Math.max(0, limitation - dejaReservees - tarifInputHandler.totalDemanded());
        tarifInputHandler.refreshRemainingUi(remaining);
    } else {
        tarifInputHandler.clearAlert();
    }
    updateSubmitState(); // désactivé par défaut si rien sélectionné

    // --- Fonctions de soumission ---
    function buildReservationPayload() {
        const event_id = parseInt(eventId, 10) || 0;
        const tarifs = {};

        // 1) Tarifs "classiques" (tous les inputs .place-input)
        getInputs().forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            if (qty <= 0) return;

            // Extrait l'id du tarif du nom de l'input (ex: tarifs[123])
            const match = input.name.match(/^tarifs\[(\d+)]$/);
            const tarifId = match ? match[1] : null;
            if (!tarifId) return;

            tarifs[tarifId] = (tarifs[tarifId] || 0) + qty;
        });

        // Tarifs spéciaux (ne comptent pas dans les totaux, mais doivent partir au backend)
        let special = null;
        const s = specialTarifHandler.getSpecialTarifSession();
        if (specialTarifHandler.hasSpecialSelection() && s) {
            const sid = String(s.id);

            // On conserve aussi la quantité côté 'tarifs'
            tarifs[sid] = (tarifs[sid] || 0) + 1;

            // Clé calculée: { [sid]: codeOuNull }
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

    // --- Gestion de la soumission du formulaire ---
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
