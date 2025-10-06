{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2 class="mb-4">Informations des participants</h2>

    <form id="reservationPlacesForm">
        <input type="hidden" id="event_id" name="event_id" value="{{ $reservation['event_id'] }}">
        {% php %} $i = 0; {% endphp %}
        {% foreach $reservation['reservation_detail'] as $detail %}
        {% php %} $i++; {% endphp %}
        <div class="mb-3">
            <input type="hidden" name="tarif_ids[]" value="{{ $detail['tarif_id'] }}">
            <label>
                <strong>Participant {{ $i }} pour le tarif</strong>
                <span class="badge bg-secondary ms-2">{{ $tarifs[$detail['tarif_id']]->getName() }}</span>
            </label>
            <div class="row">
                <div class="col-md-6">
                    <input type="text" name="names[]" class="form-control" placeholder="Nom" required value="{{ $detail['name'] }}">
                </div>
                <div class="col-md-6">
                    <input type="text" name="firstnames[]" class="form-control" placeholder="Prénom" required value="{{ $detail['firstname'] }}">
                </div>
            </div>
            {% if ($tarifs[$detail['tarif_id']]->getRequiresProof() )%}
            <div class="mt-2">
                <label>Justificatif (PDF ou image) :</label>
                <input type="file" name="justificatifs[]" accept=".pdf,image/*" class="form-control" {{ $detail['justificatif_name'] ? '' : 'required' }}>
                {% if ($detail['justificatif_name']) %}
                <div class="mt-1 text-success">Déjà fourni ({{ $detail['justificatif_original_name'] }}), pour le changer, il suffit d'envoyer un nouveau fichier</div>
                {% endif %}
            </div>
            {% else %}
            <input type="hidden" name="justificatifs[]" value="">
            {% endif %}

        </div>
        {% endforeach %}
        <div class="row">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <a href="/reservation/etape3Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>

</div>

<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_etape4.js" defer></script>
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}