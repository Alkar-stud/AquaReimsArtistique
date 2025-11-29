<div class="container-fluid" data-event-session-id="{{ $eventSessionId ?? '0' }}">
    <h2 class="mb-4">Choix des places assises</h2>

    <!-- Liste des zones (visible au départ) -->
    {% include '/piscine/_zones.tpl' with { 'zones': $zones } %}

    <!-- Bleacher pré-rendu caché (placeholders remplis côté JS) -->
    <div class="container my-3 d-none" data-component="bleacher" data-zone-id="0">
        <div class="bleacher-header mb-3">
            <div class="bleacher-controls">
                <a class="btn btn-outline-secondary btn-sm" href="#" data-action="back-zones">&larr; Retour aux zones</a>
                <button type="button" class="btn btn-outline-primary btn-sm" data-action="refresh-bleacher">Actualiser</button>
                <h2 class="card zone-card text-center m-0 p-2 d-inline-block">
                    <span class="d-none d-md-inline-block">Zone </span><span class="fw-bold" data-bleacher-zone-name>Zone</span>
                </h2>
            </div>
            <div class="legende">
                <div class="row g-2">
                    <div class="col-6 col-md-auto case placeVide">Place vide</div>
                    <div class="col-6 col-md-auto case placeClosed">Fermée</div>
                    <div class="col-6 col-md-auto case placePMR">PMR</div>
                    <div class="col-6 col-md-auto case placeVIP">VIP</div>
                    <div class="col-6 col-md-auto case placeBenevole">Bénévole</div>
                    <div class="col-6 col-md-auto case placePris">Réservée</div>
                    <div class="col-6 col-md-auto case placeTemp">En cours (autres)</div>
                    <div class="col-6 col-md-auto case placeTempSession">En cours (vous)</div>
                </div>
            </div>
        </div>

        <!-- Conteneur pour la liste des participants à placer -->
        <div id="participants-to-seat-container" class="my-3">
            <!--
                Le JavaScript pourra remplir cette zone avec la liste des participants
                qui n'ont pas encore de siège attribué.
            -->
        </div>

        <div class="gradins-row align-items-center mb-2">
            <button type="button" class="nav-zone-btn prev d-none d-md-inline-flex" data-action="prev-zone" aria-label="Zone précédente">&larr;</button>

            <div class="bleacher-container mb-4">
                <div class="gradins-wrapper" data-mode="readonly">
                    <section>
                        <div class="gradins" data-bleacher-seats>
                            <!-- Places générées en JS -->
                        </div>
                    </section>
                </div>
            </div>

            <button type="button" class="nav-zone-btn next d-none d-md-inline-flex" data-action="next-zone" aria-label="Zone suivante">&rarr;</button>
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