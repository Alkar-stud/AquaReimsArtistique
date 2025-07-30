    <div class="login-container">
        <h2>Réinitialiser votre mot de passe</h2>
        <p>Veuillez entrer votre nouveau mot de passe.</p>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <?php $flash = $_SESSION['flash_message']; ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info'); ?>">
                <?= htmlspecialchars($flash['message'] ?? ''); ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <form action="/reset-password" method="POST">
            <!-- Champ caché pour conserver le token lors de la soumission -->
            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? ''); ?>">

            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Réinitialiser</button>
        </form>
    </div>