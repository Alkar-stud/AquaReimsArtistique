<?php include __DIR__ . '/_display_details.html.php';
$limiteDepassee = $limiteDepassee ?? false;
$limiteMessage = $limiteMessage ?? '';
?>
    <div class="container">
        <h2 class="mb-4">Choix des places</h2>

        <?php if ($limiteDepassee): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($limiteMessage ?: "Vous avez dépassé la limite de places autorisées pour cette nageuse sur l'ensemble des séances de l'événement.") ?>
            </div>
        <?php endif; ?>

        <?php if ($limitation !== null): ?>
            <div class="alert alert-info mb-3">
                Limite de places par nageuse sur l'événement : <strong><?= $limitation ?></strong><br>
                Déjà réservées : <strong><?= $placesDejaReservees ?></strong><br>
                Restantes à réserver : <strong id="placesRestantes"><?= $placesRestantes ?></strong>
            </div>
        <?php endif; ?>

        <form id="reservationPlacesForm">
            <?php if (!empty($tarifs)): ?>
                <div id="tarifsContainer">
                    <?php foreach ($tarifs as $tarif): ?>
                        <?php if ($tarif->getNbPlace() !== null && !$tarif->getAccessCode()): ?>
                            <div class="mb-3">
                                <label for="tarif_<?= $tarif->getId() ?>" class="form-label">
                                    <?= htmlspecialchars($tarif->getLibelle()) ?>
                                    (<?= $tarif->getNbPlace() ?> place<?= $tarif->getNbPlace() > 1 ? 's' : '' ?> inclus<?= $tarif->getNbPlace() > 1 ? 'es' : 'e' ?>)
                                    - <?= number_format($tarif->getPrice() / 100, 2, ',', ' ') ?> €
                                </label>
                                <?php if ($tarif->getDescription()): ?>
                                    <div class="text-muted small mb-1">
                                        <?= nl2br(htmlspecialchars($tarif->getDescription())) ?>
                                    </div>
                                <?php endif; ?>
                                <input type="number"
                                       class="form-control place-input"
                                       id="tarif_<?= $tarif->getId() ?>"
                                       name="tarifs[<?= $tarif->getId() ?>]"
                                       min="0"
                                       value="<?= isset($tarifQuantities[$tarif->getId()]) ? (int)$tarifQuantities[$tarif->getId()] : 0 ?>"
                                       data-nb-place="<?= $tarif->getNbPlace() ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Aucun tarif disponible pour cet événement.</div>
            <?php endif; ?>

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
                    <a href="/reservation/etape2Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
                </div>
                <div class="col-12 col-md-6">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">Valider et continuer</button>
                </div>
            </div>
        </form>
        <div id="reservationStep3Alert"></div>
    </div>

    <script>
        window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
        window.reservation = <?= json_encode($reservation ?? []) ?>;
        window.limitationPerSwimmer = <?= json_encode($limitation) ?>;
        window.placesDejaReservees = <?= json_encode($placesDejaReservees) ?>;
        // Pour le JS, il faudra aussi transmettre la liste des tarifs spéciaux (avec code).
        window.specialTarifs = <?= json_encode(array_values(array_filter($tarifs, fn($t) => $t->getAccessCode()))) ?>;
        //Pour transmettre la liste des tarifs spéciaux déjà saisis
        window.specialTarifSession = <?= json_encode($specialTarifSession) ?>;
    </script>
    <script src="/assets/js/reservation_common.js" defer></script>
    <script src="/assets/js/reservation_etape3.js" defer></script>

    Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>
