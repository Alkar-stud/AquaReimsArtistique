<div class="container-fluid">
    <h2 class="mb-4">Choix des places</h2>

    {% if $swimmerLimit['limitReached'] %}
    <div class="alert alert-danger" id="card-swimmerLimit" role="alert" aria-live="assertive">
        La limite de places autorisées pour cette nageuse sur l'ensemble des séances de l'événement est atteinte.<br>
        Il n'y a pour le moment plus de places disponibles pour cette nageuse.
    </div>
    {% endif %}

    {% if $swimmerLimit['limit'] !== null %}
    <div class="alert alert-info mb-3" id="card-swimmerLimit-info" role="status" aria-live="polite">
        Limite de places par nageuse sur l'événement : <strong>{{ $swimmerLimit['limit'] }}</strong><br>
        Déjà réservées : <strong id="dejaReservees" aria-live="polite">{{ $swimmerLimit['currentReservations'] }}</strong><br>
        Restantes à réserver : <strong id="placesRestantes" aria-live="polite">{{ ($swimmerLimit['limit'] - $swimmerLimit['currentReservations']) }}</strong>
    </div>
    {% endif %}

    <div id="reservationAlert" role="alert" aria-live="polite" tabindex="-1"></div>

    {% php %}$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;{% endphp %}
    <form id="reservationPlacesForm"
          aria-describedby="step3-hint"
          data-limitation="{{ $swimmerLimit['limit'] ?? 'null' }}"
          data-deja-reservees="{{ $swimmerLimit['currentReservations'] ?? 0 }}"
          data-special-tarif-session="{{ json_encode($specialTarifSession ?? null) }}"
          data-special-tarif-session="{{ json_encode($specialTarifSession ?? null, $jsonFlags) }}"
          data-all-tarifs-seats="{{ json_encode($allTarifsWithSeatForThisEvent, $jsonFlags) }}"
    >

        <p id="step3-hint" class="visually-hidden">
            Saisissez le nombre de places souhaité pour chaque tarif. Les compteurs sont mis à jour automatiquement. Les champs non remplis seront ignorés.
        </p>

        <input type="hidden" id="event_id" name="event_id" value="{{ $event_id }}">

        {% if !empty($allTarifsWithSeatForThisEvent) %}
        <div id="tarifsContainer">
            {% foreach $allTarifsWithSeatForThisEvent as $tarif %}
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

                <div id="tarif_{{ $tarif->getId() }}_help" class="visually-hidden">
                    Chaque unité de ce tarif comprend {{ $tarif->getSeatCount() }} place{{ $tarif->getSeatCount() > 1 ? 's':'' }}.
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
                        aria-describedby="step3-hint tarif_{{ $tarif->getId() }}_help tarif_{{ $tarif->getId() }}_error"
                >
                <div id="tarif_{{ $tarif->getId() }}_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
            </div>
            {% endif %}
            {% endforeach %}
        </div>
        {% else %}
        <div class="alert alert-info" role="status" aria-live="polite">Aucun tarif disponible pour cet événement.</div>
        {% endif %}

        <hr>

        <div class="mb-3">
            <label for="specialCode" class="form-label">Vous avez un code ?</label>
            <div id="specialCodeHelp" class="visually-hidden">
                Entrez votre code, puis utilisez le bouton pour le valider.
            </div>
            <div class="input-group">
                <input
                        type="text"
                        class="form-control"
                        id="specialCode"
                        placeholder="Saisissez votre code"
                        style="max-width: 250px;"
                        aria-invalid="false"
                        aria-describedby="step3-hint specialCodeHelp specialCodeFeedback"
                        autocomplete="off"
                        spellcheck="false"
                >
                <button
                        type="button"
                        class="btn btn-primary"
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
                <a href="/reservation/etape2Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6 order-1 order-md-2 d-flex justify-content-md-end mb-2 mb-md-0">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>
</div>

<script type="module" src="/assets/js/reservations/etape3.js" defer></script>

{% if ($_ENV['APP_DEBUG'] == "true") %}
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
{% endif %}