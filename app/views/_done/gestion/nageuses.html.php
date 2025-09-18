<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="container-fluid">
    <h2 class="mb-4">
        <?= $groupId === 'all'
            ? 'Toutes les nageuses'
            : (isset($groupeLibelle) ? "Nageuses du groupe «" . htmlspecialchars($groupeLibelle) . "»" : "Nageuses du groupe") ?>
    </h2>
    <a href="/gestion/groupes-nageuses" class="btn btn-primary btn-sm mb-3">Retour aux groupes</a>
    <!-- Affichage mobile -->
    <div class="d-md-none mb-4">
        <?php if (!empty($nageuses)): ?>
            <ul class="list-group mb-3">
                <?php foreach ($nageuses as $nageuse): ?>
                    <li class="list-group-item d-flex flex-column gap-2">
                        <form action="/gestion/nageuses/update/<?= $nageuse->getId() ?>" method="POST" class="d-flex flex-column gap-2">
                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($nageuse->getName()) ?>">
                            <select name="groupe" class="form-select" required>
                                <?php foreach ($groupes as $groupe): ?>
                                    <option value="<?= $groupe->getId() ?>" <?= $nageuse->getGroupe() == $groupe->getId() ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($groupe->getLibelle()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="d-flex gap-2">
                                <input type="hidden" name="origine-groupe" id="origine-groupe" value="<?= $groupId; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                <a href="/gestion/nageuses/delete/<?= $nageuse->getId() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette nageuse ?');">Supprimer</a>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-info">Aucune nageuse trouvée.</div>
        <?php endif; ?>
        <!-- Formulaire d’ajout mobile -->
        <button type="button" class="btn btn-success btn-sm w-100" onclick="document.getElementById('modal-ajout-nageuse').style.display='block'">Ajouter</button>
        <div id="modal-ajout-nageuse" class="modal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5);">
            <div class="modal-dialog" style="max-width:400px; margin:10vh auto; background:#fff; border-radius:8px;">
                <div class="modal-content p-3">
                    <div class="modal-header d-flex justify-content-between align-items-center">
                        <h5 class="modal-title">Ajouter une nageuse</h5>
                        <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('modal-ajout-nageuse').style.display='none'"></button>
                    </div>
                    <form action="/gestion/nageuses/add" method="POST">
                        <div class="mb-2">
                            <label>Nom <input type="text" name="name" class="form-control" required></label>
                        </div>
                        <div class="mb-2">
                            <label>Groupe
                                <select name="groupe" class="form-select" required>
                                    <?php foreach ($groupes as $groupe): ?>
                                        <option value="<?= $groupe->getId() ?>"
                                            <?= (is_numeric($groupId) && $groupId == $groupe->getId()) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($groupe->getLibelle()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-ajout-nageuse').style.display='none'">Annuler</button>
                            <button type="submit" class="btn btn-success">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Affichage desktop -->
    <div class="table-responsive d-md-block d-none">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Nom</th>
                <th>Groupe</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Formulaire d’ajout desktop -->
            <tr>
                <form action="/gestion/nageuses/add" method="POST">
                    <td><input type="text" name="name" class="form-control" required></td>
                    <td>
                        <select name="groupe" class="form-select" required>
                            <?php foreach ($groupes as $groupe): ?>
                                <option value="<?= $groupe->getId() ?>" <?= (is_numeric($groupId) && $groupId == $groupe->getId()) ? 'selected' : '' ?>><?= htmlspecialchars($groupe->getLibelle()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><button type="submit" class="btn btn-success btn-sm">Ajouter</button></td>
                </form>
            </tr>
            <?php if (!empty($nageuses)): ?>
                <?php foreach ($nageuses as $nageuse): ?>
                    <tr>
                        <form action="/gestion/nageuses/update/<?= $nageuse->getId() ?>" method="POST">
                            <td><input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($nageuse->getName()) ?>"></td>
                            <td>
                                <select name="groupe" class="form-select" required>
                                    <?php foreach ($groupes as $groupe): ?>
                                        <option value="<?= $groupe->getId() ?>" <?= $nageuse->getGroupe() == $groupe->getId() ? 'selected' : '' ?>><?= htmlspecialchars($groupe->getLibelle()) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="hidden" name="origine-groupe" id="origine-groupe" value="<?= $groupId; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                <a href="/gestion/nageuses/delete/<?= $nageuse->getId() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette nageuse ?');">Supprimer</a>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">
                        <div class="alert alert-info mb-0">Aucune nageuse trouvée.</div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>