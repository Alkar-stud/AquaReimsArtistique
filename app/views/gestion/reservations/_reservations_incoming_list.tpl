<?php /** @var \app\Models\Reservation\ReservationTemp[] $reservations */ ?>

<form id="export-form">
    <div class="row g-2 align-items-end mb-3">
        <div class="col-md-5">
            <label for="search-input" class="form-label">Rechercher une réservation :</label>
            <div class="input-group">
                <input type="text" id="search-input" class="form-control" placeholder="Nom, email, numéro..." value="{{ $searchQuery }}">
                <button type="button" class="btn btn-secondary" id="search-button">Chercher</button>
            </div>
        </div>
        <div class="col-md-7">
            <label for="event-selector-{{ !$tab ? 'upcoming' : 'past' }}" class="form-label">Filtrer par événement :</label>
            <select id="event-selector-{{ !$tab ? 'upcoming' : 'past' }}" class="form-select" data-tab-key="{{ !$tab ? 'upcoming' : 'past' }}">
                <option value="">-- Sélectionnez un événement --</option>
                {% if !empty($events) %}
                {% foreach $events as $event %}
                {% foreach $event->getSessions() as $session %}
                <option value="{{ $session->getId() }}"  {{ $selectedSessionId == $session->getId() ? 'selected' : '' }}>
                    {{ $event->getName() }} - {{ $session->getSessionName() }} ({{ $session->getEventStartAt()->format('d/m/Y H:i') }})
                </option>
                {% endforeach %}
                {% endforeach %}
                {% endif %}
            </select>
        </div>
    </div>
</form>

<table class="table table-striped table-hover">
    <thead>
    <tr>
        <th>ID</th>
        <th>Nom</th>
        <th>Prénom</th>
        <th>Email</th>
        <th>Verrouillée</th>
        <th>Encore</th>
        <th>Nombre de places</th>
        <th>Actions</th>
    </tr>
    </thead>

    <tbody>
    {% if empty($reservations) %}
    <tr>
        <td colspan="9" class="text-center">
            Aucune réservation en cours.
        </td>
    </tr>
    {% else %}
    {% foreach $reservations as $reservation %}
    <tr data-id="{{ $reservation->getId() }}">
        <td>TEMP-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</td>

        <td>{{ $reservation->getName() }}</td>
        <td>{{ $reservation->getFirstName() }}</td>
        <td>{{ $reservation->getEmail() }}</td>

        <td>
            {% if $reservation->isLocked() %}
            <span class="badge bg-warning">Verrouillée</span>
            {% else %}
            <span class="badge bg-success">Ouverte</span>
            {% endif %}
        </td>
        <td>
            <span class="countdown"
                  data-created-at-timestamp="{{ $reservation->getCreatedAt()->getTimestamp() }}"
                  data-timeout-seconds="{{ $timeout_session_reservation }}"></span>
        </td>


        <td>
            {{ count($reservation->getDetails()) }}
        </td>

        <td>
            <!-- Boutons d'action adaptés aux réservations temporaires -->
            <button type="button" class="btn btn-sm btn-primary js-open-reservation-temp"
                    data-id="{{ $reservation->getId() }}"
                    data-bs-toggle="modal" data-bs-target="#reservation-incoming-modal">
                Voir
            </button>
            <button class="btn btn-sm btn-danger js-delete-reservation-temp"
                    data-id="{{ $reservation->getId() }}">
                Supprimer
            </button>
        </td>
    </tr>
    {% endforeach %}
    {% endif %}
    </tbody>
</table>


<!-- Modal Détails Réservation -->
{% if !empty($reservations) %}{% include '/gestion/reservations/_modal_incoming.tpl' with {'emailsTemplatesToSendManually' => $emailsTemplatesToSendManually} %}{% endif %}
