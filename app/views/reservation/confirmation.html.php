<?php
$event = $event ?? null;
$session = $session ?? null;
$nageuse = $nageuse ?? null;
$reservation = $reservation ?? [];
$tarifsById = $tarifsById ?? [];
$tarifs = $tarifs ?? [];
$tarifsByIdObj = [];
$tarifQuantities = $tarifQuantities ?? [];
$totalAmount = $totalAmount ?? 0.0;
foreach ($tarifs as $t) {
    $tarifsByIdObj[$t->getId()] = $t;
}

?>
<div class="container">
    <h2 class="mb-4">Récapitulatif de votre réservation</h2>
    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? '') ?></li>
        <?php if ($session): ?>
            <li class="list-group-item"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?></li>
        <?php endif; ?>
        <?php if ($nageuse): ?>
            <li class="list-group-item"><strong>Nageuse :</strong> <?= htmlspecialchars($nageuse->getName() ?? '') ?></li>
        <?php endif; ?>
        <?php if (!empty($reservation['user'])): ?>
            <li class="list-group-item"><strong>Réservant :</strong>
                <?= htmlspecialchars($reservation['user']['prenom'] ?? '') ?>
                <?= htmlspecialchars($reservation['user']['nom'] ?? '') ?>
                (<?= htmlspecialchars($reservation['user']['email'] ?? '') ?>)
            </li>
        <?php endif; ?>
    </ul>
    <h5>Votre panier</h5>
    <ul class="list-group mb-3">
        <?php // On itère sur les QUANTITÉS de tarifs, pas sur les participants ?>
        <?php foreach ($tarifQuantities as $tarifId => $quantity): ?>
            <?php if ($quantity > 0 && isset($tarifsByIdObj[$tarifId])):
                $tarif = $tarifsByIdObj[$tarifId];
                ?>
                <li class="list-group-item d-flex justify-content-between lh-sm">
                    <div>
                        <h6 class="my-0"><?= htmlspecialchars($tarif->getLibelle()) ?> (<?= number_format($tarif->getPrice() / 100, 2, ',', ' ') ?> €)</h6>
                        <small class="text-muted">
                            Quantité : <?= $quantity ?>
                            <?php if ($tarif->getNbPlace() > 1): ?>
                                (Pack de <?= $tarif->getNbPlace() ?> places)
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="text-muted"><?= number_format($quantity * $tarif->getPrice() / 100, 2, ',', ' ') ?> €</span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php // Affichage des compléments (étape 6) ?>
        <?php if (!empty($reservation['reservation_complement'])): ?>
            <?php foreach ($reservation['reservation_complement'] as $complement): ?>
                <?php if (isset($tarifsByIdObj[$complement['tarif_id']])):
                    $tarif = $tarifsByIdObj[$complement['tarif_id']];
                    ?>
                    <li class="list-group-item d-flex justify-content-between lh-sm">
                        <div>
                            <h6 class="my-0"><?= htmlspecialchars($tarif->getLibelle()) ?></h6>
                            <small class="text-muted">Quantité : <?= $complement['qty'] ?></small>
                        </div>
                        <span class="text-muted"><?= number_format($complement['qty'] * $tarif->getPrice() / 100, 2, ',', ' ') ?> €</span>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <li class="list-group-item d-flex justify-content-between">
            <span>Total (EUR)</span>
            <strong><?= number_format($totalAmount / 100, 2, ',', ' ') ?> €</strong>
        </li>
    </ul>
    <?php if (!empty($reservation['reservation_detail'])): ?>
        <h5>Détail des participants</h5>
        <ul class="list-group mb-3">
            <?php foreach ($reservation['reservation_detail'] as $i => $detail): ?>
                <?php
                $tarifObj = $tarifsByIdObj[$detail['tarif_id']] ?? null;
                ?>
                <li class="list-group-item">
                    <div>
                        <?= htmlspecialchars(($detail['prenom'] ?? '') . ' ' . ($detail['nom'] ?? '')) ?>
                        <?php if ($tarifObj): ?>
                            (Tarif : <em><?= htmlspecialchars($tarifObj->getLibelle()) ?></em>)
                        <?php endif; ?>
                        <?php if (!empty($detail['seat_name'])): ?>
                            — Place : <em><?= htmlspecialchars($detail['seat_name']) ?></em>
                        <?php endif; ?>
                        <?php if (!empty($detail['access_code'])): ?>
                            — Code : <em><?= htmlspecialchars($detail['access_code']) ?></em>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    </div>

    <div class="alert alert-info">
        Merci de vérifier vos informations avant validation finale.
    </div>

    <div class="row">
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <a href="/reservation/etape6Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
        </div>
        <div class="col-12 col-md-6">
            <button type="submit" class="btn btn-primary w-100 w-md-auto">Valider et payer</button>
        </div>
    </div>
</div>

<script>
    window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
</script>
<script src="/assets/js/reservation_confirmation.js" defer></script>

<hr>
Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>
