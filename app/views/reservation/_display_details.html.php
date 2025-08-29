<?php
$event = $event ?? null;
$session = $session ?? null;
$nageuse = $nageuse ?? null;
$tarifsById = $tarifsById ?? [];
?>
<div class="alert alert-info d-flex align-items-center justify-content-between mb-3" id="reservationSummary">
    <div>
        <strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? 'Non défini') ?>
        <?php if ($session): ?>
            <span class="ms-3"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?> </span>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleDetailsBtn">Détail</button>
</div>
<div id="reservationDetails" class="card mb-3" style="display:none;">
    <div class="card-body">
        <h5 class="card-title">Détail de votre réservation</h5>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? '') ?></li>
            <?php if ($session): ?>
                <li class="list-group-item"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?></li>
            <?php endif; ?>
            <?php if ($nageuse): ?>
                <li class="list-group-item"><strong>Nageuse :</strong> <?= htmlspecialchars($nageuse->getName() ?? '') ?></li>
            <?php endif; ?>
            <?php if (!empty($reservation['user'])): ?>
                <li class="list-group-item"><strong>Réservant :</strong> <?= htmlspecialchars($reservation['user']['prenom'] ?? '') ?> <?= htmlspecialchars($reservation['user']['nom'] ?? '') ?> (<?= htmlspecialchars($reservation['user']['email'] ?? '') ?>)</li>
            <?php endif; ?>
            <?php if (!empty($reservation['reservation_detail'])): ?>
                <li class="list-group-item">
                    <strong>Participants :</strong>
                    <ul>
                        <?php foreach ($reservation['reservation_detail'] as $i => $detail): ?>
                            <li>
                                <?= htmlspecialchars(($detail['prenom'] ?? '') . ' ' . ($detail['nom'] ?? '')) ?>
                                <?php if (!empty($detail['tarif_id'])): ?>
                                    (Tarif : <em><?= htmlspecialchars($tarifsById[$detail['tarif_id']] ?? $detail['tarif_id']) ?></em> )
                                <?php endif; ?>
                                Place numéro :
                                <em>
                                    <?php
                                    if (!empty($detail['seat_id'])) {
                                        // Récupérer le nom complet de la place
                                        $placeRepo = new \app\Repository\Piscine\PiscineGradinsPlacesRepository();
                                        $place = $placeRepo->findById($detail['seat_id']);
                                        echo $place ? htmlspecialchars($place->getFullPlaceName()) : htmlspecialchars($detail['seat_id']);
                                    } else {
                                        echo 'Non choisie';
                                    }
                                    ?>
                                </em>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
        <div class="d-flex justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="toggleDetailsBtnBottom">Masquer</button>
        </div>
    </div>
</div>
