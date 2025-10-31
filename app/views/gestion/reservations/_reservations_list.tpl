<form method="POST" id="export-form" action="/gestion/reservations/search">
    <div class="row g-2 align-items-end mb-3">
        <div class="col-md-5">
            <label for="search-input" class="form-label">Rechercher une réservation :</label>
            <div class="input-group">
                <input type="text" id="search-input" class="form-control" placeholder="Nom, email, numéro (sans le ARA-)..." value="{{ $searchQuery }}">
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

{% php %}
$isSearchMode = !empty($searchQuery);
$hasContext = ($selectedSessionId > 0) || $isSearchMode;
{% endphp %}

{% if $hasContext %}

<div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center mb-2">
    <div class="btn-group w-100 mb-2 mb-lg-0" role="group" aria-label="Filtres supplémentaires">

        {% php %}
        // Construit la base des paramètres en préservant la recherche si présente
        $baseParamsArray = ['tab' => $tab, 's' => $selectedSessionId, 'per_page' => $itemsPerPage];
        if (!empty($searchQuery)) { $baseParamsArray['q'] = $searchQuery; }
        $baseParams = http_build_query($baseParamsArray);
        {% endphp %}

        <a href="?{{ $baseParams }}&cancel={{ $isCancel ? 0 : 1 }}{{ !is_null($isChecked) ? '&check=' . (int)$isChecked : '' }}"
           class="btn btn-sm {{ $isCancel ? 'btn-danger' : 'btn-outline-danger' }}">
            <i class="bi bi-x-circle-fill"></i>
            <span class="d-none d-sm-inline ms-1">{{ $isCancel ? 'Masquer annulées' : 'Afficher annulées' }}</span>
        </a>

        {% php %}
        // Logique pour le bouton de filtre 'vérifié'
        $checkAndCancelParams = $baseParams . (!is_null($isCancel) ? '&cancel=' . (int)$isCancel : '');

        if (is_null($isChecked)) {
        $checkLink = '?' . $checkAndCancelParams . '&check=0';
        $checkClass = 'btn-outline-info';
        $checkText = 'Masquer vérifiées';
        } elseif ($isChecked === false) {
        $checkLink = '?' . $checkAndCancelParams . '&check=1';
        $checkClass = 'btn-info';
        $checkText = 'Afficher vérifiées';
        } else {
        $checkLink = '?' . $checkAndCancelParams . '&check=0';
        $checkClass = 'btn-info';
        $checkText = 'Afficher non-vérifiées';
        }
        {% endphp %}

        {% if !is_null($isChecked) %}
        <a href="?{{ $checkAndCancelParams }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-eye-slash-fill"></i>
            <span class="d-none d-sm-inline ms-1">Afficher tout</span>
        </a>
        {% endif %}

        <a href="{{ $checkLink }}" class="btn btn-sm {{ $checkClass }}">
            <i class="bi bi-check-circle-fill"></i>
            <span class="d-none d-sm-inline ms-1">{{ $checkText }}</span>
        </a>

        {% if $selectedSessionId > 0 %}
        <a href="?tab=extract&s={{ $selectedSessionId }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-box-arrow-up"></i>
            <span class="d-none d-sm-inline ms-1">Exporter</span>
        </a>
        {% endif %}
    </div>

    <div class="d-flex align-items-center w-100 w-lg-auto justify-content-end">
        <label for="items-per-page-selector" class="form-label me-2 mb-0">Afficher :</label>
        <select id="items-per-page-selector" class="form-select form-select-sm" style="width: auto;">
            <option value="10" {{ $itemsPerPage == 10 ? 'selected' : '' }}>10</option>
            <option value="20" {{ $itemsPerPage == 20 ? 'selected' : '' }}>20</option>
            <option value="50" {{ $itemsPerPage == 50 ? 'selected' : '' }}>50</option>
            <option value="100" {{ $itemsPerPage == 100 ? 'selected' : '' }}>100</option>
        </select>
    </div>
</div>

{% php %}
// Détermine si on affiche la colonne "Nageuse"
$showSwimmerColumn = isset($event) ? ($event->getLimitationPerSwimmer() !== null) : false;
if (!$showSwimmerColumn && !empty($reservations)) {
foreach ($reservations as $r) {
if ($r->getSwimmer()) { $showSwimmerColumn = true; break; }
}
}
// Paramètre 'q' pour la pagination
$qParam = !empty($searchQuery) ? ('&q=' . urlencode($searchQuery)) : '';
{% endphp %}

{% if empty($reservations) %}
<div class="alert alert-info mt-3">
    {{ $isSearchMode ? 'Aucune réservation trouvée pour cette recherche.' : 'Aucune réservation trouvée pour cet événement avec ces paramètres.' }}
</div>
{% else %}

<!-- Vue Desktop -->
<div class="d-none d-md-block">
    <div class="table-responsive">
        <table class="table align-middle table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>Acheteur</th>
                {% if $showSwimmerColumn %}
                <th>Nageuse</th>
                {% endif %}
                <th>Nombre de places</th>
                <th>Paiement</th>
                <th>Vérifié</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            {% foreach $reservations as $reservation %}
            <tr class="{{ $reservation->isChecked() ? 'table-light' : '' }}">
                <td>ARA-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $reservation->getName() }} {{ $reservation->getFirstName() }}</td>
                {% if $showSwimmerColumn %}
                <td>
                    {% if $reservation->getSwimmer() %}
                    {{ $reservation->getSwimmer()->getName() }}
                    {% else %}
                    N/A
                    {% endif %}
                </td>
                {% endif %}
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
                    <div class="form-check form-switch">
                        <input class="form-check-input status-toggle" type="checkbox" role="switch"
                               data-id="{{ $reservation->getId() }}"
                               id="status-switch-{{ $reservation->getId() }}"
                               {{ $reservation->isChecked() ? 'checked' : '' }}>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reservationDetailModal" data-reservation-id="{{ $reservation->getId() }}" data-context="upcoming">
                        <i class="bi bi-eye"></i>&nbsp;Détails
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
            <p class="card-text mb-1"><strong>Acheteur :</strong> {{ $reservation->getName() }} {{ $reservation->getFirstName() }}</p>
            {% if $showSwimmerColumn %}
            <p class="card-text mb-1"><strong>Nageuse :</strong>
                {% if $reservation->getSwimmer() %}
                {{ $reservation->getSwimmer()->getName() }}
                {% else %}
                N/A
                {% endif %}
            </p>
            {% endif %}
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
                <i class="bi bi-eye"></i>&nbsp;Détails
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
            <a class="page-link" href="?tab={{ $tab }}&s={{ $selectedSessionId }}&page={{ $currentPage - 1 }}&per_page={{ $itemsPerPage }}{{ $qParam }}">Précédent</a>
        </li>
        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
            <a class="page-link" href="?tab={{ $tab }}&s={{ $selectedSessionId }}&page={{ $currentPage + 1 }}&per_page={{ $itemsPerPage }}{{ $qParam }}">Suivant</a>
        </li>
    </ul>
</nav>
{% endif %}
{% endif %}
{% endif %}
