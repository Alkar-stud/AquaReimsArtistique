<div class="container-fluid" id="reservation-data-container">
    <h2>Gestion des réservations</h2>

    <ul class="nav nav-tabs mb-3" id="reservationTabs">
        <li class="nav-item">
            <a class="nav-link {{ !$tab ? 'active' : '' }}" id="tab-upcoming" href="/gestion/reservations">À venir</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'past' ? 'active' : '' }}" id="tab-past" href="/gestion/reservations?tab=past">Passées</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'extract' ? 'active' : '' }}" id="tab-past" href="/gestion/reservations?tab=extract">Extractions</a>
        </li>
    </ul>

    {% if $tab === 'extract' %}
        {% include '/gestion/reservations/_extracts.tpl' %}
    {% else %}
        {% include '/gestion/reservations/_reservations_list.tpl' %}
    {% endif %}

</div>
