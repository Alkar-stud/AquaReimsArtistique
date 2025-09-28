{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h1 class="mb-4">Réservations</h1>

    {% if empty($events) %}
    <div class="alert alert-info">
        Aucun événement à venir pour le moment.
    </div>
    {% else %}
    {% if !empty($_GET['session_expiree']) %}
    <div class="alert alert-warning">
        Votre session a expiré. Merci de recommencer votre réservation.
    </div>
    {% endif %}

    <div class="row">
        {% foreach $events as $event %}
        {% php %}

        $sessions = $event->getSessions();
        $nbSessions = is_array($sessions) ? count($sessions) : 0;
 
        $periodeOuverte = $periodesOuvertes[$event->getId()] ?? null;
        $nextPublic = $nextPublicOuvertures[$event->getId()] ?? null;
        $codeNecessaire = $periodeOuverte && $periodeOuverte->getAccessCode() !== null;
        // On calcule l'état du bouton une seule fois ici.
        $buttonDisabled = (!$periodeOuverte || $codeNecessaire) ? 'disabled' : '';

        // Libellé de la piscine
        $piscineLibelle = $event->getPiscine() ? $event->getPiscine()->getLabel() : 'Non défini';
 
        {% endphp %}

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title">{{ $event->getName() }}</h5>
                </div>
                <div class="card-body">
                    <p><strong>Lieu :</strong> {{ $piscineLibelle }}</p>

                    {% if $event->getLimitationPerSwimmer() !== null %}
                    <p>
                        <strong>Je choisis la nageuse que je viens surtout voir (mais aussi les autres ^^) :</strong>
                        <select id="swimmer_group_{{ $event->getId() }}" class="form-select d-inline w-auto ms-2" onchange="updateSwimmer(this.value, {{ $event->getId() }})">
                            <option value="">Sélectionner un groupe</option>
                            {% foreach ($groupes ?? []) as $groupe %}
                            <option value="{{ $groupe->getId() }}">
                                {{ $groupe->getName() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </p>
                    <p id="swimmer_container_{{ $event->getId() }}" style="display:none;">
                        <strong>Nageuse :</strong>
                        <select id="swimmer_{{ $event->getId() }}" class="form-select d-inline w-auto ms-2">
                            <option value="">Sélectionner une nageuse</option>
                        </select>
                    </p>
                    {% endif %}

                    {% if $nbSessions > 0 %}
                    <p>
                        <strong>{{ $nbSessions > 1 ? 'Séances' : 'Séance unique' }} :</strong>
                        {% if $nbSessions === 1 %}
                        <input
                                type="radio"
                                name="session_{{ $event->getId() }}"
                                id="session_{{ $event->getId() }}_{{ $sessions[0]->getId() }}"
                                value="{{ $sessions[0]->getId() }}"
                                checked
                        >
                        {{ $sessions[0]->getEventStartAt()->format('d/m/Y H:i') }}
                        {% else %}
                        {% foreach $sessions as $session %}
                    <div class="form-check">
                        <input
                                class="form-check-input"
                                type="radio"
                                name="session_{{ $event->getId() }}"
                                id="session_{{ $event->getId() }}_{{ $session->getId() }}"
                                value="{{ $session->getId() }}"
                        >
                        <label class="form-check-label" for="session_{{ $event->getId() }}_{{ $session->getId() }}">
                            {{ $session->getSessionName() ?? '' }} {{ $session->getSessionName() ? ' : ' : '' }}{{ $session->getEventStartAt()->format('d/m/Y H:i') }}
                        </label>
                    </div>
                    {% endforeach %}
                    {% endif %}
                    </p>
                    {% else %}
                    <p><strong>Séance :</strong> Non défini</p>
                    {% endif %}

                    {% if !$periodeOuverte || $codeNecessaire %}
                    <div class="alert alert-secondary mt-3">
                        {% if $periodeOuverte && $codeNecessaire %}
                        <div class="mb-2">
                            <label for="access_code_input_{{ $event->getId() }}"><strong>Code d'accès requis :</strong></label>
                            <input type="text" id="access_code_input_{{ $event->getId() }}" class="form-control d-inline w-auto ms-2" />
                            <button class="btn btn-primary ms-2" onclick="validerCodeAcces({{ $event->getId() }})">Valider le code</button>
                            <span id="access_code_status_{{ $event->getId() }}" class="ms-2"></span>
                        </div>
                        {% else %}
                        Les inscriptions ne sont pas ouvertes pour cet événement.
                        {% endif %}

                        {% if $nextPublic %}
                        <br>Prochaine ouverture publique :
                        <strong>{{ $nextPublic->getStartRegistrationAt()->format('d/m/Y H:i') }}</strong>
                        {% endif %}
                    </div>
                    {% endif %}

                    <button
                            class="btn btn-success mt-3"
                            id="btn_reserver_{{ $event->getId() }}"
                            onclick="validerFormulaireReservation({{ $event->getId() }})"
                            {{ $buttonDisabled }}
                        >
                        Réserver
                    </button>

                    <div id="form_error_message_{{ $event->getId() }}" class="text-danger mt-2"></div>
                </div>
            </div>
        </div>
        {% endforeach %}
    </div>
    {% endif %}
</div>

<script>
    window.swimmerPerGroup = {{! json_encode($swimmerPerGroup ?? []) !}};
    window.csrf_token = {{! json_encode($csrf_token ?? '') !}};
</script>
<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_etape1.js" defer></script>
