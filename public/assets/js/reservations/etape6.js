'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';
import { initTarifInputHandler } from './tarifInputHandler.js';
import { initSpecialTarifHandler } from './specialTarifHandler.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationPlacesForm');
    if (!form) return;

    const container = form; // Utiliser le formulaire comme conteneur principal pour la délégation
    const alertDiv = document.getElementById('reservationAlert');
    const submitButton = document.getElementById('submitButton');
    const eventIdInput = document.getElementById('event_id');
    //On active le bouton par défaut
    if (submitButton) submitButton.disabled = false;

    if (!eventIdInput) {
        console.error("L'ID de l'événement est manquant.");
        return;
    }
    const eventId = eventIdInput.value;

    // Récupération des données depuis les data-attributes du formulaire
    // Pour l'étape 6, il n'y a pas de limitation par nageuse, donc pas de 'limitation' ni 'dejaReservees'
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

    // Pour l'étape 6, il n'y a pas de limitation par nageuse, donc on passe null
    tarifInputHandler = initTarifInputHandler({
        container: container,
        alertDiv: alertDiv,
        placesRestantesSpan: null, // Pas de places restantes à afficher ici
        limitation: null,
        dejaReservees: 0,
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
        updateSubmitState: updateSubmitState,
        withSeat: false // Pour l'étape 6, les tarifs spéciaux n'ont pas de places assises
    });

    // --- Initialisation UI au chargement ---
    // Ne recalculer l'état que s'il y a une pré‑sélection
    const hasPrefilled =
        Array.from(getInputs()).some(i => (parseInt(i.value, 10) || 0) > 0) ||
        (typeof window !== 'undefined' && !!window.specialTarifSession);

    // Si pré‑sélection: appliquer la logique courante, sinon laisser activé par défaut
    if (hasPrefilled) {
        updateSubmitState();
    } else {
        submitButton && (submitButton.disabled = false);
    }

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

    async function submitEtape6(payload) {
        try {
            const data = await apiPost('/reservation/valid/6', payload);
            if (data.success) {
                window.location.href = '/reservation/confirmation';
            } else {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                showFlashMessage('danger', data.error || 'Erreur lors de la validation de l’étape 6.');
            }
        } catch (err) {
            showFlashMessage('danger', err.userMessage || err.message);
            submitButton && (submitButton.disabled = false);
        }
    }

    // --- Gestion de la soumission du formulaire ---
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        submitButton && (submitButton.disabled = true);

        try {
            const payload = buildReservationPayload();
            await submitEtape6(payload);
        } catch (err) {
            showFlashMessage('danger', err.userMessage || err.message || 'Erreur lors de la validation de l’étape 6.');
            submitButton && (submitButton.disabled = false);
        }
    });
});
