{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}
<ul class="nav nav-tabs mb-3" id="tarifTabs">
    <li class="nav-item">
        <a class="nav-link {{ $onglet === 'all' ? 'active' : '' }}" id="tab-all" href="/gestion/tarifs?onglet=all">Tous</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $onglet === 'places' ? 'active' : '' }}" id="tab-places" href="/gestion/tarifs?onglet=places">Places assises</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $onglet === 'autres' ? 'active' : '' }}" id="tab-autres" href="/gestion/tarifs?onglet=autres">Autres</a>
    </li>
</ul>
<div id="content-places" style="{{ $onglet === 'places' ? '' : 'display:none;' }}"></div>
<div id="content-autres" style="{{ $onglet === 'autres' ? '' : 'display:none;' }}"></div>
<div class="container-fluid">
    <h2 class="mb-4">Gestion des tarifs</h2>
    <div class="d-md-none mb-4">
        {% if !empty($data) %}
        <ul class="list-group mb-3">
            {% foreach $data as $tarif %}
            <li class="list-group-item d-flex justify-content-between align-items-center"
                data-id="{{ $tarif->getId() }}"
                data-label="{{ $tarif->getLabel() }}"
                data-description="{{ $tarif->getDescription() }}"
                data-nb_place="{{ $tarif->getNbPlace() ?? '' }}"
                data-age_min="{{ $tarif->getAgeMin() ?? '' }}"
                data-age_max="{{ $tarif->getAgeMax() ?? '' }}"
                data-max_tickets="{{ $tarif->getMaxTickets() ?? '' }}"
                data-price="{{ number_format($tarif->getPrice() / 100, 2, '.', '') }}"
                data-is_program_show_include="{{ $tarif->getIsProgramShowInclude() ? '1' : '0' }}"
                data-is_proof_required="{{ $tarif->getIsProofRequired() ? '1' : '0' }}"
                data-access_code="{{ $tarif->getAccessCode() !== null ? $tarif->getAccessCode() : '' }}"
                data-is_active="{{ $tarif->getIsActive() ? '1' : '0' }}"
                onclick="openTarifModal('edit', this.dataset)">
                <span>{{ $tarif->getLabel() }}</span>
                <span>{{ number_format($tarif->getPrice() / 100, 2, ',', ' ') }} €</span>
                <a href="/gestion/tarifs/delete/{{ $tarif->getId() }}" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce tarif ?');">Supprimer</a>
            </li>
            {% endforeach %}
        </ul>
        {% endif %}
        <button type="button" class="btn btn-success btn-sm w-100" onclick="openTarifModal('add')">Ajouter</button>
    </div>
    <div id="modal-tarif" class="modal" style="display:none;position:fixed;z-index:1050;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);">
        <!-- contenu modale identique -->
    </div>
    <div class="table-responsive d-md-block d-none">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Libellé</th><th>Description</th><th>Nb places</th><th>Âge min</th><th>Âge max</th><th>Max tickets</th><th>Prix</th><th>Programme inclus</th><th>Justificatif</th><th>Code accès</th><th>Actif</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <form action="/gestion/tarifs/add" method="POST">
                    <td><input type="text" name="label" class="form-control" required></td>
                    <td><input type="text" name="description" class="form-control"></td>
                    <td><input type="number" min="0" name="nb_place" class="form-control"></td>
                    <td><input type="number" min="0" name="age_min" class="form-control"></td>
                    <td><input type="number" min="0" name="age_max" class="form-control"></td>
                    <td><input type="number" min="0" name="max_tickets" class="form-control"></td>
                    <td><input type="number" min="0" step="0.01" name="price" class="form-control" required></td>
                    <td class="text-center"><input type="checkbox" name="is_program_show_include"></td>
                    <td class="text-center"><input type="checkbox" name="is_proof_required"></td>
                    <td><input type="text" name="access_code" class="form-control"></td>
                    <td class="text-center"><input type="checkbox" name="is_active" checked></td>
                    <td><button type="submit" class="btn btn-success btn-sm">Ajouter</button></td>
                </form>
            </tr>
            {% if !empty($data) %}
            {% foreach $data as $tarif %}
            <tr>
                <form action="/gestion/tarifs/update/{{ $tarif->getId() }}" method="POST">
                    <td><input type="text" name="label" class="form-control" required value="{{ $tarif->getLabel() ?? '' }}" size="50"></td>
                    <td><input type="text" name="description" class="form-control" value="{{ $tarif->getDescription() ?? '' }}" size="100"></td>
                    <td><input type="number" name="nb_place" min="0" class="form-control" value="{{ $tarif->getNbPlace() ?? '' }}"></td>
                    <td><input type="number" name="age_min" min="0" class="form-control" value="{{ $tarif->getAgeMin() ?? '' }}"></td>
                    <td><input type="number" name="age_max" class="form-control" value="{{ $tarif->getAgeMax() ?? '' }}"></td>
                    <td><input type="number" name="max_tickets" min="0" class="form-control" value="{{ $tarif->getMaxTickets() }}"></td>
                    <td><input type="number" step="0.10" min="0" name="price" class="form-control" required value="{{ number_format($tarif->getPrice() / 100, 2, '.', '') }}"></td>
                    <td class="text-center"><input type="checkbox" name="is_program_show_include" {{ $tarif->getIsProgramShowInclude() ? 'checked' : '' }}></td>
                    <td class="text-center"><input type="checkbox" name="is_proof_required" {{ $tarif->getIsProofRequired() ? 'checked' : '' }}></td>
                    <td><input type="text" name="access_code" class="form-control" value="{{ $tarif->getAccessCode() ?? '' }}"></td>
                    <td class="text-center"><input type="checkbox" name="is_active" {{ $tarif->getIsActive() ? 'checked' : '' }}></td>
                    <td>
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                        <a href="/gestion/tarifs/delete/{{ $tarif->getId() }}" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce tarif ?');">Supprimer</a>
                    </td>
                </form>
            </tr>
            {% endforeach %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
