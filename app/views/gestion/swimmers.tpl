{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="mb-0">
            {% if $GroupName %}
            Nageurs du groupe « {{ $GroupName }} »
            {% elseif $groupId == 'all' %}
            Tous les nageurs
            {% else %}
            Nageurs sans groupe
            {% endif %}
        </h2>
        <form method="GET" onsubmit="if(this.g.value){ window.location='/gestion/swimmers/'+this.g.value; return false; }">
            <div class="input-group">
                <select name="g" class="form-select" onchange="if(this.value){ window.location='/gestion/swimmers/'+this.value; }">
                    <option value="all" selected>Tous les groupes</option>
                    <option value="0" {{ $groupId == 0 ? 'selected' : '' }}>Aucun groupe</option>
                    {% foreach $groupes as $g %}
                    <option value="{{ $g->getId() }}" {{ (string)$groupId === (string)$g->getId() ? 'selected' : '' }}>
                        {{ $g->getName() }}
                    </option>
                    {% endforeach %}
                </select>
                <button class="btn btn-outline-secondary" type="button" onclick="window.location='/gestion/swimmers-groups'">Gérer groupes</button>
            </div>
        </form>
    </div>

    <!-- Mobile -->
    <div class="d-md-none mb-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Ajouter un nageur</h6>
                <form action="/gestion/swimmers/add" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <div class="mb-2">
                        <label class="form-label">Nom</label>
                        <input type="text" name="name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Groupe</label>
                        <select name="group" class="form-select form-select-sm">
                            <option value="">(Aucun, il faut en choisir un)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ (string)$groupId === (string)$g->getId() ? 'selected' : '' }}>
                                {{ $g->getName() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </div>
                    <button class="btn btn-success btn-sm w-100" type="submit">Ajouter</button>
                </form>
            </div>
        </div>

        {% if !empty($swimmers) %}
        <ul class="list-group mb-3">
            {% foreach $swimmers as $swimmer %}
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>{{ $swimmer->getName() }}</strong><br>
                        {% if $groupId == 'all' %}
                        <small class="text-muted">
                            Groupe:
                            {% if $swimmer->getGroupObject() %}
                            {{ $swimmer->getGroupObject()->getName() }}
                            {% else %}
                            Aucun
                            {% endif %}
                        </small>
                        {% endif %}
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-secondary btn-sm" type="button"
                                onclick="const f=this.closest('.list-group-item').querySelector('form[action*=\'update\']'); f.style.display=f.style.display==='none'?'block':'none'">
                            Éditer
                        </button>
                        <form method="POST" action="/gestion/swimmers/delete" onsubmit="return confirm('Supprimer ce nageur ?');">
                            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                            <input type="hidden" name="swimmer_id" value="{{ $swimmer->getId() }}">
                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                        </form>
                    </div>
                </div>
                <form action="/gestion/swimmers/update" method="POST" class="mt-2" style="display:none">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="form_anchor" value="config-card-{{ $swimmer->getId() }}">
                    <input type="hidden" name="swimmer_id" value="{{ $swimmer->getId() }}">
                    <input type="hidden" name="context" value="mobile">
                    <div class="mb-2">
                        <input type="text" name="name" class="form-control form-control-sm" required value="{{ $swimmer->getName() }}">
                    </div>
                    <div class="mb-2">
                        <select name="group" class="form-select form-select-sm">
                            <option value="">(Aucun)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ $swimmer->getGroup() == $g->getId() ? 'selected' : '' }}>
                                {{ $g->getName() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="this.closest('form').style.display='none'">Annuler</button>
                    </div>
                </form>
            </li>
            {% endforeach %}
        </ul>
        {% else %}
        <p class="text-center text-muted">Aucun nageur trouvé</p>
        {% endif %}

    </div>

    <!-- Desktop -->
    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle">
            <thead>
            <tr>
                <th style="width:35%">Nom</th>
                <th style="width:30%">Groupe</th>
                <th style="width:20%">Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <form action="/gestion/swimmers/add" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <td>
                        <input type="text" name="name" class="form-control" required>
                    </td>
                    <td>
                        <select name="group" class="form-select">
                            <option value="">(Aucun, il faut en choisir un)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ (string)$groupId === (string)$g->getId() ? 'selected' : '' }}>
                                {{ $g->getName() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm">Ajouter</button>
                    </td>
                </form>
            </tr>
            {% if !empty($swimmers) %}
            {% foreach $swimmers as $swimmer %}
            <tr>
                <form action="/gestion/swimmers/update" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="form_anchor" value="config-row-{{ $swimmer->getId() }}">
                    <input type="hidden" name="swimmer_id" value="{{ $swimmer->getId() }}">
                    <input type="hidden" name="context" value="desktop">
                    <td>
                        <input type="text" name="name" class="form-control" required value="{{ $swimmer->getName() }}">
                    </td>
                    <td>
                        <select name="group" class="form-select">
                            <option value="">(Aucun)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ $swimmer->getGroup() == $g->getId() ? 'selected' : '' }}>
                                {{ $g->getName() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </td>
                    <td class="d-flex gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                    </form>
                <form method="POST" action="/gestion/swimmers/delete" onsubmit="return confirm('Supprimer ce groupe ?');" class="d-inline">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="swimmer_id" value="{{ $swimmer->getId() }}">
                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                </form>
                    </td>
            </tr>
            {% endforeach %}
            {% else %}
            <tr>
                <td colspan="3" class="text-center text-muted">Aucun nageur</td>
            </tr>
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
