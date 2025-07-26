<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php
$types = [
    'string' => 'Chaîne',
    'int' => 'Entier',
    'float' => 'Décimal',
    'bool' => 'Booléen',
    'email' => 'Email',
    'date' => 'Date (Y-m-d)',
    'datetime' => 'Date+heure (Y-m-d H:i:s)',
    'url' => 'URL',
];
?>

<div class="container-fluid">
    <h2 class="mb-4">Gestion des configurations</h2>

    <!-- Affichage mobile -->
    <div class="d-md-none mb-4">
        <?php if (!empty($data)): ?>
            <ul class="list-group mb-3">
                <?php foreach ($data as $config): ?>
                    <li class="list-group-item">
                        <form action="/gestion/configuration/configs/update/<?= $config->getId() ?>" method="POST" class="d-flex flex-column gap-2">
                            <div>
                                <strong><?= htmlspecialchars($config->getLibelle()) ?></strong>
                                <span class="text-muted">[<?= htmlspecialchars($config->getConfigKey()) ?>]</span>
                            </div>
                            <input type="text" name="libelle" class="form-control mb-1" value="<?= htmlspecialchars($config->getLibelle()) ?>" required>
                            <input type="text" name="config_key" class="form-control mb-1" value="<?= htmlspecialchars($config->getConfigKey()) ?>" required>
                            <input type="text" name="config_value" class="form-control mb-1" value="<?= htmlspecialchars($config->getConfigValue()) ?>">
                            <div class="input-group">
                                <select class="form-select" onchange="document.getElementById(this.dataset.target).value=this.value;this.value==='autre'&&document.getElementById(this.dataset.target).focus();" data-target="config_type_input">
                                    <option value="autre">Autre...</option>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $key == $config->getConfigType() ? ' SELECTED':''?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="config_type" id="config_type_input" class="form-control" placeholder="Type personnalisé" value="<?= htmlspecialchars($config->getConfigType() ?? '') ?>">
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                <a href="/gestion/configuration/configs/delete/<?= $config->getId() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette configuration ?');">Supprimer</a>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <!-- Bouton Ajouter -->
        <button type="button" class="btn btn-success btn-sm w-100" onclick="document.getElementById('modal-ajout-config').style.display='block'">Ajouter</button>
    </div>

    <!-- Modale d'ajout mobile -->
    <div id="modal-ajout-config" class="modal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" style="max-width:500px; margin:10vh auto; background:#fff; border-radius:8px; overflow:hidden;">
            <div class="modal-content p-3">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title">Ajouter une configuration</h5>
                    <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('modal-ajout-config').style.display='none'"></button>
                </div>
                <form action="/gestion/configuration/configs/add" method="POST">
                    <div class="mb-2">
                        <label>Libellé <input type="text" name="libelle" class="form-control" required></label>
                    </div>
                    <div class="mb-2">
                        <label>Clé <input type="text" name="config_key" class="form-control" required></label>
                    </div>
                    <div class="mb-2">
                        <label>Valeur <input type="text" name="config_value" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Type
                            <div class="input-group">
                                <select class="form-select" onchange="document.getElementById(this.dataset.target).value=this.value;this.value==='autre'&&document.getElementById(this.dataset.target).focus();" data-target="config_type_input_add">
                                    <option value="autre">Autre...</option>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="config_type" id="config_type_input_add" class="form-control" placeholder="Type personnalisé">
                            </div>
                        </label>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-ajout-config').style.display='none'">Annuler</button>
                        <button type="submit" class="btn btn-success">Ajouter</button>
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
                <th>Clé</th>
                <th>Valeur</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Formulaire d’ajout -->
            <tr>
                <form action="/gestion/configuration/configs/add" method="POST">
                    <td><input type="text" name="libelle" class="form-control" required></td>
                    <td><input type="text" name="config_key" class="form-control" required></td>
                    <td><input type="text" name="config_value" class="form-control"></td>
                    <td>
                        <div class="input-group">
                            <select class="form-select" data-target="config_type_input_add_desktop" onchange="document.getElementById(this.dataset.target).value=this.value;this.value==='autre'&&document.getElementById(this.dataset.target).focus();">
                                <option value="autre">Autre...</option>
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="config_type" id="config_type_input_add_desktop" class="form-control" placeholder="Type personnalisé">
                        </div>
                    </td>
                    <td><button type="submit" class="btn btn-success btn-sm">Ajouter</button></td>
                </form>
            </tr>
            <?php if (!empty($data)): ?>
                <?php foreach ($data as $config): ?>
                    <tr>
                        <form action="/gestion/configuration/configs/update/<?= $config->getId() ?>" method="POST">
                            <td><input type="text" name="libelle" class="form-control" value="<?= htmlspecialchars($config->getLibelle()) ?>" required></td>
                            <td><input type="text" name="config_key" class="form-control" value="<?= htmlspecialchars($config->getConfigKey()) ?>" required></td>
                            <td><input type="text" name="config_value" class="form-control" value="<?= htmlspecialchars($config->getConfigValue()) ?>"></td>
                            <td>
                                <div class="input-group">
                                    <select class="form-select" data-target="config_type_input_<?= $config->getId() ?>" onchange="document.getElementById(this.dataset.target).value=this.value;this.value==='autre'&&document.getElementById(this.dataset.target).focus();">
                                        <option value="autre">Autre...</option>
                                        <?php foreach ($types as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= $key == $config->getConfigType() ? ' SELECTED':''?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="config_type" id="config_type_input_<?= $config->getId() ?>" class="form-control" placeholder="Type personnalisé" value="<?= htmlspecialchars($config->getConfigType() ?? '') ?>">
                                </div>
                            </td>
                            <td>
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                <a href="/gestion/configuration/configs/delete/<?= $config->getId() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette configuration ?');">Supprimer</a>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>