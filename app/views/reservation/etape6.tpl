<div class="container-fluid">
    <h2 class="mb-4">Choix des compléments</h2>

    {% php %}$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;{% endphp %}

    <form
            id="reservationPlacesForm"
            aria-describedby="step6-hint"
            data-special-tarif-session="{{ json_encode($specialTarifSession ?? null, $jsonFlags) }}"
    >
        <p id="step6-hint" class="visually-hidden">
            Saisissez la quantité souhaitée pour chaque complément. Les champs non remplis sont ignorés.
            Si vous avez un code spécial, entrez‑le puis validez‑le.
        </p>

        <input type="hidden" id="event_id" name="event_id" value="{{ $reservation['reservation']->getEvent() }}">

        <div id="reservationAlert" role="alert" aria-live="polite" tabindex="-1"></div>

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
                    <span class="text small text-muted">
                        {{ $tarif->getDescription() }}
                    </span>
                </label>

                <div id="tarif_{{ $tarif->getId() }}_help" class="visually-hidden">
                    Chaque unité de ce complément comprend {{ $tarif->getSeatCount() }} place{{ $tarif->getSeatCount() > 1 ? 's':'' }}.
                    Saisissez un nombre entier supérieur ou égal à 0.
                </div>

                <input
                        type="number"
                        class="form-control place-input"
                        id="tarif_{{ $tarif->getId() }}"
                        name="tarifs[{{ $tarif->getId() }}]"
                        min="0"
                        value="{{ isset($arrayTarifForForm[$tarif->getId()]) ? $arrayTarifForForm[$tarif->getId()] : 0 }}"
                        data-nb-place="{{ $tarif->getSeatCount() ?? 1 }}"
                        inputmode="numeric"
                        aria-invalid="false"
                        aria-describedby="step6-hint tarif_{{ $tarif->getId() }}_help tarif_{{ $tarif->getId() }}_error"
                >
                <div id="tarif_{{ $tarif->getId() }}_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
            </div>
            {% endif %}
            {% endforeach %}
        </div>
        {% else %}
        <div class="alert alert-info" role="status" aria-live="polite">Aucun complément disponible pour cet événement.</div>
        {% endif %}

        <hr>

        <div class="mb-3">
            <label for="specialCode" class="form-label">Vous avez un code ?</label>
            <div id="specialCodeHelp" class="visually-hidden">
                Entrez votre code puis utilisez le bouton pour le valider.
            </div>
            <div class="input-group">
                <input
                        type="text"
                        class="form-control"
                        id="specialCode"
                        placeholder="Saisissez votre code"
                        style="max-width: 250px;"
                        aria-invalid="false"
                        aria-describedby="step6-hint specialCodeHelp specialCodeFeedback"
                        autocomplete="off"
                        spellcheck="false"
                >
                <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="validateCodeBtn"
                        aria-describedby="specialCodeHelp"
                >
                    Valider le code
                </button>
            </div>
            <div id="specialCodeFeedback" class="form-text text-danger" role="alert" aria-live="polite"></div>
        </div>

        <div id="specialTarifContainer"></div>

        <br>

        <div class="row">
            <!-- order-x pour position x en mobile et order-mg-x pour desktop -->
            <!-- afin que le bouton principal soit à droite en desktop -->
            <!-- et en 1er en mobile -->
            <div class="col-12 col-md-6 order-2 order-md-1 mb-2 mb-md-0">
                <a href="/reservation/{{ $previousStep }}" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6 order-1 order-md-2 d-flex justify-content-md-end mb-2 mb-md-0">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>
</div>

<script>
    window.specialTarifSession = {{! json_encode($specialTarifSession ?? null) !}};
</script>

<script type="module" src="/assets/js/reservations/etape6.js" defer></script>

{% if ($_ENV['APP_DEBUG'] == "true") %}
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($reservation);
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
{% endphp %}
{% endif %}
