'use strict';

/**
 * Active ou désactive l'état de chargement d'un bouton.
 * @param {HTMLElement} btn - Le bouton à modifier.
 * @param {boolean} on - True pour afficher le spinner, false pour le retirer.
 */
export function buttonLoading(btn, on) {
    if (!btn) return;
    if (on) {
        btn.disabled = true;
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm ms-2';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        btn.appendChild(spinner);
        btn._spinner = spinner; // Sauvegarde une référence au spinner
    } else {
        btn.disabled = false;
        if (btn._spinner) {
            btn._spinner.remove();
            btn._spinner = null;
        }
    }
}

/** Helpers ARIA
 *
 * @param btn
 * @param enabled
 */
export function setButtonState(btn, enabled) {
    if (!btn) return;
    if (enabled) {
        btn.disabled = false;
        btn.removeAttribute('disabled');
        btn.setAttribute('aria-disabled', 'false');
    } else {
        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
    }
}

/**
 *
 * @param btn
 */
export function syncAriaDisabled(btn) {
    if (!btn) return;
    btn.setAttribute('aria-disabled', btn.disabled ? 'true' : 'false');
}

/**
 *
 * @param btn
 */
export function watchDisabledAttr(btn) {
    if (!btn) return;
    // Init
    syncAriaDisabled(btn);
    // Observe changes to `disabled` and mirror to `aria-disabled`
    const mo = new MutationObserver((mutations) => {
        for (const m of mutations) {
            if (m.type === 'attributes' && m.attributeName === 'disabled') {
                syncAriaDisabled(m.target);
            }
        }
    });
    mo.observe(btn, { attributes: true, attributeFilter: ['disabled'] });
}

/**
 *
 * @param card
 * @param eventId
 * @param message
 */
export function setAccessCodeError(card, eventId, message) {
    const input = card.querySelector(`#access_code_input_${eventId}`);
    const status = card.querySelector(`#access_code_status_${eventId}`);
    if (input) input.setAttribute('aria-invalid', 'true');
    if (status) status.textContent = message || '';
}

/**
 *
 * @param card
 * @param eventId
 */
export function clearAccessCodeError(card, eventId) {
    const input = card.querySelector(`#access_code_input_${eventId}`);
    const status = card.querySelector(`#access_code_status_${eventId}`);
    if (input) input.setAttribute('aria-invalid', 'false');
    if (status) status.textContent = '';
}


/**
 * Formate un montant en centimes en chaîne de caractères Euro.
 * @param {number} cents - Le montant en centimes.
 * @returns {string} Le montant formaté (ex: "12,34 €").
 */
export function formatEuro(cents) {
    const v = (parseInt(cents, 10) || 0) / 100;
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}


export function extractErrorMessages(errorData) {
    const fallback = 'Une erreur de communication est survenue.';
    if (!errorData || typeof errorData !== 'object') return fallback;

    const messages = [];

    // Messages simples
    if (typeof errorData.message === 'string' && errorData.message.trim() !== '') {
        messages.push(errorData.message.trim());
    }
    if (typeof errorData.error === 'string' && errorData.error.trim() !== '') {
        messages.push(errorData.error.trim());
    }

    // Regroupe les erreurs multiples.
    const collect = (val) => {
        if (!val) return;
        if (typeof val === 'string') {
            if (val.trim() !== '') messages.push(val.trim());
        } else if (Array.isArray(val)) {
            val.forEach(collect);
        } else if (typeof val === 'object') {
            Object.values(val).forEach(collect); // ignore les clés non numériques
        }
    };

    collect(errorData.errors);

    if (messages.length === 0) return fallback;

    // Déduplique et joint (à adapter: '\n' ou ' • ')
    return [...new Set(messages)].join('\n');
}