<div class="login-container">
    <h2>Connexion</h2>
    <form action="/login-check" method="POST">
        <!-- Token CSRF -->
        <input type="hidden" name="csrf_token" value="{{ $csrf_token ?? '' }}">
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-secondary w-100">Se connecter</button>
    </form>
    <div class="text-center mt-3">
        <a href="/forgot-password" class="link-secondary">Mot de passe oublié ?</a>
    </div>
</div>
