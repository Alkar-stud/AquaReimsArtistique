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
//    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
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
    /*
    if (token) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && token) meta.setAttribute('content', token);
    }
     */
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
            // Récupérer les nouveaux jetons CSRF dans la réponse
            const csrfHeader = response.headers.get('X-CSRF-Token');
            const csrfContext = response.headers.get('X-CSRF-Context');

            // Mettre à jour le jeton pour les requêtes suivantes
            if (csrfHeader) {
                updateCsrfToken(csrfHeader);
            }

            const contentType = response.headers.get('content-type') || '';
            // JSON attendu
            if (contentType.includes('application/json')) {
                const data = await response.json();
console.log('[apiPost] Réponse JSON:', data);

                if (!response.ok) {
                    if (response.status === 419) {
                        // Erreur de CSRF - on peut réessayer une fois avec le nouveau jeton
                        console.warn('Jeton CSRF expiré, actualisation du jeton...');
                        throw { userMessage: 'Session expirée, veuillez réessayer.' };
                    }

                    const msg = data && (data.error || data.message) || `HTTP ${response.status}`;
                    const err = new Error(msg);
                    err.status = response.status;
                    err.data = data;
                    err.userMessage = normalizeUserMessage(response.status, data && (data.userMessage || data.error || data.message));
                    err.url = response.url;
                    throw err;
                }
                return data;
            }

            // Non-JSON: journaliser le corps complet pour debug
            const text = await response.text();
            console.groupCollapsed(`[apiPost] Réponse non-JSON (statut ${response.status}) - ${response.url}`);
            console.log(text);
            console.groupEnd();

            const err = new Error(`Réponse non-JSON (statut ${response.status})`);
            err.status = response.status;
            err.body = text;
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

// Exposer globalement
window.apiPost = apiPost;
window.showFlash = showFlash;


/* Exemples d'usage:

// 1) Appel JSON
apiPost('/reservation/validate-access-code', { event_id, code })
  .then((data) => {
   //Traiter le retour data, genre data.success
   })
  .catch((e) => { console.error(e); });

// 2) Upload fichier
const fd = new FormData();
fd.append('file', fileInput.files[0]);
fd.append('event_id', eventId);
apiPost('/fichiers/upload', fd)
  .then((data) => {
   //Traiter le retour data, genre data.success
   })
    .catch((e) => { console.error(e); });
*/