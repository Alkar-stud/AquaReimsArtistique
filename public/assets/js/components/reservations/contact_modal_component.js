(function (global, App) {
    'use strict';

    // On s'assure que le namespace pour les composants existe
    App.Components = App.Components || {};

    // --- Variables privées du composant ---
    let _containerEl, _nameInput, _firstnameInput, _emailInput, _phoneInput;
    let _isInitialized = false;

    // --- Fonctions privées du composant ---

    /**
     * Met en cache les éléments du DOM pour une performance optimale.
     * @param {HTMLElement} containerEl - L'élément conteneur des champs de contact.
     */
    function _cacheDOMElements(containerEl) {
        _containerEl = containerEl;
        // On utilise les data-attributes qui sont communs aux deux vues
        _nameInput = containerEl.querySelector('[data-field="name"]');
        _firstnameInput = containerEl.querySelector('[data-field="firstname"]');
        _emailInput = containerEl.querySelector('[data-field="email"]');
        _phoneInput = containerEl.querySelector('[data-field="phone"]');
    }

    function _getToken() {
        // La modale utilise un input, la page de modif un data-attribute. On gère les deux.
        const tokenInput = _containerEl.closest('form')?.querySelector('#modal_reservation_token') || document.querySelector('[data-token]');
        return tokenInput ? (tokenInput.value || tokenInput.dataset.token) : null;
    }

    /**
     * Gère l'événement 'blur' sur les champs de contact.
     * @param {Event} event
     */
    function _handleBlur(event) {
        const input = event.target;
        const feedbackSpan = input.parentElement.querySelector('.feedback-span');
        const field = input.dataset.field;
        const value = input.value;
        const reservationToken = _getToken();

        // Validation de l'email
        if (field === 'email' && !global.validateEmail(value)) {
            global.showFeedback(feedbackSpan, 'error', 'Adresse e-mail invalide.');
            return;
        }

        // Validation du téléphone (s'il n'est pas vide)
        if (field === 'phone' && value.trim() !== '' && !global.validateTel(value)) {
            global.showFeedback(feedbackSpan, 'error', 'Format de téléphone invalide.');
            return;
        }

        const data = {
            typeField: 'contact',
            token: reservationToken,
            field: field,
            value: value
        };

        // Utilise la fonction globale `updateField` qui gère déjà le feedback visuel
        global.updateField(feedbackSpan, data);
    }

    // --- API Publique du composant ---
    App.Components.Contact = {
        /**
         * Initialise le composant : met en cache les éléments et attache les écouteurs.
         * @param {HTMLElement} containerEl - L'élément conteneur des champs de contact.
         */
        init: function(containerEl) {
            if (_isInitialized) {
                return;
            } // Ne s'initialise qu'une seule fois

            _cacheDOMElements(containerEl);

            const inputs = containerEl.querySelectorAll('.editable-contact');
            inputs.forEach(input => {
                if (input) {
                    input.addEventListener('blur', _handleBlur);
                }
            });

            _isInitialized = true;
        },

        /**
         * Met à jour l'interface utilisateur avec les données de la réservation.
         * @param {object} reservationData - Les données de la réservation.
         */
        updateUI: function(reservationData) {
            if (!_isInitialized) {
                console.warn("Contact component not initialized. Cannot update UI.");
                return; // Sécurité pour éviter les erreurs si les éléments ne sont pas cachés
            }

            if (_nameInput) {
                _nameInput.value = reservationData.name || '';
            }
            if (_firstnameInput) {
                _firstnameInput.value = reservationData.firstName || '';
            }
            if (_emailInput) {
                _emailInput.value = reservationData.email || '';
            }
            if (_phoneInput) {
                _phoneInput.value = reservationData.phone || '';
            }
        }
    };

})(window, window.App || (window.App = {}));