{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container"
     xmlns="http://www.w3.org/1999/html">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2 class="mb-4">Récapitulatif de votre réservation</h2>

    <fieldset {{ !$canBeModified ? 'disabled' : '' }}>
        <legend class="fs-5">Numéro d'enregistrement : <b>{{ $reservation->getId() }}</b></legend>
        {% if ($reservation->getTokenExpireAt()) %}
        <p>Modification possible jusqu'au : <u>{{ $reservation->getTokenExpireAt()->format('d/m/Y \à H\hi') }}</u></p>
        {% endif %}

        <h4 class="mb-3 mt-4">Détails de la réservation</h4>
            <ul class="list-group mb-3">
                <li class="list-group-item"><strong>Événement :</strong> {{ htmlspecialchars($reservation->getEventObject()->getName() ?? '') }}</li>
                <li class="list-group-item">
                    <strong>Séance :</strong>{{ htmlspecialchars($reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y \à H\hi')) }}
                    à la piscine <i>{{ htmlspecialchars($reservation->getEventObject()->getPiscine()->getLabel() ?? '') }}</i>
                    <small class="text-muted">({{ htmlspecialchars($reservation->getEventObject()->getPiscine()->getAddress()) }})</small>
                </li>
                <li class="list-group-item">
                    <div class="row align-items-center">
                        <div class="col-lg-2"><strong>Réservant :</strong></div>
                        <div class="col-lg-10" id="contact-fields-container">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Nom</span>
                                        <input type="text" class="form-control editable-contact" data-field="nom" value="{{ htmlspecialchars($reservation->getName() ?? '') }}" aria-label="Nom">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Prénom</span>
                                        <input type="text" class="form-control editable-contact" data-field="prenom" value="{{ htmlspecialchars($reservation->getFirstname() ?? '') }}" aria-label="Prénom">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-12 col-lg-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Email</span>
                                        <input type="email" class="form-control editable-contact" data-field="email" value="{{ htmlspecialchars($reservation->getEmail() ?? '') }}" aria-label="Email">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-12 col-lg-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Téléphone</span>
                                        <input type="tel" class="form-control editable-contact" data-field="phone" value="{{ htmlspecialchars($reservation->getPhone() ?? '') }}" aria-label="Téléphone" placeholder="Facultatif">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        <div class="list-group mb-3">
            <h5 class="mt-4">Places assises</h5>

        </div>

    </fieldset

</div>


<script src="/assets/js/reservation/reservation_modif_data.js" defer></script>