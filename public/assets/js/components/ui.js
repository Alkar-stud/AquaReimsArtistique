'use strict';

/**
 * Displays a flash message in the specified container.
 * @param {string} type - Type of message (success, error, warning, info).
 * @param {string} message - The message text.
 * @param {string} containerId - The ID of the container element (e.g., 'ajax_flash_container').
 */
export function showFlashMessage(type, message, containerId = 'ajax_flash_container') {
    let container = document.getElementById(containerId);

    if (!container) {
        // Si le conteneur n'existe pas, on le crée et on l'ajoute au début du <main>
        const mainPage = document.getElementById('main-page');
        if (!mainPage) {
            console.error("L'élément <main id='main-page'> est introuvable. Impossible d'afficher le message flash.");
            // En dernier recours, on utilise un alert()
            alert(message);
            return;
        }
        container = document.createElement('div');
        container.id = containerId;
        // On l'insère comme premier enfant de <main>
        mainPage.prepend(container);
    }

    // Clear existing messages
    container.innerHTML = '';

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    `;

    container.appendChild(alertDiv);

}

export function showFeedback(feedbackSpan, status, message = '') {
    if (!feedbackSpan) {
        return;
    }
    feedbackSpan.textContent = status === 'success' ? '✓' : status === 'error' ? '✗' : '...';
    feedbackSpan.className = 'input-group-text feedback-span';
    feedbackSpan.classList.add(
        status === 'success' ? 'text-success' :
            status === 'error'   ? 'text-danger'  : 'text-muted'
    );
    feedbackSpan.title = message;
}