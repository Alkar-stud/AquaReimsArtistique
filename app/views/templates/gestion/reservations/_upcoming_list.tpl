{% if empty($sessionsForSelect) %}
<div class="alert alert-info">Aucun événement à venir.</div>
{% else %}
<div class="mb-3">
    <label for="event-selector-upcoming" class="form-label">Choisir un événement :</label>
    <select id="event-selector-upcoming" class="form-select" data-tab-key="upcoming">
        <option value="">-- Sélectionnez un événement --</option>
        {% foreach $sessionsForSelect as $session %}
        <option value="{{ $session->id }}" {{ $selectedSessionId == $session->id ? 'selected' : '' }}>
            {{ $session->label }}
        </option>
        {% endforeach %}
    </select>
</div>

{% if $selectedSessionId %}
{% if empty($reservations) %}
<div class="alert alert-secondary">Aucune réservation trouvée pour cette session.</div>
{% else %}
<div class="d-flex justify-content-between align-items-center mb-2">
    <p class="text-muted mb-0">{{ $pagination['totalItems'] }} réservation(s) trouvée(s).</p>
    <div class="row gx-2 align-items-center">
        <div class="col-auto">
            <label for="items-per-page" class="col-form-label col-form-label-sm">Par page :</label>
        </div>
        <div class="col-auto">
            <select id="items-per-page" class="form-select form-select-sm" style="width: auto;">
                <option value="5" {{ $itemsPerPage == 5 ? 'selected' : '' }}>5</option>
                <option value="15" {{ $itemsPerPage == 15 ? 'selected' : '' }}>15</option>
                <option value="25" {{ $itemsPerPage == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ $itemsPerPage == 50 ? 'selected' : '' }}>50</option>
            </select>
        </div>
    </div>
</div>
<!-- Vue Desktop -->
<div class="d-none d-md-block">
    <div class="table-responsive">
        <table class="table align-middle table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>Acheteur</th>
                <th>Nageuse</th>
                <th>Nombre de places</th>
                <th>Paiement</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            {% foreach $reservations as $reservation %}
            <tr>
                <td>ARA-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $reservation->getPrenom() }} {{ $reservation->getNom() }}</td>
                <td>
                    {% if $reservation->getNageuse() %}
                    {{ $reservation->getNageuse()->getName() }}
                    {% else %}
                    N/A
                    {% endif %}
                </td>
                <td>{{ count($reservation->getDetails()) }}</td>
                <td>
                    {% if $reservation->getTotalAmountPaid() >= $reservation->getTotalAmount() %}
                    <span class="badge bg-success">Payé</span>
                    {% elseif $reservation->getTotalAmountPaid() == 0 && $reservation->getTotalAmount() > 0 %}
                    <span class="badge bg-danger">À payer</span>
                    {% else %}
                    <span class="badge bg-warning">À compléter</span>
                    {% endif %}
                </td>
                <td>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reservationDetailModal" data-reservation-id="{{ $reservation->getId() }}" data-context="upcoming">
                        Détails
                    </button>
                </td>
            </tr>
            {% endforeach %}
            </tbody>
        </table>
    </div>
</div>

<!-- Vue Mobile -->
<div class="d-block d-md-none">
    {% foreach $reservations as $reservation %}
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Réservation RES-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</h5>
            <p class="card-text mb-1"><strong>Acheteur :</strong> {{ $reservation->getPrenom() }} {{ $reservation->getNom() }}</p>
            <p class="card-text mb-1"><strong>Nageuse :</strong>
                {% if $reservation->getNageuse() %}
                {{ $reservation->getNageuse()->getName() }}
                {% else %}
                N/A
                {% endif %}
            </p>
            <p class="card-text mb-1"><strong>Nombre de places :</strong> {{ count($reservation->getDetails()) }}</p>
            <p class="card-text mb-2"><strong>Paiement :</strong>
                {% if $reservation->getTotalAmountPaid() >= $reservation->getTotalAmount() %}
                <span class="badge bg-success">Payé</span>
                {% elseif $reservation->getTotalAmountPaid() == 0 && $reservation->getTotalAmount() > 0 %}
                <span class="badge bg-danger">À payer</span>
                {% else %}
                <span class="badge bg-warning">À compléter</span>
                {% endif %}
            </p>
            <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#reservationDetailModal" data-reservation-id="{{ $reservation->getId() }}" data-context="upcoming">
                Consulter / Modifier
            </button>
        </div>
    </div>
    {% endforeach %}
</div>

{% if $pagination && $pagination['totalPages'] > 1 %}
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item {{ $pagination['currentPage'] <= 1 ? 'disabled' : '' }}">
            <a class="page-link" href="#" data-page="{{ $pagination['currentPage'] - 1 }}">&laquo;</a>
        </li>
        {% foreach range(1, $pagination['totalPages']) as $i %}
        <li class="page-item {{ $i == $pagination['currentPage'] ? 'active' : '' }}">
            <a class="page-link" href="#" data-page="{{ $i }}">{{ $i }}</a>
        </li>
        {% endforeach %}
        <li class="page-item {{ $pagination['currentPage'] >= $pagination['totalPages'] ? 'disabled' : '' }}">
            <a class="page-link" href="#" data-page="{{ $pagination['currentPage'] + 1 }}">&raquo;</a>
        </li>
    </ul>
</nav>
{% endif %}
{% endif %}
{% endif %}
{% endif %}