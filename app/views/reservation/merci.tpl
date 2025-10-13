{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid text-center">
    <div class="pt-5">
        {% if ($reservation->getTotalAmountPaid() > 0) %}
        <h2 class="mb-4 text-success">
            🎉 Votre paiement a bien été reçu et votre réservation enregistrée. 🎉
            <br><br>
            Un email de confirmation vous a été envoyé.
        </h2>
        {% else %}
        <h2 class="mb-4 text-success">
            🎉 Votre réservation a bien été enregistrée. 🎉
            <br><br>
            Un email de confirmation vous a été envoyé.
        </h2>
        {% endif %}

        Rendez-vous le {{ $reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y') }}
        à {{ $reservation->getEventSessionObject()->getEventStartAt()->format('H\hi') }}
        à la piscine <strong>{{ $reservation->getEventObject()->getPiscine()->getLabel() }}</strong>
        <small class="text-muted">({{ $reservation->getEventObject()->getPiscine()->getAddress() }})</small>
        <br>
        Ouverture des portes à {{ $reservation->getEventSessionObject()->getOpeningDoorsAt()->format('d/m/Y \à H\hi') }}<br>
        <br><br>
        <a href="/" class="btn btn-secondary mt-3">Retour à l'accueil</a>
    </div>
</div>