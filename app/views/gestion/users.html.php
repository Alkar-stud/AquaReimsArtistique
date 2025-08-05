<?php
function renderRoleSelect($roles, $currentUserLevel, $selectedRoleId = null, $name = 'role', $required = true) {
    $html = '<select name="' . htmlspecialchars($name) . '" class="form-select"' . ($required ? ' required' : '') . '>';
    $html .= '<option>Choisissez le rôle</option>';
    foreach ($roles as $role) {
        if ($role->getLevel() < $currentUserLevel) { continue; }
        $selected = ($selectedRoleId && $role->getId() === $selectedRoleId) ? 'selected' : '';
        $disabled = ($role->getLevel() <= $currentUserLevel) ? 'disabled' : '';
        $html .= '<option value="' . $role->getId() . "\" $selected $disabled>" . htmlspecialchars($role->getLibelle()) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="container">
    <h2>Gestion des utilisateurs</h2>

    <!-- Desktop : Tableau -->
    <div class="d-none d-md-block">
        <table class="table table-bordered align-middle">
            <thead>
            <tr>
                <th>Nom d'utilisateur</th>
                <th>Email</th>
                <th>Nom d'affichage</th>
                <th>Rôle</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Ligne d'ajout -->
            <tr>
                <form method="POST" action="/gestion/users/add">
                    <td><input type="text" name="username" class="form-control" required></td>
                    <td><input type="email" name="email" class="form-control" required></td>
                    <td><input type="text" name="display_name" class="form-control"></td>
                    <td>
						<?= renderRoleSelect($roles, $_SESSION['user']['role']['level']) ?>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm w-100 mb-1">Ajouter</button>
                    </td>
                </form>
            </tr>
            <!-- Utilisateurs existants -->
            <?php 
			if (!isset($users) || !is_array($users)) {
				$users = [];
			}
            foreach ($users as $user): ?>
                <tr>
                    <form method="POST" action="/gestion/users/edit">
                        <input type="hidden" name="id" value="<?= $user->getId() ?>">
                        <td><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user->getUsername()) ?>" required></td>
                        <td><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user->getEmail()) ?>" required></td>
                        <td><input type="text" name="display_name" class="form-control" value="<?= htmlspecialchars($user->getDisplayName()) ?>"></td>
                        <td>
							<?= renderRoleSelect($roles, $_SESSION['user']['role']['level'], $user->getRole()?->getId() ?? null) ?>
                        </td>
                        <td class="d-flex flex-column gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Modifier</button>
                            <button type="button" class="btn btn-danger btn-sm w-100"
                                    onclick="if(confirm('Supprimer cet utilisateur ?')){ window.location='/gestion/users/delete?id=<?= $user->getId() ?>'; }">
                                Supprimer
                            </button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile : Cards -->
    <div class="d-block d-md-none">
        <!-- Ajout utilisateur en card mobile -->
        <div class="card mb-3 border-success">
            <div class="card-body">
                <form method="POST" action="/gestion/users/add">
                    <div class="mb-2">
                        <input type="text" name="username" class="form-control" placeholder="Nom d'utilisateur *" required>
                    </div>
                    <div class="mb-2">
                        <input type="email" name="email" class="form-control" placeholder="Email *" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="display_name" class="form-control" placeholder="Nom d'affichage">
                    </div>
                    <div class="mb-2">
						<?= renderRoleSelect($roles, $_SESSION['user']['role']['level']) ?>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Ajouter</button>
                </form>
            </div>
        </div>
        <!-- Utilisateurs existants en card mobile -->
        <?php foreach ($users as $user): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($user->getUsername()) ?></h5>
                    <p class="card-text mb-1"><strong>Email :</strong> <?= htmlspecialchars($user->getEmail()) ?></p>
                    <p class="card-text mb-1"><strong>Nom d'affichage :</strong> <?= htmlspecialchars($user->getDisplayName()) ?></p>
                    <p class="card-text mb-2"><strong>Rôle :</strong> <?= $user->getRole() ? htmlspecialchars($user->getRole()->getLibelle()) : '' ?></p>
                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal-<?= $user->getId() ?>">Modifier</button>
                        <button class="btn btn-danger btn-sm"
                                onclick="if(confirm('Supprimer cet utilisateur ?')){ window.location='/gestion/users/delete?id=<?= $user->getId() ?>'; }">
                            Supprimer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modale d'édition mobile -->
            <div class="modal fade" id="editUserModal-<?= $user->getId() ?>" tabindex="-1" aria-labelledby="editUserModalLabel-<?= $user->getId() ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="/gestion/users/edit">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editUserModalLabel-<?= $user->getId() ?>">Modifier <?= htmlspecialchars($user->getUsername()) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?= $user->getId() ?>">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user->getEmail()) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nom d'affichage</label>
                                    <input type="text" name="display_name" class="form-control" value="<?= htmlspecialchars($user->getDisplayName()) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Rôle</label>
										<?= renderRoleSelect($roles, $_SESSION['user']['role']['level'], $user->getRole()?->getId() ?? null) ?>
                                </div>
                            </div>
                            <div class="modal-footer flex-column flex-sm-row">
                                <button type="button" class="btn btn-warning w-100 mb-2" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
