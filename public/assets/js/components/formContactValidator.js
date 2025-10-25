'use strict';

import { validateEmail, validateTel } from './validators.js';

/**
 * Crée ou récupère un élément de feedback pour un champ de formulaire.
 * @param {HTMLInputElement} input - Le champ de formulaire.
 * @returns {HTMLElement} L'élément de feedback.
 */
function ensureInvalidFeedback(input) {
    let fb = input.parentElement.querySelector(`.invalid-feedback[data-for="${input.id}"]`);
    if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        fb.dataset.for = input.id;
        input.parentElement.appendChild(fb);
    }
    return fb;
}

/**
 * Normalise une chaîne de caractères pour la comparaison (enlève accents, espaces, etc.).
 * @param {string} s - La chaîne à normaliser.
 * @returns {string} La chaîne normalisée.
 */
function normalize(s) {
    if (!s) return '';
    return s
        .trim()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z]/g, '')
        .toLocaleLowerCase('fr-FR');
}

/**
 * Met à jour l'état visuel (valide/invalide) d'un champ.
 * @param {HTMLInputElement} input - Le champ.
 * @param {boolean} ok - True si valide, false sinon.
 * @param {string} [message=''] - Le message d'erreur à afficher.
 */
export function setState(input, ok, message = '') {
    const fb = ensureInvalidFeedback(input);
    if (ok) {
        input.classList.remove('is-invalid');
        fb.textContent = '';
        if (input.value.trim()) input.classList.add('is-valid');
        else input.classList.remove('is-valid');
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        fb.textContent = message;
    }
}

/**
 * Valide que le nom et le prénom ne sont pas identiques.
 * @param {HTMLInputElement} nameInput - Le champ du nom.
 * @param {HTMLInputElement} firstnameInput - Le champ du prénom.
 * @returns {boolean} True si la validation passe.
 */
export function validateNameAndFirstname(nameInput, firstnameInput) {
    const n = normalize(nameInput.value);
    const p = normalize(firstnameInput.value);

    if (n && p && n === p) {
        const msg = 'Le nom et le prénom ne doivent pas être identiques.';
        setState(nameInput, false, msg);
        setState(firstnameInput, false, msg);
        return false;
    }

    setState(nameInput, true);
    setState(firstnameInput, true);
    return true;
}

/**
 * Valide un champ email.
 * @param {HTMLInputElement} emailInput - Le champ email.
 * @returns {boolean} True si valide.
 */
export function validateEmailField(emailInput) {
    const value = emailInput.value.trim();
    const ok = !value || validateEmail(value); // Valide si vide ou si le format est bon
    setState(emailInput, ok, ok ? '' : 'Adresse email invalide.');
    return ok;
}

/**
 * Valide un champ téléphone.
 * @param {HTMLInputElement} phoneInput - Le champ téléphone.
 * @returns {boolean} True si valide.
 */
export function validatePhoneField(phoneInput) {
    const value = phoneInput.value.trim();
    if (!value) { // Le téléphone est facultatif
        setState(phoneInput, true);
        return true;
    }
    const ok = validateTel(value);
    setState(phoneInput, ok, ok ? '' : 'Format attendu : 0X XX XX XX XX ou +33XXXXXXXXX.');
    return ok;
}