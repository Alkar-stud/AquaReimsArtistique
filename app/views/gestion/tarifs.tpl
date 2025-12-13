<div class="container-fluid">
    <h2 class="mb-4">Gestion des tarifs</h2>
    <ul class="nav nav-tabs mb-3" id="tarifTabs">
        <li class="nav-item">
            <a class="nav-link {{ !$onglet ? 'active' : '' }}" id="tab-all" href="/gestion/tarifs">Tous</a>
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
    <div class="d-md-none mb-4">
        {% if !empty($data) %}
        <ul class="list-group mb-3">
            {% foreach $data_loop as $tarif_loop %}
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-bold">{{ $tarif_loop['item']->getName() }}</span>
                    <small class="text-muted d-block">{{ number_format($tarif_loop['item']->getPrice() / 100, 2, ',', ' ') }} €</small>
                </div>
                <button type="button" class="btn btn-secondary btn-sm"
                        data-id="{{ $tarif_loop['item']->getId() }}"
                        data-name="{{ $tarif_loop['item']->getName() }}"
                        data-description="{{ $tarif_loop['item']->getDescription() }}"
                        data-seat_count="{{ $tarif_loop['item']->getSeatCount() ?? '' }}"
                        data-min_age="{{ $tarif_loop['item']->getMinAge() ?? '' }}"
                        data-max_age="{{ $tarif_loop['item']->getMaxAge() ?? '' }}"
                        data-max_tickets="{{ $tarif_loop['item']->getMaxTickets() ?? '' }}"
                        data-price="{{ number_format($tarif_loop['item']->getPrice() / 100, 2, '.', '') }}"
                        data-includes_program="{{ $tarif_loop['item']->getIncludesProgram() ? '1' : '0' }}"
                        data-requires_proof="{{ $tarif_loop['item']->getRequiresProof() ? '1' : '0' }}"
                        data-access_code="{{ $tarif_loop['item']->getAccessCode() ?? '' }}"
                        data-is_active="{{ $tarif_loop['item']->isActive() ? '1' : '0' }}"
                        onclick="openTarifModal('edit', this.dataset)">
                    <i class="bi bi-pencil-square"></i>&nbsp;Éditer
                </button>
            </li>
            {% endforeach %}
        </ul>
        {% endif %}
        <button type="button" class="btn btn-success btn-sm w-100" onclick="openTarifModal('add')">
            <i class="bi bi-plus-circle"></i>&nbsp;Ajouter
        </button>
    </div>
    <div id="modal-tarif" class="modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-tarif-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form id="modal-tarif-form" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="onglet" value="{{ $onglet }}">
                        <input type="hidden" name="tarif_id" id="modal-tarif-id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal-tarif-name" class="form-label">Nom</label>
                                <input type="text" name="name" id="modal-tarif-name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal-tarif-description" class="form-label">Description</label>
                                <textarea name="description" id="modal-tarif-description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="row">
                        </div>
                        <div class="mb-3">
                            <label for="modal-tarif-price" class="form-label">Prix (€)</label>
                            <input type="number" min="0" step="0.01" name="price" id="modal-tarif-price" class="form-control" required>
                        </div>
                            <div class="col-md-4 mb-3">
                                <label for="modal-tarif-seat_count" class="form-label">Nb. sièges</label>
                                <input type="number" min="0" name="seat_count" id="modal-tarif-seat_count" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3 d-none">
                                <label for="modal-tarif-min_age" class="form-label">Âge min.</label>
                                <input type="number" min="0" name="min_age" id="modal-tarif-min_age" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3 d-none">
                                <label for="modal-tarif-max_age" class="form-label">Âge max.</label>
                                <input type="number" min="0" name="max_age" id="modal-tarif-max_age" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal-tarif-max_tickets" class="form-label">Max. tickets / commande</label>
                                <input type="number" min="0" name="max_tickets" id="modal-tarif-max_tickets" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal-tarif-access_code" class="form-label">Code d'accès</label>
                                <input type="text" name="access_code" id="modal-tarif-access_code" class="form-control">
                            </div>
                        </div>
                        <div class="d-flex gap-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="includes_program" id="modal-tarif-includes_program">
                                <label class="form-check-label" for="modal-tarif-includes_program">Programme inclus</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="requires_proof" id="modal-tarif-requires_proof">
                                <label class="form-check-label" for="modal-tarif-requires_proof">Justificatif requis</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="modal-tarif-is_active">
                                <label class="form-check-label" for="modal-tarif-is_active">Actif</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" id="modal-tarif-delete-btn" class="btn btn-danger">
                            <i class="bi bi-trash"></i>&nbsp;Supprimer
                        </button>
                        <div>
                            <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>&nbsp;Annuler
                            </button>
                            <button type="submit" class="btn btn-secondary">
                                <i class="bi bi-save"></i>&nbsp;Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
                <form id="modal-tarif-delete-form" action="/gestion/tarifs/delete" method="POST" class="d-none">
                    <input type="hidden" name="onglet" value="{{ $onglet }}">
                    <input type="hidden" name="tarif_id" id="modal-tarif-delete-id">
                </form>
            </div>
        </div>
    </div>
    <div class="table-responsive d-md-block d-none">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Nom</th
                ><th>Description</th>
                <th>Nb sièges</th>
                <th class="d-none">Âge min.</th>
                <th class="d-none">Âge max.</th>
                <th>Max tickets</th>
                <th>Prix</th>
                <th>Prog. inclus</th>
                <th>Justif. requis</th>
                <th>Code d'accès</th>
                <th>Actif</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <form action="/gestion/tarifs/add" method="POST">
                    <input type="hidden" name="onglet" value="{{ $onglet }}">
                    <td><input type="text" name="name" class="form-control" required></td>
                    <td><input type="text" name="description" class="form-control"></td>
                    <td><input type="number" min="0" name="seat_count" class="form-control"></td>
                    <td><input type="number" min="0" name="min_age" class="form-control d-none"></td>
                    <td><input type="number" min="0" name="max_age" class="form-control d-none"></td>
                    <td><input type="number" min="0" name="max_tickets" class="form-control"></td>
                    <td><input type="number" min="0" step="0.01" name="price" class="form-control" required></td>
                    <td class="text-center"><input type="checkbox" name="includes_program"></td>
                    <td class="text-center"><input type="checkbox" name="requires_proof"></td>
                    <td><input type="text" name="access_code" class="form-control"></td>
                    <td class="text-center"><input type="checkbox" name="is_active" checked></td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-plus-circle"></i>&nbsp;Ajouter
                        </button>
                    </td>
                </form>
            </tr>
            {% if !empty($data) %}
            {% foreach $data_loop as $tarif_loop %}
            <tr>
                <form action="/gestion/tarifs/update" method="POST" id="form-update-{{ $tarif_loop['item']->getId() }}" class="d-contents"></form>
                <td>
                    <input type="hidden" name="onglet" value="{{ $onglet }}" form="form-update-{{ $tarif_loop['item']->getId() }}">
                    <input type="hidden" name="tarif_id" value="{{ $tarif_loop['item']->getId() }}" form="form-update-{{ $tarif_loop['item']->getId() }}">
                    <input type="text" name="name" class="form-control" required value="{{ $tarif_loop['item']->getName() ?? '' }}" size="50" form="form-update-{{ $tarif_loop['item']->getId() }}">
                </td>
                <td><input type="text" name="description" class="form-control" value="{{ $tarif_loop['item']->getDescription() ?? '' }}" size="100" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td><input type="number" name="seat_count" min="0" class="form-control" value="{{ $tarif_loop['item']->getSeatCount() ?? '' }}" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td><input type="number" name="min_age" min="0" class="form-control" value="{{ $tarif_loop['item']->getMinAge() ?? '' }}" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td><input type="number" name="max_age" class="form-control" value="{{ $tarif_loop['item']->getMaxAge() ?? '' }}" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td><input type="number" name="max_tickets" min="0" class="form-control" value="{{ $tarif_loop['item']->getMaxTickets() }}" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td><input type="number" step="0.10" min="0" name="price" class="form-control" required value="{{ number_format($tarif_loop['item']->getPrice() / 100, 2, '.', '') }}" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td class="text-center"><input type="checkbox" name="includes_program" {{ $tarif_loop['item']->getIncludesProgram() ? 'checked' : '' }} form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td class="text-center"><input type="checkbox" name="requires_proof" {{ $tarif_loop['item']->getRequiresProof() ? 'checked' : '' }} form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td><input type="text" name="access_code" class="form-control" value="{{ $tarif_loop['item']->getAccessCode() ?? '' }}" form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td class="text-center"><input type="checkbox" name="is_active" {{ $tarif_loop['item']->isActive() ? 'checked' : '' }} form="form-update-{{ $tarif_loop['item']->getId() }}"></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm w-100" form="form-update-{{ $tarif_loop['item']->getId() }}">
                            <i class="bi bi-save"></i>&nbsp;Enregistrer
                        </button>
                        <form action="/gestion/tarifs/delete" method="POST" onsubmit="return confirm('Supprimer ce tarif ?');" class="d-inline">
                            <input type="hidden" name="onglet" value="{{ $onglet }}">
                            <input type="hidden" name="tarif_id" value="{{ $tarif_loop['item']->getId() }}">
                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                <i class="bi bi-trash"></i>&nbsp;Supprimer
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            {% endforeach %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/gestion/tarifs.js" defer></script>