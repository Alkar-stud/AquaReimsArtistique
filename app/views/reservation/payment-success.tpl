<div class="container-fluid">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 text-center">
            <h1 class="display-4 text-success">
                {% if ($reservation && $reservation['totals']['total_amount'] > 0) %}
                    🎉 Paiement réussi !
                {% else %}
                    🎉 Réservation enregistrée !
                {% endif %}
            </h1>
            <div class="alert alert-success">
                Merci, votre réservation a bien été enregistrée.<br>
                <strong>Numéro de réservation :</strong> {{ $reservationNumber?? 'ben non, pas encore fait :-)' }}
            </div>
        </div>
    </div>

    {% if ($event) %}
    <div class="mb-3">
        <strong>Rendez-vous pour le : </strong>
        {{ htmlspecialchars($event->getSessions->getEventStartAt()->format('d/m/Y H:i')) }},
        <strong>Ouverture des portes :</strong>
        {{ htmlspecialchars($event->getSessions->getOpeningDoorsAt()->format('d/m/Y H:i')) }}
    </div>
    {% endif %}

</div>