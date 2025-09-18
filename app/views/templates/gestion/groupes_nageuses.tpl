{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2 class="mb-4">Gestion des groupes nageuses</h2>

    <!-- Mobile -->
    <div class="d-md-none mb-4">
        {% if !empty($groupes) %}
        <ul class="list-group mb-3">
            {% foreach $groupes as $groupe %}
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    {{ $groupe->getLibelle() }}
                    <small class="text-muted">{{ $groupe->getCoach() }}</small>
                </span>
                <a href="/gestion/nageuses/{{ $groupe->getId() }}" class="btn btn-primary btn-sm">Nageuses</a>
            </li>
            {% endforeach %}
        </ul>
        {% endif %}
        <button type="button" class="btn btn-success btn-sm w-100" onclick="document.getElementById('modal-ajout-groupe').style.display='block'">Ajouter un groupe</button>
    </div>

    <!-- Modale d'ajout mobile -->
    <div id="modal-ajout-groupe" class="modal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" style="max-width:500px; margin:10vh auto; background:#fff; border-radius:8px; overflow:hidden;">
            <div class="modal-content p-3">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title">Ajouter un groupe</h5>
                    <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('modal-ajout-groupe').style.display='none'"></button>
                </div>
                <form action="/gestion/groupes-nageuses/add" method="POST">
                    <div class="mb-2">
                        <label>Nom du groupe <input type="text" name="libelle" class="form-control" required></label>
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
            <tr>
                <form action="/gestion/groupes-nageuses/add" method="POST">
                    <td><input type="text" name="libelle" class="form-control" required></td>
                    <td><input type="text" name="coach" class="form-control"></td>
                    <td><input type="number" name="order" class="form-control" required></td>
                    <td></td>
                    <td class="text-center"><input type="checkbox" name="is_active" checked></td>
                    <td><button type="submit" class="btn btn-success btn-sm">Ajouter</button></td>
                </form>
            </tr>
            {% if !empty($groupes) %}
            {% foreach $groupes as $groupe %}
            <tr>
                <form action="/gestion/groupes-nageuses/update/{{ $groupe->getId() }}" method="POST">
                    <td><input type="text" name="libelle" class="form-control" required value="{{ $groupe->getLibelle() }}"></td>
                    <td><input type="text" name="coach" class="form-control" value="{{ $groupe->getCoach() }}"></td>
                    <td><input type="number" name="order" class="form-control" required value="{{ $groupe->getOrder() }}"></td>
                    <td>
                        <a href="/gestion/nageuses/{{ $groupe->getId() }}" class="btn btn-primary btn-sm">Nageuses du groupe</a>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="is_active" {{ $groupe->getIsActive() ? 'checked' : '' }}>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                        <a href="/gestion/groupes-nageuses/delete/{{ $groupe->getId() }}" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce groupe ?');">Supprimer</a>
                    </td>
                </form>
            </tr>
            {% endforeach %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
