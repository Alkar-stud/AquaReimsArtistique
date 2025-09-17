<div class="login-container">
    <h2>Mot de passe oublié</h2>
    <p>Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>
    {% if $flash_message %}
    <div class="alert alert-{{ $flash_message['type'] ?? 'info' }}">
        {{ $flash_message['message'] ?? '' }}
    </div>
    {% endif %}
    <form action="/forgot-password" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token ?? '' }}">
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Envoyer le lien</button>
    </form>
</div>
