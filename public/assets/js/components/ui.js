'use strict';

/**
 * Displays a flash message in the specified container.
 * @param {string} type - Type of message (success, error, warning, info).
 * @param {string} message - The message text.
 * @param {string} containerId - The ID of the container element (e.g., 'ajax_flash_container').
 */
export function showFlashMessage(type, message, containerId = 'ajax_flash_container') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.warn(`Flash message container with ID "${containerId}" not found.`);
        return;
    }

    // Clear existing messages
    container.innerHTML = '';
    container.className = ''; // Clear existing classes

    const alertDiv = document.createElement('div');
    alertDiv.classList.add('alert', `alert-${type}`, 'mt-3'); // Add Bootstrap alert classes
    alertDiv.setAttribute('role', 'alert');
    alertDiv.textContent = message;

    container.appendChild(alertDiv);

    // Optionally, hide the message after some time
    setTimeout(() => {
        alertDiv.remove();
    }, 5000); // Message disappears after 5 seconds
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