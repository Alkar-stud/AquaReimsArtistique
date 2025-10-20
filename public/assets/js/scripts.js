/**
 * Récupère le jeton CSRF depuis la balise meta.
 * @returns {string|null} Le jeton CSRF.
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : null;
}

/**
 * Met à jour le jeton CSRF dans la balise meta.
 * @param token Le nouveau jeton CSRF.
 * @returns {void}
 */
function updateCsrfToken(token) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        csrfMeta.content = token;
    }
}

/**
 *  Normalisation des messages d'erreur pour l'utilisateur
 * @param status
 * @param fallback
 * @returns {string}
 */
function normalizeUserMessage(status, fallback = null) {
    if (fallback && typeof fallback === 'string') {
        return fallback;
    }

    if (status === 401) {
        return 'Authentification requise.';
    }
    if (status === 403) {
        return 'Accès refusé.';
    }
    if (status === 404) {
        return 'Ressource introuvable.';
    }
    if (status === 413) {
        return 'Fichier trop volumineux.';
    }
    if (status === 419 || status === 440) {
        return 'Sécurité: votre session ou token a expiré. Rechargez la page.';
    }
    if (status === 429) {
        return 'Trop de requêtes. Veuillez réessayer plus tard.';
    }
    if (status >= 500) {
        return 'Une erreur interne est survenue. Merci de réessayer.';
    }

    return 'Une erreur est survenue. Merci de réessayer.';
}

document.addEventListener('DOMContentLoaded', function () {
    const configDropdown = document.getElementById('configDropdown');
    const menu = configDropdown ? configDropdown.nextElementSibling : null;

    if (configDropdown && menu) {
        // gestion du clic (mobile et desktop)
        configDropdown.addEventListener('click', function (e) {
            e.preventDefault();
            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            } else {
                menu.classList.add('show');
                menu.style.display = 'block';
                configDropdown.setAttribute('aria-expanded', 'true');
            }
        });

        // gestion du hover (desktop uniquement)
        configDropdown.addEventListener('mouseenter', function () {
            if (window.innerWidth > 768) {
                menu.classList.add('show');
                menu.style.display = 'block';
                configDropdown.setAttribute('aria-expanded', 'true');
            }
        });
        configDropdown.addEventListener('mouseleave', function () {
            if (window.innerWidth > 768) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            }
        });
        menu.addEventListener('mouseenter', function () {
            if (window.innerWidth > 768) {
                menu.classList.add('show');
                menu.style.display = 'block';
                configDropdown.setAttribute('aria-expanded', 'true');
            }
        });
        menu.addEventListener('mouseleave', function () {
            if (window.innerWidth > 768) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            }
        });

        // Ferme le menu si on clique ailleurs
        document.addEventListener('click', function (e) {
            if (!configDropdown.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            }
        });

        // Initialement masqué
        menu.style.display = 'none';
    }
});

/**
 * Affichage "flash" côté client (similaire à $flash_message serveur)
 * @param {string} type 'success', 'info', 'warning', 'danger'
 * @param {string} message Le message à afficher
 * @param {string} containerId L'ID de l'élément où injecter le message
 */
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

/**
 * Client API centralisé. Gère la communication, le traitement des réponses JSON,
 * la gestion des erreurs et la mise à jour du token CSRF.
 * @param {string} url L'URL de l'endpoint.
 * @param {object} options L'objet d'options pour `fetch()` (method, headers, body, etc.).
 * @returns {Promise<any>} Une promesse résolue avec les données JSON ou rejetée avec une erreur structurée.
 */
async function apiClient(url, options = {}) {
    // Fusionne les en-têtes par défaut avec ceux fournis, en donnant la priorité à ces derniers.
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers,
    };

    const fetchOptions = {
        ...options,
        headers,
        credentials: 'same-origin',
        referrerPolicy: 'same-origin',
    };

    return fetch(url, fetchOptions)
        .then(async (response) => {
            // Met à jour le token CSRF à partir de la réponse, s'il est présent.
            const csrfHeader = response.headers.get('X-CSRF-Token');
            if (csrfHeader) updateCsrfToken(csrfHeader);

            const contentType = response.headers.get('content-type') || '';
            const raw = await response.text(); // Lire le corps UNE seule fois
            const isJson = contentType.includes('application/json');

            if (!isJson) {
                console.error(`[apiClient] Réponse non-JSON (statut ${response.status}) - ${response.url}`);
                console.error('[apiClient] Corps:', raw);
                const err = new Error(`Réponse non-JSON (statut ${response.status})`);
                err.status = response.status;
                err.body = raw;
                err.url = response.url;
                err.userMessage = normalizeUserMessage(response.status);
                return Promise.reject(err);
            }

            let data;
            try {
                data = raw ? JSON.parse(raw) : null;
            } catch {
                console.error('[apiClient] Échec du parsing JSON. Corps brut:', raw);
                console.error(raw);
                const err = new Error('Réponse JSON invalide');
                err.status = response.status;
                err.body = raw;
                err.url = response.url;
                err.userMessage = normalizeUserMessage(response.status);
                return Promise.reject(err);
            }

            if (!response.ok) {
                const msg = data?.error || data?.message || `HTTP ${response.status}`;
                const err = new Error(msg);
                err.status = response.status;
                err.data = data;
                err.url = response.url;
                err.userMessage = normalizeUserMessage(response.status, data?.userMessage || data?.error || data?.message);
                console.error(`[apiClient] HTTP ${response.status} - ${response.url}`);
                console.error('[apiClient] Corps:', raw);
                return Promise.reject(err);
            }

            return data;
        })
        .catch((error) => {
            // Gérer les erreurs réseau (ex: fetch échoue) et les rejets manuels
            console.error('[apiClient] Erreur attrapée:', error);
            // Renvoyer une promesse rejetée pour que le .catch() du code appelant fonctionne
            return Promise.reject(error);
        });
}

/**
 * Raccourci pour les requêtes POST. Prépare le body et les en-têtes CSRF.
 * @param {string} url
 * @param {object|FormData} body
 * @param {object} opts
 * @returns {Promise<any>}
 */
async function apiPost(url, body, opts = {}) {
    // Récupérer le jeton CSRF
    const csrfToken = getCsrfToken();
    const isFormData = (typeof FormData !== 'undefined') && (body instanceof FormData);

    const headers = {
        'X-CSRF-Context': '/reservation', // Contexte par défaut, peut être surchargé
        'X-CSRF-Token': csrfToken,
        ...opts.headers,
    };

    // Ne pas fixer Content-Type pour FormData (le navigateur gère le boundary)
    const shouldJsonEncode = !isFormData && body !== undefined && body !== null && typeof body === 'object';
    if (shouldJsonEncode) {
        headers['Content-Type'] = 'application/json';
    }

    const fetchBody = shouldJsonEncode ? JSON.stringify(body) : body;

    return apiClient(url, {
        method: 'POST',
        headers,
        body: fetchBody,

        });
}


/**
 * Raccourci pour les requêtes GET. Construit l'URL avec les paramètres.
 * @param {string} url
 * @param {object} params
 * @param {object} opts
 * @returns {Promise<any>}
 */
async function apiGet(url, params = {}, opts = {}) {
    const finalUrl = new URL(url, window.location.origin);
    if (params) {
        Object.keys(params).forEach(key => finalUrl.searchParams.append(key, params[key]));
    }

    return apiClient(finalUrl.toString(), {
        method: 'GET',
        headers: opts.headers || {},
    });
}