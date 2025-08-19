<div class="container">
    <h1 class="mb-4">Réservations</h1>

    <?php if (empty($events)): ?>
        <div class="alert alert-info">
            Aucun événement à venir pour le moment.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($events as $event): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title"><?= htmlspecialchars($event->getLibelle()) ?></h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Lieu :</strong> <?= htmlspecialchars($event->getPiscine()->getLibelle() ?? 'Non défini') ?></p>
                            <p><strong>Date :</strong> <?= $event->getEventStartAt()->format('d/m/Y H:i') ?></p>

                            <form action="/reservation/details" method="post" class="reservation-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="event_id" value="<?= $event->getId() ?>">

                                <?php if (!empty($event->getAssociateEvent())): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Choisissez votre date</label>
                                        <select name="event_id" class="form-select">
                                            <option value="<?= $event->getId() ?>"><?= $event->getEventStartAt()->format('d/m/Y H:i') ?></option>
                                            <?php foreach ($event->getAssociateEvent() as $associatedEvent): ?>
                                                <option value="<?= $associatedEvent->getId() ?>">
                                                    <?= $associatedEvent->getEventStartAt()->format('d/m/Y H:i') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($event->getLimitationPerSwimmer())): ?>
                                    <div class="mb-3 swimmer-selection">
                                        <label class="form-label">Groupe de nageuses</label>
                                        <select name="groupe_id" class="form-select groupe-select" data-event-id="<?= $event->getId() ?>">
                                            <option value="">Sélectionnez un groupe</option>
                                            <?php foreach ($groupes as $groupe): ?>
                                                <option value="<?= $groupe->getId() ?>"><?= htmlspecialchars($groupe->getLibelle()) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3 swimmer-selection">
                                        <label class="form-label">Nageuse</label>
                                        <select name="nageuse_id" class="form-select nageuse-select" disabled>
                                            <option value="">Sélectionnez d'abord un groupe</option>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <input type="hidden" name="has_limitation" value="<?= !empty($event->getLimitationPerSwimmer()) ? $event->getLimitationPerSwimmer() : '0' ?>">
                                <button type="submit" class="btn btn-primary">Réserver</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="/assets/js/reservation.js" defer></script>