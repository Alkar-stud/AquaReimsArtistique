<div class="login-container">
    <h2>Réinitialiser votre mot de passe</h2>
    <p>Veuillez entrer votre nouveau mot de passe.</p>
    {% if $flash_message %}
    <div class="alert alert-{{ $flash_message['type'] ?? 'info' }}">
        {{ $flash_message['message'] ?? '' }}
    </div>
    {% endif %}
    <form action="/reset-password" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token ?? '' }}">
        <input type="hidden" name="token" value="{{ $token ?? '' }}">
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
