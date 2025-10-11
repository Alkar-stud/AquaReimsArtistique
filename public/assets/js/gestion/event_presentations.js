
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const el = this;
            const itemId = Number(this.dataset.id);
            const newStatus = Boolean(this.checked);
            el.disabled = true;

            window.apiPost('/gestion/accueil/toggle-status', { id: itemId, status: newStatus }, {
                headers: { 'X-CSRF-Context': '/gestion/accueil' }
            })
                .then(data => {
                    // Si le serveur renvoie un nouveau token dans le body, on met la meta à jour
                    if (data && data.csrfToken) {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) meta.content = String(data.csrfToken);
                    }
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    el.disabled = false;
                    alert('Une erreur de communication est survenue. Veuillez réessayer.');
                });
        });
    });

    function formatToDateTimeLocal(dateString) {
        if (!dateString) return '';
        // La date est au format 'YYYY-MM-DD HH:MM:SS'
        // On la transforme en 'YYYY-MM-DDTHH:MM' pour l'input datetime-local
        return dateString.substring(0, 16).replace(' ', 'T');
    }

    const addEventSelect = document.getElementById('add_event');
    const addDisplayUntilInput = document.getElementById('add_display_until');

    if (addEventSelect && addDisplayUntilInput) {
        addEventSelect.addEventListener('change', function() {
            const eventId = this.value;
            if (eventId === 0) {
                return;
            }
            if (window.eventSessions && window.eventSessions[eventId]) {
                addDisplayUntilInput.value = formatToDateTimeLocal(window.eventSessions[eventId]);
            }
        });
    }

    document.querySelectorAll('[id^="event-"]').forEach(select => {
        select.addEventListener('change', function() {
            const eventId = this.value;
            if (eventId === 0) {
                return;
            }
            const itemId = this.id.split('-')[1];
            const displayUntilInput = document.getElementById(`display_until-${itemId}`);
            if (displayUntilInput && window.eventSessions && window.eventSessions[eventId]) {
                displayUntilInput.value = formatToDateTimeLocal(window.eventSessions[eventId]);
            }
        });
    });
});
