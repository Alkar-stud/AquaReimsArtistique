<div class="container-fluid">
    <h2 class="mb-4">Confirmation de votre réservation</h2>

    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Événement :</strong> {{ $event->getName() ?? '' }}</li>
        {% if ($eventSession) %}
        <li class="list-group-item"><strong>Séance :</strong> {{ $eventSession->getEventStartAt()->format('d/m/Y H:i') }}</li>
        {% endif %}
        {% if ($swimmer) %}
        <li class="list-group-item"><strong>Nageuse/nageur :</strong> {{ $swimmer->getName() ?? '' }}</li>
        {% endif %}
        <?php if (!empty($reservation['booker'])): ?>
        <li class="list-group-item"><strong>Réservant :</strong>
            {{ $reservation['booker']['name'] ?? '' }}
            {{ $reservation['booker']['firstname'] ?? '' }}
            ({{ $reservation['booker']['email'] ?? '' }})
            {{ $reservation['booker']['phone'] ?? '' }}
        </li>
        {% endif %}
        <li class="list-group-item"><strong>Nombre de places réservées :</strong> {{ count($reservation['reservation_detail']) ?? 0 }}</li>
    </ul>

    <h5>Votre panier</h5>

    <!-- boucle des détails préparée -->
    <ul class="list-group mb-3">
        <h5>Détail des participants : </h5>
        {% foreach $details as $tarif_id => $group %}
        <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="me-3">
                <strong>{{ $group['tarif_name'] ?? '' }}</strong>
                {% if !empty($group['description']) %}
                <small class="text-muted">— {{ $group['description'] }}</small>
                {% endif %}
                <div class="mt-1">
                    {% foreach $group['participants'] as $i => $p %}
                    {{ ($p['name'] ?? '') . ' ' . ($p['firstname'] ?? '') }}
                    {% if !empty($p['place_id']) %}
                    <em>(place {{ $p['place_id'] }})</em>
                    {% endif %}
                    {% if !empty($p['justificatif_name']) %}
                    <div class="text-muted small">({{ $p['justificatif_original_name'] }})</div>
                    {% endif %}
                    {% if !empty($p['tarif_access_code']) %}
                    <div class="text-muted small">(code {{ $p['tarif_access_code'] }})</div>
                    {% endif %}
                    {% if $i < ($group['count'] - 1) %}<br>{% endif %}
                    {% endforeach %}
                </div>
            </div>
            <div class="ms-auto text-end">
                <strong>{{ number_format(($group['total'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
                <div class="text-muted small">
                    {% if $group['seatCount'] > 0 %}
                    {{ $group['packs'] }} × {{ number_format($group['price'] / 100, 2, ',', ' ') }} € ({{ $group['seatCount'] }} place{{ $group['seatCount'] > 1 ? 's' : '' }})
                    {% else %}
                    {{ $group['count'] }} × {{ number_format($group['price'] / 100, 2, ',', ' ') }} €
                    {% endif %}
                </div>
            </div>
        </li>
        {% endforeach %}
    </ul>

    <!-- boucle des compléments préparée -->
    {% if (!empty($complements)) %}
    <h5>Compléments</h5>
    <ul class="list-group mb-3">
        {% foreach $complements as $tarif_id => $group %}
        <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="me-3">
                <strong>{{ $group['tarif_name'] ?? '' }}</strong>
                {% if !empty($group['description']) %}
                <small class="text-muted">— {{ $group['description'] }}</small>
                {% endif %}
                <div class="mt-1">
                    Qté : {{ $group['qty'] }}
                </div>
                {% if (!empty($group['codes'])) %}
                <div class="text-muted small">(code {{ implode(', ', $group['codes']) }})</div>
                {% endif %}
            </div>
            <div class="ms-auto text-end">
                <strong>{{ number_format(($group['total'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
                <div class="text-muted small">
                    {{ $group['qty'] }} × {{ number_format($group['price'] / 100, 2, ',', ' ') }} €
                </div>
            </div>
        </li>
        {% endforeach %}
    </ul>
    {% endif %}

    <!-- grandTotal fourni par le contrôleur -->
    <ul class="list-group mb-4">
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><strong>Total à payer</strong></span>
            <strong>{{ number_format($grandTotal / 100, 2, ',', ' ') }} €</strong>
        </li>
    </ul>

    <div class="alert alert-info text-center">
        Merci de vérifier vos informations{{ $grandTotal == 0 ? ' avant de valider':' avant paiement' }}.
    </div>

    <div class="row">
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <a href="/reservation/etape6Display" class="btn btn-secondary w-100 w-md-auto" id="returnBtn">Modifier ma réservation</a>
        </div>
        <div class="col-12 col-md-6">
            <a href="/reservation/payment" class="btn btn-primary w-100 w-md-auto" id="submitButton">Valider et {{ $grandTotal == 0 ? 'enregistrer':'payer' }}</a>
        </div>
    </div>
</div>

{% if ($_ENV['APP_DEBUG'] == "true") %}
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
{% endif %}
