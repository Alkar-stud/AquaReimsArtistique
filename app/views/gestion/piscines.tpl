<div class="container-fluid">
    <h2 class="mb-4">Gestion des piscines</h2>

    <!-- Vue Mobile -->
    <div class="d-md-none mb-4">
        <button type="button" class="btn btn-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modal-ajout-piscine">
            <i class="bi bi-plus-circle"></i>&nbsp;Ajouter une piscine
        </button>
        {% if !empty($data) %}
        <ul class="list-group mt-3">
            {% foreach $data_loop as $piscine_loop %}
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-bold">{{ $piscine_loop['item']->getLabel() }}</span>
                    <small class="text-muted d-block">Capacité: {{ $piscine_loop['item']->getMaxPlaces() }}</small>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-edit-piscine-{{ $piscine_loop['item']->getId() }}">
                        <i class="bi bi-pencil-square"></i>&nbsp;Éditer
                    </button>
                </div>
            </li>
            {% endforeach %}
        </ul>
        {% endif %}
    </div>

    <!-- Modale d'ajout (Mobile & Desktop) -->
    <div class="modal fade" id="modal-ajout-piscine" tabindex="-1" aria-labelledby="modal-ajout-piscine-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-ajout-piscine-label">Ajouter une piscine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form action="/gestion/piscines/add" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add-label" class="form-label">Nom de la piscine</label>
                            <input type="text" name="label" id="add-label" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="add-address" class="form-label">Adresse</label>
                            <input type="text" name="address" id="add-address" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="add-capacity" class="form-label">Capacité</label>
                            <input type="number" name="capacity" id="add-capacity" class="form-control" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="add-numberedSeats" class="form-label">Places numérotées</label>
                            <select name="numberedSeats" id="add-numberedSeats" class="form-select">
                                <option value="non">Non</option>
                                <option value="oui">Oui</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>&nbsp;Annuler
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i>&nbsp;Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modales de modification (générées dans la boucle) -->
    {% if !empty($data) %}
    {% foreach $data_loop as $piscine_loop %}
    <div class="modal fade" id="modal-edit-piscine-{{ $piscine_loop['item']->getId() }}" tabindex="-1" aria-labelledby="modal-edit-piscine-label-{{ $piscine_loop['item']->getId() }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-edit-piscine-label-{{ $piscine_loop['item']->getId() }}">Modifier : {{ $piscine_loop['item']->getLabel() }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form action="/gestion/piscines/update" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="piscine_id" value="{{ $piscine_loop['item']->getId() }}">
                        <div class="mb-3">
                            <label for="edit-label-{{ $piscine_loop['item']->getId() }}" class="form-label">Nom</label>
                            <input type="text" name="label" id="edit-label-{{ $piscine_loop['item']->getId() }}" class="form-control" required value="{{ $piscine_loop['item']->getLabel() }}">
                        </div>
                        <div class="mb-3">
                            <label for="edit-address-{{ $piscine_loop['item']->getId() }}" class="form-label">Adresse</label>
                            <input type="text" name="address" id="edit-address-{{ $piscine_loop['item']->getId() }}" class="form-control" value="{{ $piscine_loop['item']->getAddress() }}">
                        </div>
                        <div class="mb-3">
                            <label for="edit-capacity-{{ $piscine_loop['item']->getId() }}" class="form-label">Capacité</label>
                            <input type="number" name="capacity" id="edit-capacity-{{ $piscine_loop['item']->getId() }}" class="form-control" min="0" value="{{ $piscine_loop['item']->getMaxPlaces() }}">
                        </div>
                        <div class="mb-3">
                            <label for="edit-numberedSeats-{{ $piscine_loop['item']->getId() }}" class="form-label">Places numérotées</label>
                            <select name="numberedSeats" id="edit-numberedSeats-{{ $piscine_loop['item']->getId() }}" class="form-select">
                                <option value="non" {{ !$piscine_loop['item']->getNumberedSeats() ? 'selected' : '' }}>Non</option>
                                <option value="oui" {{ $piscine_loop['item']->getNumberedSeats() ? 'selected' : '' }}>Oui</option>
                            </select>
                            {% if $piscine_loop['item']->getNumberedSeats() %}
                            <div class="mt-2">
                                <a href="#" onclick="alert('A venir');">Gestion des gradins</a>
                            </div>
                            {% endif %}
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <form action="/gestion/piscines/delete" method="POST" onsubmit="return confirm('Supprimer cette piscine ?');">
                            <input type="hidden" name="piscine_id" value="{{ $piscine_loop['item']->getId() }}">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i>&nbsp;Supprimer
                            </button>
                        </form>
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
            </div>
        </div>
    </div>
    {% endforeach %}
    {% endif %}

    <!-- Vue Desktop -->
    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Nom de la piscine</th>
                <th>Adresse</th>
                <th>Capacité</th>
                <th style="width: 220px;">Places numérotées</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Ligne formulaire d’ajout -->
            <tr>
                <form action="/gestion/piscines/add" method="POST" id="form-add-piscine" class="d-contents"></form>
                <td><input type="text" name="label" class="form-control" form="form-add-piscine" required></td>
                <td><input type="text" name="address" class="form-control" form="form-add-piscine"></td>
                <td><input type="number" name="capacity" class="form-control" min="0" form="form-add-piscine"></td>
                <td>
                    <select name="numberedSeats" class="form-select" form="form-add-piscine">
                        <option value="non">Non</option>
                        <option value="oui">Oui</option>
                    </select>
                </td>
                <td>
                    <button type="submit" class="btn btn-success btn-sm" form="form-add-piscine">
                        <i class="bi bi-plus-circle"></i>&nbsp;Ajouter
                    </button>
                </td>
            </tr>
            {% if !empty($data) %}
            {% foreach $data_loop as $piscine_loop %}
            <tr>
                <form action="/gestion/piscines/update" method="POST" id="form-update-piscine-{{ $piscine_loop['item']->getId() }}" class="d-contents"></form>
                <td>
                    <input type="hidden" name="piscine_id" value="{{ $piscine_loop['item']->getId() }}" form="form-update-piscine-{{ $piscine_loop['item']->getId() }}">
                    <input type="text" name="label" class="form-control" required value="{{ $piscine_loop['item']->getLabel() }}" form="form-update-piscine-{{ $piscine_loop['item']->getId() }}">
                </td>
                <td><input type="text" name="address" class="form-control" value="{{ $piscine_loop['item']->getAddress() }}" form="form-update-piscine-{{ $piscine_loop['item']->getId() }}"></td>
                <td><input type="number" name="capacity" class="form-control" min="0" value="{{ $piscine_loop['item']->getMaxPlaces() }}" form="form-update-piscine-{{ $piscine_loop['item']->getId() }}"></td>
                <td>
                    <select name="numberedSeats" class="form-select" form="form-update-piscine-{{ $piscine_loop['item']->getId() }}">
                        <option value="non" {{ !$piscine_loop['item']->getNumberedSeats() ? 'selected' : '' }}>Non</option>
                        <option value="oui" {{ $piscine_loop['item']->getNumberedSeats() ? 'selected' : '' }}>Oui</option>
                    </select>
                    {% if $piscine_loop['item']->getNumberedSeats() %}
                    <div class="mt-1"><a href="#" onclick="alert('A venir');">Gestion des gradins</a></div>
                    {% endif %}
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm" form="form-update-piscine-{{ $piscine_loop['item']->getId() }}">
                            <i class="bi bi-save me-1"></i>&nbsp;Enregistrer
                        </button>
                        <form action="/gestion/piscines/delete" method="POST" onsubmit="return confirm('Supprimer cette piscine ?');">
                            <input type="hidden" name="piscine_id" value="{{ $piscine_loop['item']->getId() }}">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i>&nbsp;Supprimer</button>
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
