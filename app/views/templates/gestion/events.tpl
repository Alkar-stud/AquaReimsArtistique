{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2 class="mb-4">Gestion des événements</h2>

    <!-- Mobile -->
    <div class="d-md-none mb-4">
        <button type="button" class="btn btn-success btn-sm w-100 mb-3" onclick="openEventModal('add')">
            Ajouter un événement
        </button>

        {% if !empty($events) %}
        <ul class="list-group mb-3">
            {% foreach $events as $event %}
            <li class="list-group-item event-row" data-id="{{ $event->getId() }}">
                <div class="d-flex justify-content-between align-items-center">
                    <h5>{{ $event->getLibelle() }}</h5>
                    <div>
                        <button class="btn btn-secondary btn-sm" onclick="openEventModal('edit', {{ $event->getId() }})">
                            Modifier
                        </button>
                        <a href="/gestion/events/delete/{{ $event->getId() }}" class="btn btn-danger btn-sm"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                            Supprimer
                        </a>
                    </div>
                </div>
                <div>
                    <p>
                        <strong>Lieu:</strong> {{ $event->getPiscine() ? $event->getPiscine()->getLibelle() : '?' }}<br>
                        <strong>Date:</strong>
                        {{ (function() use ($event) {
                        $sessions = $event->getSessions();
                        if (!empty($sessions)) {
                        usort($sessions, fn($a,$b)=> $a->getEventStartAt() <=> $b->getEventStartAt());
                        return $sessions[0]->getEventStartAt()->format('d/m/Y H:i');
                        }
                        return 'Non défini';
                        })() }}
                        <br>
                        <strong>1ère ouverture inscriptions:</strong>
                        {{ (function() use ($event) {
                        $nextDate = null;
                        foreach ($event->getInscriptionDates() as $d) {
                        if (!$nextDate || $d->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                        $nextDate = $d;
                        }
                        }
                        return $nextDate ? $nextDate->getStartRegistrationAt()->format('d/m/Y H:i') : 'Aucune ouverture prévue';
                        })() }}
                        <br>
                        <strong>Nombre de tarifs</strong> {{ count($event->getTarifs()) }}
                    </p>
                </div>
            </li>
            {% endforeach %}
        </ul>
        {% else %}
        <p class="text-center">Aucun événement enregistré</p>
        {% endif %}
    </div>

    <!-- Desktop -->
    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle text-center">
            <thead>
            <tr>
                <th>Libellé</th>
                <th>Lieu</th>
                <th>1ère date de l'événement</th>
                <th>1ère ouverture inscriptions</th>
                <th>Nombre de tarifs</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <form id="quickAddForm" class="d-flex align-items-center">
                    <td><input type="text" class="form-control" id="quickAdd_libelle" placeholder="Libellé" required></td>
                    <td>
                        <select class="form-select" id="quickAdd_lieu" required>
                            <option value="">Sélectionner</option>
                            {% foreach $piscines as $piscine %}
                            <option value="{{ $piscine->getId() }}">{{ $piscine->getLibelle() }}</option>
                            {% endforeach %}
                        </select>
                    </td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td colspan="2" class="text-center">
                        <button type="button" class="btn btn-success" onclick="openEventModal('add', null, true)">
                            Continuer l'ajout
                        </button>
                    </td>
                </form>
            </tr>

            {% if !empty($events) %}
            {% foreach $events as $event %}
            <tr class="event-row" data-id="{{ $event->getId() }}">
                <td>{{ $event->getLibelle() }}</td>
                <td>{{ $event->getPiscine() ? $event->getPiscine()->getLibelle() : '?' }}</td>
                <td>
                    {{ (function() use ($event) {
                    $sessions = $event->getSessions();
                    if (!empty($sessions)) {
                    usort($sessions, fn($a,$b)=> $a->getEventStartAt() <=> $b->getEventStartAt());
                    return $sessions[0]->getEventStartAt()->format('d/m/Y H:i');
                    }
                    return 'Non défini';
                    })() }}
                </td>
                <td>
                    {{ (function() use ($event) {
                    $nextDate = null;
                    foreach ($event->getInscriptionDates() as $d) {
                    if (!$nextDate || $d->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                    $nextDate = $d;
                    }
                    }
                    return $nextDate ? $nextDate->getStartRegistrationAt()->format('d/m/Y H:i') : 'Aucune ouverture prévue';
                    })() }}
                </td>
                <td>{{ count($event->getTarifs()) }}</td>
                <td>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEventModal('edit', {{ $event->getId() }})">
                        Modifier
                    </button>
                    <a href="/gestion/events/delete/{{ $event->getId() }}" class="btn btn-danger btn-sm"
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                        Supprimer
                    </a>
                </td>
            </tr>
            {% endforeach %}
            {% else %}
            <tr>
                <td colspan="7" class="text-center">Aucun événement enregistré</td>
            </tr>
            {% endif %}
            </tbody>
        </table>
    </div>

    <!-- Modale -->
    <div id="eventModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Ajouter un événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form id="eventForm" method="POST">
                    <div class="modal-body">
                        <ul class="nav nav-tabs mb-3">
                            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#eventDetails">Informations générales</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#eventTarifs">Tarifs</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#eventInscriptions">Périodes d'inscription</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#eventSessions">Séances</a></li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="eventDetails">
                                <div class="mb-3">
                                    <label class="form-label">Libellé</label>
                                    <input type="text" name="libelle" id="event_libelle" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Lieu</label>
                                    <select name="lieu" id="event_lieu" class="form-select" required>
                                        <option value="">Sélectionner</option>
                                        {% foreach $piscines as $piscine %}
                                        <option value="{{ $piscine->getId() }}">{{ $piscine->getLibelle() }}</option>
                                        {% endforeach %}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="event_limitation_per_swimmer">Limitation par nageur</label>
                                    <input type="number" class="form-control" id="event_limitation_per_swimmer" name="limitation_per_swimmer" min="0" placeholder="Laissez vide pour aucune limitation">
                                    <div class="form-text">0 ou vide = aucune limitation</div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="eventTarifs">
                                <div class="mb-3">
                                    <p class="text-muted">Sélectionnez les tarifs applicables</p>
                                    <h6 class="mb-3">Tarifs avec places</h6>
                                    <div class="row row-cols-1 row-cols-md-2 g-3" id="tarifs-avec-places">
                                        {% foreach $tarifs as $tarif %}
                                        {% if $tarif->getId() == -1 %}{% continue %}{% endif %}
                                        {% if $tarif->getNbPlace() %}
                                        <div class="col">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="tarifs[]" value="{{ $tarif->getId() }}" id="tarif_{{ $tarif->getId() }}">
                                                        <label class="form-check-label" for="tarif_{{ $tarif->getId() }}">
                                                            <strong>{{ $tarif->getLibelle() }}</strong>
                                                            <span class="float-end">{{ number_format($tarif->getPrice()/100, 2, ',', ' ') }} €</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {% endif %}
                                        {% endforeach %}
                                    </div>

                                    <hr class="mt-4 mb-2">
                                    <h6 class="mb-3">Tarifs sans places</h6>
                                    <div class="row row-cols-1 row-cols-md-2 g-3" id="tarifs-sans-places">
                                        {% foreach $tarifs as $tarif %}
                                        {% if $tarif->getId() == -1 %}{% continue %}{% endif %}
                                        {% if !$tarif->getNbPlace() %}
                                        <div class="col">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="tarifs[]" value="{{ $tarif->getId() }}" id="tarif_{{ $tarif->getId() }}">
                                                        <label class="form-check-label" for="tarif_{{ $tarif->getId() }}">
                                                            <strong>{{ $tarif->getLibelle() }}</strong>
                                                            <span class="float-end">{{ number_format($tarif->getPrice()/100, 2, ',', ' ') }} €</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {% endif %}
                                        {% endforeach %}
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="eventInscriptions">
                                <div id="inscription-dates-container"></div>
                                <button type="button" class="btn btn-outline-primary mt-3" id="add-inscription-period">
                                    <i class="bi bi-plus-circle"></i> Ajouter une période d'inscription
                                </button>
                                <template id="inscription-period-template">
                                    <div class="card mb-3 inscription-period">
                                        <div class="card-body">
                                            <input type="hidden" name="inscription_dates[__INDEX__][id]" data-field="id">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="card-title mb-0">Période d'inscription</h6>
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-period">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Libellé</label>
                                                <input type="text" class="form-control" name="inscription_dates[__INDEX__][libelle]" data-field="libelle" required>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Date d'ouverture</label>
                                                    <input type="datetime-local" class="form-control" name="inscription_dates[__INDEX__][start_at]" data-field="start_at" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Date de clôture</label>
                                                    <input type="datetime-local" class="form-control" name="inscription_dates[__INDEX__][close_at]" data-field="close_at" required>
                                                </div>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Code d'accès (optionnel)</label>
                                                <input type="text" class="form-control" name="inscription_dates[__INDEX__][access_code]" data-field="access_code">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="tab-pane fade" id="eventSessions">
                                <div id="sessions-container"></div>
                                <button type="button" class="btn btn-outline-primary mt-3" id="add-session-btn">
                                    <i class="bi bi-plus-circle"></i> Ajouter une séance
                                </button>
                                <template id="session-template">
                                    <div class="card mb-3 session-item">
                                        <div class="card-body">
                                            <input type="hidden" name="sessions[__INDEX__][id]" data-field="id">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="card-title mb-0">Séance</h6>
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-session">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Libellé de la séance</label>
                                                <input type="text" class="form-control" name="sessions[__INDEX__][session_name]" data-field="session_name" required>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Début de la séance</label>
                                                    <input type="datetime-local" class="form-control" name="sessions[__INDEX__][event_start_at]" data-field="event_start_at" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Ouverture des portes</label>
                                                    <input type="datetime-local" class="form-control" name="sessions[__INDEX__][opening_doors_at]" data-field="opening_doors_at" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/gestion/events.js"></script>
