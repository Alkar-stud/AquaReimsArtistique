// Fonction pour récupérer les données d'un événement pour édition
async function fetchEventData(eventId) {
    const events = window.eventsArray;

    const event = events.find(e => e.id === eventId);

    if (event) {
        document.getElementById('event_libelle').value = event.libelle;
        document.getElementById('event_lieu').value = event.lieu;
        document.getElementById('event_start_at').value = event.event_start_at;
        document.getElementById('event_opening_doors_at').value = event.opening_doors_at;
        // Gérer la checkbox limitation_per_swimmer
        document.getElementById('event_limitation_per_swimmer').value = event.limitation_per_swimmer !== null ? event.limitation_per_swimmer : '';

        // Gestion des événements associés : filtrer la liste
        const associateEventSelect = document.getElementById('event_associate_event');

        // D'abord vider les options existantes
        while (associateEventSelect.options.length > 1) { // Garder l'option "Aucun"
            associateEventSelect.remove(1);
        }

        // Ajouter toutes les options d'événements sauf l'événement en cours
        events.forEach(e => {
            if (e.id !== eventId) {
                const option = new Option(e.libelle, e.id);
                associateEventSelect.add(option);
            }
        });

        // Sélectionner l'événement associé si existant
        if (event.associate_event) {
            associateEventSelect.value = event.associate_event;
        }

        // Cocher les tarifs associés
        event.tarifs.forEach(tarifId => {
            const checkbox = document.getElementById('tarif_' + tarifId);
            if (checkbox) checkbox.checked = true;
        });

        // Ajouter les périodes d'inscription
        event.inscription_dates.forEach(date => {
            addInscriptionPeriod(date);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Variables pour la gestion des modales et des formulaires
    const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
    const eventForm = document.getElementById('eventForm');
    const inscriptionContainer = document.getElementById('inscription-dates-container');
    const periodTemplate = document.getElementById('inscription-period-template');
    let periodCount = 0;

    // Gestion des périodes d'inscription
    document.getElementById('add-inscription-period').addEventListener('click', function() {
        addInscriptionPeriod();
    });

    // Délégation d'événement pour supprimer une période
    inscriptionContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-period')) {
            e.target.closest('.inscription-period').remove();
        }
    });

    // Fonction pour ouvrir la modale d'événement (ajout ou édition)
    window.openEventModal = function(mode, eventId = null, isQuickAdd = false) {
        // Réinitialiser le formulaire
        eventForm.reset();
        inscriptionContainer.innerHTML = '';
        periodCount = 0;

        if (mode === 'add') {
            document.getElementById('eventModalTitle').textContent = 'Ajouter un événement';
            eventForm.action = '/gestion/events/add';

            // Si c'est un ajout rapide, préremplir les champs
            if (isQuickAdd) {
                document.getElementById('event_libelle').value = document.getElementById('quickAdd_libelle').value;
                document.getElementById('event_lieu').value = document.getElementById('quickAdd_lieu').value;
            }
        } else {
            document.getElementById('eventModalTitle').textContent = 'Modifier l\'événement';
            eventForm.action = '/gestion/events/update/' + eventId;

            // Charger les données de l'événement
            fetchEventData(eventId);
        }

        eventModal.show();
    };

    // Fonction pour ajouter une période d'inscription
    function addInscriptionPeriod(data = null) {
        const template = periodTemplate.content.cloneNode(true);
        const inputs = template.querySelectorAll('input, select');

        // Remplacer l'index par le nombre actuel
        inputs.forEach(input => {
            const name = input.getAttribute('name') || '';
            input.setAttribute('name', name.replace('__INDEX__', periodCount));

            // Remplir les données si disponibles
            if (data) {
                const fieldName = input.getAttribute('data-field');
                if (fieldName && data[fieldName] !== undefined) {
                    input.value = data[fieldName];
                }
            }
        });

        inscriptionContainer.appendChild(template);
        periodCount++;
    }
    window.addInscriptionPeriod = addInscriptionPeriod;

});

document.addEventListener('DOMContentLoaded', function() {
    // Sélectionner tous les éléments cliquables
    const eventRows = document.querySelectorAll('.event-row');

    // Variables pour la détection de double tap sur mobile
    let lastTap = 0;
    const tapDelay = 300; // délai en ms pour considérer deux taps comme un double tap

    eventRows.forEach(row => {
        // Conserver le double-clic pour desktop
        row.addEventListener('dblclick', function() {
            handleRowAction(this);
        });

        // Ajouter la gestion du double tap pour mobile
        row.addEventListener('touchend', function(e) {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTap;

            if (tapLength < tapDelay && tapLength > 0) {
                // Double tap détecté
                e.preventDefault();
                handleRowAction(this);
            }

            lastTap = currentTime;
        });

        // Style pour indiquer que l'élément est cliquable
        row.style.cursor = 'pointer';
    });

    // Fonction commune pour traiter l'action sur la ligne
    function handleRowAction(row) {
        const eventId = row.getAttribute('data-id');
        if (!eventId) return;

        // Ouvrir la modale en mode édition et charger les données
        window.openEventModal('edit', parseInt(eventId));
    }
});

// --- Gestion des séances (sessions) ---
const sessionsContainer = document.getElementById('sessions-container');
const sessionTemplate = document.getElementById('session-template');
let sessionCount = 0;

document.getElementById('add-session-btn').addEventListener('click', function() {
    addSession();
});

sessionsContainer.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-session')) {
        e.target.closest('.session-item').remove();
    }
});

function addSession(data = null) {
    const template = sessionTemplate.content.cloneNode(true);
    const inputs = template.querySelectorAll('input');
    inputs.forEach(input => {
        const name = input.getAttribute('name') || '';
        input.setAttribute('name', name.replace('__INDEX__', sessionCount));
        if (data) {
            const fieldName = input.getAttribute('data-field');
            if (fieldName && data[fieldName] !== undefined) {
                if (fieldName === 'opening_doors_at' || fieldName === 'event_start_at') {
                    input.value = data[fieldName].replace(' ', 'T').slice(0, 16);
                } else {
                    input.value = data[fieldName];
                }
            }
        }
    });
    sessionsContainer.appendChild(template);
    sessionCount++;
}

// --- Préremplissage des séances lors de l'édition ---
window.fetchEventData = async function(eventId) {
    const events = window.eventsArray;
    const event = events.find(e => e.id === eventId);

    if (!event) return;

    // Libellé, lieu, limitation
    const libelleInput = document.getElementById('event_libelle');
    if (libelleInput) libelleInput.value = event.libelle;

    const lieuInput = document.getElementById('event_lieu');
    if (lieuInput) lieuInput.value = event.lieu;

    const limitationInput = document.getElementById('event_limitation_per_swimmer');
    if (limitationInput) limitationInput.value = event.limitation_per_swimmer !== null ? event.limitation_per_swimmer : '';

    // Décoche tous les checkboxes de tarifs
    document.querySelectorAll('input[type="checkbox"][id^="tarif_"]').forEach(cb => cb.checked = false);

    // Coche ceux de l'événement
    if (event.tarifs && Array.isArray(event.tarifs)) {
        event.tarifs.forEach(function(tarifId) {
            var cb = document.getElementById('tarif_' + tarifId);
            if (cb) cb.checked = true;
        });
    }

    // Périodes d'inscription
    const inscriptionContainer = document.getElementById('inscription-dates-container');
    inscriptionContainer.innerHTML = '';
    let periodCount = 0;
    if (event.inscription_dates && Array.isArray(event.inscription_dates)) {
        event.inscription_dates.forEach(function(date) {
            addInscriptionPeriod(date);
            periodCount++;
        });
    }

    // Séances
    const sessionsContainer = document.getElementById('sessions-container');
    sessionsContainer.innerHTML = '';
    let sessionCount = 0;
    if (event.sessions && Array.isArray(event.sessions)) {
        event.sessions.forEach(function(session) {
            addSession(session);
            sessionCount++;
        });
    }
};
