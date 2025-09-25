/**
 * Affiche un message flash dans un conteneur spécifié.
 * @param {string} type Le type de message (success, danger, warning, info).
 * @param {string} message Le contenu du message.
 * @param {HTMLElement} container L'élément où injecter le message.
 */
export function showFlashMessage(type, message, container) {
    if (!container) {
        console.error('Flash message container not found.');
        return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    container.innerHTML = ''; // Nettoyer les anciens messages
    container.appendChild(alertDiv);
}