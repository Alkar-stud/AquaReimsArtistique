<?php
$participants = $reservation['reservation_detail'] ?? [];
?>
    <div id="display_details">
        <?php include __DIR__ . '/_display_details.html.php'; ?>
    </div>
    <div class="container">
        <h2 class="mb-4">Choix des places</h2>
        <div class="mb-3">
            <strong>Participants et attribution des places :</strong>
            <table class="table table-sm w-auto" id="participantsTable">
                <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Place choisie</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($participants as $i => $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nom'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['prenom'] ?? '') ?></td>
                        <td class="chosen-seat" id="chosen-seat-<?= $i ?>">
                            <?= ($p['seat_name'] != '') ? htmlspecialchars($p['seat_name']) : 'Non choisie' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form id="form_etape5" class="mb-4">
            <input type="hidden" name="selectedSeats" id="selectedSeats">
            <div class="row">
                <div class="col-12 col-md-6 mb-2 mb-md-0">
                    <a href="/reservation/etape4Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
                </div>
                <div class="col-12 col-md-6">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitBtnTop">Valider et continuer</button>
                </div>
            </div>
        </form>

        <div id="zones-container">
            <div id="zones-mini-plan" class="mb-4">
                <div class="d-flex flex-wrap justify-content-center">
                    <?php foreach ($zonesWithPlaces as $z): ?>
                        <button type="button"
                                class="btn btn-outline-primary m-2 zone-btn"
                                data-zone="<?= $z['zone']->getId() ?>">
                            Zone <?= htmlspecialchars($z['zone']->getZoneName()) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="text-center text-muted small">Cliquez sur une zone pour choisir vos places</div>
            </div>

            <div id="zone-plan-container" class="mb-4" style="display:none;">
                <!-- Le plan de la zone sera injecté ici par JavaScript -->
            </div>
        </div>
        <form id="form_etape5_bottom" class="mb-4">
            <div class="row">
                <div class="col-12 col-md-6 mb-2 mb-md-0">
                    <a href="/reservation/etape4Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
                </div>
                <div class="col-12 col-md-6">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitBtnBottom">Valider et continuer</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
        const nbPlacesAssises = <?= (int)$nbPlacesAssises ?>;
        window.seatNames = {};
        <?php foreach ($zonesWithPlaces as $z): foreach ($z['places'] as $place): ?>
        window.seatNames[<?= $place->getId() ?>] = <?= json_encode($place->getFullPlaceName()) ?>;
        <?php endforeach; endforeach; ?>

        // Initialisation des places déjà choisies pour chaque participant
        window.participantSeatsInit = <?= json_encode(array_map(
            fn($p) => $p['seat_id'] ?? null,
            $participants
        )) ?>;
    </script>
    <script src="/assets/js/reservation_common.js" defer></script>
    <script src="/assets/js/reservation_etape5.js" defer></script>

    Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>