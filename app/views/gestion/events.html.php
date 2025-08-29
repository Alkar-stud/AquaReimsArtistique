<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="container-fluid">
    <h2 class="mb-4">Gestion des événements</h2>

    <!-- Version mobile : Liste d'événements -->
    <div class="d-md-none mb-4">
        <button type="button" class="btn btn-success btn-sm w-100 mb-3" onclick="openEventModal('add')">
            Ajouter un événement
        </button>

        <?php if (!empty($data['events'])): ?>
            <ul class="list-group mb-3">
                <?php foreach ($data['events'] as $event): ?>
                    <li class="list-group-item event-row" data-id="<?= $event->getId() ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5><?= htmlspecialchars($event->getLibelle()) ?></h5>
                            <div>
                                <button class="btn btn-secondary btn-sm" onclick="openEventModal('edit', <?= $event->getId() ?>)">
                                    Modifier
                                </button>
                                <a href="/gestion/events/delete/<?= $event->getId() ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                                    Supprimer
                                </a>
                            </div>
                        </div>
                        <div>
                            <p>
                                <strong>Lieu:</strong> <?= $event->getPiscine() ? htmlspecialchars($event->getPiscine()->getLibelle()) : '?' ?><br>
                                <strong>Date:</strong>
                                <?php
                                $sessions = $event->getSessions();
                                if (!empty($sessions)) {
                                    // On prend la première séance (la plus proche)
                                    usort($sessions, fn($a, $b) => $a->getEventStartAt() <=> $b->getEventStartAt());
                                    echo $sessions[0]->getEventStartAt()->format('d/m/Y H:i');
                                } else {
                                    echo "Non défini";
                                }
                                ?>
                                <br>
                                <strong>1ère ouverture inscriptions:</strong>
                                <?php
                                $nextDate = null;
                                foreach ($event->getInscriptionDates() as $date) {
                                    if (!$nextDate || $date->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                                        $nextDate = $date;
                                    }
                                }
                                echo $nextDate ? $nextDate->getStartRegistrationAt()->format('d/m/Y H:i') : "Aucune ouverture prévue";
                                ?>
                                <br>
                                <strong>Nombre de tarifs</strong> <?php echo count($event->getTarifs()); ?>
                            </p>

                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-center">Aucun événement enregistré</p>
        <?php endif; ?>
    </div>

    <!-- Version desktop : Tableau d'événements -->
    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle text-center">
            <thead>
            <tr>
                <th>Libellé</th>
                <th>Lieu</th>
                <th>1ère date de l'événement</th>
                <th>1ère ouverture inscriptions</th>
                <th>Nombre de tarifs</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <form id="quickAddForm" class="d-flex align-items-center">
                    <td><input type="text" class="form-control" id="quickAdd_libelle" placeholder="Libellé" required></td>
                    <td>
                        <select class="form-select" id="quickAdd_lieu" required>
                            <option value="">Sélectionner</option>
                            <?php foreach ($data['piscines'] as $piscine): ?>
                                <option value="<?= $piscine->getId() ?>"><?= htmlspecialchars($piscine->getLibelle()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td colspan="2" class="text-center">
                        <button type="button" class="btn btn-success" onclick="openEventModal('add', null, true)">
                            Continuer l'ajout
                        </button>
                    </td>
                </form>
            </tr>


            <?php if (!empty($data['events'])): ?>
                <?php foreach ($data['events'] as $event): ?>
                    <tr class="event-row" data-id="<?= $event->getId() ?>">
                        <td><?= htmlspecialchars($event->getLibelle()) ?></td>
                        <td><?= $event->getPiscine() ? htmlspecialchars($event->getPiscine()->getLibelle()) : '?' ?></td>
                        <td>
                            <?php
                            $sessions = $event->getSessions();
                            if (!empty($sessions)) {
                                // On prend la première séance (la plus proche)
                                usort($sessions, fn($a, $b) => $a->getEventStartAt() <=> $b->getEventStartAt());
                                echo $sessions[0]->getEventStartAt()->format('d/m/Y H:i');
                            } else {
                                echo "Non défini";
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $nextDate = null;
                            foreach ($event->getInscriptionDates() as $date) {
                                if (!$nextDate || $date->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                                    $nextDate = $date;
                                }
                            }
                            echo $nextDate ? $nextDate->getStartRegistrationAt()->format('d/m/Y H:i') : "Aucune ouverture prévue";
                            ?>
                        </td>
                        <td>
                            <?php echo count($event->getTarifs()); ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openEventModal('edit', <?= $event->getId() ?>)">
                                Modifier
                            </button>
                            <a href="/gestion/events/delete/<?= $event->getId() ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                                Supprimer
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">Aucun événement enregistré</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modale d'ajout/modification d'événement -->
    <div id="eventModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Ajouter un événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form id="eventForm" method="POST">
                    <div class="modal-body">
                        <ul class="nav nav-tabs mb-3">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#eventDetails">Informations générales</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#eventTarifs">Tarifs</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#eventInscriptions">Périodes d'inscription</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#eventSessions">Séances</a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Onglet Informations générales -->
                            <div class="tab-pane fade show active" id="eventDetails">
                                <div class="mb-3">
                                    <label class="form-label">Libellé</label>
                                    <input type="text" name="libelle" id="event_libelle" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Lieu</label>
                                    <select name="lieu" id="event_lieu" class="form-select" required>
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($data['piscines'] as $piscine): ?>
                                            <option value="<?= $piscine->getId() ?>"><?= htmlspecialchars($piscine->getLibelle()) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="event_limitation_per_swimmer">Limitation par nageur</label>
                                    <input type="number" class="form-control" id="event_limitation_per_swimmer" name="limitation_per_swimmer" min="0" placeholder="Laissez vide pour aucune limitation">
                                    <div class="form-text">Nombre maximum de personnes qu'un nageur peut inscrire (0 ou vide = aucune limitation)</div>
                                </div>
                            </div>

                            <!-- Onglet Tarifs -->
                            <div class="tab-pane fade" id="eventTarifs">
                                <div class="mb-3">
                                    <p class="text-muted">Sélectionnez les tarifs applicables à cet événement</p>
                                    <h6 class="mb-3">Tarifs avec places</h6>
                                    <div class="row row-cols-1 row-cols-md-2 g-3" id="tarifs-avec-places">
                                        <?php
                                        $hasSeparator = false;
                                        foreach ($data['tarifs'] as $tarif): ?>
                                            <?php if ($tarif->getId() === -1):
                                                $hasSeparator = true;
                                                continue; // On ne traite pas le séparateur ici
                                            endif; ?>

                                            <?php if (!$hasSeparator && $tarif->getNbPlace()): ?>
                                                <div class="col">
                                                    <div class="card">
                                                        <!-- Contenu du tarif avec places -->
                                                        <div class="card-body">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="tarifs[]" value="<?= $tarif->getId() ?>" id="tarif_<?= $tarif->getId() ?>">
                                                                <label class="form-check-label" for="tarif_<?= $tarif->getId() ?>">
                                                                    <strong><?= htmlspecialchars($tarif->getLibelle()) ?></strong>
                                                                    <span class="float-end"><?= number_format($tarif->getPrice(), 2, ',', ' ') ?> €</span>
                                                                </label>
                                                            </div>
                                                            <!-- Reste du contenu du tarif -->
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Séparateur visible dans tous les cas -->
                                    <hr class="mt-4 mb-2">
                                    <h6 class="mb-3">Tarifs sans places</h6>

                                    <div class="row row-cols-1 row-cols-md-2 g-3" id="tarifs-sans-places">
                                        <?php
                                        $hasSeparator = false;
                                        foreach ($data['tarifs'] as $tarif): ?>
                                            <?php if ($tarif->getId() === -1):
                                                $hasSeparator = true;
                                                continue;
                                            endif; ?>

                                            <?php if (!$tarif->getNbPlace()): ?>
                                                <div class="col">
                                                    <div class="card">
                                                        <!-- Contenu du tarif sans places -->
                                                        <div class="card-body">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="tarifs[]" value="<?= $tarif->getId() ?>" id="tarif_<?= $tarif->getId() ?>">
                                                                <label class="form-check-label" for="tarif_<?= $tarif->getId() ?>">
                                                                    <strong><?= htmlspecialchars($tarif->getLibelle()) ?></strong>
                                                                    <span class="float-end"><?= number_format($tarif->getPrice(), 2, ',', ' ') ?> €</span>
                                                                </label>
                                                            </div>
                                                            <!-- Reste du contenu du tarif -->
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Périodes d'inscription -->
                            <div class="tab-pane fade" id="eventInscriptions">
                                <div id="inscription-dates-container">
                                    <!-- Les périodes d'inscription seront ajoutées dynamiquement ici -->
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-3" id="add-inscription-period">
                                    <i class="bi bi-plus-circle"></i> Ajouter une période d'inscription
                                </button>

                                <!-- Template pour les périodes d'inscription -->
                                <template id="inscription-period-template">
                                    <div class="card mb-3 inscription-period">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="card-title mb-0">Période d'inscription</h6>
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-period">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Libellé</label>
                                                <input type="text" class="form-control" name="inscription_dates[__INDEX__][libelle]"
                                                       data-field="libelle" required>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Date d'ouverture</label>
                                                    <input type="datetime-local" class="form-control"
                                                           name="inscription_dates[__INDEX__][start_at]" data-field="start_at" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Date de clôture</label>
                                                    <input type="datetime-local" class="form-control"
                                                           name="inscription_dates[__INDEX__][close_at]" data-field="close_at" required>
                                                </div>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Code d'accès (optionnel)</label>
                                                <input type="text" class="form-control"
                                                       name="inscription_dates[__INDEX__][access_code]" data-field="access_code">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <!-- Gestion des séances -->
                            <div class="tab-pane fade" id="eventSessions">
                                <div id="sessions-container"></div>
                                <button type="button" class="btn btn-outline-primary mt-3" id="add-session-btn">
                                    <i class="bi bi-plus-circle"></i> Ajouter une séance
                                </button>
                                <!-- Template pour une séance -->
                                <template id="session-template">
                                    <div class="card mb-3 session-item">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="card-title mb-0">Séance</h6>
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-session">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Libellé de la séance</label>
                                                <input type="text" class="form-control" name="sessions[__INDEX__][session_name]" data-field="session_name" required>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Début de la séance</label>
                                                    <input type="datetime-local" class="form-control" name="sessions[__INDEX__][event_start_at]" data-field="event_start_at" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Ouverture des portes</label>
                                                    <input type="datetime-local" class="form-control" name="sessions[__INDEX__][opening_doors_at]" data-field="opening_doors_at" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>


                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php
//Pour le JS
$eventsArray = array_map(function($e) {
    $nextDate = null;
    $now = new DateTime();
    foreach ($e->getInscriptionDates() as $date) {
        if ($date->getStartRegistrationAt() > $now) {
            if (!$nextDate || $date->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                $nextDate = $date;
            }
        }
    }

    $sessions = $e->getSessions();
    usort($sessions, fn($a, $b) => $a->getEventStartAt() <=> $b->getEventStartAt());
    $firstSession = $sessions[0] ?? null;

    return [
        'id' => $e->getId(),
        'libelle' => $e->getLibelle(),
        'lieu' => $e->getLieu(),
        'limitation_per_swimmer' => $e->getLimitationPerSwimmer(),
        'nextOpeningDate' => $nextDate ? $nextDate->getStartRegistrationAt()->format('Y-m-d\TH:i') : null,
        'tarifCount' => count($e->getTarifs()),
        'tarifs' => array_map(function($t) {
            return $t->getId();
        }, $e->getTarifs()),
        'inscription_dates' => array_map(function($d) {
            return [
                'id' => $d->getId(),
                'libelle' => $d->getLibelle(),
                'start_at' => $d->getStartRegistrationAt()->format('Y-m-d\TH:i'),
                'close_at' => $d->getCloseRegistrationAt()->format('Y-m-d\TH:i'),
                'access_code' => $d->getAccessCode()
            ];
        }, $e->getInscriptionDates()),
        'sessions' => array_map(function($s) {
            return [
                'session_name' => $s->getSessionName(),
                'opening_doors_at' => $s->getOpeningDoorsAt()->format('Y-m-d\TH:i'),
                'event_start_at' => $s->getEventStartAt()->format('Y-m-d\TH:i')
            ];
        }, $e->getSessions()),
    ];
}, $data['events']);
?>

<script>
    window.eventsArray = <?= json_encode($eventsArray) ?>;
</script>
<script src="/assets/js/gestion_events.js"></script>
