<div class="container-fluid">
    <h2 class="mb-4">Confirmation de votre réservation</h2>

    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Événement :</strong> {{ htmlspecialchars($event->getName() ?? '') }}</li>
        {% if ($eventSession) %}
        <li class="list-group-item"><strong>Séance :</strong> {{ htmlspecialchars($eventSession->getEventStartAt()->format('d/m/Y H:i')) }}</li>
        {% endif %}
        {% if ($swimmer) %}
        <li class="list-group-item"><strong>Nageuse/nageur :</strong> {{ htmlspecialchars($swimmer->getName() ?? '') }}</li>
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

    {% php %}$grandTotal = 0;{% endphp %}

    <ul class="list-group mb-3">
        <h5>Détail des participants : </h5>
        {% foreach $details as $tarif_id => $group %}
        {% php %}
        $participants = array_values(array_filter($group, 'is_array'));
        $count = count($participants);
        $seatCount = $tarifs[$tarif_id]->getSeatCount() ?? 0;
        $price = $tarifs[$tarif_id]->getPrice();
        $packs = ($seatCount > 0) ? intdiv($count, $seatCount) : $count;
        $total = $packs * $price;

        // 2) Ajoute au total global
        $grandTotal += $total;
        {% endphp %}
        <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="me-3">
                <strong>{{ htmlspecialchars($group['tarif_name'] ?? '') }}</strong>
                {% if !empty($group['description']) %}
                <small class="text-muted">— {{ htmlspecialchars($group['description']) }}</small>
                {% endif %}
                <div class="mt-1">
                    {% foreach $participants as $i => $p %}
                    {{ htmlspecialchars(($p['firstname'] ?? '') . ' ' . ($p['name'] ?? '')) }}
                    {% if !empty($p['place_id']) %}
                    <em>(place {{ htmlspecialchars($p['place_id']) }})</em>
                    {% endif %}
                    {% if !empty($p['tarif_access_code']) %}
                    <em>(code {{ htmlspecialchars($p['tarif_access_code']) }})</em>
                    {% endif %}
                    {% if $i < ($count - 1) %}<br>{% endif %}
                    {% endforeach %}
                </div>
            </div>
            <div class="ms-auto text-end">
                <strong>{{ number_format($total / 100, 2, ',', ' ') }} €</strong>
                <div class="text-muted small">
                    {% if $seatCount > 0 %}
                    {{ $packs }} × {{ number_format($price / 100, 2, ',', ' ') }} € ({{ $seatCount }} place{{ $seatCount > 1 ? 's' : '' }})
                    {% else %}
                    {{ $count }} × {{ number_format($price / 100, 2, ',', ' ') }} €
                    {% endif %}
                </div>
            </div>
        </li>
        {% endforeach %}
    </ul>

    {% if (!empty($complements)) %}
    <h5>Compléments</h5>
    <ul class="list-group mb-3">
        {% foreach $complements as $tarif_id => $group %}
        {% php %}
        $qty = (int)($group['qty'] ?? 0);
        $price = (int)($group['price'] ?? 0);
        $total = $qty * $price;
        $codesLine = '';
        if (!empty($group['codes'])) {
        $codesLine = '(code ' . implode(', ', $group['codes']) . ')';
        }

        // Ajoute au total global
        $grandTotal += $total;
        {% endphp %}
        <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="me-3">
                <strong>{{ htmlspecialchars($group['tarif_name'] ?? '') }}</strong>
                {% if !empty($group['description']) %}
                <small class="text-muted">— {{ htmlspecialchars($group['description']) }}</small>
                {% endif %}
                <div class="mt-1">
                    Qté&nbsp;: {{ $qty }}
                </div>
                {% if !empty($codesLine) %}
                <div class="text-muted small">{{ htmlspecialchars($codesLine) }}</div>
                {% endif %}
            </div>
            <div class="ms-auto text-end">
                <strong>{{ number_format($total / 100, 2, ',', ' ') }} €</strong>
                <div class="text-muted small">
                    {{ $qty }} × {{ number_format($price / 100, 2, ',', ' ') }} €
                </div>
            </div>
        </li>
        {% endforeach %}
    </ul>
    {% endif %}

    <ul class="list-group mb-4">
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><strong>Total à payer</strong></span>
            <strong>{{ number_format($grandTotal / 100, 2, ',', ' ') }} €</strong>
        </li>
    </ul>

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
