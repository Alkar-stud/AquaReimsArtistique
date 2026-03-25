{% php %}
$paymentStatus = $_GET['status'] ?? null;
$checkoutIntentId = $_GET['checkoutIntentId'] ?? null;
{% endphp %}

<div class="container-fluid"
     id="reservation-data-container"
     data-reservation-id="{{ $reservation->getId() }}"
     data-token="{{ $reservation->getToken() }}"
     data-base-due-cents="{{ (int)($amountDue ?? ($reservation->getTotalAmount() - $reservation->getTotalAmountPaid())) }}"
>
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

    <h2 class="mb-4">Récapitulatif de votre réservation</h2>

    <!-- FAQ Section -->
    <div class="accordion mb-4" id="faqAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="faqHeading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse" aria-expanded="false" aria-controls="faqCollapse">
                    <i class="bi bi-question-circle me-2"></i> <strong>Aide : Que puis-je faire sur cette page ?</strong>
                </button>
            </h2>
            <div id="faqCollapse" class="accordion-collapse collapse" aria-labelledby="faqHeading" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    <h5>Questions fréquentes</h5>

                    <div class="mb-3">
                        <strong>Comment ai-je accédé à cette page ?</strong>
                        <p class="mb-1">Vous avez reçu un lien personnalisé dans votre email de confirmation de commande. Ce lien vous permet de consulter et modifier votre réservation.</p>
                    </div>

                    <div class="mb-3">
                        <strong>📝 Puis-je modifier les informations des participants ?</strong>
                        <p class="mb-1">Oui ! Vous pouvez modifier à tout moment :</p>
                        <ul class="mb-1">
                            <li>Les coordonnées du réservant (nom, prénom, email, téléphone)</li>
                            <li>Les noms et prénoms de tous les participants</li>
                        </ul>
                        <p class="mb-1 text-muted"><small>Les modifications sont automatiquement enregistrées après quelques secondes de saisie.</small></p>
                    </div>

                    <div class="mb-3">
                        <strong>🎫 Puis-je ajouter des compléments à ma réservation ?</strong>
                        <p class="mb-1">Oui ! Si des compléments sont disponibles pour votre événement, vous les trouverez dans la section "Ajouter des compléments". Cliquez simplement sur le bouton "Ajouter" pour les inclure dans votre commande.</p>
                    </div>

                    <div class="mb-3">
                        <strong>➕ Puis-je augmenter la quantité d'un complément déjà commandé ?</strong>
                        <p class="mb-1">Oui ! Utilisez les boutons <kbd>+</kbd> et <kbd>-</kbd> dans la section "Compléments" pour ajuster les quantités de vos articles selon vos besoins (dans la limite des stocks disponibles).</p>
                    </div>

                    <div class="mb-3">
                        <strong>🎟️ J'ai un code promotionnel, comment l'utiliser ?</strong>
                        <p class="mb-1">Dans la section "Ajouter des compléments", vous trouverez un champ "Vous avez un code ?". Saisissez votre code et cliquez sur "Valider le code" pour bénéficier de l'offre associée.</p>
                    </div>

                    <div class="mb-3">
                        <strong>💝 Comment faire un don à l'association ?</strong>
                        <p class="mb-1">Un curseur vous permet d'ajouter un don à votre commande. Vous pouvez :</p>
                        <ul class="mb-1">
                            <li>Ajuster le montant avec le curseur ou saisir directement une valeur</li>
                            <li>Utiliser le bouton "Arrondir" pour arrondir votre total à l'euro supérieur</li>
                        </ul>
                        <p class="mb-1 text-muted"><small>Le don est facultatif et soutient les activités de l'association.</small></p>
                    </div>

                    <div class="mb-3">
                        <strong>💳 Comment payer le solde restant ?</strong>
                        <p class="mb-1">Si un montant reste à payer, un bouton "Payer avec HelloAsso" apparaîtra automatiquement. Cliquez dessus pour effectuer votre paiement sécurisé en ligne.</p>
                    </div>

                    <div class="mb-3">
                        <strong>❌ Puis-je annuler ma réservation ?</strong>
                        <p class="mb-1">Oui, un bouton "Annuler la réservation" est disponible en bas de page. L'annulation est possible jusqu'à la date limite indiquée en haut de la page.</p>
                    </div>

                    <div class="mb-3">
                        <strong>⏰ Jusqu'à quand puis-je modifier ma réservation ?</strong>
                        <p class="mb-1">La date limite de modification est indiquée en haut de page sous le numéro d'enregistrement. Passé ce délai, les modifications ne seront plus possibles.</p>
                    </div>

                    <div class="mb-3">
                        <strong>🔒 Mes données sont-elles sécurisées ?</strong>
                        <p class="mb-1">Oui, votre lien est personnel et sécurisé. Toutes les modifications sont enregistrées de manière sécurisée et les paiements sont gérés par HelloAsso, une plateforme de paiement certifiée.</p>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Besoin d'aide ?</strong> Si vous rencontrez un problème, contactez-nous en répondant à l'email de confirmation que vous avez reçu.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <fieldset {{! !$canBeModified ? 'disabled' : '' !}}>
        <legend class="fs-5">Numéro d'enregistrement : <b>ARA-{{ str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) }}</b></legend>
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
                                           id="contact_email"
                                           class="form-control editable-contact"
                                           data-field="email"
                                           value="{{ isset($reservation) && method_exists($reservation,'getEmail') ? $reservation->getEmail() : '' }}"
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
        <ul class="list-group mb-3" id="participants-container">
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
                        <em>(place {{ $p['place_number'] }})</em>
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
        <div id="complements-section">
            <h5>Compléments</h5>
            {% if !empty($reservationView['complements']) %}
            <div id="complements-container" class="d-flex flex-wrap gap-2 mb-3">
                {% foreach $reservationView['complements'] as $tarif_id => $complementGroup %}
                <div class="card">
                    <div class="card-body d-flex align-items-start gap-2" data-complement-row-id="{{ $complementGroup['id'] }}">
                        <div class="flex-grow-1">
                            <div class="tarif-header">
                                <span class="tarif-name">{{ $complementGroup['tarif']->getName() ?? '' }}</span>
                                <span class="tarif-price">{{ number_format((($complementGroup['price'] ?? null) ?? ($complementGroup['tarif']->getPrice() ?? 0)) / 100, 2, ',', ' ') }} €</span>
                            </div>

                            {% if !empty($complementGroup['tarif']->getDescription()) %}
                            <div class="text small text-muted">{{ $complementGroup['tarif']->getDescription() }}</div>
                            {% endif %}

                            <div class="mt-1">Qté : <span class="fw-bold">{{ $complementGroup['qty'] }}</span></div>

                            {% if isset($complementGroup['codes']) && !empty($complementGroup['codes']) %}
                            <div class="text-muted small">(code {{ implode(', ', $complementGroup['codes']) }})</div>
                            {% endif %}
                        </div>

                        <div class="text-end">
                            <div class="input-group input-group-sm mb-2" style="max-width: 120px;">
                                <button class="btn btn-minus btn-sm complement-qty-btn" type="button" data-action="minus" data-complement-id="{{ $complementGroup['id'] }}" aria-label="Diminuer la quantité pour {{ $complementGroup['tarif']->getName() }}">-</button>
                                <label for="qty-complement-{{ $complementGroup['id'] }}" class="visually-hidden">Quantité pour {{ $complementGroup['tarif']->getName() }}</label>
                                <input type="text" class="form-control text-center" id="qty-complement-{{ $complementGroup['id'] }}" value="{{ $complementGroup['qty'] }}" readonly aria-readonly="true" inputmode="numeric" aria-live="polite">

                                {% if (is_null($complementGroup['tarif']->getMaxTickets()) || $complementGroup['qty'] < $complementGroup['tarif']->getMaxTickets()) %}
                                <button class="btn btn-secondary btn-sm complement-qty-btn" type="button" data-action="plus" data-complement-id="{{ $complementGroup['id'] }}" aria-label="Augmenter la quantité pour {{ $complementGroup['tarif']->getName() }}">+</button>
                                {% endif %}
                            </div>

                            <strong>{{ number_format(($complementGroup['total'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
                            <div class="text-muted small">{{ $complementGroup['qty'] ?? 0 }} × {{ number_format((($complementGroup['price'] ?? null) ?? ($complementGroup['tarif']->getPrice() ?? 0)) / 100, 2, ',', ' ') }} €</div>
                        </div>
                    </div>
                </div>
                {% endforeach %}
                <div class="w-100">
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-light mt-1">
                        <span><strong>Sous-total compléments</strong></span>
                        <strong>{{ number_format(($reservationView['totals']['complements_subtotal'] ?? 0) / 100, 2, ',', ' ') }} €</strong>
                    </div>
                </div>
            </div>
            {% endif %}

            <!-- Ajouter des articles si disponibles -->
            {% if isset($availableComplements) && !empty($availableComplements) %}
            <h5 class="mt-4">Ajouter des compléments</h5>
            <div id="available-complements-container" class="d-flex flex-wrap gap-2 mb-3">
                {% foreach $availableComplements as $tarif %}
                {% if !$tarif->getAccessCode() %}
                <div class="card">
                    <div class="card-body d-flex align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div class="tarif-header">
                                <span class="tarif-name">{{ $tarif->getName() ?? '' }}</span>
                                <span class="tarif-price">{{ number_format(($tarif->getPrice() ?? 0) / 100, 2, ',', ' ') }} €</span>
                            </div>
                            {% if !empty($tarif->getDescription()) %}
                            <div class="text small text-muted">{{ $tarif->getDescription() }}</div>
                            {% endif %}
                        </div>

                        <div class="text-end">
                            <button class="btn btn-secondary btn-sm add-complement-btn" type="button" data-tarif-id="{{ $tarif->getId() }}">
                                <i class="bi bi-plus-circle"></i>&nbsp;Ajouter
                            </button>
                        </div>
                    </div>
                </div>
                {% endif %}
                {% endforeach %}

                <div class="w-100 mt-2">
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
            </div>
        </div>
        {% endif %}

        <!-- Totaux + Don -->
        <div class="card mt-4" id="totals-card">
            <div class="card-body text-end">

                <!-- Toujours visible: Don -->
                <div class="mb-3">
                    <label for="donation-slider" class="form-label fs-6 fw-normal">
                        Faire un don à l'association :
                    </label>
                    <div class="d-inline-block align-middle">
                        <label for="donation-amount-input" class="visually-hidden">Montant du don</label>
                        <input type="number" id="donation-amount-input" min="0" step="0.1" value="0" class="form-control form-control-sm" style="width: 90px; display: inline-block;"> €
                        <button type="button" id="round-up-donation-btn" class="btn btn-outline-secondary btn-sm d-none ms-2" title="Arrondir à l'euro supérieur" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;">
                            Arrondir
                        </button>
                    </div>

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

                <!-- Total avec don (affiché si don > 0, ou si baseDue <=0 et don crée un dû) -->
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
                    <button type="button" id="pay-balance-btn" class="btn p-0 border-0" title="Payer le solde avec HelloAsso">
                        <img src="/assets/images/payer-avec-helloasso.svg" alt="Payer le solde avec HelloAsso" style="height: 50px;">
                    </button>
                    {% if (isset($_ENV['APP_ENV']) && in_array($_ENV['APP_ENV'], ['local', 'preprod'])) %}
                    <div class="alert alert-info mt-4">
                        <p class="mb-0"><b>Environnement de test :</b> voici la carte bancaire à utiliser : <b>4242424242424242</b>. Validité <b>date supérieure au mois en cours</b>, code : <b>3 chiffres au choix</b>.</p>
                        <p class="mb-0">Il faut cliquer sur le lien, la redirection automatique est désactivée en environnement de test.</p>
                    </div>
                    {% endif %}
                </div>
            </div>
        </div>

        <br>
        <div class="d-flex justify-content-end">
            <button class="btn btn-warning cancel-button"{{ $canBeModified ? '' : ' disabled' }}>Annuler la réservation</button>
        </div>
        <br>
    </fieldset>
    {% endif %}
</div>

<script type="module" src="/assets/js/reservations/modifData.js"></script>