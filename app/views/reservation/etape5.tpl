<div class="container-fluid">
    <h2 class="mb-4">Choix des places assises</h2>

    <form id="reservationPlacesForm">

        <div class="row">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <a href="/reservation/etape4Display" class="btn btn-secondary w-100 w-md-auto">Modifier mon choix précédent</a>
            </div>
            <div class="col-12 col-md-6">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et continuer</button>
            </div>
        </div>
    </form>
</div>

<script src="/assets/js/reservations/etape5.js" defer></script>

{% if ($_ENV['APP_DEBUG'] == "true") %}
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
{% endif %}