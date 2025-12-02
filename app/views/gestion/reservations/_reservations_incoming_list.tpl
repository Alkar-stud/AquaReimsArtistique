<?php /** @var \app\Models\Reservation\ReservationTemp[] $reservations */ ?>

<table class="table table-striped table-hover">
    <thead>
    <tr>
        <th>ID</th>
        <th>Session</th>
        <th>Nom</th>
        <th>Prénom</th>
        <th>Email</th>
        <th>Téléphone</th>
        <th>Verrouillée</th>
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
        <td>{{ $reservation->getId() }}</td>

        <td>
            {{ $reservation->getEventSession() }}
        </td>

        <td>{{ $reservation->getName() }}</td>
        <td>{{ $reservation->getFirstName() }}</td>
        <td>{{ $reservation->getEmail() }}</td>
        <td>{{ $reservation->getPhone() }}</td>

        <td>
            {% if $reservation->isLocked() %}
            <span class="badge bg-warning">Verrouillée</span>
            {% else %}
            <span class="badge bg-success">Ouverte</span>
            {% endif %}
        </td>

        <td>
            {{ count($reservation->getDetails()) }}
        </td>

        <td>
            <!-- Boutons d\'action adaptés aux réservations temporaires -->
            <button class="btn btn-sm btn-primary js-open-reservation-temp"
                    data-id="{{ $reservation->getId() }}">
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
