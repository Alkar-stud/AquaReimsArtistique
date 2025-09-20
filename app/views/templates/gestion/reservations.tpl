{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2>Gestion des réservations</h2>

    <ul class="nav nav-tabs" id="reservations-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-tab="upcoming" type="button" role="tab">
                Réservations à venir
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-tab="past" type="button" role="tab">
                Passées
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <div id="tab-upcoming" class="tab-pane active">
            <!-- Desktop -->
            <!-- inclusion du contenu en JS -->

            <!-- Mobile -->
            <div class="d-block d-md-none">
                <!-- inclusion du contenu en JS -->
            </div>
        </div>
        <div id="tab-past" class="tab-pane d-none">
            <!-- Desktop -->

        </div>
    </div>

</div>

{% include '/gestion/reservations/_modal_details.tpl' %}

<script src="/assets/js/reservation_common.js" defer></script>
<script src="/assets/js/reservation_modif_data.js" defer></script>


{% if str_starts_with($uri, '/gestion/reservations') %}
<script type="module" src="/assets/js/gestion/reservations.js" defer></script>
{% endif %}