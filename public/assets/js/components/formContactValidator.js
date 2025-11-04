'use strict';

import { validateEmail, validateTel } from './validators.js';

/**
 * Convertit n'importe quelle valeur de message en chaîne lisible.
 * - string/Error: direct.
 * - array: concaténé avec ` • `.
 * - objet: `.message`/`.text` sinon `JSON.stringify` (fallback).
 * @param {*} msg - Message brut (string, Error, array, object, etc.).
 * @returns {string} Message prêt pour l'affichage.
 */
function toMessageString(msg) {
    if (msg == null) return '';
    if (typeof msg === 'string') return msg;
    if (msg instanceof Error) return msg.message || String(msg);
    if (Array.isArray(msg)) return msg.map(toMessageString).filter(Boolean).join(' • ');
    if (typeof msg === 'object') {
        if (typeof msg.message === 'string') return msg.message;
        if (typeof msg.text === 'string') return msg.text;
        try { return JSON.stringify(msg); } catch { return String(msg); }
    }
    return String(msg);
}


/**
 * Récupère ou crée le conteneur d'erreur (`.invalid-feedback`) pour un champ.
 * - Assure un `id` stable: `${input.id}_error`.
 * - Prépare ARIA: `role="alert"`, `aria-live="polite"`.
 * @param {HTMLInputElement} input - Champ ciblé.
 * @returns {HTMLElement} Élément de feedback.
 */
function ensureInvalidFeedback(input) {
    const byId = input.form?.querySelector(`#${input.id}_error`);
    if (byId) return byId;
    let fb = input.parentElement?.querySelector(`.invalid-feedback[data-for="${input.id}"]`);
    if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        fb.dataset.for = input.id;
        fb.id = `${input.id}_error`;
        fb.setAttribute('role', 'alert');
        fb.setAttribute('aria-live', 'polite');
        (input.parentElement || input).appendChild(fb);
    }
    return fb;
}

/**
 * Normalise un nom/prénom pour les comparaisons:
 * - Trim, suppression des accents, des caractères non \[a‑zA‑Z], minuscule locale.
 * - À utiliser uniquement pour la logique métier (jamais pour l'affichage).
 * @param {string} s - Chaîne à normaliser.
 * @returns {string} Chaîne normalisée.
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
 * Met à jour l'état visuel et ARIA d'un champ.
 * - Pose/retire les classes `is-invalid`/`is-valid` \[selon votre CSS/Bootstrap].
 * - Met `aria-invalid`.
 * - Écrit le message d'erreur dans le feedback associé.
 * @param {HTMLInputElement} input - Champ ciblé.
 * @param {boolean} ok - `true` si valide, sinon `false`.
 * @param {string|*} [message=''] - Message d'erreur à afficher si invalide.
 * @returns {void}
 */
export function setState(input, ok, message = '') {
    const fb = ensureInvalidFeedback(input);
    if (ok) {
        input.classList.remove('is-invalid');
        fb.textContent = '';
        if (input.value.trim()) {
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
        }
        input.setAttribute('aria-invalid', 'false');
    } else {
        const text = toMessageString(message) || 'Veuillez corriger ce champ.';
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        fb.textContent = text;
        input.setAttribute('aria-invalid', 'true');
        if (!input.getAttribute('aria-describedby')) {
            input.setAttribute('aria-describedby', `${input.id}_error`);
        }
    }
}

/**
 * Valide le couple nom/prénom et met à jour les deux champs.
 * Règles:
 * - Chaque valeur doit avoir plus d’un caractère (après normalisation).
 * - Aucune présence de chiffres dans les valeurs brutes.
 * - Pas de 3 caractères identiques consécutifs (après normalisation).
 * - Nom et prénom ne doivent pas être identiques (après normalisation).
 * @param {HTMLInputElement} nameInput - Champ "Nom".
 * @param {HTMLInputElement} firstnameInput - Champ "Prénom".
 * @returns {boolean} `true` si l'ensemble est valide, sinon `false`.
 */
export function validateNameAndFirstname(nameInput, firstnameInput) {
    // Utiliser les valeurs brutes pour détecter les chiffres
    const nRaw = nameInput.value.trim();
    const pRaw = firstnameInput.value.trim();

    const n = normalize(nRaw);
    const p = normalize(pRaw);

    if (n.length <= 1 || p.length <= 1) {
        const msg = 'Le nom et le prénom doivent contenir plus d’un caractère.';
        setState(nameInput, false, msg);
        setState(firstnameInput, false, msg);
        return false;
    }

    // Vérifier les chiffres sur la valeur brute (pas normalisée)
    if (/\d/.test(nRaw) || /\d/.test(pRaw)) {
        const msg = 'Le nom et le prénom ne doivent pas contenir de chiffres.';
        setState(nameInput, false, msg);
        setState(firstnameInput, false, msg);
        return false;
    }

    if (/(.)\1\1/.test(n) || /(.)\1\1/.test(p)) {
        const msg = 'Le nom et le prénom ne doivent pas contenir trois caractères identiques consécutifs.';
        setState(nameInput, false, msg);
        setState(firstnameInput, false, msg);
        return false;
    }

    if (n === p) {
        const msg = 'Le nom et le prénom ne doivent pas être identiques.';
        setState(nameInput, false, msg);
        setState(firstnameInput, false, msg);
        return false;
    }

    setState(nameInput, true, '');
    setState(firstnameInput, true, '');
    return true;
}

/**
 * Valide le champ e‑mail et met à jour l'UI.
 * - Accepte vide (`required` HTML gère l'obligation si nécessaire).
 * - Utilise le validateur pur `validateEmail`.
 * @param {HTMLInputElement} emailInput - Champ "Email".
 * @returns {boolean} `true` si vide ou valide, sinon `false`.
 */
export function validateEmailField(emailInput) {
    const value = emailInput.value.trim();
    const ok = !value || validateEmail(value);
    setState(emailInput, ok, ok ? '' : 'Adresse email invalide.');
    return ok;
}

/**
 * Valide le champ téléphone et met à jour l'UI.
 * - Champ optionnel: vide accepté.
 * - Utilise le validateur pur `validateTel`.
 * @param {HTMLInputElement} phoneInput - Champ "Téléphone".
 * @returns {boolean} `true` si vide ou conforme, sinon `false`.
 */
export function validatePhoneField(phoneInput) {
    const value = phoneInput.value.trim();
    if (!value) {
        setState(phoneInput, true);
        return true;
    }
    const ok = validateTel(value);
    setState(phoneInput, ok, ok ? '' : 'Format attendu : 0X XX XX XX XX ou +33XXXXXXXXX.');
    return ok;
}