<div class="container-fluid">
    <h2 class="mb-4">Informations des participants</h2>

    <form id="reservationPlacesForm" enctype="multipart/form-data" aria-describedby="step4-hint">
        <p id="step4-hint" class="visually-hidden">
            Renseignez le nom et le prénom de chaque participant. Les champs marqués * sont obligatoires.
            Si un justificatif est requis, téléversez un fichier PDF ou image.
        </p>

        <input type="hidden" id="event_id" name="event_id" value="{{ $reservation['event_id'] }}">
        {% php %} $i = 0; {% endphp %}
        {% foreach $reservation['reservation_detail'] as $detail %}
        {% php %} $i++; {% endphp %}
        <div class="mb-3 participant-row">
            <input type="hidden" name="tarif_ids[]" value="{{ $detail['tarif_id'] }}">

            <label class="d-block mb-2">
                <strong>Participant {{ $i }} pour le tarif</strong>
                <span class="badge bg-secondary ms-2">{{ $tarifs[$detail['tarif_id']]->getName() }}</span>
            </label>

            <div class="row">
                <div class="col-md-6 mb-2 mb-md-0">
                    <label for="name_{{ $i }}" class="form-label">Nom du participant {{ $i }} *</label>
                    <input
                            type="text"
                            id="name_{{ $i }}"
                            name="names[]"
                            class="form-control"
                            required
                            autocomplete="family-name"
                            autocapitalize="words"
                            value="{{ $detail['name'] }}"
                            aria-invalid="false"
                            aria-describedby="step4-hint name_{{ $i }}_error"
                    >
                    <div id="name_{{ $i }}_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
                </div>
                <div class="col-md-6">
                    <label for="firstname_{{ $i }}" class="form-label">Prénom du participant {{ $i }} *</label>
                    <input
                            type="text"
                            id="firstname_{{ $i }}"
                            name="firstnames[]"
                            class="form-control"
                            required
                            autocomplete="given-name"
                            autocapitalize="words"
                            value="{{ $detail['firstname'] }}"
                            aria-invalid="false"
                            aria-describedby="step4-hint firstname_{{ $i }}_error"
                    >
                    <div id="firstname_{{ $i }}_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
                </div>
            </div>

            {% if ($tarifs[$detail['tarif_id']]->getRequiresProof() )%}
            <div class="mt-2">
                <label for="justificatif_{{ $i }}" class="form-label">Justificatif (PDF ou image){{
                    $detail['justificatif_name'] ? '' : ' *'
                    }}</label>
                <div id="justificatif_{{ $i }}_help" class="visually-hidden">
                    Formats acceptés : PDF ou image. Téléversez un fichier si requis.
                </div>
                <input
                        type="file"
                        id="justificatif_{{ $i }}"
                        name="justificatifs[]"
                        accept=".pdf,image/*"
                        class="form-control"
                        {{ $detail['justificatif_name'] ? '' : 'required' }}
                        aria-invalid="false"
                        aria-describedby="step4-hint justificatif_{{ $i }}_help justificatif_{{ $i }}_error"
                >
                {% if ($detail['justificatif_name']) %}
                <div class="mt-1 text-success">
                    Déjà fourni ({{ $detail['justificatif_original_name'] }}), pour le changer, il suffit d'envoyer un nouveau fichier.
                </div>
                {% endif %}
                <div id="justificatif_{{ $i }}_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
            </div>
            {% else %}
            <input type="hidden" name="justificatifs[]" value="">
            {% endif %}

        </div>
        {% endforeach %}

        <div class="row">
            <!-- order-x pour position x en mobile et order-mg-x pour desktop -->
            <!-- afin que le bouton principal soit à droite en desktop -->
            <!-- et en 1er en mobile -->
            <div class="col-12 col-md-6 order-2 order-md-1 mb-2 mb-md-0">
                <a href="/reservation/etape3Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6 order-1 order-md-2 d-flex justify-content-md-end mb-2 mb-md-0">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>

    <div id="reservationAlert" role="alert" aria-live="polite" tabindex="-1"></div>
</div>

<script type="module" src="/assets/js/reservations/etape4.js" defer></script>

{% if ($_ENV['APP_DEBUG'] == "true") %}
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
{% endif %}