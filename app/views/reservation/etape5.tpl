<div class="container-fluid">
    <h2 class="mb-4">Choix des places assises</h2>

    <!-- Liste des zones (visible au départ) -->
    {% include '/piscine/_zones.tpl' with { 'zones': $zones } %}

    <!-- Bleacher pré-rendu caché (placeholders remplis côté JS) -->
    <div class="container my-3 d-none" data-component="bleacher" data-zone-id="0">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="#" data-action="back-zones">&larr; Retour aux zones</a>
                <button type="button" class="btn btn-outline-primary btn-sm" data-action="refresh-bleacher">Actualiser</button>
            </div>
            <div class="text-center flex-fill">
                <h2 class="h5 m-0">
                    <span data-bleacher-zone-name>Zone</span>
                    <small class="text-muted" data-bleacher-zone-meta>(0 rangs × 0 places)</small>
                </h2>
            </div>
        </div>

        <div class="legende my-3">
            <div class="row g-2">
                <div class="col-6 col-md-3 case placeVide">Place vide</div>
                <div class="col-6 col-md-3 case placeClosed">Fermée</div>
                <div class="col-6 col-md-3 case placePMR">PMR</div>
                <div class="col-6 col-md-3 case placeVIP">VIP</div>
                <div class="col-6 col-md-3 case placeBenevole">Bénévole</div>
                <div class="col-6 col-md-3 case placePris">Réservée</div>
                <div class="col-6 col-md-3 case placeTemp">En cours (autres)</div>
                <div class="col-6 col-md-3 case placeTempSession">En cours (vous)</div>
            </div>
        </div>
        <div class="bleacher-container mb-4">
            <div class="gradins-wrapper" data-mode="readonly">
                <section>
                    <div class="gradins" data-bleacher-seats>
                        <!-- Places générées en JS -->
                    </div>
                </section>

            </div>

        </div>
    </div>

    <form id="reservationPlacesForm">
        <div class="row">
            <div class="col-12 col-md-6 order-2 order-md-1 mb-2 mb-md-0">
                <a href="/reservation/etape4Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6 order-1 order-md-2 d-flex justify-content-md-end mb-2 mb-md-0">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>
</div>
<script type="module" src="/assets/js/reservations/etape5.js" defer></script>
{% if ($_ENV['APP_DEBUG'] == "true") %}
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
{% endif %}