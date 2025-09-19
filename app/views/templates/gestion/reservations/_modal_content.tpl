<form id="reservationDetailForm">
    <input type="hidden" name="reservation_id" value="{{ $reservation->getId() }}">

    <h4>Acheteur</h4>
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Prénom</label>
            <input type="text" class="form-control" value="{{ $reservation->getPrenom() }}" {{ $isReadOnly ? 'disabled' : '' }}>
        </div>
        <div class="col-md-6">
            <label class="form-label">Nom</label>
            <input type="text" class="form-control" value="{{ $reservation->getNom() }}" {{ $isReadOnly ? 'disabled' : '' }}>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="{{ $reservation->getEmail() }}" {{ $isReadOnly ? 'disabled' : '' }}>
        </div>
        <div class="col-md-6">
            <label class="form-label">Téléphone</label>
            <input type="tel" class="form-control" value="{{ $reservation->getPhone() }}" {{ $isReadOnly ? 'disabled' : '' }}>
        </div>
    </div>

    <h4>Détails des places ({{ count($reservation->getDetails()) }})</h4>
    <hr>
    {% if empty($reservation->getDetails()) %}
    <p class="text-muted">Aucune place associée à cette réservation.</p>
    {% else %}
    {% foreach $reservation->getDetails() as $detail %}
    <div class="card mb-3">
        <div class="card-body">
            <div class="mt-2 text-muted">
                Tarif: {{ $detail->getTarifObject() ? $detail->getTarifObject()->getLibelle() : 'N/A' }}
                {% if $detail->getPlaceObject() && $detail->getPlaceObject()->getZoneObject() %}
                | Place : {{ $detail->getPlaceObject()->getZoneObject()->getZoneName() }}{{ $detail->getPlaceObject()->getShortPlaceName() }}

                {% endif %}
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Prénom du participant</label>
                    <input type="text" class="form-control" value="{{ $detail->getPrenom() }}" {{ $isReadOnly ? 'disabled' : '' }}>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nom du participant</label>
                    <input type="text" class="form-control" value="{{ $detail->getNom() }}" {{ $isReadOnly ? 'disabled' : '' }}>
                </div>
            </div>
        </div>
    </div>
    {% endforeach %}
    {% endif %}

    <h4 class="mt-4">Détails des compléments ({{ count($reservation->getComplements()) }})</h4>
    <hr>
    {% if empty($reservation->getComplements()) %}
    <p class="text-muted">Aucun complément associé à cette réservation.</p>
    {% else %}
    {% foreach $reservation->getComplements() as $complement %}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Produit</label>
                    {{ $complement->getTarifObject() ? $complement->getTarifObject()->getLibelle() : 'N/A' }}
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantité</label>
                    <input type="number" class="form-control" min="0" value="{{ $complement->getQty() }}" {{ $isReadOnly ? 'disabled' : '' }}>
                </div>
                <div class="col-md-2">
                    <p class="text-muted mb-1">Prix: {{ $complement->getTarifObject() ? number_format($complement->getTarifObject()->getPrice() / 100, 2, ',', ' ') . ' €' : 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
    {% endforeach %}
    {% endif %}

</form>

<div class="modal-footer mt-3">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
    {% if !$isReadOnly %}
    <button type="button" class="btn btn-primary" id="saveReservationDetails">Enregistrer les modifications</button>
    {% endif %}
</div>