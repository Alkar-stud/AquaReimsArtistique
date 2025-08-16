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
                                <strong>Date:</strong> <?= $event->getEventStartAt()->format('d/m/Y H:i') ?><br>
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
                <th>Date de l'événement</th>
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
                    <td><input type="datetime-local" class="form-control" id="quickAdd_event_start_at" required></td>
                    <td>-</td>
                    <td>-</td>
                    <td colspan="2" class="text-center">
                        <button type="button" class="btn btn-success" onclick="openEventModal('add', null)">
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
                        <td><?= $event->getEventStartAt()->format('d/m/Y H:i') ?></td>
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
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date de l'événement</label>
                                        <input type="datetime-local" name="event_start_at" id="event_start_at" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ouverture des portes</label>
                                        <input type="datetime-local" name="opening_doors_at" id="event_opening_doors_at" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="event_limitation_per_swimmer">Limitation par nageur</label>
                                    <input type="number" class="form-control" id="event_limitation_per_swimmer" name="limitation_per_swimmer" min="0" placeholder="Laissez vide pour aucune limitation">
                                    <div class="form-text">Nombre maximum de personnes qu'un nageur peut inscrire (0 ou vide = aucune limitation)</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Événement associé (optionnel)</label>
                                    <select name="associate_event" id="event_associate_event" class="form-select">
                                        <option value="">Aucun</option>
                                        <?php foreach ($data['events'] as $associateEvent): ?>
                                            <option value="<?= $associateEvent->getId() ?>"><?= htmlspecialchars($associateEvent->getLibelle()) ?></option>
                                        <?php endforeach; ?>
                                    </select>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables pour la gestion des modales et des formulaires
        const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
        const eventForm = document.getElementById('eventForm');
        const inscriptionContainer = document.getElementById('inscription-dates-container');
        const periodTemplate = document.getElementById('inscription-period-template');
        let periodCount = 0;

        // Gestion des périodes d'inscription
        document.getElementById('add-inscription-period').addEventListener('click', function() {
            addInscriptionPeriod();
        });

        // Délégation d'événement pour supprimer une période
        inscriptionContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-period')) {
                e.target.closest('.inscription-period').remove();
            }
        });

        // Fonction pour ouvrir la modale d'événement (ajout ou édition)
        window.openEventModal = function(mode, eventId = null) {
            const isQuickAdd = typeof eventId === 'boolean' && eventId === true;

            // Réinitialiser le formulaire
            eventForm.reset();
            inscriptionContainer.innerHTML = '';
            periodCount = 0;

            if (mode === 'add') {
                document.getElementById('eventModalTitle').textContent = 'Ajouter un événement';
                eventForm.action = '/gestion/events/add';

                // Si c'est un ajout rapide, préremplir les champs
                if (isQuickAdd) {
                    document.getElementById('event_libelle').value = document.getElementById('quickAdd_libelle').value;
                    document.getElementById('event_lieu').value = document.getElementById('quickAdd_lieu').value;
                    document.getElementById('event_start_at').value = document.getElementById('quickAdd_event_start_at').value;
                    document.getElementById('event_opening_doors_at').value = document.getElementById('quickAdd_opening_doors_at').value;
                }
            } else {
                document.getElementById('eventModalTitle').textContent = 'Modifier l\'événement';
                eventForm.action = '/gestion/events/update/' + eventId;

                // Charger les données de l'événement
                fetchEventData(eventId);
            }

            eventModal.show();
        };

        // Fonction pour ajouter une période d'inscription
        function addInscriptionPeriod(data = null) {
            const template = periodTemplate.content.cloneNode(true);
            const inputs = template.querySelectorAll('input, select');

            // Remplacer l'index par le nombre actuel
            inputs.forEach(input => {
                const name = input.getAttribute('name') || '';
                input.setAttribute('name', name.replace('__INDEX__', periodCount));

                // Remplir les données si disponibles
                if (data) {
                    const fieldName = input.getAttribute('data-field');
                    if (fieldName && data[fieldName] !== undefined) {
                        input.value = data[fieldName];
                    }
                }
            });

            inscriptionContainer.appendChild(template);
            periodCount++;
        }

        // Fonction pour récupérer les données d'un événement pour édition
        async function fetchEventData(eventId) {
            const events = <?= json_encode(array_map(function($e) {
                $nextDate = null;
                $now = new DateTime();
                foreach ($e->getInscriptionDates() as $date) {
                    if ($date->getStartRegistrationAt() > $now) {
                        if (!$nextDate || $date->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                            $nextDate = $date;
                        }
                    }
                }

                return [
                    'id' => $e->getId(),
                    'libelle' => $e->getLibelle(),
                    'lieu' => $e->getLieu(),
                    'event_start_at' => $e->getEventStartAt()->format('Y-m-d\TH:i'),
                    'opening_doors_at' => $e->getOpeningDoorsAt()->format('Y-m-d\TH:i'),
                    'limitation_per_swimmer' => $e->getLimitationPerSwimmer(),
                    'associate_event' => $e->getAssociateEvent(),
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
                    }, $e->getInscriptionDates())
                ];
            }, $data['events'])) ?>;

            const event = events.find(e => e.id === eventId);

            if (event) {
                document.getElementById('event_libelle').value = event.libelle;
                document.getElementById('event_lieu').value = event.lieu;
                document.getElementById('event_start_at').value = event.event_start_at;
                document.getElementById('event_opening_doors_at').value = event.opening_doors_at;
                // Gérer la checkbox limitation_per_swimmer
                document.getElementById('event_limitation_per_swimmer').value = event.limitation_per_swimmer !== null ? event.limitation_per_swimmer : '';

                // Gestion des événements associés : filtrer la liste
                const associateEventSelect = document.getElementById('event_associate_event');

                // D'abord vider les options existantes
                while (associateEventSelect.options.length > 1) { // Garder l'option "Aucun"
                    associateEventSelect.remove(1);
                }

                // Ajouter toutes les options d'événements sauf l'événement en cours
                events.forEach(e => {
                    if (e.id !== eventId) {
                        const option = new Option(e.libelle, e.id);
                        associateEventSelect.add(option);
                    }
                });

                // Sélectionner l'événement associé si existant
                if (event.associate_event) {
                    associateEventSelect.value = event.associate_event;
                }

                // Cocher les tarifs associés
                event.tarifs.forEach(tarifId => {
                    const checkbox = document.getElementById('tarif_' + tarifId);
                    if (checkbox) checkbox.checked = true;
                });

                // Ajouter les périodes d'inscription
                event.inscription_dates.forEach(date => {
                    addInscriptionPeriod(date);
                });
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Sélectionner tous les éléments cliquables
        const eventRows = document.querySelectorAll('.event-row');

        // Variables pour la détection de double tap sur mobile
        let lastTap = 0;
        const tapDelay = 300; // délai en ms pour considérer deux taps comme un double tap

        eventRows.forEach(row => {
            // Conserver le double-clic pour desktop
            row.addEventListener('dblclick', function() {
                handleRowAction(this);
            });

            // Ajouter la gestion du double tap pour mobile
            row.addEventListener('touchend', function(e) {
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;

                if (tapLength < tapDelay && tapLength > 0) {
                    // Double tap détecté
                    e.preventDefault();
                    handleRowAction(this);
                }

                lastTap = currentTime;
            });

            // Style pour indiquer que l'élément est cliquable
            row.style.cursor = 'pointer';
        });

        // Fonction commune pour traiter l'action sur la ligne
        function handleRowAction(row) {
            const eventId = row.getAttribute('data-id');
            if (!eventId) return;

            // Ouvrir la modale en mode édition et charger les données
            window.openEventModal('edit', parseInt(eventId));
        }
    });
</script>