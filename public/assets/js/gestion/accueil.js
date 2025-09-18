document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const itemId = this.dataset.id;
            const newStatus = this.checked;
            this.disabled = true;
            fetch('/gestion/accueil/toggle-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id: itemId, status: newStatus })
            })
                .then(response => {
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    this.disabled = false;
                    alert('Une erreur de communication est survenue. Veuillez réessayer.');
                });
        });
    });

    function calculateDisplayDate(sessionDate) {
        const lastSessionDate = new Date(sessionDate);
        lastSessionDate.setDate(lastSessionDate.getDate() + window.delaiToDisplay);
        const year = lastSessionDate.getFullYear();
        const month = String(lastSessionDate.getMonth() + 1).padStart(2, '0');
        const day = String(lastSessionDate.getDate()).padStart(2, '0');
        const hours = String(lastSessionDate.getHours()).padStart(2, '0');
        const minutes = String(lastSessionDate.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    const addEventSelect = document.getElementById('add_event');
    const addDisplayUntilInput = document.getElementById('add_display_until');

    if (addEventSelect && addDisplayUntilInput) {
        addEventSelect.addEventListener('change', function() {
            const eventId = this.value;
            if (eventId == 0) {
                return;
            }
            if (window.eventSessions && window.eventSessions[eventId]) {
                addDisplayUntilInput.value = calculateDisplayDate(window.eventSessions[eventId]);
            }
        });
    }

    document.querySelectorAll('[id^="event-"]').forEach(select => {
        select.addEventListener('change', function() {
            const eventId = this.value;
            if (eventId == 0) {
                return;
            }
            const itemId = this.id.split('-')[1];
            const displayUntilInput = document.getElementById(`display_until-${itemId}`);
            if (displayUntilInput && window.eventSessions && window.eventSessions[eventId]) {
                displayUntilInput.value = calculateDisplayDate(window.eventSessions[eventId]);
            }
        });
    });
});
