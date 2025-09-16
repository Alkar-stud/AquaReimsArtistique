<?php
// Variables attendues : $reservation, $reservationDetails, $reservationComplements, $event, $session, $tarifsByIdObj

$canBeModified = new DateTime() < $reservation->getTokenExpireAt();
$canBeModified = !$reservation->isCanceled();
?>
    <div class="container"
         id="reservation-data-container"
         data-reservation-id="<?= $reservation->getId() ?>"
         data-token="<?= $reservation->getToken() ?>"
    >
        <h3 class="mb-3">Récapitulatif de la réservation</h3>
        <?php if (!$canBeModified): ?>
            <div class="alert alert-warning"><b>La modification n'est plus possible car le lien a expiré.</b></div>
        <?php endif; ?>

        <fieldset <?= !$canBeModified ? 'disabled' : '' ?>>
            <legend class="fs-5">Numéro d'enregistrement : <b><?= $reservation->getId() ?></b></legend>

            <?php if ($reservation->getTokenExpireAt()): ?>
                <p>Modification possible jusqu'au : <u><?= $reservation->getTokenExpireAt()->format('d/m/Y à H:i') ?></u></p>
            <?php endif; ?>

            <h4 class="mb-3 mt-4">Détails de la réservation</h4>
            <ul class="list-group mb-3">
                <li class="list-group-item"><strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? '') ?></li>
                <?php if ($session): ?>
                    <li class="list-group-item"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?> à la piscine <i><?= htmlspecialchars($event->getPiscine()->getLibelle() ?? '') ?></i></li>
                <?php endif; ?>

                <li class="list-group-item">
                    <div class="row align-items-center">
                        <div class="col-lg-2"><strong>Réservant :</strong></div>
                        <div class="col-lg-10" id="contact-fields-container">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Nom</span>
                                        <input type="text" class="form-control editable-contact" data-field="nom" value="<?= htmlspecialchars($reservation->getNom() ?? '') ?>" aria-label="Nom">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Prénom</span>
                                        <input type="text" class="form-control editable-contact" data-field="prenom" value="<?= htmlspecialchars($reservation->getPrenom() ?? '') ?>" aria-label="Prénom">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-12 col-lg-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Email</span>
                                        <input type="email" class="form-control editable-contact" data-field="email" value="<?= htmlspecialchars($reservation->getEmail() ?? '') ?>" aria-label="Email">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-12 col-lg-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Téléphone</span>
                                        <input type="tel" class="form-control editable-contact" data-field="phone" value="<?= htmlspecialchars($reservation->getPhone() ?? '') ?>" aria-label="Téléphone" placeholder="Facultatif">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <?php if (!empty($reservationDetails)): ?>
                <div class="list-group mb-3">
                    <h5 class="mt-4">Places assises</h5>
                    <div class="list-group mb-3">
                        <?php
                        // On regroupe les participants par tarif pour les afficher sous le bon pack
                        $detailsByTarif = [];
                        foreach ($reservationDetails as $detail) {
                            $detailsByTarif[$detail->getTarif()][] = $detail;
                        }

                        // On boucle sur les quantités de tarifs (packs).
                        foreach ($tarifQuantities as $tarifId => $quantity):
                            $tarifObj = $tarifsByIdObj[$tarifId] ?? null;
                            if (!$tarifObj) continue;

                            $unitPrice = $tarifObj->getPrice();
                            $totalPrice = $quantity * $unitPrice;
                            $detailsGroup = $detailsByTarif[$tarifId] ?? [];
                            ?>
                            <div class="list-group-item">
                                <!-- Ligne de résumé pour le tarif -->
                                <div class="d-flex justify-content-between align-items-center fw-bold mb-2">
                                 <span>
                                     <?= $quantity ?> x <?= htmlspecialchars($tarifObj->getLibelle()) ?>
                                     <small class="fw-normal text-muted">
                                        (<?= $tarifObj->getDescription() ?>)
                                        (<?= number_format($unitPrice / 100, 2, ',', ' ') ?> €)
                                     </small>
                                 </span>
                                    <span><?= number_format($totalPrice / 100, 2, ',', ' ') ?> €</span>
                                </div>

                                <!-- Lignes de détail pour chaque participant de ce tarif -->
                                <?php foreach ($detailsGroup as $detail): ?>
                                    <div class="row gx-2 gy-1 align-items-center mb-2 ps-md-3 participant-row">
                                        <div class="col-lg-8">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">Nom</span>
                                                        <input type="text" class="form-control editable-detail" data-detail-id="<?= $detail->getId() ?>" data-field="nom" value="<?= htmlspecialchars($detail->getNom() ?? '') ?>" aria-label="Nom du participant">
                                                        <span class="input-group-text feedback-span"></span>
                                                    </div>
                                                </div>                                            <div class="col-md-6">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">Prénom</span>
                                                        <input type="text" class="form-control editable-detail" data-detail-id="<?= $detail->getId() ?>" data-field="prenom" value="<?= htmlspecialchars($detail->getPrenom() ?? '') ?>" aria-label="Prénom du participant">
                                                        <span class="input-group-text feedback-span"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <?php if ($detail->getPlaceObject()): ?>
                                                <small class="text-muted">Place :</small>
                                                <strong><?= htmlspecialchars($detail->getPlaceObject()->getFullPlaceName()) ?></strong>
                                            <?php else: ?>
                                                <small class="text-muted">Pas de place assignée</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; // Fin de la boucle par groupe de tarif ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($reservationComplements)): ?>
                <h5 class="mt-4">Compléments</h5>
                <div class="list-group mb-3">
                    <?php foreach ($reservationComplements as $item): ?>
                        <?php
                        $tarif = $tarifsByIdObj[$item->getTarif()] ?? null;
                        $qty = (int)$item->getQty();
                        $subtotal = $tarif ? ($tarif->getPrice() * $qty) : 0;
                        ?>
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                 <div class="col-md-6">
                                     <span class="fw-bold">
                                        <?= htmlspecialchars($tarif ? $tarif->getLibelle() : 'Tarif inconnu') ?>
                                        <small class="text-muted">(<?= number_format($tarif->getPrice() / 100, 2, ',', ' ') ?> €)</small>
                                     </span>
                                 </div>
                                 <div class="col-md-6 d-flex justify-content-end align-items-center">
                                        <div class="input-group input-group-sm" style="max-width: 100px;">
                                            <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="minus" data-complement-id="<?= $item->getId() ?>">-</button>
                                            <input type="text" class="form-control text-center" id="qty-complement-<?= $item->getId() ?>" value="<?= $qty ?>" readonly>
                                            <?php if ($item->getQty() > $tarif->getMaxTickets()): ?>
                                            <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="plus" data-complement-id="<?= $item->getId() ?>">+</button>
                                            <?php endif; ?>
                                        </div>
                                        <span class="fw-bold text-nowrap ps-3" id="subtotal-complement-<?= $item->getId() ?>"><?= number_format($subtotal / 100, 2, ',', ' ') ?> €</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($availableComplements)): ?>
                <h5 class="mt-4">Ajouter des articles</h5>
                <div class="list-group mb-3">
                    <?php foreach ($availableComplements as $tarif): ?>
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-12 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><?= htmlspecialchars($tarif->getLibelle()) ?> <small class="text-muted">(<?= number_format($tarif->getPrice() / 100, 2, ',', ' ') ?> €)</small></span>
                                    <button class="btn btn-success btn-sm add-complement-btn" type="button" data-tarif-id="<?= $tarif->getId() ?>">
                                        <i class="bi bi-plus-circle"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            $amountDue = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();
            ?>
            <div class="card mt-4" id="totals-card">
                <div class="card-body text-end">
                    <?php if($amountDue > 0): ?>
                    <div class="fs-5">Nouveau total : <strong id="new-total-amount"><?= number_format($reservation->getTotalAmount() / 100, 2, ',', ' ') ?> €</strong></div>
                    <?php endif; ?>
                    <div class="text-success">Déjà payé : <strong id="total-paid-amount"><?= number_format($reservation->getTotalAmountPaid() / 100, 2, ',', ' ') ?> €</strong></div>
                    <hr>
                    <div id="amount-due-container" class="fs-4 fw-bold">
                        <?php
                        if ($amountDue > 0) {
                            ?>
                            Reste à payer : <span class="text-danger" id="amount-due"><?= number_format($amountDue / 100, 2, ',', ' '); ?> €</span>
                            <button class="btn btn-danger" onClick="alert('Fonctionnalité à implémenter : Payer le reste à payer');">Payer</button>
                        <?php
                        } elseif ($amountDue < 0) {
                            echo 'Crédit disponible : <span class="text-info" id="amount-due">' . number_format(abs($amountDue) / 100, 2, ',', ' ') . ' €</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <br>
            <div class="d-flex justify-content-end">
                <button class="btn btn-warning cancel-button">Annuler la réservation</button>
            </div>
        </fieldset>
    </div>


<?php if ($canBeModified): ?>
    <script src="/assets/js/reservation_common.js" defer></script>
    <script src="/assets/js/reservation_modif_data.js" defer></script>
<?php endif; ?>