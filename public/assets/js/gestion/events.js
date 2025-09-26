document.addEventListener('DOMContentLoaded', function() {
    /**
     * =================================================================
     * CONSTANTES DE CONFIGURATION
     * =================================================================
     */
        // Délai en minutes pour pré-remplir l'ouverture des portes avant le début d'une séance
    const DOORS_OPENING_OFFSET_MINUTES = 30;
    // Nombre de jours avant la séance pour la clôture des inscriptions à pré-remplir
    const INSCRIPTION_CLOSE_DAY_OFFSET = 1;
    // Heure de la journée pour pré-remplir la clôture des inscriptions (12 = midi)
    const INSCRIPTION_CLOSE_HOUR = 12;

    /**
     * =================================================================
     * SÉLECTION DES ÉLÉMENTS DU DOM
     * =================================================================
     */
    const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
    const eventForm = document.getElementById('eventForm');
    const validationErrorsContainer = document.getElementById('validation-errors');
    const eventModalTitle = document.getElementById('eventModalTitle');

    // Conteneurs et templates pour les éléments dynamiques
    const sessionsContainer = document.getElementById('sessions-container');
    const sessionTemplate = document.getElementById('session-template');
    const inscriptionContainer = document.getElementById('inscription-dates-container');
    const inscriptionTemplate = document.getElementById('inscription-period-template');


    // Compteurs pour les index uniques
    let sessionIndex = 0;
    let inscriptionIndex = 0;

    /**
     * =================================================================
     * FONCTIONS DE MANIPULATION DYNAMIQUE (SÉANCES, PÉRIODES)
     * =================================================================
     */

    // Ajoute un nouvel élément (séance ou période) au formulaire
    function addItem(type, data = null) {
        const isSession = type === 'session';
        const template = isSession ? sessionTemplate : inscriptionTemplate;
        const container = isSession ? sessionsContainer : inscriptionContainer;
        let index = isSession ? sessionIndex++ : inscriptionIndex++;

        const clone = template.content.cloneNode(true);
        const itemElement = clone.querySelector('.dynamic-item');

        // Met à jour les attributs 'name' et 'id' pour avoir un index unique
        itemElement.querySelectorAll('[name*="__INDEX__"]').forEach(el => {
            el.name = el.name.replace('__INDEX__', index);
            el.id = el.id.replace('__INDEX__', index);
        });

        // Ajoute l'écouteur pour le bouton de suppression
        itemElement.querySelector('.remove-item-btn').addEventListener('click', () => {
            itemElement.remove();
        });

        // Logique spécifique pour les séances (pré-remplissage date)
        if (isSession) {
            const startDateInput = itemElement.querySelector('.session-start-date');
            const doorsDateInput = itemElement.querySelector('[name*="opening_doors_at"]');
            startDateInput.addEventListener('change', () => {
                if (startDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    // On soustrait les minutes. new Date() interprète la valeur de l'input comme étant locale.
                    startDate.setMinutes(startDate.getMinutes() - DOORS_OPENING_OFFSET_MINUTES);
                    // Pour éviter les problèmes de fuseau horaire avec toISOString(), on reconstruit la chaîne manuellement.
                    const year = startDate.getFullYear();
                    const month = String(startDate.getMonth() + 1).padStart(2, '0');
                    const day = String(startDate.getDate()).padStart(2, '0');
                    const hours = String(startDate.getHours()).padStart(2, '0');
                    const minutes = String(startDate.getMinutes()).padStart(2, '0');
                    doorsDateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            });
        }

        // Si des données sont fournies (mode édition), on remplit les champs
        if (data) {
            Object.keys(data).forEach(key => {
                // L'ID est dans un champ caché, les autres sont nommés `... [key]`
                const inputName = key === 'id' ? `[id]` : `[${key}]`;
                const input = itemElement.querySelector(`[name*="${inputName}"]`);
                if (input) {
                    input.value = data[key];
                }
            });
        }

        container.appendChild(itemElement);
        // Si on vient d'ajouter une période d'inscription, on tente de pré-remplir la date de clôture.
        if (!isSession) {
            prefillInscriptionCloseDate();
        }
    }

    // Pré-remplit la date de clôture d'inscription en fonction de la première séance
    function prefillInscriptionCloseDate() {
        const firstSessionDateInput = sessionsContainer.querySelector('.session-start-date');
        const inscriptionCloseDateInputs = inscriptionContainer.querySelectorAll('[name*="close_registration_at"]');

        if (firstSessionDateInput && firstSessionDateInput.value) {
            const sessionDate = new Date(firstSessionDateInput.value);
            sessionDate.setDate(sessionDate.getDate() - INSCRIPTION_CLOSE_DAY_OFFSET);
            sessionDate.setHours(INSCRIPTION_CLOSE_HOUR, 0, 0, 0); // à midi

            inscriptionCloseDateInputs.forEach(input => {
                if (!input.value) { // Ne pas écraser une valeur déjà saisie
                    // Idem, on reconstruit la date manuellement pour éviter les décalages de fuseau horaire.
                    const year = sessionDate.getFullYear();
                    const month = String(sessionDate.getMonth() + 1).padStart(2, '0');
                    const day = String(sessionDate.getDate()).padStart(2, '0');
                    const hours = String(sessionDate.getHours()).padStart(2, '0');
                    const minutes = String(sessionDate.getMinutes()).padStart(2, '0');
                    input.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            });
        }
    }

    /**
     * =================================================================
     * GESTION DE L'ÉDITION D'UN ÉVÉNEMENT
     * =================================================================
     */

    // Remplit la modale avec les données d'un événement pour l'édition
    function populateModalForEdit(eventData) {
        // 1. Réinitialiser le formulaire (important pour passer du mode ajout à édition)
        resetModalForm();

        // 2. Configurer la modale pour l'édition
        eventModalTitle.textContent = "Modifier l'événement";
        eventForm.action = `/gestion/events/update`; // La route sera la même, on utilise l'ID caché
        document.getElementById('event_id').value = eventData.id;

        // 3. Remplir l'onglet "Informations"
        document.getElementById('event_name').value = eventData.name;
        document.getElementById('event_place').value = eventData.place;
        document.getElementById('event_limitation_per_swimmer').value = eventData.limitation_per_swimmer || '';

        // 4. Remplir l'onglet "Tarifs"
        const tarifCheckboxes = document.querySelectorAll('#pane-tarifs input[name="tarifs[]"]');
        tarifCheckboxes.forEach(checkbox => {
            checkbox.checked = eventData.tarifs.includes(parseInt(checkbox.value));
        });

        // 5. Remplir l'onglet "Séances"
        if (eventData.sessions && eventData.sessions.length > 0) {
            eventData.sessions.forEach(sessionData => {
                addItem('session', sessionData);
            });
        }

        // 6. Remplir l'onglet "Périodes d'inscription"
        if (eventData.inscription_dates && eventData.inscription_dates.length > 0) {
            eventData.inscription_dates.forEach(inscriptionData => {
                addItem('inscription', inscriptionData);
            });
        }

        // 7. Afficher la modale
        eventModal.show();
    }

    // Écouteur de clic délégué pour les boutons "Modifier"
    document.addEventListener('click', function(e) {
        const editButton = e.target.closest('.edit-event-btn');
        if (editButton) {
            const eventData = JSON.parse(editButton.dataset.eventJson);
            populateModalForEdit(eventData);
        }
    });

    /**
     * =================================================================
     * GESTION DES ÉVÉNEMENTS DU FORMULAIRE
     * =================================================================
     */

    // Clic sur les boutons "Ajouter"
    document.getElementById('add-session-btn').addEventListener('click', () => addItem('session'));
    document.getElementById('add-inscription-btn').addEventListener('click', () => addItem('inscription'));

    // Clic sur le bouton d'ajout rapide (desktop)
    const desktopAddBtn = document.getElementById('desktop-add-btn');
    if (desktopAddBtn) {
        desktopAddBtn.addEventListener('click', () => {
            const desktopNameInput = document.getElementById('desktop_add_name');
            const desktopPlaceInput = document.getElementById('desktop_add_place');

            // S'assurer que la modale est en mode "Ajout".
            resetModalForm();

            // On pré-remplit les champs de la modale avec les valeurs du formulaire rapide
            document.getElementById('event_name').value = desktopNameInput.value;
            document.getElementById('event_place').value = desktopPlaceInput.value;

            eventModal.show();
        });
    }

    // Quand on change d'onglet, on essaie de pré-remplir la date de clôture.
    document.getElementById('tab-inscriptions').addEventListener('shown.bs.tab', prefillInscriptionCloseDate);

    // Soumission du formulaire
    eventForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (validateForm()) {
            this.submit();
        }
    });

    // Fonction pour réinitialiser complètement la modale
    function resetModalForm() {
        eventForm.reset();
        eventModalTitle.textContent = 'Ajouter un événement';
        eventForm.action = '/gestion/events/add';
        document.getElementById('event_id').value = '';

        sessionsContainer.innerHTML = '';
        inscriptionContainer.innerHTML = '';
        sessionIndex = 0;
        inscriptionIndex = 0;

        validationErrorsContainer.classList.add('d-none');
        validationErrorsContainer.innerHTML = '';

        // S'assurer que le premier onglet est actif
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-info')).show();
    }

    // Réinitialisation du formulaire à la fermeture de la modale
    document.getElementById('eventModal').addEventListener('hidden.bs.modal', () => {
        resetModalForm();
        // Vider aussi le formulaire d'ajout rapide desktop au cas où
        const desktopNameInput = document.getElementById('desktop_add_name');
        if(desktopNameInput) desktopNameInput.value = '';
    });

    /**
     * =================================================================
     * LOGIQUE DE VALIDATION
     * =================================================================
     */

    function validateForm() {
        const errors = [];

        // Onglet 1 : Informations
        if (!document.getElementById('event_name').value.trim()) {
            errors.push({ tab: 'tab-info', message: "Le libellé de l'événement est obligatoire." });
        }
        if (!document.getElementById('event_place').value) {
            errors.push({ tab: 'tab-info', message: "Le lieu de l'événement est obligatoire." });
        }

        // Onglet 2: Tarifs
        const hasSeatedTarif = [...document.querySelectorAll('#pane-tarifs input[type="checkbox"]:checked')]
            .some(cb => {
                // Cette vérification est un peu fragile. Idéalement, on aurait une data-attribute.
                // On se base sur le fait qu'il est dans la première liste.
                return cb.closest('.list-group').previousElementSibling.textContent.includes('avec places');
            });
        if (!hasSeatedTarif) {
            errors.push({ tab: 'tab-tarifs', message: "Au moins un tarif 'avec places' doit être sélectionné." });
        }

        // Onglet  : Séances
        if (sessionsContainer.children.length === 0) {
            errors.push({ tab: 'tab-sessions', message: "Au moins une séance est obligatoire." });
        } else {
            sessionsContainer.querySelectorAll('input[required]').forEach(input => {
                if (!input.value) {
                    errors.push({ tab: 'tab-sessions', message: `Le champ '${input.previousElementSibling.textContent}' est manquant pour une séance.` });
                }
            });
        }

        // Onglet 4 : Périodes d'inscription
        if (inscriptionContainer.children.length === 0) {
            errors.push({ tab: 'tab-inscriptions', message: "Au moins une période d'inscription est obligatoire." });
        } else {
            inscriptionContainer.querySelectorAll('input[required]').forEach(input => {
                if (!input.value) {
                    errors.push({ tab: 'tab-inscriptions', message: `Le champ '${input.previousElementSibling.textContent}' est manquant pour une période.` });
                }
            });
        }

        // Affichage des erreurs
        if (errors.length > 0) {
            // On ne garde que les erreurs uniques par message pour la clarté
            const uniqueErrors = [...new Map(errors.map(e => [e.message, e])).values()];

            validationErrorsContainer.innerHTML = '<strong>Veuillez corriger les erreurs suivantes :</strong><ul>' +
                uniqueErrors.map(e => `<li>${e.message}</li>`).join('') +
                '</ul>';
            validationErrorsContainer.classList.remove('d-none');

            // Activer le premier onglet contenant une erreur
            const firstErrorTab = document.getElementById(uniqueErrors[0].tab);
            if (firstErrorTab) {
                bootstrap.Tab.getOrCreateInstance(firstErrorTab).show();
            }
            return false;
        }

        // Si tout est OK
        validationErrorsContainer.classList.add('d-none');
        return true;
    }
});
