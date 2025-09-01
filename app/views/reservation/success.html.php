<?php
// Variables attendues : $reservation, $reservationDetails, $reservationComplements, $event, $session, $nageuse, $tarifs, $reservationNumber
$event = $event ?? null;
$session = $session ?? null;
$nageuse = $nageuse ?? null;
$reservation = $reservation ?? null;
$reservationDetails = $reservationDetails ?? [];
$reservationComplements = $reservationComplements ?? [];
$tarifs = $tarifs ?? [];
$reservationNumber = $reservationNumber ?? '';

$tarifsByIdObj = [];
foreach ($tarifs as $t) {
    $tarifsByIdObj[$t->getId()] = $t;
}
?>

<div class="container">
    <h2 class="mb-4 text-success">🎉 Paiement réussi !</h2>
    <div class="alert alert-success">
        Merci, votre réservation a bien été enregistrée et payée.<br>
        <strong>Numéro de réservation :</strong> <?= htmlspecialchars($reservationNumber) ?>
    </div>

    <?php if ($session): ?>
        <div class="mb-3">
            <strong>Rendez-vous pour le : </strong>
            <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?>,
            <strong>Ouverture des portes :</strong>
            <?= htmlspecialchars($session->getOpeningDoorsAt()->format('d/m/Y H:i')) ?>
        </div>
    <?php endif; ?>

    <h4 class="mb-3">Récapitulatif de votre réservation</h4>
    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? '') ?></li>
        <?php if ($session): ?>
            <li class="list-group-item"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?></li>
        <?php endif; ?>
        <?php if ($nageuse): ?>
            <li class="list-group-item"><strong>Nageuse :</strong> <?= htmlspecialchars($nageuse->getName() ?? '') ?></li>
        <?php endif; ?>
        <?php if ($reservation): ?>
            <li class="list-group-item"><strong>Réservant :</strong>
                <?= htmlspecialchars($reservation->getPrenom() ?? '') ?>
                <?= htmlspecialchars($reservation->getNom() ?? '') ?>
                (<?= htmlspecialchars($reservation->getEmail() ?? '') ?>)
            </li>
        <?php endif; ?>
    </ul>

    <?php if (!empty($reservationDetails)): ?>
        <h5>Participants avec places assises</h5>
        <ul class="list-group mb-3">
            <?php foreach ($reservationDetails as $detail): ?>
                <?php
                $tarifObj = $tarifsByIdObj[$detail->getTarif()] ?? null;
                $prix = $tarifObj ? $tarifObj->getPrice() : 0;
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <?= htmlspecialchars(($detail->getPrenom() ?? '') . ' ' . ($detail->getNom() ?? '')) ?>
                        <?php if ($tarifObj): ?>
                            (Tarif : <em><?= htmlspecialchars($tarifObj->getLibelle()) ?></em>)
                        <?php endif; ?>
                        <?php if ($detail->getPlaceNumber()): ?>
                            — Place : <em><?= htmlspecialchars($detail->getPlaceNumber()) ?></em>
                        <?php endif; ?>
                    </div>
                    <span class="float-end fw-bold"><?= number_format($prix, 2, ',', ' ') ?> €</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($reservationComplements)): ?>
        <h5>Tarifs sans places assises</h5>
        <ul class="list-group mb-3">
            <?php foreach ($reservationComplements as $item): ?>
                <?php
                $tarif = $tarifsByIdObj[$item->getTarif()] ?? null;
                $qty = (int)$item->getQty();
                $subtotal = $tarif ? ($tarif->getPrice() * $qty) : 0;
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($tarif ? $tarif->getLibelle() : 'Tarif inconnu') ?> (x<?= $qty ?>)
                    <span class="float-end fw-bold"><?= number_format($subtotal, 2, ',', ' ') ?> €</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="text-end mb-3 fs-5">
        <strong>Total payé : <?= number_format($reservation->getTotalAmountPaid() / 100, 2, ',', ' ') ?> €</strong>
    </div>

    <div class="alert alert-info">
        Un e-mail de confirmation vous sera envoyé prochainement, vous aurez dedans un lien vous permettant de gérer votre réservation.
    </div>
    <a href="/" class="btn btn-primary">Retour à l'accueil</a>
</div>


<hr>
Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>
