// Utils de validation existants
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function validateTel(tel) {
    const v = String(tel || '').replace(/\s+/g, '');
    // Accepte 0XXXXXXXXX (10 chiffres) ou +33XXXXXXXXX (9 chiffres sans le 0)
    return /^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/.test(v);
}

/**
 * Récupère le jeton CSRF depuis la balise meta.
 * @returns {string|null} Le jeton CSRF.
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

/**
 * Met à jour le jeton CSRF dans la balise meta.
 */
function updateCsrfToken(token) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        csrfMeta.content = token;
    }
}

// Affichage "flash" côté client (similaire à $flash_message serveur)
function showFlash(type, message, containerId = 'ajax_flash_container') {
    const allowed = new Set(['success', 'info', 'warning', 'danger']);
    const level = allowed.has(type) ? type : 'danger';

    let container = document.getElementById(containerId);
    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        // Par défaut, on l'insère en haut du <main> s'il existe, sinon en body
        const main = document.querySelector('main') || document.body;
        main.prepend(container);
    }
    container.innerHTML = `
<div class="alert alert-${level}" role="alert">
  ${message ? String(message) : 'Une erreur est survenue.'}
</div>`;
}

// Normalisation des messages d'erreur pour l'utilisateur
function normalizeUserMessage(status, fallback = null) {
    if (fallback && typeof fallback === 'string') return fallback;

    if (status === 401) return 'Authentification requise.';
    if (status === 403) return 'Accès refusé.';
    if (status === 404) return 'Ressource introuvable.';
    if (status === 413) return 'Fichier trop volumineux.';
    if (status === 419 || status === 440) return 'Sécurité: votre session ou token a expiré. Rechargez la page.';
    if (status === 429) return 'Trop de requêtes. Veuillez réessayer plus tard.';
    if (status >= 500) return 'Une erreur interne est survenue. Merci de réessayer.';

    return 'Une erreur est survenue. Merci de réessayer.';
}

// Point unique pour POST + gestion CSRF + parsing JSON + log non-JSON
function apiPost(url, body, opts = {}) {
    const isFormData = (typeof FormData !== 'undefined') && (body instanceof FormData);
    // Récupérer le jeton CSRF
    let csrfToken = getCsrfToken();

    const headers = Object.assign(
        {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Context' : '/reservation', // Explicite pour les réservations
            'X-CSRF-Token' : csrfToken
        },
        opts.headers || {}
    );

    // Ne pas fixer Content-Type pour FormData (le navigateur gère le boundary)
    const shouldJsonEncode = !isFormData && body !== undefined && body !== null && typeof body === 'object';
    if (shouldJsonEncode && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
    }

console.log('body : ', body);
    const fetchBody = shouldJsonEncode ? JSON.stringify(body) : body;
console.log('fetchBody : ', fetchBody);
    return fetch(url, {
        method: 'POST',
        headers,
        body: fetchBody,
        credentials: 'same-origin',
        referrerPolicy: 'same-origin',
        redirect: 'follow'
    })
        .then(async (response) => {
            // Récupère le nouveau token côté réponse
            const csrfHeader = response.headers.get('X-CSRF-Token');
            // Mettre à jour le jeton pour les requêtes suivantes
            if (csrfHeader) updateCsrfToken(csrfHeader);

            const contentType = response.headers.get('content-type') || '';
            const raw = await response.text(); // Lire le corps UNE seule fois
            const isJson = contentType.includes('application/json');

            if (isJson) {
                let data;
                try {
                    data = raw ? JSON.parse(raw) : null;
                } catch {
                    console.error('[apiPost] Échec du parsing JSON. Corps brut:');
                    console.error(raw);
                    const err = new Error('Réponse JSON invalide');
                    err.status = response.status;
                    err.body = raw;
                    err.url = response.url;
                    err.userMessage = normalizeUserMessage(response.status);
                    throw err;
                }

                if (!response.ok) {
                    if (response.status === 419) {
                        console.warn('Jeton CSRF expiré, actualisation du jeton...');
                        throw { userMessage: 'Session expirée, veuillez réessayer.' };
                    }
                    const msg = data && (data.error || data.message) || `HTTP ${response.status}`;
                    const err = new Error(msg);
                    err.status = response.status;
                    err.data = data;
                    err.url = response.url;
                    err.userMessage = normalizeUserMessage(response.status, data && (data.userMessage || data.error || data.message));
                    console.error(`[apiPost] HTTP ${response.status} - ${response.url}`);
                    console.error('[apiPost] Corps:', raw);
                    throw err;
                }

                console.log('[apiPost] Réponse JSON:', data);
                return data;
            }

            // Non-JSON: réutiliser 'raw' (ne pas relire le corps)
            console.error(`[apiPost] Réponse non-JSON (statut ${response.status}) - ${response.url}`);
            console.error('[apiPost] Corps:', raw);

            const err = new Error(`Réponse non-JSON (statut ${response.status})`);
            err.status = response.status;
            err.body = raw;
            err.url = response.url;
            err.userMessage = normalizeUserMessage(response.status);
            throw err;
        });
}

// Extrait l'id de tarif à partir de name="tarifs[123]"
function parseTarifIdFromName(name) {
    const m = String(name || '').match(/^tarifs\[(\d+)]$/);
    return m ? m[1] : null;
}

function euroFromCents(cents) {
    const n = (parseInt(cents, 10) || 0) / 100;
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

// Exposer globalement
window.apiPost = apiPost;
window.showFlash = showFlash;

