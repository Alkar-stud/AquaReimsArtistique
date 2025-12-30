<div class="container-fluid">
    <h2 class="mb-4">Informations personnelles</h2>

    <form id="reservationInfosForm" class="mb-4" autocomplete="on" aria-describedby="step2-hint">
        <p id="step2-hint" class="visually-hidden">Les champs marqués \* sont obligatoires.</p>

        <input type="hidden" id="event_id" name="event_id" value="{{ $reservation['reservation']->getEvent() }}">
        <input type="hidden" id="event_session_id" name="event_session_id" value="{{ $reservation['reservation']->getEventSession() }}">

        <div class="mb-3">
            <label for="name" class="form-label">Nom *</label>
            <input
                    type="text"
                    class="form-control"
                    id="name"
                    name="name"
                    required
                    autocomplete="family-name"
                    autocapitalize="words"
                    aria-invalid="false"
                    aria-describedby="step2-hint name_error"
                    value="{{ $reservation['reservation']->getName() ?? '' }}"
            >
            <div id="name_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
        </div>

        <div class="mb-3">
            <label for="firstname" class="form-label">Prénom *</label>
            <input
                    type="text"
                    class="form-control"
                    id="firstname"
                    name="firstname"
                    required
                    autocomplete="given-name"
                    autocapitalize="words"
                    aria-invalid="false"
                    aria-describedby="step2-hint firstname_error"
                    value="{{ $reservation['reservation']->getFirstName() ?? '' }}"
            >
            <div id="firstname_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Adresse mail *</label>
            <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    inputmode="email"
                    spellcheck="false"
                    aria-invalid="false"
                    aria-describedby="step2-hint email_error"
                    value="{{ $reservation['reservation']->getEmail() ?? '' }}"
            >
            <div id="email_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Téléphone</label>
            <input
                    type="tel"
                    class="form-control"
                    id="phone"
                    name="phone"
                    autocomplete="tel"
                    inputmode="tel"
                    aria-invalid="false"
                    aria-describedby="step2-hint phone_error"
                    value="{{ $reservation['reservation']->getPhone() ?? '' }}"
            >
            <div id="phone_error" class="invalid-feedback" role="alert" aria-live="polite"></div>
        </div>

        <!-- Consentement RGPD -->
        <div class="mb-3 form-check">
            <input
                    class="form-check-input"
                    type="checkbox"
                    id="rgpd_consent"
                    name="rgpd_consent"
                    value="1"
                    required
                    aria-describedby="rgpd_consent_help"
            >
            <label class="form-check-label" for="rgpd_consent">
                J’accepte que Aqua Reims Artistique utilise mes données personnelles uniquement dans le cadre des galas : envoi d’e‑mails liés à ma réservation et informations sur les prochains galas.
            </label>
            <div id="rgpd_consent_help" class="form-text">
                Vous pouvez retirer votre consentement et demander la modification/suppression de vos données en écrivant à {{ defined('EMAIL_CLUB') ? EMAIL_CLUB : 'contact@aquareimsartistique.fr' }}.
                Politique de confidentialité : <a href="https://aquareimsartistique.fr/politique-confidentialite/" target="_blank" rel="noopener noreferrer">https://aquareimsartistique.fr/politique-confidentialite/</a>.
            </div>
        </div>

        <div class="row">
            <!-- order-x pour position x en mobile et order-mg-x pour desktop -->
            <!-- afin que le bouton principal soit à droite en desktop -->
            <!-- et en 1er en mobile -->
            <div class="col-12 col-md-6 order-2 order-md-1 mb-2 mb-md-0">
                <a href="/reservation" class="btn btn-secondary w-100 w-md-auto">Retour</a>
            </div>
            <div class="col-12 col-md-6 order-1 order-md-2 d-flex justify-content-md-end mb-2 mb-md-0">
                <button type="submit" class="btn btn-primary w-100 w-md-auto">Valider et continuer</button>
            </div>
        </div>
    </form>
    <div id="reservationAlert" role="alert" aria-live="polite" tabindex="-1"></div>
</div>

<script>
    window.swimmerPerGroup = {{! json_encode($swimmerPerGroup ?? []) !}};
</script>

<script type="module" src="/assets/js/reservations/etape2.js" defer></script>


{% if ($_ENV['APP_DEBUG'] == "true") %}
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($reservation);
echo '</pre>';
{% endphp %}
{% endif %}