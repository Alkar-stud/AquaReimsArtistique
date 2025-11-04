<div class="login-container">
    <h1>Connexion</h1>
    <form action="/login-check" method="POST">

        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <!-- Indice pour le bouton (annoncé aux lecteurs d'écran) -->
        <p id="login-hint" class="visually-hidden">Tous les champs sont obligatoires.</p>

        <button
            type="submit"
            class="btn btn-secondary w-100"
            aria-describedby="login-hint login-error"
        >
            Se connecter
        </button>

        <div id="login-error" class="visually-hidden" role="alert" aria-live="assertive"></div>
    </form>
    <div class="text-center mt-3">
        <a href="/forgot-password" class="link-secondary">Mot de passe oublié ?</a>
    </div>
</div>

{% if ($_ENV['APP_DEBUG'] == "true") %}
{% php %}
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
{% endphp %}
{% endif %}