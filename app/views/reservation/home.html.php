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
                            <p>
                                <strong>
                                    <?= $nbSessions > 1 ? 'Séances' : 'Séance' ?> :
                                </strong>
                                <?php
                                if ($nbSessions > 0) {
                                    usort($sessions, fn($a, $b) => $a->getEventStartAt() <=> $b->getEventStartAt());
                                    if ($nbSessions === 1) {
                                        echo $sessions[0]->getEventStartAt()->format('d/m/Y H:i');
                                    } else {
                                        echo '<ul class="mb-0">';
                                        foreach ($sessions as $session) {
                                            echo '<li>' . htmlspecialchars($session->getSessionName() ?? '') . ' : ' . $session->getEventStartAt()->format('d/m/Y H:i') . '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                } else {
                                    echo "Non défini";
                                }
                                ?>
                            </p>


                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="/assets/js/reservation.js" defer></script>