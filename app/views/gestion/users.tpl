{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container">
    <h2>Gestion des utilisateurs</h2>

    <!-- Desktop -->
    <div class="d-none d-md-block">
        <table class="table table-bordered align-middle">
            <thead>
            <tr>
                <th>Nom d'utilisateur</th>
                <th>Email</th>
                <th>Nom d'affichage</th>
                <th>Rôle</th>
                <th>Actif</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Ajout -->
            <tr>
                <form method="POST" action="/gestion/users/add">
                    <!-- Token CSRF -->
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token ?? '' }}">
                    <td><input type="text" name="username" class="form-control" required></td>
                    <td><input type="email" name="email" class="form-control" required></td>
                    <td><input type="text" name="display_name" class="form-control"></td>
                    <td>
                        <select name="role" class="form-select" required>
                            <option>Choisissez le rôle</option>
                            {% foreach $roles as $role %}
                            {% if $role->getLevel() >= $_SESSION['user']['role']['level'] %}
                            <option value="{{ $role->getId() }}" {{! $role->getLevel() <= $_SESSION['user']['role']['level'] ? 'disabled' : '' !}}>
                                {{ $role->getLabel() }}
                            </option>
                            {% endif %}
                            {% endforeach %}
                        </select>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input" type="checkbox" disabled checked>
                        </div>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm w-100 mb-1">Ajouter</button>
                    </td>
                </form>
            </tr>

            {% if !empty($users) %}
            {% foreach $users as $user %}
            <tr>
                <form method="POST" action="/gestion/users/edit">
                    <!-- Token CSRF -->
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token ?? '' }}">
                    <input type="hidden" name="id" value="{{ $user->getId() }}">
                    <td><input type="text" name="username" class="form-control" value="{{ $user->getUsername() }}" required></td>
                    <td><input type="email" name="email" class="form-control" value="{{ $user->getEmail() }}" required></td>
                    <td><input type="text" name="display_name" class="form-control" value="{{ $user->getDisplayName() }}"></td>
                    <td>
                        <select name="role" class="form-select" required>
                            <option>Choisissez le rôle</option>
                            {% foreach $roles as $role %}
                            {% if $role->getLevel() >= $_SESSION['user']['role']['level'] %}
                            <option value="{{ $role->getId() }}"
                                    {{! ($user->getRole() && $user->getRole()->getId() === $role->getId()) ? 'selected' : '' !}}
                                    {{! $role->getLevel() <= $_SESSION['user']['role']['level'] ? 'disabled' : '' !}}>
                                {{ $role->getLabel() }}
                            </option>
                            {% endif %}
                            {% endforeach %}
                        </select>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input user-status-toggle"
                                   type="checkbox"
                                   id="user-status-{{ $user->getId() }}"
                                   data-id="{{ $user->getId() }}"
                                    {{! $user->getIsActif() ? 'checked' : '' !}}>
                        </div>
                    </td>
                    <td class="d-flex flex-column gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Modifier</button>
                        <button type="submit"
                                class="btn btn-danger btn-sm w-100"
                                formaction="/gestion/users/delete"
                                formmethod="POST"
                                onclick="return confirm('Supprimer cet utilisateur ?');">
                            Supprimer
                        </button>
                    </td>
                </form>
            </tr>
            {% endforeach %}
            {% else %}
            <tr>
                <td colspan="6" class="text-center text-muted">Aucun utilisateur.</td>
            </tr>
            {% endif %}
            </tbody>
        </table>
    </div>

    <!-- Mobile -->
    <div class="d-block d-md-none">
        <!-- Ajout -->
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
                        <select name="role" class="form-select" required>
                            <option>Choisissez le rôle</option>
                            {% foreach $roles as $role %}
                            {% if $role->getLevel() >= $_SESSION['user']['role']['level'] %}
                            <option value="{{ $role->getId() }}" {{! $role->getLevel() <= $_SESSION['user']['role']['level'] ? 'disabled' : '' !}}>
                                {{ $role->getLabel() }}
                            </option>
                            {% endif %}
                            {% endforeach %}
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label d-block">Actif :</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" disabled checked>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Ajouter</button>
                </form>
            </div>
        </div>

        {% foreach $users as $user %}
        <div class="card mb-3 border-success">
            <div class="card-body">
                <form method="POST" action="/gestion/users/edit">
                    <input type="hidden" name="id" value="{{ $user->getId() }}">
                    <div class="mb-2">
                        <input type="text" name="username" class="form-control" placeholder="Nom d'utilisateur *" value="{{ $user->getUsername() }}" required>
                    </div>
                    <div class="mb-2">
                        <input type="email" name="email" class="form-control" placeholder="Email *" value="{{ $user->getEmail() }}" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="display_name" class="form-control" placeholder="Nom d'affichage" value="{{ $user->getDisplayName() }}">
                    </div>
                    <div class="mb-2">
                        <select name="role" class="form-select" required>
                            <option>Choisissez le rôle</option>
                            {% foreach $roles as $role %}
                            {% if $role->getLevel() >= $_SESSION['user']['role']['level'] %}
                            <option
                                    value="{{ $role->getId() }}"
                                    {{! ($user->getRole() && $user->getRole()->getId() === $role->getId()) ? 'selected' : '' !}}
                                    {{! $role->getLevel() <= $_SESSION['user']['role']['level'] ? 'disabled' : '' !}}
                            >
                                {{ $role->getLabel() }}
                            </option>
                            {% endif %}
                            {% endforeach %}
                        </select>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input user-status-toggle"
                               type="checkbox"
                               id="user-status-mobile-{{ $user->getId() }}"
                               data-id="{{ $user->getId() }}"
                                {{! $user->getIsActif() ? 'checked' : '' !}}>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Modifier</button>
                        <button type="submit"
                                class="btn btn-danger btn-sm w-100"
                                formaction="/gestion/users/delete"
                                formmethod="POST"
                                onclick="return confirm('Supprimer cet utilisateur ?');">
                            Supprimer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        {% endforeach %}
    </div>
</div>

<script src="/assets/js/gestion/users.js" defer></script>
