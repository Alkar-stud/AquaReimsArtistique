{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Gestion de la page d'accueil</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-2"></i>Ajouter un contenu
        </button>
    </div>

    <div class="btn-group mb-4" role="group">
        <a href="/gestion/accueil" class="btn {{ ($searchParam ?? 'displayed') == 'displayed' ? 'btn-primary' : 'btn-outline-primary' }}">
            Contenus à venir
        </a>
        <a href="/gestion/accueil/list/0" class="btn {{ ($searchParam ?? '') == '0' ? 'btn-primary' : 'btn-outline-primary' }}">
            Tous les contenus (y compris passés)
        </a>
    </div>

    <div class="responsive-table">
        <table class="table table-bordered align-middle table-responsive">
            <thead class="table-light">
            <tr>
                <th scope="col">Gala associé</th>
                <th scope="col">Fin d'affichage</th>
                <th scope="col" class="text-center" style="width: 100px;">Affiché ?</th>
                <th scope="col" style="width: 130px;">Actions</th>
            </tr>
            </thead>
            <tbody>
            {% if empty($accueil) %}
            <tr>
                <td colspan="4" class="text-center">Aucun contenu à afficher.</td>
            </tr>
            {% else %}
            {% foreach $accueil as $item %}
            <tr>
                <td data-label="Gala associé">
                    {{ $item->getEventObject() ? $item->getEventObject()->getLibelle() : 'Aucun gala associé' }}
                </td>
                <td data-label="Fin d'affichage">{{ $item->getDisplayUntil()->format('d/m/Y H:i') }}</td>
                <td data-label="Affiché ?">
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input status-toggle"
                               type="checkbox"
                               role="switch"
                               data-id="{{ $item->getId() }}"
                               id="status-switch-{{ $item->getId() }}"
                               {{ $item->isDisplayed() ? 'checked' : '' }}>
                    </div>
                </td>
                <td>
                    <button type="button"
                            class="btn btn-primary btn-sm w-100"
                            data-bs-toggle="modal"
                            data-bs-target="#editModal-{{ $item->getId() }}">
                        <i class="bi bi-pencil-square me-1"></i> Modifier
                    </button>
                </td>
            </tr>
            {% endforeach %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>

<!-- Modale d'Ajout -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form method="POST" action="/gestion/accueil/add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">Ajouter un nouveau contenu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="add_event" class="form-label">Gala associé</label>
                                <select name="event" id="add_event" class="form-select" required>
                                    <option value="0">Aucun gala associé</option>
                                    {% foreach $events as $event %}
                                    <option value="{{ $event->getId() }}" data-event-id="{{ $event->getId() }}">
                                        {{ $event->getLibelle() }}
                                    </option>
                                    {% endforeach %}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="add_display_until" class="form-label">Afficher jusqu'au</label>
                                <input type="datetime-local" id="add_display_until" name="display_until" class="form-control" required>
                            </div>

                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="add_is_displayed" name="is_displayed" value="1" checked>
                                    <label class="form-check-label" for="add_is_displayed">Afficher ce contenu sur la page d'accueil</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add_content" class="form-label">Contenu</label>
                        <textarea name="content" id="add_content" class="form-control ckeditor-textarea" rows="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modales d'Édition -->
{% foreach $accueil as $item %}
<div class="modal fade" id="editModal-{{ $item->getId() }}" tabindex="-1" aria-labelledby="editModalLabel-{{ $item->getId() }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form method="POST" action="/gestion/accueil/edit">
                <input type="hidden" name="id" value="{{ $item->getId() }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel-{{ $item->getId() }}">Modifier le contenu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="event-{{ $item->getId() }}" class="form-label">Gala associé</label>
                            <select name="event" id="event-{{ $item->getId() }}" class="form-select" required>
                                <option value="0">Aucun gala associé</option>
                                {% foreach $events as $event %}
                                <option value="{{ $event->getId() }}" {{ $item->getEvent() == $event->getId() ? 'selected' : '' }}>
                                    {{ $event->getLibelle() }}
                                </option>
                                {% endforeach %}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="display_until-{{ $item->getId() }}" class="form-label">Afficher jusqu'au</label>
                            <input type="datetime-local" id="display_until-{{ $item->getId() }}" name="display_until" class="form-control" value="{{ $item->getDisplayUntil()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_displayed-{{ $item->getId() }}" name="is_displayed" value="1" {{ $item->isDisplayed() ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_displayed-{{ $item->getId() }}">Afficher ce contenu sur le page d'accueil</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="content-{{ $item->getId() }}" class="form-label">Contenu</label>
                        <textarea name="content" id="content-{{ $item->getId() }}" class="form-control ckeditor-textarea" rows="10">{{ $item->getContent() ?? '' }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    <a href="/gestion/accueil/delete/{{ $item->getId() }}" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce contenu ?');">Supprimer</a>
                </div>
            </form>
        </div>
    </div>
</div>
{% endforeach %}

<script>
    window.eventSessions = {{ json_encode($eventSessions ?? []) }};
    window.delaiToDisplay = {{ $delaiToDisplay }};
</script>
<script src="/assets/js/gestion/accueil.js" defer></script>


<script>
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

        const eventSessions = {{ json_encode($eventSessions ?? []) }};
    const delaiToDisplay = {{ $delaiToDisplay }};

    function calculateDisplayDate(sessionDate) {
        const lastSessionDate = new Date(sessionDate);
        lastSessionDate.setDate(lastSessionDate.getDate() + delaiToDisplay);
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
            if (eventSessions[eventId]) {
                addDisplayUntilInput.value = calculateDisplayDate(eventSessions[eventId]);
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
            if (displayUntilInput && eventSessions[eventId]) {
                displayUntilInput.value = calculateDisplayDate(eventSessions[eventId]);
            }
        });
    });
    });
</script>
