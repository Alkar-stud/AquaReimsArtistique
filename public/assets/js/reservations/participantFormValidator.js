'use strict';

import { setState, validateNameAndFirstname } from '../components/formContactValidator.js';

/**
 * Vérifie l'unicité de tous les couples nom/prénom dans le formulaire.
 * Applique l'état visuel 'is-invalid' aux doublons.
 * @param {HTMLFormElement} form - Le formulaire contenant les participants.
 */
function checkAllForUniqueness(form) {
    const participantRows = form.querySelectorAll('.participant-row');
    const couples = new Map();

    // Première passe : on groupe les participants par couple nom/prénom
    participantRows.forEach(row => {
        const nameInput = row.querySelector('input[name="names[]"]');
        const firstnameInput = row.querySelector('input[name="firstnames[]"]');
        const name = nameInput.value.trim().toLowerCase();
        const firstname = firstnameInput.value.trim().toLowerCase();

        if (name && firstname) {
            const key = `${name}|${firstname}`;
            if (!couples.has(key)) {
                couples.set(key, []);
            }
            couples.get(key).push({ nameInput, firstnameInput });
        }
    });

    // Deuxième passe : on applique les états de validation
    participantRows.forEach(row => {
        const nameInput = row.querySelector('input[name="names[]"]');
        const firstnameInput = row.querySelector('input[name="firstnames[]"]');
        const name = nameInput.value.trim().toLowerCase();
        const firstname = firstnameInput.value.trim().toLowerCase();
        const key = `${name}|${firstname}`;

        // On ne réinitialise que l'erreur d'unicité, pas les autres (ex: nom/prénom identiques)
        if (nameInput.classList.contains('is-invalid') && nameInput.parentElement.querySelector('.invalid-feedback').textContent === 'Ce participant est déjà présent.') {
            setState(nameInput, true);
            setState(firstnameInput, true);
        }

        if (couples.has(key) && couples.get(key).length > 1) {
            const msg = 'Ce participant est déjà présent.';
            setState(nameInput, false, msg);
            setState(firstnameInput, false, msg);
        }
    });
}

/**
 * Initialise les écouteurs d'événements pour la validation en direct des champs de participant.
 * @param {HTMLFormElement} form - Le formulaire.
 */
export function initParticipantFieldListeners(form) {
    form.querySelectorAll('.participant-row').forEach(row => {
        const nameInput = row.querySelector('input[name="names[]"]');
        const firstnameInput = row.querySelector('input[name="firstnames[]"]');

        [nameInput, firstnameInput].forEach(input => {
            input.addEventListener('input', () => {
                // 1. Valide que nom et prénom sont différents sur la ligne actuelle
                validateNameAndFirstname(nameInput, firstnameInput);
                // 2. Valide l'unicité sur l'ensemble du formulaire
                checkAllForUniqueness(form);
            });
        });
    });
}

/**
 * Valide tous les formulaires de participants sur la page.
 * @param {HTMLFormElement} form - Le formulaire contenant les participants.
 * @returns {{isValid: boolean, errors: string[]}} - Un objet indiquant si la validation est réussie et la liste des erreurs.
 */
export function validateAllParticipants(form) {
    const names = Array.from(form.querySelectorAll('input[name="names[]"]'));
    const firstnames = Array.from(form.querySelectorAll('input[name="firstnames[]"]'));
    const justificatifs = Array.from(form.querySelectorAll('input[name="justificatifs[]"]'));

    let errors = [];
    const couples = new Set();
    let allValid = true;

    for (let i = 0; i < names.length; i++) {
        const nameInput = names[i];
        const firstnameInput = firstnames[i];
        const justifInput = justificatifs[i];

        const name = nameInput.value.trim();
        const firstname = firstnameInput.value.trim();

        // Réinitialiser les états de validation
        setState(nameInput, true);
        setState(firstnameInput, true);

        // Validation des champs requis (gérée par HTML5, mais on double-vérifie)
        if (!name) {
            errors.push(`Le nom du participant ${i + 1} est manquant.`);
            setState(nameInput, false, 'Champ requis');
            allValid = false;
        }
        if (!firstname) {
            errors.push(`Le prénom du participant ${i + 1} est manquant.`);
            setState(firstnameInput, false, 'Champ requis');
            allValid = false;
        }

        // Validation nom != prénom
        if (!validateNameAndFirstname(nameInput, firstnameInput)) {
            errors.push(`Le nom et le prénom du participant ${i + 1} doivent être différents.`);
            allValid = false;
        }

        // Validation de l'unicité du couple nom/prénom
        if (name && firstname) {
            const key = (name + '|' + firstname).toLowerCase();
            if (couples.has(key)) {
                errors.push(`Le couple nom/prénom du participant ${i + 1} est déjà utilisé.`);
                setState(nameInput, false, 'Ce participant est déjà présent.');
                setState(firstnameInput, false, 'Ce participant est déjà présent.');
                allValid = false;
            }
            couples.add(key);
        }
    }

    return { isValid: allValid, errors };
}