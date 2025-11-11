export function initEventPresentations() {
    const apiPost = (window.apiPost
        ? window.apiPost.bind(window)
        : async (url, body, options = {}) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                },
                body: JSON.stringify(body)
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json().catch(() => ({}));
        });

    const formatToDateTimeLocal = (dateString) => {
        if (!dateString) return '';
        return dateString.substring(0, 16).replace(' ', 'T');
    };

    // Switch "Affiché ?"
    document.querySelectorAll('.status-toggle').forEach((toggle) => {
        if (toggle.dataset.epInit === '1') return;
        toggle.dataset.epInit = '1';

        toggle.addEventListener('change', async function () {
            const el = this;
            const itemId = Number(this.dataset.id);
            const newStatus = Boolean(this.checked);
            el.disabled = true;

            try {
                const data = await apiPost('/gestion/accueil/toggle-status', { id: itemId, status: newStatus });

            } catch (error) {
                console.error('Erreur:', error);
                el.disabled = false;
                alert('Une erreur de communication est survenue. Veuillez réessayer.');
            }
        });
    });

    // Ajout: synchro "Gala associé" -> "Afficher jusqu'au"
    const addEventSelect = document.getElementById('add_event');
    const addDisplayUntilInput = document.getElementById('add_display_until');
    if (addEventSelect && addDisplayUntilInput && addEventSelect.dataset.epInit !== '1') {
        addEventSelect.dataset.epInit = '1';
        addEventSelect.addEventListener('change', function () {
            const eventId = this.value; // string
            if (eventId === '0') return;
            if (window.eventSessions && window.eventSessions[eventId]) {
                addDisplayUntilInput.value = formatToDateTimeLocal(window.eventSessions[eventId]);
            }
        });
    }

    // Edition: synchro pour chaque ligne
    document.querySelectorAll('[id^="event-"]').forEach((select) => {
        if (select.dataset.epInit === '1') return;
        select.dataset.epInit = '1';

        select.addEventListener('change', function () {
            const eventId = this.value; // string
            if (eventId === '0') return;
            const itemId = this.id.split('-')[1];
            const displayUntilInput = document.getElementById(`display_until-${itemId}`);
            if (displayUntilInput && window.eventSessions && window.eventSessions[eventId]) {
                displayUntilInput.value = formatToDateTimeLocal(window.eventSessions[eventId]);
            }
        });
    });
}