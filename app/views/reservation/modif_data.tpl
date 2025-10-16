{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

{% php %}
$paymentStatus = $_GET['status'] ?? null;
$checkoutIntentId = $_GET['checkoutIntentId'] ?? null;
{% endphp %}

{% if $paymentStatus == 'success' && $checkoutIntentId %}
<div class="container text-center" id="payment-check-container" data-checkout-id="{{ $checkoutIntentId }}">
    <h2 class="mb-4">Vérification de votre paiement...</h2>
    <div id="payment-check-spinner" class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Chargement...</span>
    </div>
    <p id="payment-check-message" class="mt-3">Nous vérifions la confirmation de votre paiement auprès de nos services. Veuillez patienter.</p>
    <div id="payment-check-error" class="alert alert-danger mt-3" style="display: none;"></div>
    <div id="payment-check-success" class="alert alert-success mt-3" style="display: none;">
        Paiement confirmé ! Vous allez être redirigé vers le récapitulatif mis à jour.
    </div>
</div>
{% else %}

<div class="container-fluid"
     id="reservation-data-container"
     data-reservation-id="{{ $reservation->getId() }}"
     data-token="{{ $reservation->getToken() }}"
     data-base-due-cents="{{ (int)($amountDue ?? ($reservation->getTotalAmount() - $reservation->getTotalAmountPaid())) }}"
>
    <h2 class="mb-4">Récapitulatif de votre réservation</h2>

    <fieldset {{! !$canBeModified ? 'disabled' : '' !}}>
        <legend class="fs-5">Numéro d'enregistrement : <b>{{ $reservation->getId() }}</b></legend>
        {% if ($reservation->getTokenExpireAt()) %}
        <p>Modification possible jusqu'au : <u>{{ $reservation->getTokenExpireAt()->format('d/m/Y \à H\hi') }}</u></p>
        {% endif %}

        <h4 class="mb-3 mt-4">Détails de la réservation</h4>
        <ul class="list-group mb-3">
            <li class="list-group-item">
                <strong>Événement :</strong>
                {{ $reservation->getEventObject()->getName() ?? '' }}
            </li>
            <li class="list-group-item">
                <strong>Séance :</strong>
                {{ $reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y \à H\hi') }}
                à la piscine <i>{{ $reservation->getEventObject()->getPiscine()->getLabel() ?? '' }}</i>
                <small class="text-muted">({{ $reservation->getEventObject()->getPiscine()->getAddress() ?? '' }})</small>
            </li>
            <li class="list-group-item">
                <div class="row align-items-center">
                    <div class="col-lg-2"><strong>Réservant :</strong></div>
                    <div class="col-lg-10" id="contact-fields-container">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Nom</span>
                                    <input type="text"
                                           class="form-control editable-contact"
                                           data-field="name"
                                           value="{{ method_exists($reservation,'getNom') ? $reservation->getNom() : (method_exists($reservation,'getName') ? $reservation->getName() : '') }}"
                                           aria-label="name">
                                    <span class="input-group-text feedback-span"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Prénom</span>
                                    <input type="text"
                                           class="form-control editable-contact"
                                           data-field="firstname"
                                           value="{{ method_exists($reservation,'getPrenom') ? $reservation->getPrenom() : (method_exists($reservation,'getFirstName') ? $reservation->getFirstName() : '') }}"
                                           aria-label="Prénom">
                                    <span class="input-group-text feedback-span"></span>
                                </div>
                            </div>
                            <div class="col-md-12 col-lg-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Email</span>
                                    <input type="email"
                                           class="form-control editable-contact"
                                           data-field="email"
                                           value="{{ method_exists($reservation,'getEmail') ? $reservation->getEmail() : '' }}"
                                           aria-label="Email">
                                    <span class="input-group-text feedback-span"></span>
                                </div>
                            </div>
                            <div class="col-md-12 col-lg-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Téléphone</span>
                                    <input type="tel"
                                           class="form-control editable-contact"
                                           data-field="phone"
                                           value="{{ method_exists($reservation,'getPhone') ? $reservation->getPhone() : '' }}"
                                           aria-label="Téléphone"
                                           placeholder="Facultatif">
                                    <span class="input-group-text feedback-span"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <!-- Détail des participants -->
        <h5>Détail des participants :</h5>
        <ul class="list-group mb-3">
            {% foreach $reservationView['details'] as $tarif_id => $group %}
            <li class="list-group-item d-flex justify-content-between align-items-start">
                <div class="me-3">
                    <strong>{{ $group['tarif']->getName() ?? '' }}</strong>
                    {% if !empty($group['tarif']->getDescription()) %}
                    <small class="text-muted">— {{ $group['tarif']->getDescription() ?? '' }}</small>
                    {% endif %}

                    <div class="mt-1">
                        {% foreach $group['participants'] as $i => $p %}
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Nom</span>
                                    <input type="text" class="form-control editable-detail" data-detail-id="{{ $p['id'] }}" data-field="name" value="{{ $p['name'] }}" aria-label="Nom du participant">
                                    <span class="input-group-text feedback-span"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Prénom</span>
                                    <input type="text" class="form-control editable-detail" data-detail-id="{{ $p['id'] }}" data-field="firstname" value="{{ $p['firstname'] }}" aria-label="Prénom du participant">
                                    <span class="input-group-text feedback-span"></span>
                                </div>
                            </div>
                        </div>
                        {% if !empty($p['place_number']) %}
                        <em>(place {{ $p['place_number'] }}</em>
                        {% endif %}
                        {% if $i < ($group['count'] - 1) %}<br>{% endif %}
                        {% endforeach %}
                    </div>
                </div>

                <div class="ms-auto text-end">
                    <strong>{{ number_format(($group['total'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
                    <div class="text-muted small">
                        {% if $group['seatCount'] > 0 %}
                        {{ $group['packs'] }} × {{ number_format(($group['price'] ?? 0) / 100, 2, ',', ' ') }} €
                        ({{ $group['seatCount'] }} place{{ $group['seatCount'] > 1 ? 's' : '' }})
                        {% else %}
                        {{ $group['count'] }} × {{ number_format(($group['price'] ?? 0) / 100, 2, ',', ' ') }} €
                        {% endif %}
                    </div>
                </div>
            </li>
            {% endforeach %}
            <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                <span><strong>Sous-total participants</strong></span>
                <strong>{{ number_format(($reservationView['totals']['details_subtotal'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
            </li>
        </ul>

        <!-- Compléments -->
        <h5>Compléments</h5>
        {% if !empty($reservationView['complements']) %}
        <ul class="list-group mb-3">
            {% foreach $reservationView['complements'] as $tarif_id => $complementGroup %}
            <li class="list-group-item d-flex justify-content-between align-items-start">
                <div class="me-3">
                    <strong>{{ $complementGroup['tarif']->getName() ?? '' }}</strong>
                    {% if !empty($complementGroup['tarif']->getDescription()) %}
                    <small class="text-muted">— {{ $complementGroup['tarif']->getDescription() }}</small>
                    {% endif %}
                    <div class="mt-1">Qté : {{ $complementGroup['qty'] }}</div>
                    {% if isset($complementGroup['codes']) && !empty($complementGroup['codes']) %}
                    <div class="text-muted small">(code {{ implode(', ', $complementGroup['codes']) }})</div>
                    {% endif %}
                </div>

                <div class="ms-auto text-end">
                    <div class="input-group input-group-sm" style="max-width: 100px;">
                        <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="minus" data-complement-id="{{ $complementGroup['id'] }}">-</button>
                        <input type="text" class="form-control text-center" id="qty-complement-{{ $complementGroup['id'] }}" value="{{ $complementGroup['qty'] }}" readonly>

                        {% if (is_null($complementGroup['tarif']->getMaxTickets()) || $complementGroup['qty'] < $complementGroup['tarif']->getMaxTickets()) %}
                        <button class="btn btn-outline-secondary btn-sm complement-qty-btn" type="button" data-action="plus" data-complement-id="{{ $complementGroup['id'] }}">+</button>
                        {% endif %}
                    </div>

                    <strong>{{ number_format(($complementGroup['total'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
                    <div class="text-muted small">
                        {{ $complementGroup['qty'] ?? 0 }}
                        × {{ number_format((($complementGroup['price'] ?? null) ?? ($complementGroup['tarif']->getPrice() ?? 0)) / 100, 2, ',', ' ') }} €
                    </div>
                </div>
            </li>
            {% endforeach %}
            <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                <span><strong>Sous-total compléments</strong></span>
                <strong>{{ number_format(($reservationView['totals']['complements_subtotal'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
            </li>
        </ul>
        {% endif %}

        <!-- Ajouter des articles si disponibles -->
        {% if isset($availableComplements) && !empty($availableComplements) %}
        <h5 class="mt-4">Ajouter des compléments</h5>
        <div class="list-group mb-3">
            {% foreach $availableComplements as $tarif %}
            {% if !$tarif->getAccessCode() %}
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                     <span class="fw-bold">
                         {{ $tarif->getName() ?? '' }}
                         <small class="text-muted">({{ number_format(($tarif->getPrice() ?? 0) / 100, 2, ',', ' ') }} €)</small>
                     </span>
                    <button class="btn btn-success btn-sm add-complement-btn" type="button" data-tarif-id="{{ $tarif->getId() }}">
                        <i class="bi bi-plus-circle"></i> Ajouter
                    </button>
                </div>
            </div>
            {% endif %}
            {% endforeach %}
            <div class="mb-3">
                <label for="specialCode" class="form-label">Vous avez un code ?</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="specialCode" placeholder="Saisissez votre code" style="max-width: 250px;">
                    <button type="button" class="btn btn-outline-primary" id="validateCodeBtn">Valider le code</button>
                </div>
                <div id="specialCodeFeedback" class="form-text text-danger"></div>
            </div>
            <div id="specialTarifContainer"></div>
        </div>
        {% endif %}

        <!-- Totaux + Don -->
        <div class="card mt-4" id="totals-card">
            <div class="card-body text-end">

                <!-- Toujours visible: Don -->
                <div class="mb-3">
                    <label for="donation-slider" class="form-label fs-6 fw-normal">
                        Faire un don à l'association : <strong id="donation-amount-display">0,00 €</strong>
                        <button type="button" id="round-up-donation-btn" class="btn btn-outline-secondary btn-sm d-none ms-2" title="Arrondir à l'euro supérieur" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;">
                            Arrondir
                        </button>
                    </label>
                    <div class="donation-slider-container ms-auto">
                        <input
                                type="range"
                                class="form-range"
                                min="0"
                                max="{{ $maxDonationEuros }}"
                                step="0.1"
                                value="0"
                                id="donation-slider"
                        >
                    </div>
                    <small class="form-text text-muted"></small>
                </div>

                {% if ($amountDue > 0) %}
                <div class="fs-5">
                    Nouveau total :
                    <strong id="new-total-amount">
                        {{ number_format($reservation->getTotalAmount() / 100, 2, ',', ' ') }} €
                    </strong>
                </div>
                {% endif %}

                <div class="text-success">
                    Déjà payé :
                    <strong id="total-paid-amount">
                        {{ number_format($reservation->getTotalAmountPaid() / 100, 2, ',', ' ') }} €
                    </strong>
                </div>

                <hr>

                <!-- Total avec don (affiché si don > 0, ou si baseDue<=0 et don crée un dû) -->
                <div class="mt-2 d-none" id="total-with-donation">
                    Total à régler (avec don) :
                    <span class="text-danger" id="total-to-pay-with-donation">{{ number_format(max(0, $amountDue) / 100, 2, ',', ' ') }} €</span>
                </div>

                <!-- Bloc montant dû (dynamique) -->
                <div id="amount-due-container" class="fs-4 fw-bold">
                    <!-- Message crédit / à jour (affiché si baseDue <= 0 et don ne dépasse pas le crédit) -->
                    <div id="credit-message" class="<?= ($amountDue <= 0) ? '' : 'd-none' ?>">
                        <?php if ($amountDue < 0): ?>
                        Crédit disponible :
                        <span class="text-info" id="credit-amount">
                            {{ number_format(abs($amountDue) / 100, 2, ',', ' ') }} €
                        </span>
                        <?php else: ?>
                        <div class="text-success">Vous êtes à jour dans vos paiements.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Ligne "Reste à payer" (masquée si baseDue <= 0) -->
                    <div id="due-line" class="<?= ($amountDue > 0) ? '' : 'd-none' ?>">
                        Reste à payer :
                        <span class="text-danger" id="amount-due">
                        {{ number_format(max(0, $amountDue) / 100, 2, ',', ' ') }} €
                    </span>
                    </div>
                </div>

                <!-- Section paiement: toujours rendue, masquée par défaut -->
                <div id="pay-balance-section" class="mt-3 d-none">
                    <a id="pay-balance-btn" href="#" title="Payer le solde avec HelloAsso">
                        <img src="/assets/images/payer-avec-helloasso.svg" alt="Payer le solde avec HelloAsso" style="height: 50px;">
                    </a>
                </div>

                {% if (isset($_ENV['APP_ENV']) and in_array($_ENV['APP_ENV'], ['local', 'dev']) and $amountDue > 0) %}
                <div class="alert alert-info mt-4">
                    <p class="mb-0"><b>Environnement de test :</b> voici la carte bancaire à utiliser : <b>4242424242424242</b>. Validité <b>date supérieure au mois en cours</b>, code : <b>3 chiffres au choix</b>.</p>
                    <p class="mb-0">Il faut cliquer sur le lien, la redirection automatique est désactivée en environnement de test.</p>
                </div>
                {% endif %}
            </div>
        </div>

        <br>
        <div class="d-flex justify-content-end">
            <button class="btn btn-warning cancel-button"{{ $canBeModified ? '' : ' disabled' }}>Annuler la réservation</button>
        </div>
        <br>
    </fieldset>
</div>
{% endif %}

<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_modif_data.js" defer></script>