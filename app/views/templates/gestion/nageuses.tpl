{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="mb-0">
            {% if $groupeLibelle %}
            Nageuses du groupe « {{ $groupeLibelle }} »
            {% elseif $groupId == 'all' %}
            Toutes les nageuses
            {% else %}
            Nageuses
            {% endif %}
        </h2>
        <form method="GET" onsubmit="if(this.g.value){ window.location='/gestion/nageuses/'+this.g.value; return false; }">
            <div class="input-group">
                <select name="g" class="form-select" onchange="if(this.value){ window.location='/gestion/nageuses/'+this.value; }">
                    <option value="all" {{ $groupId == 'all' ? 'selected' : '' }}>Tous les groupes</option>
                    {% foreach $groupes as $g %}
                    <option value="{{ $g->getId() }}" {{ (string)$groupId === (string)$g->getId() ? 'selected' : '' }}>
                        {{ $g->getLibelle() }}
                    </option>
                    {% endforeach %}
                </select>
                <button class="btn btn-outline-secondary" type="button" onclick="window.location='/gestion/groupes-nageuses'">Gérer groupes</button>
            </div>
        </form>
    </div>

    <!-- Mobile -->
    <div class="d-md-none mb-4">
        {% if !empty($nageuses) %}
        <ul class="list-group mb-3">
            {% foreach $nageuses as $nageuse %}
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>{{ $nageuse->getName() }}</strong><br>
                        <small class="text-muted">
                            Groupe:
                            {% set current = null %}
                            {% foreach $groupes as $g %}
                            {% if $g->getId() == $nageuse->getGroupe() %}
                            {% set current = $g %}
                            {% endif %}
                            {% endforeach %}
                            {{ $current ? $current->getLibelle() : '—' }}
                        </small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-secondary" type="button"
                                onclick="const f=this.parentElement.parentElement.nextElementSibling; f.style.display=f.style.display==='none'?'block':'none'">
                            Éditer
                        </button>
                        <a class="btn btn-danger"
                           onclick="return confirm('Supprimer cette nageuse ?')"
                           href="/gestion/nageuses/delete/{{ $nageuse->getId() }}">X</a>
                    </div>
                </div>
                <form action="/gestion/nageuses/update/{{ $nageuse->getId() }}" method="POST" class="mt-2" style="display:none">
                    <input type="hidden" name="origine-groupe" value="{{ $groupId }}">
                    <div class="mb-2">
                        <input type="text" name="name" class="form-control form-control-sm" required value="{{ $nageuse->getName() }}">
                    </div>
                    <div class="mb-2">
                        <select name="groupe" class="form-select form-select-sm">
                            <option value="">(Aucun)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ $nageuse->getGroupe() == $g->getId() ? 'selected' : '' }}>
                                {{ $g->getLibelle() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success btn-sm">Enregistrer</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="this.closest('form').style.display='none'">Annuler</button>
                    </div>
                </form>
            </li>
            {% endforeach %}
        </ul>
        {% else %}
        <p class="text-center text-muted">Aucune nageuse trouvée</p>
        {% endif %}

        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Ajouter une nageuse</h6>
                <form action="/gestion/nageuses/add" method="POST">
                    <div class="mb-2">
                        <label class="form-label">Nom</label>
                        <input type="text" name="name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Groupe</label>
                        <select name="groupe" class="form-select form-select-sm">
                            <option value="">(Aucun)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ (string)$groupId === (string)$g->getId() ? 'selected' : '' }}>
                                {{ $g->getLibelle() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </div>
                    <button class="btn btn-success btn-sm w-100" type="submit">Ajouter</button>
                </form>
            </div>
        </div>
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
                <form action="/gestion/nageuses/add" method="POST">
                    <td>
                        <input type="text" name="name" class="form-control" required>
                    </td>
                    <td>
                        <select name="groupe" class="form-select">
                            <option value="">(Aucun)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ (string)$groupId === (string)$g->getId() ? 'selected' : '' }}>
                                {{ $g->getLibelle() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm">Ajouter</button>
                    </td>
                </form>
            </tr>
            {% if !empty($nageuses) %}
            {% foreach $nageuses as $nageuse %}
            <tr>
                <form action="/gestion/nageuses/update/{{ $nageuse->getId() }}" method="POST">
                    <input type="hidden" name="origine-groupe" value="{{ $groupId }}">
                    <td>
                        <input type="text" name="name" class="form-control" required value="{{ $nageuse->getName() }}">
                    </td>
                    <td>
                        <select name="groupe" class="form-select">
                            <option value="">(Aucun)</option>
                            {% foreach $groupes as $g %}
                            <option value="{{ $g->getId() }}" {{ $nageuse->getGroupe() == $g->getId() ? 'selected' : '' }}>
                                {{ $g->getLibelle() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </td>
                    <td class="d-flex gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                        <a href="/gestion/nageuses/delete/{{ $nageuse->getId() }}"
                           onclick="return confirm('Supprimer cette nageuse ?')"
                           class="btn btn-danger btn-sm">Supprimer</a>
                    </td>
                </form>
            </tr>
            {% endforeach %}
            {% else %}
            <tr>
                <td colspan="3" class="text-center text-muted">Aucune nageuse</td>
            </tr>
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
