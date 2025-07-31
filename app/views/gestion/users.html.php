<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="container">
    <h2>Créer un utilisateur</h2>
    <form method="POST" action="/gestion/users/add">
        <div class="mb-3">
            <label for="username" class="form-label">Nom d'utilisateur *</label>
            <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Adresse email *</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="display_name" class="form-label">Nom d'affichage (facultatif)</label>
            <input type="text" name="display_name" id="display_name" class="form-control">
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Rôle *</label>
            <select name="role" id="role" class="form-select" required>
                <option>Choisissez le role</option>
                <?php foreach ($roles as $role): ?>
                    <?php
                    $disabled = ($role->getLevel() <= $_SESSION['user']['role']['level']) ? 'disabled' : '';
                    ?>
                    <option value="<?= $role->getId() ?>" <?= $disabled ?>>
                        <?= htmlspecialchars($role->getLibelle()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Créer</button>
        <a href="/gestion/users" class="btn btn-secondary">Annuler</a>
    </form>
</div>