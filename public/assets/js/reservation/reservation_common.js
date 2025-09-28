// Utils de validation existants
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function validateTel(tel) {
    return /^0[1-9](\d{8})$/.test(tel.replace(/\s+/g, ''));
}

// MAJ CSRF depuis les réponses JSON
function updateCsrfTokenFrom(data) {
    if (data && typeof data.csrf_token === 'string' && data.csrf_token.length > 0) {
        window.csrf_token = data.csrf_token;
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
    const headers = Object.assign(
        { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        opts.headers || {}
    );

    // Ne pas fixer Content-Type pour FormData (le navigateur gère le boundary)
    const shouldJsonEncode = !isFormData && body !== undefined && body !== null && typeof body === 'object';
    if (shouldJsonEncode && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
    }

    // Injecter le CSRF si absent
    if (!headers['X-CSRF-TOKEN'] && typeof window !== 'undefined' && typeof window.csrf_token === 'string') {
        headers['X-CSRF-TOKEN'] = window.csrf_token;
    }

    const fetchBody = shouldJsonEncode ? JSON.stringify(body) : body;

    return fetch(url, {
        method: 'POST',
        headers,
        body: fetchBody,
        credentials: 'same-origin',
        referrerPolicy: 'same-origin',
        redirect: 'follow'
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type') || '';

            // JSON attendu
            if (contentType.includes('application/json')) {
                const data = await response.json();
                updateCsrfTokenFrom(data);

                if (!response.ok) {
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

// Exposer globalement
window.apiPost = apiPost;
window.showFlash = showFlash;


// File: 'public/assets/js/reservation/reservation_etape1.js'




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