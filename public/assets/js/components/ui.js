'use strict';

/**
 * Affiche un message dans une div définie
 * @param {string} type - Type du message (success, error, warning, info).
 * @param {string} message - Texte du message.
 * @param {string} containerId - L'ID du conteneur dans lequel afficher le message.
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

/**
 * Retour dans un feedback associé à des inputs
 *
 * @param feedbackSpan
 * @param status
 * @param message
 */
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