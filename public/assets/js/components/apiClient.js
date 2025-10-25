'use strict';

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

async function client(endpoint, { body, ...customConfig } = {}) {
    const headers = { 'Content-Type': 'application/json' };
    const config = {
        method: body ? 'POST' : 'GET',
        ...customConfig,
        headers: {
            ...headers,
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrfToken(),
            ...customConfig.headers,
        },
    };

    if (body) {
        config.body = JSON.stringify(body);
    }

    const response = await fetch(endpoint, config);

    // On lit la réponse brute une seule fois
    const responseText = await response.text();

    if (!response.ok) {
        // Si le statut est une erreur (4xx, 5xx), on logue toujours la réponse brute
        console.error(`Erreur API: ${response.status} - ${response.statusText}`, responseText);

        let errorData;
        try {
            // On essaie de parser la réponse pour obtenir un message d'erreur structuré
            errorData = JSON.parse(responseText);
        } catch (e) {
            // Si ça échoue (c'est du HTML), on crée un message d'erreur de secours
            errorData = { message: response.statusText || 'Réponse invalide du serveur.' };
        }

        const error = new Error(errorData.message);
        error.userMessage = errorData.message || 'Une erreur de communication est survenue.';
        error.body = responseText; // On attache le corps brut de l'erreur pour un débogage avancé
        return Promise.reject(error);
    }

    // Si la réponse est OK (2xx), on essaie de la parser en JSON
    try {
        return JSON.parse(responseText);
    } catch (e) {
        console.error("La réponse du serveur était attendue en JSON, mais le format est invalide.", responseText);
        const error = new Error("Format de réponse invalide du serveur.");
        error.userMessage = "La réponse du serveur est dans un format inattendu.";
        return Promise.reject(error);
    }
}

export function apiGet(endpoint, params = {}) {
    const url = new URL(endpoint, window.location.origin);
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
    return client(url);
}

export function apiPost(endpoint, body, customConfig = {}) {
    return client(endpoint, { ...customConfig, body });
}

export function apiDelete(endpoint, customConfig = {}) {
    return client(endpoint, { ...customConfig, method: 'DELETE' });
}