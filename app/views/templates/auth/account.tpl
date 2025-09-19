{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container">
    <h2 class="mb-4">Mon compte</h2>
    {% if $_SESSION['user']['role']['level'] > 0 %}
    <!-- Formulaire de modification des informations -->
    <form action="/account/update" method="POST" class="mb-5">
        <div class="mb-3">
            <label for="displayname" class="form-label">Nom affiché</label>
            <input type="text" class="form-control" id="displayname" name="displayname"
                   value="{{ $_SESSION['user']['displayname'] ?? '' }}">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Adresse e-mail</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ $_SESSION['user']['email'] ?? '' }}" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Mettre à jour mes informations</button>
    </form>
    {% endif %}
    <!-- Formulaire de changement de mot de passe -->
    <form action="/account/password" method="POST">
        <input type="text" name="username" value="{{ $_SESSION['user']['username'] ?? '' }}" autocomplete="username" hidden>
        <h4 class="mb-3">Changer mon mot de passe</h4>
        <div class="mb-3">
            <label for="current_password" class="form-label">Mot de passe actuel</label>
            <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="mb-3">
            <label for="new_password" class="form-label">Nouveau mot de passe</label>
            <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" required>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-secondary w-100" disabled>Changer mon mot de passe</button>
    </form>
</div>
