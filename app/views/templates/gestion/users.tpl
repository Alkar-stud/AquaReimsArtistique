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
                    <td><input type="text" name="username" class="form-control" required></td>
                    <td><input type="email" name="email" class="form-control" required></td>
                    <td><input type="text" name="display_name" class="form-control"></td>
                    <td>
                        <select name="role" class="form-select" required>
                            <option>Choisissez le rôle</option>
                            {% foreach $roles as $role %}
                            {% if $role->getLevel() >= $_SESSION['user']['role']['level'] %}
                            <option value="{{ $role->getId() }}" {{! $role->getLevel() <= $_SESSION['user']['role']['level'] ? 'disabled' : '' !}}>
                                {{ $role->getLibelle() }}
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
                                {{ $role->getLibelle() }}
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
                        <button type="button" class="btn btn-danger btn-sm w-100"
                                onclick="if(confirm('Supprimer cet utilisateur ?')){ window.location='/gestion/users/delete?id={{ $user->getId() }}'; }">
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
                                {{ $role->getLibelle() }}
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
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">{{ $user->getUsername() }}</h5>
                <p class="mb-1"><strong>Email :</strong> {{ $user->getEmail() }}</p>
                <p class="mb-1"><strong>Nom d'affichage :</strong> {{ $user->getDisplayName() }}</p>
                <p class="mb-1"><strong>Rôle :</strong> {{ $user->getRole() ? $user->getRole()->getLibelle() : '' }}</p>
                <p class="mb-2">
                    <strong>Actif :</strong>
                </p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input user-status-toggle"
                           type="checkbox"
                           id="user-status-mobile-{{ $user->getId() }}"
                           data-id="{{ $user->getId() }}"
                            {{! $user->getIsActif() ? 'checked' : '' !}}>
                </div>
                <div class="d-flex flex-column gap-2">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal-{{ $user->getId() }}">Modifier</button>
                    <button class="btn btn-danger btn-sm"
                            onclick="if(confirm('Supprimer cet utilisateur ?')){ window.location='/gestion/users/delete?id={{ $user->getId() }}'; }">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal édition -->
        <div class="modal fade" id="editUserModal-{{ $user->getId() }}" tabindex="-1" aria-labelledby="editUserModalLabel-{{ $user->getId() }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="/gestion/users/edit">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editUserModalLabel-{{ $user->getId() }}">Modifier {{ $user->getUsername() }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="{{ $user->getId() }}">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="{{ $user->getEmail() }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nom d'affichage</label>
                                <input type="text" name="display_name" class="form-control" value="{{ $user->getDisplayName() }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rôle</label>
                                <select name="role" class="form-select" required>
                                    <option>Choisissez le rôle</option>
                                    {% foreach $roles as $role %}
                                    {% if $role->getLevel() >= $_SESSION['user']['role']['level'] %}
                                    <option value="{{ $role->getId() }}"
                                            {{! ($user->getRole() && $user->getRole()->getId() === $role->getId()) ? 'selected' : '' !}}
                                            {{! $role->getLevel() <= $_SESSION['user']['role']['level'] ? 'disabled' : '' !}}>
                                        {{ $role->getLibelle() }}
                                    </option>
                                    {% endif %}
                                    {% endforeach %}
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer flex-column flex-sm-row">
                            <button type="button" class="btn btn-warning w-100 mb-2" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {% endforeach %}
    </div>
</div>

<script src="/assets/js/gestion/users.js" defer></script>

