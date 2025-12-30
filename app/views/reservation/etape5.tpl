<div class="container-fluid" data-event-session-id="{{ $reservation['reservation']->getEventSession() ?? '0' }}">
    <h2 class="mb-4">Choix des places assises</h2>

    <form id="reservationPlacesForm">
        <!-- Bloc de boutons TOP (toujours visible) -->
        <div class="row mb-3">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <a href="/reservation/etape4Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" data-role="submit-reservation" disabled>Valider et continuer</button>
            </div>
        </div>

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
            <h3 class="h5">Participants à placer</h3>
            <ul class="list-group list-group-horizontal-md" id="participants-list">
                <!-- Le JavaScript remplira cette liste -->
                <li class="list-group-item text-muted">Chargement des participants...</li>
            </ul>
        </div>


        <div class="gradins-row align-items-center mb-2">
            <button type="button" class="nav-zone-btn prev d-none d-md-inline-flex" data-action="prev-zone" aria-label="Zone précédente">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div class="bleacher-container mb-4">
                <div class="gradins-wrapper" data-mode="readonly">
                    <section>
                        <div class="gradins" data-bleacher-seats>
                            <!-- Places générées en JS -->
                        </div>
                    </section>
                </div>
            </div>

            <button type="button" class="nav-zone-btn next d-none d-md-inline-flex" data-action="next-zone" aria-label="Zone suivante">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <!-- Bloc de boutons BOTTOM -->
        <div class="row">
            <div class="col-12 col-md-6 order-2 order-md-1 mb-2 mb-md-0">
                <a href="/reservation/etape4Display" class="btn btn-secondary w-100 w-md-auto">Retour</a>
            </div>
            <div class="col-12 col-md-6 order-1 order-md-2 d-flex justify-content-md-end mb-2 mb-md-0">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" data-role="submit-reservation" disabled>Valider et continuer</button>
            </div>
        </div>
    </form>

    <!-- Données pour le JavaScript -->
    <script type="application/json" id="reservation-details-data">
        {{! json_encode(array_map(fn($d) => $d->toArray(), $reservation['reservation_details'] ?? [])) !}}
    </script>
</div>

<script type="module" src="/assets/js/reservations/etape5.js" defer></script>
{% if ($_ENV['APP_DEBUG'] == "true") %}
{% php %}
echo '<pre>';
print_r($reservation);
echo '</pre>';
{% endphp %}
{% endif %}