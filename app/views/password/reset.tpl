<div class="login-container">
    <h2 class="mb-4">Réinitialiser votre mot de passe</h2>
    <p>Veuillez choisir un nouveau mot de passe. Il doit respecter les règles suivantes :</p>
    {% if $password_rules %}
    <ul class="list-unstyled small text-muted">
        {% foreach $password_rules_loop as $rule %}
        <li><i class="fas fa-check-circle text-success"></i> {{ $rule['item'] }}</li>
        {% endforeach %}
    </ul>
    {% endif %}

    <form action="/reset-password-submit" method="POST" class="mb-3">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token ?? '' }}">
        <input type="hidden" name="token" value="{{ $token ?? '' }}">

        <div class="mb-3">
            <label for="password" class="form-label">Nouveau mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
        </div>

        <div class="mb-3">
            <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
            <input type="password" class="form-control" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-secondary w-100">Réinitialiser</button>
    </form>
</div>
