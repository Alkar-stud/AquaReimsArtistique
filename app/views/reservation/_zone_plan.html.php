<?php
// Organisation des places par rang
$placesByRank = [];
foreach ($places as $place) {
    $placesByRank[$place->getRankInZone()][] = $place;
}
ksort($placesByRank); // Rang 0 en haut
?>
<div class="zone-detail" id="zone-detail-<?= $zone->getId() ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>Zone <?= htmlspecialchars($zone->getZoneName()) ?></strong>
        <button type="button" class="btn btn-secondary btn-sm retour-zones">Retour aux zones</button>
    </div>
    <div class="legende d-flex flex-wrap gap-3 mb-2">
        <div class="case placeLibre"><span class="me-1"></span>Place libre</div>
        <div class="case placePMR"><span class="me-1"></span>Place à mobilité réduite</div>
        <div class="case placePris"><span class="me-1"></span>Place déjà réservée</div>
        <div class="case placeClosed"><span class="me-1"></span>Place non disponible</div>
        <div class="case placeVIP"><span class="me-1"></span>Place VIP</div>
        <div class="case placeBenevole"><span class="me-1"></span>Place bénévole</div>
        <div class="case placeTemp"><span class="me-1"></span>Place en cours de réservation</div>
    </div>
    <div id="etape5Alert"></div>
    <div class="zone-plan overflow-auto" style="max-width:100vw;">
        <table class="table table-bordered text-center align-middle mb-0 zone-plan" style="min-width: 400px;">
            <tbody>
            <?php foreach ($placesByRank as $rank => $placesRow): ?>
                <tr>
                    <?php foreach ($placesRow as $place):
                        $classes = [];
                        $disabled = false;
                        $reason = 'Place libre';
                        if (!$place->isOpen()) {
                            $classes[] = 'tdplaceClosed';
                            $disabled = true;
                            $reason = 'Place fermée';
                        } elseif (in_array($place->getId(), $placesReservees ?? [])) {
                            $classes[] = 'tdplacePris';
                            $disabled = true;
                            $reason = 'Déjà réservée';
                        } elseif ($place->isVip()) {
                            $classes[] = 'tdplaceVIP';
                            $disabled = true;
                            $reason = 'Réservée VIP';
                        } elseif ($place->isVolunteer()) {
                            $classes[] = 'tdplaceBenevole';
                            $disabled = true;
                            $reason = 'Réservée bénévole';
                        } elseif ($place->isPmr()) {
                            $classes[] = 'tdplacePMR';
                            $reason = 'Accessible PMR';
                        }
                        //Vérification si les places sont en cours de réservation
                        if (array_key_exists($place->getId(), $placesSessions)) {
                            //Si la session est la session courante, case cliquable, on change la couleur
                            //Sinon c'est la session de quelqu'un d'autre, case non cliquable, on met une couleur différente avec en $reason en cours de réservation.
                            if ($placesSessions[$place->getId()] == session_id()) {
                                $classes[] = 'tdplaceTempSession';
                                $reason = 'En cours pour vous';
                            } else {
                                $classes[] = 'tdplaceTemp';
                                $disabled = true;
                                $reason = 'En cours de réservation';
                            }
                        }

                        $btnClass = $disabled ? 'btn-secondary' : '';
                        ?>
                        <td class="<?= implode(' ', $classes) ?>">
                            <?php if ($disabled): ?>
                                <span class="seat btn <?= $btnClass ?> mb-1" style="opacity:0.7;" title="<?= htmlspecialchars($reason) ?>">
                                    <?= $place->getFullPlaceName() ?>
                                </span>
                            <?php else: ?>
                                <button type="button"
                                        class="seat btn <?= $btnClass ?> mb-1"
                                        data-seat="<?= $place->getId() ?>"
                                        onclick="toggleSeat(this)"
                                        title="<?= htmlspecialchars($reason) ?>">
                                    <?= $place->getFullPlaceName() ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($zone->getComments()): ?>
        <div class="text-center text-muted small mt-2"><?= htmlspecialchars($zone->getComments()) ?></div>
    <?php endif; ?>
</div>