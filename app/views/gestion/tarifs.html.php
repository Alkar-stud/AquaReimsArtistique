<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php
$tarifs_places = array_filter($data ?? [], fn($t) => $t->getNbPlace() !== null);
$tarifs_autres = array_filter($data ?? [], fn($t) => $t->getNbPlace() === null);
$onglet = $_SESSION['onglet_tarif'] ?? 'all';
unset($_SESSION['onglet_tarif']);
?>
<ul class="nav nav-tabs mb-3" id="tarifTabs">
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'all' ? 'active' : '' ?>" id="tab-all" href="/gestion/tarifs?onglet=all">Tous</a>

    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'places' ? 'active' : '' ?>" id="tab-places" href="/gestion/tarifs?onglet=places">Places assises</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'autres' ? 'active' : '' ?>" id="tab-autres" href="/gestion/tarifs?onglet=autres">Autres</a>
    </li>
</ul>
<div id="content-places" style="<?= $onglet === 'places' ? '' : 'display:none;' ?>">
    <!-- Table ou liste pour $tarifs_places + formulaire d’ajout -->
</div>
<div id="content-autres" style="<?= $onglet === 'autres' ? '' : 'display:none;' ?>">
    <!-- Table ou liste pour $tarifs_autres + formulaire d’ajout -->
</div>
<div class="container-fluid">
    <h2 class="mb-4">Gestion des tarifs</h2>

    <!-- Affichage mobile -->
    <div class="d-md-none mb-4">
        <?php if (!empty($data)): ?>
            <ul class="list-group mb-3">
                <?php foreach ($data as $tarif): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center"
                        data-id="<?= $tarif->getId() ?>"
                        data-libelle="<?= htmlspecialchars($tarif->getLibelle()) ?>"
                        data-description="<?= htmlspecialchars($tarif->getDescription()) ?>"
                        data-nb_place="<?= htmlspecialchars($tarif->getNbPlace() ?? '') ?>"
                        data-age_min="<?= htmlspecialchars($tarif->getAgeMin() ?? '') ?>"
                        data-age_max="<?= htmlspecialchars($tarif->getAgeMax() ?? '') ?>"
                        data-max_tickets="<?= htmlspecialchars($tarif->getMaxTickets() ?? '') ?>"
                        data-price="<?= number_format($tarif->getPrice(), 2, '.', '') ?>"
                        data-is_program_show_include="<?= $tarif->getIsProgramShowInclude() ? '1' : '0' ?>"
                        data-is_proof_required="<?= $tarif->getIsProofRequired() ? '1' : '0' ?>"
                        data-access_code="<?= htmlspecialchars($tarif->getAccessCode()) ?>"
                        data-is_active="<?= $tarif->getIsActive() ? '1' : '0' ?>"
                        onclick="openTarifModal('edit', this.dataset)">
                        <span><?= htmlspecialchars($tarif->getLibelle()) ?></span>
                        <span><?= number_format($tarif->getPrice(), 2, ',', ' ') ?> €</span>
                        <a href="/gestion/tarifs/delete/<?= $tarif->getId() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce tarif ?');">Supprimer</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <!-- Bouton Ajouter -->
        <button type="button" class="btn btn-success btn-sm w-100" onclick="openTarifModal('add')">Ajouter</button>
    </div>

    <!-- Modale unique -->
    <div id="modal-tarif" class="modal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" style="max-width:500px; margin:10vh auto; background:#fff; border-radius:8px; overflow:hidden;">
            <div class="modal-content p-3">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title" id="modal-tarif-title">Ajouter un tarif</h5>
                    <button type="button" class="btn-close" aria-label="Fermer" onclick="closeTarifModal()"></button>
                </div>
                <form id="tarif-form" method="POST">
                    <div class="mb-2">
                        <label>Libellé <input type="text" name="libelle" class="form-control" required></label>
                    </div>
                    <div class="mb-2">
                        <label>Description <input type="text" name="description" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Nb places <input type="number" name="nb_place" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Âge min <input type="number" name="age_min" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Âge max <input type="number" name="age_max" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Max tickets <input type="number" name="max_tickets" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Prix <input type="number" step="0.01" name="price" class="form-control" required></label>
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" name="is_program_show_include" class="form-check-input" id="progInclu">
                        <label class="form-check-label" for="progInclu">Programme inclus</label>
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" name="is_proof_required" class="form-check-input" id="justif">
                        <label class="form-check-label" for="justif">Justificatif</label>
                    </div>
                    <div class="mb-2">
                        <label>Code accès <input type="text" name="access_code" class="form-control"></label>
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="actif" checked>
                        <label class="form-check-label" for="actif">Actif</label>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeTarifModal()">Annuler</button>
                        <button type="submit" class="btn btn-success" id="modal-tarif-submit">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Affichage desktop -->
    <div class="table-responsive d-md-block d-none">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Libellé</th>
                <th>Description</th>
                <th>Nb places</th>
                <th>Âge min</th>
                <th>Âge max</th>
                <th>Max tickets</th>
                <th>Prix</th>
                <th>Programme inclus</th>
                <th>Justificatif</th>
                <th>Code accès</th>
                <th>Actif</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Formulaire d’ajout -->
            <tr>
                <form action="/gestion/tarifs/add" method="POST">
                    <td><input type="text" name="libelle" class="form-control" required></td>
                    <td><input type="text" name="description" class="form-control"></td>
                    <td><input type="number" min="0"  name="nb_place" class="form-control"></td>
                    <td><input type="number" min="0"  name="age_min" class="form-control"></td>
                    <td><input type="number" min="0"  name="age_max" class="form-control"></td>
                    <td><input type="number" min="0"  name="max_tickets" class="form-control"></td>
                    <td><input type="number" min="0"  step="0.01" name="price" class="form-control" required></td>
                    <td class="text-center"><input type="checkbox" name="is_program_show_include"></td>
                    <td class="text-center"><input type="checkbox" name="is_proof_required"></td>
                    <td><input type="text" name="access_code" class="form-control"></td>
                    <td class="text-center"><input type="checkbox" name="is_active" checked></td>
                    <td><button type="submit" class="btn btn-success btn-sm">Ajouter</button></td>
                </form>
            </tr>
            <?php if (!empty($data)): ?>
                <?php foreach ($data as $tarif): ?>
                    <tr>
                        <form action="/gestion/tarifs/update/<?= $tarif->getId() ?>" method="POST">
                            <td><input type="text" name="libelle" class="form-control" required value="<?= htmlspecialchars($tarif->getLibelle() ?? '') ?>" size="50"></td>
                            <td><input type="text" name="description" class="form-control" value="<?= htmlspecialchars($tarif->getDescription() ?? '') ?>" size="100"></td>
                            <td><input type="number" name="nb_place" min="0"  class="form-control" value="<?= htmlspecialchars($tarif->getNbPlace() ?? '') ?>"></td>
                            <td><input type="number" name="age_min" min="0"  class="form-control" value="<?= htmlspecialchars($tarif->getAgeMin() ?? '') ?>"></td>
                            <td><input type="number" name="age_max" class="form-control" value="<?= htmlspecialchars($tarif->getAgeMax() ?? '') ?>"></td>
                            <td><input type="number" name="max_tickets" min="0"  class="form-control" value="<?= $tarif->getMaxTickets() ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="price" class="form-control" required value="<?= number_format($tarif->getPrice(), 2, '.', '') ?>"></td>
                            <td class="text-center"><input type="checkbox" name="is_program_show_include" <?= $tarif->getIsProgramShowInclude() ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="is_proof_required" <?= $tarif->getIsProofRequired() ? 'checked' : '' ?>></td>
                            <td><input type="text" name="access_code" class="form-control" value="<?= htmlspecialchars($tarif->getAccessCode() ?? '') ?>"></td>
                            <td class="text-center"><input type="checkbox" name="is_active" <?= $tarif->getIsActive() ? 'checked' : '' ?>></td>
                            <td>
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                <a href="/gestion/tarifs/delete/<?= $tarif->getId() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce tarif ?');">Supprimer</a>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
