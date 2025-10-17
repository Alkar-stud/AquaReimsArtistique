<div class="container-fluid">
    <h2 class="mb-4">Choix des compléments</h2>

    <form id="reservationPlacesForm">
        <input type="hidden" id="event_id" name="event_id" value="{{ $reservation['event_id'] }}">
        {% if !empty($allTarifsWithoutSeatForThisEvent) %}
        <div id="tarifsContainer">
            {% foreach $allTarifsWithoutSeatForThisEvent as $tarif %}
            {% if $tarif->getAccessCode() === null %}
            <div class="mb-3">
                <label for="tarif_{{ $tarif->getId() }}" class="form-label">
                    <strong>{{ $tarif->getName() }}</strong>
                    ({{ $tarif->getSeatCount() }} place{{ $tarif->getSeatCount() > 1 ? 's':'' }} incluse{{ $tarif->getSeatCount() > 1 ? 's':'' }})
                    - {{ number_format($tarif->getPrice() / 100, 2, ',', ' ') }} €
                    <br>
                    <div class="text small text-muted">
                        {{ $tarif->getDescription() }}
                    </div>
                </label>
                <input type="number"
                       class="form-control place-input"
                       id="tarif_{{ $tarif->getId() }}"
                       name="tarifs[{{ $tarif->getId() }}]"
                       min="0"
                       value="{{ isset($arrayTarifForForm[$tarif->getId()]) ? $arrayTarifForForm[$tarif->getId()] : 0 }}"
                       data-nb-place="{{ $tarif->getSeatCount() ?? 1 }}">
            </div>
            {% endif %}
            {% endforeach %}
        </div>
        {% else %}
        <div class="alert alert-info">Aucun tarif disponible pour cet événement.</div>
        {% endif %}

        <hr>
        <div class="mb-3">
            <label for="specialCode" class="form-label">Vous avez un code ?</label>
            <div class="input-group">
                <input type="text" class="form-control" id="specialCode" placeholder="Saisissez votre code" style="max-width: 250px;">
                <button type="button" class="btn btn-outline-primary" id="validateCodeBtn">Valider le code</button>
            </div>
            <div id="specialCodeFeedback" class="form-text text-danger"></div>
        </div>
        <div id="specialTarifContainer"></div>
        <br>
        <div class="row">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <a href="/reservation/{{ $previousStep }}" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>


</div>

<script>
    window.specialTarifSession  = {{! json_encode($specialTarifSession ?? null) !}}; // préremplissage code spécial
</script>

<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_etape6.js" defer></script>
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
