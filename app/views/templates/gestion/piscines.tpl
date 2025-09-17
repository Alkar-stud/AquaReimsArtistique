{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}
<div class="container-fluid">
    <h2 class="mb-4">Gestion des piscines</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Nom</th>
                <th>Adresse</th>
                <th>Capacité</th>
                <th>Places numérotées</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Ligne formulaire d’ajout -->
            <tr>
                <form action="/gestion/piscines/add" method="POST">
                    <td data-label="Nom">
                        <label>
                            <input type="text" name="nom" class="form-control" required>
                        </label>
                    </td>
                    <td data-label="Adresse">
                        <label>
                            <input type="text" name="adresse" class="form-control">
                        </label>
                    </td>
                    <td data-label="Capacité">
                        <label>
                            <input type="number" name="capacity" class="form-control" min="0">
                        </label>
                    </td>
                    <td data-label="Places numérotées">
                        <label>
                            <select name="numberedSeats" class="form-select">
                                <option value="non">Non</option>
                                <option value="oui">Oui</option>
                            </select>
                        </label>
                    </td>
                    <td data-label="Actions">
                        <button type="submit" class="btn btn-success btn-sm">Ajouter</button>
                    </td>
                </form>
            </tr>
            {% if !empty($data) %}
            {% foreach $data as $piscine %}
            <tr>
                <form action="/gestion/piscines/update/{{ $piscine->getId() }}" method="POST">
                    <td data-label="Nom">
                        <input type="text" name="nom" class="form-control" required value="{{ $piscine->getLibelle() }}">
                    </td>
                    <td data-label="Adresse">
                        <input type="text" name="adresse" class="form-control" value="{{ $piscine->getAdresse() }}">
                    </td>
                    <td data-label="Capacité">
                        <input type="number" name="capacity" class="form-control" min="0" value="{{ $piscine->getMaxPlaces() }}">
                    </td>
                    <td data-label="Places numérotées">
                        <select name="numberedSeats" class="form-select">
                            <option value="non" {{ !$piscine->getNumberedSeats() ? 'selected' : '' }}>Non</option>
                            <option value="oui" {{ $piscine->getNumberedSeats() ? 'selected' : '' }}>Oui</option>
                        </select>
                        {% if $piscine->getNumberedSeats() %}
                        <a href="#" onclick="alert('A venir');">Gestion des gradins</a>
                        {% endif %}
                    </td>
                    <td data-label="Actions">
                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                        <a href="/gestion/piscines/delete/{{ $piscine->getId() }}" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette piscine ?');">Supprimer</a>
                    </td>
                </form>
            </tr>
            {% endforeach %}
            {% endif %}
            </tbody>
        </table>
    </div>
</div>
