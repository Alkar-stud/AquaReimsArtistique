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

/**
 *
 * @param isoString
 * @returns {string}
 */
export function formatTime(isoString) {
    if (!isoString) return '';
    const d = new Date(isoString);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
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


/**
 * Convertit une durée ISO8601 de type PT... en secondes (supporte D/H/M/S).
 * Ex: "PT2H" => 7200, "PT20M" => 1200
 * Retourne 0 si spec invalide.
 */
export function parseIsoDurationToSeconds(spec) {
    if (!spec || typeof spec !== 'string') return 0;
    const m = /P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/i.exec(spec.trim());
    if (!m) return 0;
    const days = Number(m[1] || 0);
    const hours = Number(m[2] || 0);
    const minutes = Number(m[3] || 0);
    const seconds = Number(m[4] || 0);
    return days * 86400 + hours * 3600 + minutes * 60 + seconds;
}

/**
 * Calcule une date ISO d'expiration en partant d'un createdAt (ISO) et d'une spec ISO8601.
 * Retourne null si impossible.
 */
export function computeExpiryIsoFromCreatedAndSpec(createdIso, spec) {
    if (!createdIso || !spec) return null;
    const base = new Date(createdIso);
    if (isNaN(base.getTime())) return null;
    const seconds = parseIsoDurationToSeconds(spec);
    if (!seconds) return null;
    return new Date(base.getTime() + seconds * 1000).toISOString();
}

export function initCountdown(element, expiresAtMs = null) {
    if (!element) return;

    let expiresAt = null;
    if (typeof expiresAtMs === 'number' && !Number.isNaN(expiresAtMs)) {
        expiresAt = Number(expiresAtMs);
    } else if (element.dataset.expiresAt) {
        // Support du timestamp (ancien fonctionnement)
        const timestamp = Number(element.dataset.expiresAt);
        if (!Number.isNaN(timestamp) && timestamp > 0) expiresAt = timestamp * 1000; // Convert seconds to milliseconds
    } else if (element.dataset.createdAtTimestamp && element.dataset.timeoutSeconds) {
        // NOUVELLE LOGIQUE (inspirée de la debug-bar)
        const createdAt = Number(element.dataset.createdAtTimestamp);
        const timeout = Number(element.dataset.timeoutSeconds);

        console.log('Countdown data:', { createdAt, timeout, element }); // LIGNE À AJOUTER POUR LE DÉBOGAGE

        if (createdAt > 0 && timeout > 0) {
            expiresAt = (createdAt + timeout) * 1000;
        } else {
            console.warn('Invalid data for countdown:', { createdAt, timeout });
        }
    }

    if (!expiresAt) {
        element.textContent = '--:--';
        return;
    }

    // Mise à jour périodique — NE PAS arrêter l'intervalle définitivement sur "Expiré"
    const updateCountdown = () => {
        if (!document.body.contains(element)) {
            if (element._countdownInterval) {
                clearInterval(element._countdownInterval);
                element._countdownInterval = null;
            }
            return;
        }

        const now = Date.now();
        const distance = expiresAt - now;

        // Tolérance courte pour éviter faux "Expiré" liés à latence/synchro (-5s)
        if (distance <= 0 && distance > -5000) {
            element.textContent = '00:00';
            element.classList.remove('text-danger');
            return;
        }

        if (distance <= 0) {
            // Affiche Expiré mais garde l'intervalle pour possibilité de récupération
            element.textContent = 'Expiré';
            element.classList.add('text-danger');
            return;
        }

        // Si on était en état "Expiré", on restaure l'affichage normal
        if (element.classList.contains('text-danger')) {
            element.classList.remove('text-danger');
        }

        const totalSeconds = Math.floor(distance / 1000);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        if (hours > 0) {
            element.textContent = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        } else {
            element.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    };

    // Lancer et stocker l'intervalle (évite doublons)
    updateCountdown();
    if (element._countdownInterval) clearInterval(element._countdownInterval);
    element._countdownInterval = setInterval(updateCountdown, 1000);
}