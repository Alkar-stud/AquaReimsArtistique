<div class="login-container">
    <h2>Connexion</h2>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <?php $flash = $_SESSION['flash_message']; ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
            <?= htmlspecialchars($flash['message'] ?? ''); ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <form action="/login" method="POST">
        <!-- Token CSRF -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
    </form>
    <div class="text-center mt-3">
        <a href="/forgot-password" class="link-secondary">Mot de passe oublié ?</a>
    </div>
</div>