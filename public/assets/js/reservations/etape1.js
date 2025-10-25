'use strict';

import { apiPost } from '../components/apiClient.js';
import { initAccessCodeHandler } from './specialCodeHandler.js';

/**
 * Met à jour la liste des nageuses en fonction du groupe sélectionné.
 * @param {HTMLElement} groupSelect - L'élément select du groupe.
 * @param {object} swimmerData - Les données des nageuses par groupe.
 */
function updateSwimmerList(groupSelect, swimmerData) {
    const eventId = groupSelect.dataset.eventId;
    const swimmerContainer = document.getElementById(`swimmer_container_${eventId}`);
    const swimmerSelect = document.getElementById(`swimmer_${eventId}`);
    const selectedGroupId = groupSelect.value;

    if (!swimmerContainer || !swimmerSelect) return;

    swimmerSelect.innerHTML = '<option value="">Sélectionner une nageuse</option>';

    if (selectedGroupId && swimmerData[selectedGroupId]) {
        swimmerData[selectedGroupId].forEach(swimmer => {
            const option = new Option(`${swimmer.name}`, swimmer.id);
            swimmerSelect.add(option);
        });
        swimmerContainer.style.display = 'block';
    } else {
        swimmerContainer.style.display = 'none';
    }
}

/**
 * Valide le formulaire pour un événement et redirige si tout est OK.
 * @param {HTMLElement} eventCard - La carte de l'événement.
 */
async function validateAndReserve(eventCard) {
    const eventId = eventCard.dataset.eventId;
    const errorEl = eventCard.querySelector(`#form_error_message_${eventId}`);
    errorEl.textContent = '';

    // Validation de la session
    const sessionRadio = eventCard.querySelector(`input[name="session_${eventId}"]:checked`);
    if (!sessionRadio) {
        errorEl.textContent = 'Veuillez sélectionner une séance.';
        return;
    }
    const sessionId = sessionRadio.value;

    // Validation de la nageuse (si applicable)
    const swimmerSelect = eventCard.querySelector(`#swimmer_${eventId}`);
    const groupSelect = eventCard.querySelector(`#swimmer_group_${eventId}`);
    let swimmerId = null;

    // On vérifie s'il y a une sélection de groupe (ce qui implique une sélection de nageuse)
    if (groupSelect) {
        if (!groupSelect.value) {
            errorEl.textContent = 'Veuillez sélectionner un groupe.';
            return;
        }
        if (!swimmerSelect || !swimmerSelect.value) {
            errorEl.textContent = 'Veuillez sélectionner une nageuse.';
            return;
        }
        swimmerId = swimmerSelect.value;
    }

    // Validation du code d'accès (si le champ est visible et non validé)
    const codeInput = eventCard.querySelector(`#access_code_input_${eventId}`);
    let accessCode = null;
    if (codeInput && !codeInput.disabled) {
        accessCode = codeInput.value.trim();
        if (!accessCode) {
            errorEl.textContent = "Veuillez valider un code d'accès.";
            return;
        }
    }

    // Construction du payload pour l'API
    const payload = {
        event_id: eventId,
        event_session_id: sessionId,
        swimmer_id: swimmerId ? parseInt(swimmerId, 10) : null,
        access_code: accessCode
    };

    const reserveBtn = eventCard.querySelector(`#btn_reserver_${eventId}`);
    if (reserveBtn) {
        reserveBtn.disabled = true;
        reserveBtn.innerHTML = 'Validation... <span class="spinner-border spinner-border-sm"></span>';
    }

    try {
        const result = await apiPost('/reservation/valid/1', payload);
        if (result.success) {
            window.location.href = '/reservation/etape2Display';
        } else {
            throw new Error(result.error || 'Une erreur est survenue lors de la validation.');
        }
    } catch (error) {
        errorEl.textContent = error.userMessage || error.message;
        if (reserveBtn) reserveBtn.disabled = false;
        if (reserveBtn) reserveBtn.innerHTML = 'Réserver';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // On récupère les données des nageuses depuis le data-attribute
    const swimmerDataEl = document.getElementById('swimmer-data');
    const swimmerData = swimmerDataEl ? JSON.parse(swimmerDataEl.dataset.swimmers) : {};

    // On itère sur chaque carte d'événement pour attacher les écouteurs
    document.querySelectorAll('.event-card').forEach(card => {
        const eventId = card.dataset.eventId;

        // Gestionnaire pour la sélection de groupe de nageuses
        const groupSelect = card.querySelector(`#swimmer_group_${eventId}`);
        if (groupSelect) {
            groupSelect.addEventListener('change', () => updateSwimmerList(groupSelect, swimmerData));
        }

        // Gestionnaire pour le code d'accès
        initAccessCodeHandler(card);

        // Gestionnaire pour le bouton "Réserver"
        const reserveBtn = card.querySelector(`#btn_reserver_${eventId}`);
        if (reserveBtn) {
            reserveBtn.addEventListener('click', () => validateAndReserve(card));
        }
    });
});