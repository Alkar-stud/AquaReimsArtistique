<div class="container">
    <h1 class="mb-4">Réservations</h1>

    <?php if (empty($events)): ?>
        <div class="alert alert-info">
            Aucun événement à venir pour le moment.
        </div>
    <?php else: ?>
        <div class="row">
            <?php if (!empty($_GET['session_expiree'])): ?>
                <div class="alert alert-warning">
                    Votre session a expiré. Merci de recommencer votre réservation.
                </div>
            <?php endif; ?>
            <?php foreach ($events as $event): ?>
                <?php
                $sessions = $event->getSessions();
                $nbSessions = count($sessions);
                $periodeOuverte = $periodesOuvertes[$event->getId()] ?? null;
                $nextPublic = $nextPublicOuvertures[$event->getId()] ?? null;
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
                                    <strong>Je choisis la nageuse que je viens surtout voir (mais aussi les autres ^^) :</strong>
                                    <select id="groupe_nageuses_<?= $event->getId() ?>" class="form-select d-inline w-auto ms-2" onchange="updateNageuses(this.value, <?= $event->getId() ?>)">
                                        <option value="">Sélectionner un groupe</option>
                                        <?php foreach ($groupes as $groupe): ?>
                                            <option value="<?= $groupe->getId() ?>">
                                                <?= htmlspecialchars($groupe->getLibelle()) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p id="nageuse_container_<?= $event->getId() ?>" style="display: <?= ($selectedNageuse ? 'block' : 'none'); ?>">
                                    <strong>Nageuse :</strong>
                                    <select id="nageuse_<?= $event->getId() ?>" class="form-select d-inline w-auto ms-2">
                                        <option value="">Sélectionner une nageuse</option>
                                        <?php
                                        if ($selectedGroupe && isset($nageusesParGroupe[$selectedGroupe])) {
                                            foreach ($nageusesParGroupe[$selectedGroupe] as $nageuse) {
                                                echo "<option value=\"{$nageuse['id']}\">{$nageuse['nom']}</option>";
                                            }
                                        }
                                        ?>
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
                                                   value="<?= $session->getId() ?>"
                                                <?= ($session->getId() == $selectedSession) ? 'checked' : '' ?>>
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

                            <?php
                            $periodeOuverte = $periodesOuvertes[$event->getId()] ?? null;
                            $nextPublic = $nextPublicOuvertures[$event->getId()] ?? null;
                            $codeNecessaire = $periodeOuverte && $periodeOuverte->getAccessCode() !== null;
                            if ($periodeOuverte && !$codeNecessaire): ?>
                                <!-- Période ouverte sans code : bouton réservation direct -->
                                <button
                                        class="btn btn-success mt-3"
                                        id="btn_reserver_<?= $event->getId() ?>"
                                        onclick="validerFormulaireReservation(<?= $event->getId() ?>)"
                                >Réserver</button>
                            <?php else: ?>
                                <div class="alert alert-secondary mt-3">
                                    <?php if ($periodeOuverte && $codeNecessaire): ?>
                                        <div class="mb-2">
                                            <label for="access_code_input_<?= $event->getId() ?>"><strong>Code d'accès requis :</strong></label>
                                            <input type="text" id="access_code_input_<?= $event->getId() ?>" class="form-control d-inline w-auto ms-2" />
                                            <button class="btn btn-primary ms-2" onclick="validerCodeAcces(<?= $event->getId() ?>)">Valider le code</button>
                                            <span id="access_code_status_<?= $event->getId() ?>" class="ms-2 text-danger"></span>
                                        </div>
                                    <?php else: ?>
                                        Les inscriptions ne sont pas ouvertes pour cet événement.
                                    <?php endif; ?>
                                    <?php if ($nextPublic): ?>
                                        <br>
                                        Ouverture à tous :
                                        <strong><?= $nextPublic->getStartRegistrationAt()->format('d/m/Y H:i') ?></strong>
                                    <?php endif; ?>
                                </div>
                                <button
                                        class="btn btn-success mt-3"
                                        id="btn_reserver_<?= $event->getId() ?>"
                                        onclick="validerFormulaireReservation(<?= $event->getId() ?>)"
                                    <?= $codeNecessaire || !$periodeOuverte ? 'disabled' : '' ?>
                                >Réserver</button>
                            <?php endif; ?>
                            <div id="formulaire_reservation_<?= $event->getId() ?>" style="display:none;" class="mt-3"></div>
                            <div id="form_error_message_<?= $event->getId() ?>" class="text-danger mt-2"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
    window.nageusesParGroupe = <?= json_encode($nageusesParGroupe) ?>;
    window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
</script>
<script src="/assets/js/reservation_common.js" defer></script>
<script src="/assets/js/reservation_etape1.js" defer></script>
