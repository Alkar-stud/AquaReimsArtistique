{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2 class="mb-4">Gestion des groupes de Nageuses</h2>

    {% if !isset($_GET['g']) %}
    <a href="/gestion/swimmers-groups?g=all" class="btn btn-secondary">Afficher aussi les désactivés</a>
    {% else %}
    <a href="/gestion/swimmers-groups" class="btn btn-secondary">Afficher seulement les actifs</a>
    {% endif %}

    <!-- Mobile -->
    <div class="d-md-none mb-4">
    <br>
        <button type="button" class="btn btn-success btn-sm w-100" onclick="document.getElementById('modal-ajout-groupe').style.display='block'">Ajouter un groupe</button>
        {% if !empty($data) %}
        <ul class="list-group mb-3">
            {% foreach $data as $groupe %}
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    {{ $groupe->getName() }}
                    <small class="text-muted">{{ $groupe->getCoach() }}</small>
                </span>
                <a href="/gestion/swimmers/{{ $groupe->getId() }}" class="btn btn-secondary btn-sm">Nageuses</a>
            </li>
            {% endforeach %}
        </ul>
        {% endif %}
    </div>

    <!-- Modale d'ajout mobile -->
    <div id="modal-ajout-groupe" class="modal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" style="max-width:500px; margin:10vh auto; background:#fff; border-radius:8px; overflow:hidden;">
            <div class="modal-content p-3">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title">Ajouter un groupe</h5>
                    <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('modal-ajout-groupe').style.display='none'"></button>
                </div>
                <form action="/gestion/swimmers-groups/add" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <div class="mb-2">
                        <label>Nom du groupe <input type="text" name="name" class="form-control" required></label>
                    </div>
                    <div class="mb-2">
                        <label>Coach <input type="text" name="coach" class="form-control"></label>
                    </div>
                    <div class="mb-2">
                        <label>Ordre <input type="number" name="order" class="form-control" required></label>
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="actif" checked>
                        <label class="form-check-label" for="actif">Actif</label>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-ajout-groupe').style.display='none'">Annuler</button>
                        <button type="submit" class="btn btn-success">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Desktop -->
    <div class="table-responsive d-md-block d-none">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Nom du groupe</th>
                <th>Coach</th>
                <th>Ordre</th>
                <th>Nageuses</th>
                <th>Actif</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Formulaire d'ajout -->
            <tr>
                <form action="/gestion/swimmers-groups/add" method="POST" id="form-add" class="d-contents"></form>
                <td>
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}" form="form-add">
                    <input type="text" name="name" class="form-control" required form="form-add">
                </td>
                <td><input type="text" name="coach" class="form-control" form="form-add"></td>
                <td><input type="number" name="order" class="form-control" required form="form-add"></td>
                <td></td>
                <td class="text-center"><input type="checkbox" name="is_active" checked form="form-add"></td>
                <td><button type="submit" class="btn btn-success btn-sm" form="form-add">Ajouter</button></td>
            </tr>
            {% if !empty($data) %}
            {% foreach $data as $groupe %}
            <tr>
                <!-- Le formulaire update -->
                <form action="/gestion/swimmers-groups/update" method="POST" id="form-update-{{ $groupe->getId() }}" class="d-contents">
                <td>
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="group_id" value="{{ $groupe->getId() }}">
                        <input type="text" name="name" class="form-control" required value="{{ $groupe->getName() }}">
                </td>
                <td><input type="text" name="coach" class="form-control" value="{{ $groupe->getCoach() }}" form="form-update-{{ $groupe->getId() }}"></td>
                <td><input type="number" name="order" class="form-control" required value="{{ $groupe->getOrder() }}" form="form-update-{{ $groupe->getId() }}"></td>
                <td>
                    <a href="/gestion/swimmers/{{ $groupe->getId() }}" class="btn btn-primary btn-sm">Nageuses du groupe</a>
                </td>
                <td class="text-center">
                    <input type="checkbox" name="is_active" {{ $groupe->getIsActive() ? 'checked' : '' }} form="form-update-{{ $groupe->getId() }}">
                </td>
                <td>
                    <button type="submit" class="btn btn-secondary btn-sm" form="form-update-{{ $groupe->getId() }}">Enregistrer</button>
                </form>
                <form method="POST" action="/gestion/swimmers-groups/delete" onsubmit="return confirm('Supprimer ce groupe ?');" class="d-inline">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="form_anchor" value="config-row-{{ $groupe->getId() }}">
                    <input type="hidden" name="group_id" value="{{ $groupe->getId() }}">
                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                </form>
                </td>
            </tr>
            {% endforeach %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
