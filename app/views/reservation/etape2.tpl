<div class="container-fluid">
    <h2 class="mb-4">Informations personnelles</h2>

    <form id="reservationInfosForm" class="mb-4">
        <input type="hidden" id="event_id" name="event_id" value="{{ $reservation['event_id'] }}">
        <div class="mb-3">
            <label for="name" class="form-label">Nom *</label>
            <input type="text" class="form-control" id="name" name="name" required
                   value="{{ htmlspecialchars($reservation['booker']['name'] ?? '') }}">
        </div>
        <div class="mb-3">
            <label for="firstname" class="form-label">Prénom *</label>
            <input type="text" class="form-control" id="firstname" name="firstname" required
                   value="{{ htmlspecialchars($reservation['booker']['firstname'] ?? '') }}">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Adresse mail *</label>
            <input type="email" class="form-control" id="email" name="email" required
                   value="{{ htmlspecialchars($reservation['booker']['email'] ?? '') }}">
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Téléphone</label>
            <input type="tel" class="form-control" id="phone" name="phone"
                   value="{{ htmlspecialchars($reservation['booker']['phone'] ?? '') }}">
        </div>
        <div class="row">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <a href="/reservation" class="btn btn-secondary w-100 w-md-auto">Modifier la session choisie</a>
            </div>
            <div class="col-12 col-md-6">
                <button type="submit" class="btn btn-primary w-100 w-md-auto">Valider et continuer</button>
            </div>
        </div>
    </form>
    <div id="reservationAlert"></div>
</div>


Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}

<script>
    window.swimmerPerGroup = {{! json_encode($swimmerPerGroup ?? []) !}};
</script>
<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_etape2.js" defer></script>
