'use strict';

/**
 * Active ou désactive l'état de chargement d'un bouton.
 * @param {HTMLElement} btn - Le bouton à modifier.
 * @param {boolean} on - True pour afficher le spinner, false pour le retirer.
 */
export function buttonLoading(btn, on) {
    if (!btn) return;
    if (on) {
        btn.disabled = true;
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm ms-2';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        btn.appendChild(spinner);
        btn._spinner = spinner; // Sauvegarde une référence au spinner
    } else {
        btn.disabled = false;
        if (btn._spinner) {
            btn._spinner.remove();
            btn._spinner = null;
        }
    }
}