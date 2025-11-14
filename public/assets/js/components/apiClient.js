'use strict';

import { extractErrorMessages } from './utils.js';

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function getCsrfContext() {
    const meta = document.querySelector('meta[name="csrf-context"]');
    return meta ? meta.content : '';
}

function setCsrfMeta(nextToken, nextContext) {
    if (nextToken) {
        let metaToken = document.querySelector('meta[name="csrf-token"]');
        if (!metaToken) {
            metaToken = document.createElement('meta');
            metaToken.setAttribute('name', 'csrf-token');
            document.head.appendChild(metaToken);
        }
        metaToken.setAttribute('content', nextToken);
    }
    if (nextContext) {
        let metaContext = document.querySelector('meta[name="csrf-context"]');
        if (!metaContext) {
            metaContext = document.createElement('meta');
            metaContext.setAttribute('name', 'csrf-context');
            document.head.appendChild(metaContext);
        }
        metaContext.setAttribute('content', nextContext);
    }
}

async function client(endpoint, { body, ...customConfig } = {}) {
    const headers = {};
    const config = {
        method: body ? 'POST' : 'GET',
        ...customConfig,
        headers: {
            ...headers,
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrfToken(),
            'X-CSRF-Context': getCsrfContext(),
            ...customConfig.headers,
        },
    };

    if (body) {
        if (body instanceof FormData) {
            config.body = body;
        } else {
            headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(body);
        }
    }

    const response = await fetch(endpoint, config);

    // Rafraîchit le jeton et le contexte si fournis par le serveur
    const nextToken = response.headers.get('X-CSRF-Token');
    const nextContext = response.headers.get('X-CSRF-Context');
    if (nextToken || nextContext) {
        setCsrfMeta(nextToken, nextContext);
    }

    const responseText = await response.text();

    if (!response.ok) {
        //console.error("Réponse d'erreur du serveur :", responseText);
        let errorData;
        try {
            errorData = JSON.parse(responseText);
        } catch {
            errorData = { message: response.statusText || 'Réponse invalide du serveur.' };
        }

        // Gestion de la redirection sur les réponses d'erreur (ex: session expirée 419)
        if (errorData.redirect) {
            window.location.href = errorData.redirect;
            return new Promise(() => {}); // Empêche le code appelant de continuer
        }

        const error = new Error(errorData.message);
        error.userMessage = extractErrorMessages(errorData);
        error.userMessage = errorData.message || errorData.errors || 'Une erreur de communication est survenue.';
        error.body = responseText;
        return Promise.reject(error);
    }

    try {
        const data = JSON.parse(responseText);

        // Gestion automatique de la redirection même en cas de succès
        if (data.redirect) {
            window.location.href = data.redirect;
            return new Promise(() => {}); // Promesse en attente
        }

        return data;
    } catch {
        console.error('Réponse non-JSON reçue:', responseText);
        return responseText;
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
