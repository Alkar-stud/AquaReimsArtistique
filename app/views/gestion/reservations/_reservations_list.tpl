<form method="POST" id="export-form" action="/gestion/reservations/search">
    <div class="row g-2 align-items-end mb-3">
        <div class="col-md-5">
            <label for="search-input" class="form-label">Rechercher une réservation :</label>
            <div class="input-group">
                <input type="text" id="search-input" class="form-control" placeholder="Nom, email, numéro (sans le ARA-)...">
                <button type="submit" class="btn btn-secondary" id="search-button" disabled>Chercher</button>
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
                    {{ $event->getName() }} ({{ $session->getEventStartAt()->format('d/m/Y H:i') }})
                </option>
                {% endforeach %}
                {% endforeach %}
                {% endif %}
            </select>
        </div>
    </div>
</form>

{% if $selectedSessionId > 0 %}

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="btn-group" role="group" aria-label="Filtres supplémentaires">
        {% php %}
        // On prépare les paramètres de base pour les liens
        $baseParams = http_build_query(['tab' => $tab, 's' => $selectedSessionId, 'per_page' => $itemsPerPage]);
        {% endphp %}
        <a href="?{{ $baseParams }}&cancel={{ $isCancel ? 0 : 1 }}{{ !is_null($isChecked) ? '&check=' . (int)$isChecked : '' }}"
           class="btn btn-sm {{ $isCancel ? 'btn-danger' : 'btn-outline-danger' }}">
            {{ $isCancel ? 'Masquer annulées' : 'Afficher que annulées' }}
        </a>
        <a href="?{{ $baseParams }}&check={{ $isChecked ? 0 : 1 }}{{ !is_null($isCancel) ? '&cancel=' . (int)$isCancel : '' }}"
           class="btn btn-sm {{ $isChecked ? 'btn-info' : 'btn-outline-info' }}">
            {{ $isChecked ? 'Masquer vérifiées' : 'Afficher que vérifiées' }}
        </a>
        <a href="?tab=extract&s={{ $selectedSessionId }}"
           class="btn btn-sm btn-outline-secondary">
            Exporter les données
        </a>
    </div>

    <div class="d-flex align-items-center">
        <label for="items-per-page-selector" class="form-label me-2 mb-0">Afficher :</label>
        <select id="items-per-page-selector" class="form-select form-select-sm" style="width: auto;">
            <option value="10" {{ $itemsPerPage == 10 ? 'selected' : '' }}>10</option>
            <option value="20" {{ $itemsPerPage == 20 ? 'selected' : '' }}>20</option>
            <option value="50" {{ $itemsPerPage == 50 ? 'selected' : '' }}>50</option>
            <option value="100" {{ $itemsPerPage == 100 ? 'selected' : '' }}>100</option>
        </select>
    </div>
</div>
{% if empty($reservations) %}
<div class="alert alert-info mt-3">Aucune réservation trouvée pour cet événement avec ces paramètres.</div>
{% else %}


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
            <tr class="{{ $reservation->isChecked() ? 'table-light' : '' }}">
                <td>ARA-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $reservation->getFirstName() }} {{ $reservation->getName() }}</td>
                <td>
                    {% if $reservation->getSwimmer() %}
                    {{ $reservation->getSwimmer()->getName() }}
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reservationDetailModal" data-reservation-id="{{ $reservation->getId() }}" data-context="upcoming">
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
        <div class="card mb-3 {{ $reservation->isChecked() ? 'bg-light' : '' }}">
            <h5 class="card-title">Réservation ARA-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</h5>
            <p class="card-text mb-1"><strong>Acheteur :</strong> {{ $reservation->getFirstName() }} {{ $reservation->getName() }}</p>
            <p class="card-text mb-1"><strong>Nageuse :</strong>
                {% if $reservation->getSwimmer() %}
                {{ $reservation->getSwimmer()->getName() }}
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
            <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#reservationDetailModal" data-reservation-id="{{ $reservation->getId() }}" data-context="upcoming">
                Consulter / Modifier
            </button>
        </div>
    </div>
    {% endforeach %}
</div>


<!-- Modal Détails Réservation -->
{% include '/gestion/reservations/_modal.tpl' %}


{% if $totalPages > 1 %}
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
            <a class="page-link" href="?tab={{ $tab }}&s={{ $selectedSessionId }}&page={{ $currentPage - 1 }}&per_page={{ $itemsPerPage }}">Précédent</a>
        </li>
        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
            <a class="page-link" href="?tab={{ $tab }}&s={{ $selectedSessionId }}&page={{ $currentPage + 1 }}&per_page={{ $itemsPerPage }}">Suivant</a>
        </li>
    </ul>
</nav>
{% endif %}
{% endif %}
{% endif %}