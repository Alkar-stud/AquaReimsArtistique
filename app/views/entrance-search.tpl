<div class="container-fluid">
    <div class="mb-3 sticky-reservation-header shadow-sm px-2 py-2">
        <h5 class="mb-2">Rechercher une réservation</h5>

        <form method="GET" action="/entrance/search" class="mb-4">
            <div class="input-group">
                <input type="text"
                       name="q"
                       id="search-input"
                       class="form-control"
                       placeholder="Nom ou numéro de réservation..."
                       value="{{ $searchQuery }}"
                       autofocus>
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </div>
        </form>
    </div>
    <div>
        Séances aujourd'hui : <br>
        {% foreach $todaySessions as $session %}
            {{ $session['name'] }} : {{ $session['entered'] }} personnes entrée(s) / reste(s) {{ $session['total'] - $session['entered'] }}.

        <br>
        {% endforeach %}
    </div>

    {% if $searchQuery != '' %}
    {% if count($reservations) == 0 %}
    <div class="alert alert-warning">
        Aucune réservation trouvée pour "<strong>{{ $searchQuery }}</strong>"
    </div>
    {% else %}
    <div class="alert alert-info">
        {{ count($reservations) }} réservation(s) trouvée(s)
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>N°</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Session</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            {% foreach $reservations as $reservation %}
            <tr class="cursor-pointer"
                onclick="window.location.href='/entrance?token={{ $reservation->getToken() }}'">
                <td>{{ $reservation->getId() }}</td>
                <td>{{ $reservation->getName() }}</td>
                <td>{{ $reservation->getFirstName() }}</td>
                <td>{{ $reservation->getEventSessionObject()->getSessionName() }}</td>
                <td>
                    <a href="/entrance?token={{ $reservation->getToken() }}"
                       class="btn btn-sm btn-primary">
                        Accéder <i class="fas fa-arrow-right"></i>
                    </a>
                </td>
            </tr>
            {% endforeach %}
            </tbody>
        </table>
    </div>
    {% endif %}
    {% endif %}
</div>