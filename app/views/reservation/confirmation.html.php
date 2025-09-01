<?php
$event = $event ?? null;
$session = $session ?? null;
$nageuse = $nageuse ?? null;
$reservation = $reservation ?? [];
$tarifsById = $tarifsById ?? [];
$tarifs = $tarifs ?? [];
$tarifsByIdObj = [];
foreach ($tarifs as $t) {
    $tarifsByIdObj[$t->getId()] = $t;
}

$total = 0;

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

    <?php if (!empty($reservation['reservation_detail'])): ?>
        <h5>Participants avec places assises</h5>
        <ul class="list-group mb-3">
            <?php foreach ($reservation['reservation_detail'] as $i => $detail): ?>
                <?php
                $tarifObj = $tarifsByIdObj[$detail['tarif_id']] ?? null;
                $prix = $tarifObj ? $tarifObj->getPrice() : 0;
                $total += $prix;
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
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
                    <span class="float-end fw-bold"><?= number_format($prix, 2, ',', ' ') ?> €</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($reservation['reservation_complement'])): ?>
        <h5>Tarifs sans places assises</h5>
        <ul class="list-group mb-3">
            <?php foreach ($reservation['reservation_complement'] as $item): ?>
                <?php
                $tarif = $tarifsByIdObj[$item['tarif_id']] ?? null;
                $qty = (int)$item['qty'];
                $unit = $tarif ? $tarif->getPrice() : 0;
                $subtotal = $unit * $qty;
                $total += $subtotal;
                ?>
                <li class="list-group-item">
                    <?= htmlspecialchars($tarif ? $tarif->getLibelle() : $item['tarif_id']) ?>
                    — Quantité : <strong><?= $qty ?></strong>
                    <?php if ($tarif): ?>
                        — Prix unitaire : <strong><?= number_format($unit, 2, ',', ' ') ?> €</strong>
                        <span class="float-end fw-bold"><?= number_format($subtotal, 2, ',', ' ') ?> €</span>
                    <?php endif; ?>
                    <?php if ($tarif && $tarif->getDescription()): ?>
                        <br><span class="text-muted small"><?= nl2br(htmlspecialchars($tarif->getDescription())) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="text-end mb-3">
        <strong>Total à payer : <?= number_format($total, 2, ',', ' ') ?> €</strong>
    </div>

    <div class="alert alert-info">
        Merci de vérifier vos informations avant validation finale.
    </div>
    <a href="/reservation/etape6Display" class="btn btn-secondary ms-2">Modifier mon choix précédent</a>
    <button type="submit" class="btn btn-primary">Valider et payer</button>
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
