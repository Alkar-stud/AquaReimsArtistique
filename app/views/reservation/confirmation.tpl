<div class="container-fluid">
    <h2 class="mb-4">Confirmation de votre réservation</h2>

    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Événement :</strong> {{ htmlspecialchars($event->getName() ?? '') }}</li>
        {% if ($eventSession) %}
        <li class="list-group-item"><strong>Séance :</strong> {{ htmlspecialchars($eventSession->getEventStartAt()->format('d/m/Y H:i')) }}</li>
        {% endif %}
        {% if ($swimmer) %}
        <li class="list-group-item"><strong>Nageuse :</strong> {{ htmlspecialchars($swimmer->getName() ?? '') }}</li>
        {% endif %}
        <?php if (!empty($reservation['booker'])): ?>
        <li class="list-group-item"><strong>Réservant :</strong>
            {{ htmlspecialchars($reservation['booker']['name'] ?? '') }}
            {{ htmlspecialchars($reservation['booker']['firstname'] ?? '') }}
            ({{ htmlspecialchars($reservation['booker']['email'] ?? '') }})
            {{ htmlspecialchars($reservation['booker']['phone'] ?? '') }}
        </li>
        {% endif %}
        <li class="list-group-item"><strong>Nombre de places réservées :</strong> {{ count($reservation['reservation_detail']) ?? 0 }}</li>
    </ul>

    <h5>Votre panier</h5>

    <ul class="list-group mb-3">
        {% foreach $reservation['reservation_detail'] as $detail %}
        <li class="list-group-item d-flex justify-content-between lh-sm">
            <div>
                <h6 class="my-0"><?= htmlspecialchars($tarifs[$detail['tarif_id']]->getName()) ?> (<?= number_format($tarifs[$detail['tarif_id']]->getPrice() / 100, 2, ',', ' ') ?> €)</h6>
            </div>
        </li>

        {% endforeach %}
    </ul>


    //Finir l'affichage du récapitulatif et dans le controlleur calculer les totaux



    <div class="alert alert-info">
        Merci de vérifier vos informations avant validation finale.
    </div>

    <div class="row">
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <a href="/reservation/etape6Display" class="btn btn-secondary w-100 w-md-auto">Modifier ma réservation</a>
        </div>
        <div class="col-12 col-md-6">
            <button type="submit" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et payer</button>
        </div>
    </div>
</div>




<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_confirmation.js" defer></script>
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
