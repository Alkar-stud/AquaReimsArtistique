<div class="container-fluid">
    <div class="mb-3 sticky-reservation-header shadow-sm px-2 py-2">
        <h5 class="mb-2">Réservation ARA-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</h5>
        <div class="d-flex gap-2 mb-2">
            {% if $reservation->isCanceled() %}
            <span class="badge bg-danger">Réservation annulée</span>
            {% else %}
            <span class="badge bg-success">Réservation confirmée</span>
            {% endif %}
            {% if $reservation->isChecked() %}
            <span class="badge bg-success">Vérifiée</span>
            {% else %}
            <span class="badge bg-warning">À contrôler</span>
            {% endif %}

            <span class="badge bg-info" id="every-one-is-present" style="display:{{ $everyOneIsPresent ? 'block':'none' }};">Tous présents</span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="card-subtitle mb-2 text-muted">Informations</h6>
            <p class="mb-1"><strong>{{ $reservation->getEventObject()->getName() }}</strong></p>
            <p class="mb-1 small">{{ $reservation->getEventSessionObject()->getSessionName() }}</p>
            <p class="mb-2 small">{{ $reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y à H:i') }}</p>
            <p class="mb-1 small">{{ $reservation->getName() }} {{ $reservation->getFirstName() }}</p>
        </div>
    </div>

    {% if !$reservation->isChecked() %}
    <div class="card mb-3">
        <div class="card-body p-3 bg-info">
            <div class="form-check">
                <input
                        class="form-check-input"
                        type="checkbox"
                        id="checkReservation"
                        data-action="check-reservation"
                        data-reservation-id="{{ $reservation->getId() }}">
                <label class="form-check-label fw-bold" for="checkReservation">
                    Marquer comme vérifiée - Il faut contrôler les compléments, le nombre de participants et leurs places ainsi que le(s) paiement(s).
                </label>
            </div>
        </div>
    </div>
    {% endif %}

    {% if !empty($reservation->getComplements()) %}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center p-3">
            <h6 class="mb-0">Compléments</h6>
            <button
                    class="btn btn-sm {% if $reservation->getComplementsGivenAt() %}btn-success{% else %}btn-outline-primary{% endif %}"
                    data-action="toggle-complements"
                    data-reservation-id="{{ $reservation->getId() }}"
                    data-complement-given="{% if $reservation->getComplementsGivenAt() %}true{% else %}false{% endif %}"
                    {% if $reservation->getComplementsGivenAt() %}
                    data-complements-given-at="{{ $reservation->getComplementsGivenAt()->format('Y-m-d\\TH:i:s') }}"
                    {% endif %}>
                {% if $reservation->getComplementsGivenAt() %}
                <i class="bi bi-check-circle-fill"></i>&nbsp;Remis à {{ $reservation->getComplementsGivenAt()->format('H:i') }}
                {% else %}
                <i class="bi bi-circle"></i>&nbsp;À remettre
                {% endif %}
            </button>
        </div>
        <ul class="list-group list-group-flush">
            {% foreach $reservation->getComplements() as $complement %}
            <li class="list-group-item py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span>{{ $complement->getTarifObject()->getName() }}</span>
                    <span class="badge bg-secondary">{{ $complement->getQty() }}</span>
                </div>
            </li>
            {% endforeach %}
        </ul>
    </div>
    {% endif %}

    <div class="card">
        <div class="card-header p-3">
            <h6 class="mb-0">Participants ({{ count($reservation->getDetails()) }})</h6>
        </div>
        <div class="list-group list-group-flush">
            {% foreach $reservation->getDetails() as $detail %}
            <div
                    class="list-group-item p-3 {% if $detail->getEnteredAt() %}list-group-item-success{% endif %}"
                    data-action="toggle-participant"
                    data-detail-id="{{ $detail->getId() }}"
                    style="cursor: pointer; user-select: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold">
                            {{ $detail->getName() }} {{ $detail->getFirstName() }}
                        </div>
                        <div class="small text-muted">
                            {{ $detail->getTarifObject()->getName() }}
                            {% if $detail->getPlaceNumber() %}
                            · Place {{ $detail->getPlaceNumber() }}
                            {% endif %}
                        </div>
                    </div>
                    <div class="ms-3">
                        {% if $detail->getEnteredAt() %}
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        {% else %}
                        <i class="bi bi-circle text-secondary fs-4"></i>
                        {% endif %}
                    </div>
                </div>
                {% if $detail->getEnteredAt() %}
                <div class="small text-muted mt-1">
                    Entré à {{ $detail->getEnteredAt()->format('H:i') }}
                </div>
                {% endif %}
            </div>
            {% endforeach %}
        </div>
    </div>

</div>

<script type="module" src="/assets/js/entrance.js"></script>
