<div class="row">
    <!-- Colonne Export PDF -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i>Quel PDF générer ?
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label d-block">Type de document :</label>
                    <!-- Liste des types de PDF -->
                    {% php %}$__firstKey = array_key_first($pdfTypes);{% endphp %}
                    {% foreach $pdfTypes as $key => $cfg %}
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="pdf-type-selector"
                               id="type-{{ $key }}" value="{{ $key }}"
                               {{ $key === $__firstKey ? 'checked' : '' }}>
                        <label class="form-check-label" for="type-{{ $key }}">{{ $cfg['label'] }}</label>
                    </div>
                    {% endforeach %}

                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Trier par :</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="pdf-sort-selector" id="sort-id" value="IDreservation">
                        <label class="form-check-label" for="sort-id">ID de réservation</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="pdf-sort-selector" id="sort-name" value="NomReservation" checked>
                        <label class="form-check-label" for="sort-name">Nom de la réservation</label>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a id="generate-pdf-btn" href="#" target="_blank" class="btn btn-primary">
                    <i class="bi bi-download me-2"></i>Générer le PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Colonne Export CSV -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary">
                <i class="bi bi-filetype-csv me-2"></i>Quel CSV extraire ?
            </div>
            <div class="card-body">
                <p class="card-title fw-bold">Champs à inclure :</p>
                <div class="d-flex flex-wrap">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="csv-field-name" value="name" checked>
                        <label class="form-check-label" for="csv-field-name">Nom</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="csv-field-firstName" value="firstName" checked>
                        <label class="form-check-label" for="csv-field-firstName">Prénom</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="csv-field-email" value="email" checked>
                        <label class="form-check-label" for="csv-field-email">Email</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="csv-field-phone" value="phone">
                        <label class="form-check-label" for="csv-field-phone">Téléphone</label>
                    </div>
                </div>

                <hr>

                <div class="mb-3">
                    <label for="csv-tarif-selector" class="form-label">Extraire pour un tarif spécifique :</label>
                    <select id="csv-tarif-selector" class="form-select">
                        <option value="all" selected>Tous les participants</option>
                        {% if !empty($tarifs) %}
                        {% foreach $tarifs as $tarif %}
                        <option value="{{ $tarif->getId() }}">
                            {{ $tarif->getName() }}
                        </option>
                        {% endforeach %}
                        {% endif %}
                    </select>
                </div>
            </div>
            <div class="card-footer text-end">
                <button id="generate-csv-btn" class="btn btn-primary"><i class="bi bi-table me-2"></i>Générer le CSV</button>
            </div>
        </div>
    </div>
</div>