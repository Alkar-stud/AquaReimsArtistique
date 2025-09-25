<div class="container-fluid">
    <h2>Gestion des réservations</h2>

    <!-- Les messages flash seront affichés ici si nécessaire -->
    <div id="flash-message-container"></div>

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
        <div id="reservations-content" class="tab-pane active">
            <!-- Le contenu des onglets (upcoming/past) sera chargé ici par JavaScript -->
            <div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>
        </div>
    </div>
</div>

{% if str_starts_with($uri, '/gestion/reservations') %}
<script type="module" src="/assets/js/gestion/reservations.js" defer></script>
{% endif %}