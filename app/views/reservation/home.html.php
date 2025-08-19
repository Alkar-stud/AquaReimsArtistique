<div class="container">
    <h1 class="mb-4">Réservations</h1>

    <?php if (empty($events)): ?>
        <div class="alert alert-info">
            Aucun événement à venir pour le moment.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($events as $event): ?>
                <?php
                $sessions = $event->getSessions();
                $nbSessions = count($sessions);
                ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title"><?= htmlspecialchars($event->getLibelle()) ?></h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Lieu :</strong> <?= htmlspecialchars($event->getPiscine()->getLibelle() ?? 'Non défini') ?></p>
                            <?php if ($event->getLimitationPerSwimmer() !== null): ?>
                                <p>
                                    <strong>Je choisis la nageuse que je viens applaudir :</strong>
                                    <select id="groupe_nageuses_<?= $event->getId() ?>" class="form-select d-inline w-auto ms-2" onchange="updateNageuses(this.value, <?= $event->getId() ?>)">
                                        <option value="">Sélectionner un groupe</option>
                                        <?php foreach ($groupes as $groupe): ?>
                                            <option value="<?= $groupe->getId() ?>"><?= htmlspecialchars($groupe->getLibelle()) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p id="nageuse_container_<?= $event->getId() ?>" style="display: none;">
                                    <strong>Nageuse :</strong>
                                    <select id="nageuse_<?= $event->getId() ?>" class="form-select d-inline w-auto ms-2">
                                        <option value="">Sélectionner une nageuse</option>
                                    </select>
                                </p>
                            <?php endif; ?>

                            <!-- Pour afficher les séances -->
                            <?php if ($nbSessions > 0): ?>
                                <p>
                                <strong>
                                    <?= $nbSessions > 1 ? 'Séances' : 'Séance unique' ?> :
                                </strong>
                                <?php if ($nbSessions === 1): ?>
                                    <input type="radio" name="session_<?= $event->getId() ?>" id="session_<?= $event->getId() ?>_<?= $sessions[0]->getId() ?>" value="<?= $sessions[0]->getId() ?>" CHECKED>
                                    <?= $sessions[0]->getEventStartAt()->format('d/m/Y H:i') ?>
                                <?php else: ?>
                                    <?php foreach ($sessions as $session): ?>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="session_<?= $event->getId() ?>"
                                                   id="session_<?= $event->getId() ?>_<?= $session->getId() ?>"
                                                   value="<?= $session->getId() ?>">
                                            <label class="form-check-label" for="session_<?= $event->getId() ?>_<?= $session->getId() ?>">
                                                <?= htmlspecialchars($session->getSessionName() ?? '') ?> : <?= $session->getEventStartAt()->format('d/m/Y H:i') ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p><strong>Séance :</strong> Non défini</p>
                            <?php endif; ?>

                            <button class="btn btn-success mt-3" onclick="validerFormulaireReservation(<?= $event->getId() ?>)">Réserver</button>
                            <div id="formulaire_reservation_<?= $event->getId() ?>" style="display:none;" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
    window.nageusesParGroupe = <?= json_encode($nageusesParGroupe) ?>;
</script>
<script src="/assets/js/reservation.js" defer></script>