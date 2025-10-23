(function (global) {
    'use strict';
    const App = global.App || (global.App = {});

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

    async function client(url, options = {}) {
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
                const csrfHeader = response.headers.get('X-CSRF-Token');
                if (csrfHeader && App.CSRF) App.CSRF.update(csrfHeader);

                const contentType = response.headers.get('content-type') || '';
                const raw = await response.text();
                const isJson = contentType.includes('application/json');

                if (!isJson) {
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
                    return Promise.reject(err);
                }

                return data;
            });
    }

    async function post(url, body, opts = {}) {
        const csrfToken = App.CSRF ? App.CSRF.get() : null;
        const isFormData = (typeof FormData !== 'undefined') && (body instanceof FormData);
        const headers = {
            'X-CSRF-Context': '/reservation',
            'X-CSRF-Token': csrfToken,
            ...opts.headers,
        };
        const shouldJson = !isFormData && body && typeof body === 'object';
        if (shouldJson) headers['Content-Type'] = 'application/json';
        return client(url, { method: 'POST', headers, body: shouldJson ? JSON.stringify(body) : body });
    }

    async function get(url, params = {}, opts = {}) {
        const finalUrl = new URL(url, global.location.origin);
        if (params) Object.keys(params).forEach(k => finalUrl.searchParams.append(k, params[k]));
        return client(finalUrl.toString(), { method: 'GET', headers: opts.headers || {} });
    }

    App.register('Api', { client, post, get });
})(window);