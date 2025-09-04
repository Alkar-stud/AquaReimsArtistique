<?php
$delaiToDisplay = 4; // délai par défaut en jour pour la fin d'affichage de la page d'accueil
?>
<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<style>
    /* Styles pour rendre le tableau responsive sur mobile */
    @media screen and (max-width: 768px) {
        .responsive-table thead {
            display: none; /* On cache les en-têtes classiques */
        }
        .responsive-table tr {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            border-radius: .25rem;
            padding: .5rem;
        }
        .responsive-table td {
            display: block; /* Chaque cellule prend toute la largeur */
            text-align: right; /* Aligne la donnée à droite */
            padding: .5rem;
            border: none;
            border-bottom: 1px solid #dee2e6;
            position: relative;
        }
        .responsive-table td:last-child {
            border-bottom: none;
        }
        .responsive-table td:before {
            content: attr(data-label); /* Affiche le label */
            position: absolute;
            left: .5rem;
            text-align: left;
            font-weight: bold;
        }
    }
</style>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Gestion de la page d'accueil</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-2"></i>Ajouter un contenu
        </button>
    </div>

    <div class="btn-group mb-4" role="group">
        <a href="/gestion/accueil" class="btn <?= ($searchParam ?? 'displayed') === 'displayed' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Contenus à venir
        </a>
        <a href="/gestion/accueil/list/0" class="btn <?= ($searchParam ?? '') === '0' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Tous les contenus (y compris passés)
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle responsive-table">
            <thead class="table-light">
            <tr>
                <th scope="col">Gala associé</th>
                <th scope="col">Fin d'affichage</th>
                <th scope="col" class="text-center" style="width: 100px;">Affiché ?</th>
                <th scope="col" style="width: 130px;">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($accueil)): ?>
                <tr>
                    <td colspan="4" class="text-center">Aucun contenu à afficher.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($accueil as $item): ?>
                    <tr>
                        <td data-label="Gala associé">
                            <?= $item->getEventObject() ? $item->getEventObject()->getLibelle() : 'Aucun gala associé' ?>
                        </td>
                        <td data-label="Fin d'affichage"><?= $item->getDisplayUntil()->format('d/m/Y H:i') ?></td>
                        <td data-label="Affiché :">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input status-toggle"
                                       type="checkbox"
                                       role="switch"
                                       data-id="<?= $item->getId() ?>"
                                       id="status-switch-<?= $item->getId() ?>"
                                    <?= $item->isDisplayed() ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-primary btn-sm w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal-<?= $item->getId() ?>">
                                <i class="bi bi-pencil-square me-1"></i> Modifier
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modale d'Ajout -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form method="POST" action="/gestion/accueil/add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">Ajouter un nouveau contenu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="add_event" class="form-label">Gala associé</label>
                                <select name="event" id="add_event" class="form-select" required>
                                    <option value="0">Aucun gala associé</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event->getId() ?>" data-event-id="<?= $event->getId() ?>">
                                            <?= htmlspecialchars($event->getLibelle()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="add_display_until" class="form-label">Afficher jusqu'au</label>
                                <input type="datetime-local" id="add_display_until" name="display_until" class="form-control" required>
                            </div>

                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="add_is_displayed" name="is_displayed" value="1" checked>
                                    <label class="form-check-label" for="add_is_displayed">Afficher ce contenu sur la page d'accueil</label>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="mb-3">
                        <label for="add_content" class="form-label">Contenu</label>
                        <textarea name="content" id="add_content" class="form-control ckeditor-textarea" rows="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modales d'Édition -->
<?php foreach ($accueil as $item): ?>
    <div class="modal fade" id="editModal-<?= $item->getId() ?>" tabindex="-1" aria-labelledby="editModalLabel-<?= $item->getId() ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <form method="POST" action="/gestion/accueil/edit">
                    <input type="hidden" name="id" value="<?= $item->getId() ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel-<?= $item->getId() ?>">Modifier le contenu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="event-<?= $item->getId() ?>" class="form-label">Gala associé</label>
                                <select name="event" id="event-<?= $item->getId() ?>" class="form-select" required>
                                    <option value="0">Aucun gala associé</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event->getId() ?>" <?= $item->getEvent() == $event->getId() ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($event->getLibelle()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="display_until-<?= $item->getId() ?>" class="form-label">Afficher jusqu'au</label>
                                <input type="datetime-local" id="display_until-<?= $item->getId() ?>" name="display_until" class="form-control" value="<?= $item->getDisplayUntil()->format('Y-m-d\TH:i') ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_displayed-<?= $item->getId() ?>" name="is_displayed" value="1" <?= $item->isDisplayed() ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_displayed-<?= $item->getId() ?>">Afficher ce contenu sur le page d'accueil</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="content-<?= $item->getId() ?>" class="form-label">Contenu</label>
                            <textarea name="content" id="content-<?= $item->getId() ?>" class="form-control ckeditor-textarea" rows="10"><?= htmlspecialchars($item->getContent() ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <!-- Bouton supprimer en option -->
                        <a href="/gestion/accueil/delete/<?= $item->getId() ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce contenu ?');">Supprimer</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        //Pour gérer le siwtch pour le statut d'affichage
        document.querySelectorAll('.status-toggle').forEach(toggle => {
            toggle.addEventListener('change', function () {
                const itemId = this.dataset.id;
                const newStatus = this.checked;

                // On désactive le switch pour éviter les double clics pendant la requête
                this.disabled = true;

                fetch('/gestion/accueil/toggle-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ id: itemId, status: newStatus })
                })
                    .then(response => {
                        // Une fois la requête terminée, on recharge la page.
                        // Le contrôleur a déjà préparé le message flash en session.
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        // En cas d'erreur, on réactive le switch et on alerte l'utilisateur.
                        this.disabled = false;
                        alert('Une erreur de communication est survenue. Veuillez réessayer.');
                    });
            });
        });


        // Récupération des données de sessions préchargées et du délai configuré pour préremplir display_until
        const eventSessions = <?= json_encode($eventSessions ?? []); ?>;
        const delaiToDisplay = <?= $delaiToDisplay ?>;

        // Fonction pour calculer la date d'affichage à partir d'une date de session
        function calculateDisplayDate(sessionDate) {
            // Convertir la date de la dernière session en objet Date
            const lastSessionDate = new Date(sessionDate);

            // Ajouter le délai configuré en jours
            lastSessionDate.setDate(lastSessionDate.getDate() + delaiToDisplay);

            // Formatter la date au format attendu par l'input datetime-local
            const year = lastSessionDate.getFullYear();
            const month = String(lastSessionDate.getMonth() + 1).padStart(2, '0');
            const day = String(lastSessionDate.getDate()).padStart(2, '0');
            const hours = String(lastSessionDate.getHours()).padStart(2, '0');
            const minutes = String(lastSessionDate.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // Gestion du formulaire d'ajout
        const addEventSelect = document.getElementById('add_event');
        const addDisplayUntilInput = document.getElementById('add_display_until');

        if (addEventSelect && addDisplayUntilInput) {
            addEventSelect.addEventListener('change', function() {
                const eventId = this.value;

                // Si "Aucun gala associé" est sélectionné, on ne fait rien
                if (eventId === 0) {
                    return;
                }

                // Utiliser les données préchargées pour mettre à jour la date
                if (eventSessions[eventId]) {
                    addDisplayUntilInput.value = calculateDisplayDate(eventSessions[eventId]);
                }
            });
        }

        // Gestion des formulaires de modification
        document.querySelectorAll('[id^="event-"]').forEach(select => {
            select.addEventListener('change', function() {
                const eventId = this.value;

                // Si "Aucun gala associé" est sélectionné, on ne fait rien
                if (eventId === 0) {
                    return;
                }

                // Extraire l'ID de l'élément à partir de l'ID du select (format: "event-123")
                const itemId = this.id.split('-')[1];
                const displayUntilInput = document.getElementById(`display_until-${itemId}`);

                // Mettre à jour la date d'affichage si on a les données nécessaires
                if (displayUntilInput && eventSessions[eventId]) {
                    displayUntilInput.value = calculateDisplayDate(eventSessions[eventId]);
                }
            });
        });
    });
</script>