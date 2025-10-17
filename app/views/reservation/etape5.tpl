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

<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_etape5.js" defer></script>
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}