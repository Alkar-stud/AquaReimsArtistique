{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}
<div class="container-fluid">
    <h2 class="mb-4">Gestion des configurations</h2>

    <!-- Affichage mobile -->
    <div class="d-md-none mb-4">
        <button type="button" class="btn btn-success btn-sm w-100"
                onclick="document.getElementById('modal-ajout-config').style.display='block'">Ajouter</button>
        {% if !empty($data) %}
        <ul class="list-group mb-3">
            {% foreach $data as $config %}
            <li class="list-group-item">
                <form action="/gestion/configs/update" method="POST" id="form-edit-{{ $config->getId() }}" class="d-flex flex-column gap-2">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="form_anchor" value="config-card-{{ $config->getId() }}">
                    <input type="hidden" name="config_id" value="{{ $config->getId() }}">
                    <div>
                        <strong>{{ $config->getLabel() }}</strong>
                        <span class="text-muted">[{{ $config->getConfigKey() }}]</span>
                    </div>
                    <input type="text" name="label" class="form-control" value="{{ $config->getLabel() }}" required>
                    <input type="text" name="config_key" class="form-control" value="{{ $config->getConfigKey() }}" required>
                    <input type="text" name="config_value" class="form-control" value="{{ $config->getConfigValue() }}">
                    <div class="input-group">
                        <select class="form-select"
                                onchange="const input=document.getElementById('config_type_input_{{ $config->getId() }}-mobile'); if(this.value==='autre'){input.value='';input.removeAttribute('readonly');input.focus();}else{input.value=this.value;input.setAttribute('readonly','readonly');}">
                            <option value="autre">Autre...</option>
                            <option value="string" {{ $config->getConfigType()==='string' ? 'selected' : '' }}>Chaîne</option>
                            <option value="int" {{ $config->getConfigType()==='int' ? 'selected' : '' }}>Entier</option>
                            <option value="float" {{ $config->getConfigType()==='float' ? 'selected' : '' }}>Décimal</option>
                            <option value="bool" {{ $config->getConfigType()==='bool' ? 'selected' : '' }}>Booléen</option>
                            <option value="email" {{ $config->getConfigType()==='email' ? 'selected' : '' }}>Email</option>
                            <option value="date" {{ $config->getConfigType()==='date' ? 'selected' : '' }}>Date</option>
                            <option value="datetime" {{ $config->getConfigType()==='datetime' ? 'selected' : '' }}>Date+heure</option>
                            <option value="url" {{ $config->getConfigType()==='url' ? 'selected' : '' }}>URL</option>
                        </select>
                        <input type="text" name="config_type"
                               id="config_type_input_{{ $config->getId() }}-mobile"
                               class="form-control"
                               placeholder="Type personnalisé"
                               value="{{ $config->getConfigType() ?? '' }}">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm w-100">Enregistrer</button>
                    </div>
                </form>
                <div class="d-flex flex-column gap-2 mt-2">
                    <form method="POST" action="/gestion/configs/delete" onsubmit="return confirm('Supprimer cette configuration ?');">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="form_anchor" value="config-card-{{ $config->getId() }}">
                        <input type="hidden" name="config_id" value="{{ $config->getId() }}">
                        <button type="submit" class="btn btn-danger btn-sm w-100">Supprimer</button>
                    </form>
                </div>
            </li>
            {% endforeach %}
            {% endif %}
        </ul>
    </div>

    <!-- Affichage ajout mobile -->
    <div id="modal-ajout-config" class="modal"
         style="display:none;position:fixed;z-index:1050;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" style="max-width:500px;margin:10vh auto;background:#fff;border-radius:8px;overflow:hidden;">
            <div class="modal-content p-3">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title">Ajouter une configuration</h5>
                    <button type="button" class="btn-close" aria-label="Fermer"
                            onclick="document.getElementById('modal-ajout-config').style.display='none'"></button>
                </div>
                <form action="/gestion/configs/add" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="context" value="mobile">
                    <div class="mb-2">
                        <label>Libellé
                            <input type="text" name="label" class="form-control" required>
                        </label>
                    </div>
                    <div class="mb-2">
                        <label>Clé
                            <input type="text" name="config_key" class="form-control" required>
                        </label>
                    </div>
                    <div class="mb-2">
                        <label>Valeur
                            <input type="text" name="config_value" class="form-control">
                        </label>
                    </div>
                    <div class="mb-2">
                        <label>Type
                            <div class="input-group">
                                <select class="form-select"
                                        onchange="document.getElementById('config_type_input_add').value=this.value; if(this.value==='autre'){document.getElementById('config_type_input_add').focus();}">
                                    <option value="autre">Autre...</option>
                                    <option value="string">Chaîne</option>
                                    <option value="int">Entier</option>
                                    <option value="float">Décimal</option>
                                    <option value="bool">Booléen</option>
                                    <option value="email">Email</option>
                                    <option value="date">Date</option>
                                    <option value="datetime">Date+heure</option>
                                    <option value="url">URL</option>
                                </select>
                                <input type="text" name="config_type" id="config_type_input_add" class="form-control" placeholder="Type personnalisé">
                            </div>
                        </label>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary"
                                onclick="document.getElementById('modal-ajout-config').style.display='none'">Annuler</button>
                        <button type="submit" class="btn btn-success">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Affichage desktop -->
    <div class="table-responsive d-md-block d-none">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Libellé</th>
                <th>Clé</th>
                <th>Valeur</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <form action="/gestion/configs/add" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="context" value="desktop">
                    <td><input type="text" name="label" class="form-control" required></td>
                    <td><input type="text" name="config_key" class="form-control" required></td>
                    <td><input type="text" name="config_value" class="form-control"></td>
                    <td>
                        <div class="input-group">
                            <select class="form-select"
                                    onchange="document.getElementById('config_type_input_add_desktop').value=this.value; if(this.value==='autre'){document.getElementById('config_type_input_add_desktop').focus();}">
                                <option value="autre">Autre...</option>
                                <option value="string">Chaîne</option>
                                <option value="int">Entier</option>
                                <option value="float">Décimal</option>
                                <option value="bool">Booléen</option>
                                <option value="email">Email</option>
                                <option value="date">Date</option>
                                <option value="datetime">Date+heure</option>
                                <option value="url">URL</option>
                            </select>
                            <input type="text" name="config_type" id="config_type_input_add_desktop" class="form-control" placeholder="Type personnalisé">
                        </div>
                    </td>
                    <td><button type="submit" class="btn btn-success btn-sm">Ajouter</button></td>
                </form>
            </tr>
            {% if !empty($data) %}
            {% foreach $data as $config %}
            <tr>
                <form action="/gestion/configs/update" method="POST" id="form-edit-{{ $config->getId() }}">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="form_anchor" value="config-row-{{ $config->getId() }}">
                    <input type="hidden" name="config_id" value="{{ $config->getId() }}">
                    <td><input type="text" name="label" class="form-control" value="{{ $config->getLabel() }}" required></td>
                    <td><input type="text" name="config_key" class="form-control" value="{{ $config->getConfigKey() }}" required></td>
                    <td><input type="text" name="config_value" class="form-control" value="{{ $config->getConfigValue() }}"></td>
                    <td>
                        <div class="input-group">
                            <select class="form-select"
                                    onchange="const input=document.getElementById('config_type_input_{{ $config->getId() }}-desktop'); if(this.value==='autre'){input.value='';input.removeAttribute('readonly');input.focus();}else{input.value=this.value;input.setAttribute('readonly','readonly');}">
                                <option value="autre">Autre...</option>
                                <option value="string" {{ $config->getConfigType()==='string' ? 'selected' : '' }}>Chaîne</option>
                                <option value="int" {{ $config->getConfigType()==='int' ? 'selected' : '' }}>Entier</option>
                                <option value="float" {{ $config->getConfigType()==='float' ? 'selected' : '' }}>Décimal</option>
                                <option value="bool" {{ $config->getConfigType()==='bool' ? 'selected' : '' }}>Booléen</option>
                                <option value="email" {{ $config->getConfigType()==='email' ? 'selected' : '' }}>Email</option>
                                <option value="date" {{ $config->getConfigType()==='date' ? 'selected' : '' }}>Date</option>
                                <option value="datetime" {{ $config->getConfigType()==='datetime' ? 'selected' : '' }}>Date+heure</option>
                                <option value="url" {{ $config->getConfigType()==='url' ? 'selected' : '' }}>URL</option>
                            </select>
                            <input type="text" name="config_type"
                                   id="config_type_input_{{ $config->getId() }}-desktop"
                                   class="form-control"
                                   placeholder="Type personnalisé"
                                   value="{{ $config->getConfigType() ?? '' }}"
                                   {{ in_array($config->getConfigType(), ['string','int','float','bool','email','date','datetime','url']) ? 'readonly' : '' }}>
                        </div>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                </form>
                <form method="POST" action="/gestion/configs/delete" onsubmit="return confirm('Supprimer cette configuration ?');" class="d-inline">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="form_anchor" value="config-row-{{ $config->getId() }}">
                    <input type="hidden" name="config_id" value="{{ $config->getId() }}">
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
