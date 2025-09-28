
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const itemId = this.dataset.id;
            const newStatus = this.checked;
            this.disabled = true;

            // Récupérer le token CSRF depuis le premier formulaire de la page (ou un endroit plus spécifique si besoin)
            const form = document.querySelector('form');
            const csrfToken = form ? form.querySelector('[name="csrf_token"]').value : '';

            fetch('/gestion/accueil/toggle-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ id: itemId, status: newStatus })
            })
                .then(response => {
                    // On vérifie si la réponse est bien du JSON.
                    const contentType = response.headers.get('content-type');
                    if (response.ok && contentType && contentType.includes('application/json')) {
                        // Si tout va bien, on parse le JSON.
                        return response.json();
                    }

                    // Si la réponse n'est pas du JSON (ex : une page d'erreur HTML),
                    // on lit la réponse comme du texte pour l'afficher en console.
                    return response.text().then(text => {
                        console.error("--- ERREUR CÔTÉ SERVEUR (Réponse non-JSON) ---");
                        console.error("URL de la requête :", response.url);
                        console.error("Statut de la réponse :", response.status);
                        console.error("Contenu de la réponse (HTML/Texte) :", text);

                        // On rejette la promesse avec un message clair.
                        throw new Error(`Le serveur a renvoyé une réponse non-JSON (statut: ${response.status}). Consultez la console pour voir le détail de l'erreur HTML.`);
                    });
                })
                .then(data => {
                    // Si le serveur renvoie un nouveau token, on met à jour tous les formulaires de la page.
                    if (data.csrfToken) {
                        document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                            input.value = data.csrfToken;
                        });
                    }
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    this.disabled = false;
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
            if (eventId == 0) {
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
            if (eventId == 0) {
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
