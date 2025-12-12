<div class="container-fluid">
    <h1 class="mb-4">Réservations</h1>

    {% if empty($events) %}
    <div class="alert alert-info">
        Aucun événement à venir pour le moment.
    </div>
    {% else %}

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
        // Ne référencer que les éléments réellement présents
        $describedBy = "form_error_message_{$event->getId()}";
        if (!$periodeOuverte || $codeNecessaire) {
        $describedBy = "event_note_{$event->getId()} " . $describedBy;
        }
        {% endphp %}

        <div class="col-md-6 mb-4">
            <div class="card event-card" data-event-id="{{ $event->getId() }}">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title">{{ $event->getName() }}</h2>
                </div>
                <div class="card-body">
                    <p><strong>Lieu :</strong> {{ $piscineLibelle }}</p>

                    {% if $event->getLimitationPerSwimmer() !== null %}
                    <p>
                        <label for="swimmer_group_{{ $event->getId() }}"><strong>Je choisis la nageuse que je viens surtout voir (mais aussi les autres ^^) :</strong></label>
                        <select id="swimmer_group_{{ $event->getId() }}" data-event-id="{{ $event->getId() }}" class="form-select d-inline w-auto ms-2">
                            <option value="">Sélectionner un groupe</option>
                            {% foreach ($groupes ?? []) as $groupe %}
                            <option value="{{ $groupe->getId() }}">
                                {{ $groupe->getName() }}
                            </option>
                            {% endforeach %}
                        </select>
                    </p>
                    <p id="swimmer_container_{{ $event->getId() }}" style="display:none;">
                        <label for="swimmer_{{ $event->getId() }}"><strong>Nageuse :</strong></label>
                        <select id="swimmer_{{ $event->getId() }}" class="form-select d-inline w-auto ms-2">
                            <option value="">Sélectionner une nageuse</option>
                        </select>
                    </p>
                    {% endif %}

                    {% if $nbSessions > 0 %}
                    <fieldset class="mt-2" id="sessions_fieldset_{{ $event->getId() }}">
                        <legend class="form-label">
                            <strong>{{ $nbSessions > 1 ? 'Séances' : 'Séance unique' }}</strong>
                        </legend>

                        {% if $nbSessions === 1 %}
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="radio"
                                name="session_{{ $event->getId() }}"
                                id="session_{{ $event->getId() }}_{{ $sessions[0]->getId() }}"
                                value="{{ $sessions[0]->getId() }}"
                                {% if isset($nbSpectatorsPerSession[$sessions[0]->getId()]['is_full']) and $nbSpectatorsPerSession[$sessions[0]->getId()]['is_full'] === true %}
                                disabled
                                {% else %}
                                checked
                                {% endif %}
                            >
                            <label class="form-check-label" for="session_{{ $event->getId() }}_{{ $sessions[0]->getId() }}">
                                {{ $sessions[0]->getEventStartAt()->format('d/m/Y H:i') }}
                                {% if isset($nbSpectatorsPerSession[$sessions[0]->getId()]['is_full']) and $nbSpectatorsPerSession[$sessions[0]->getId()]['is_full'] === true %}
                                 - Complet
                                {% endif %}
                            </label>
                        </div>
                        {% else %}
                        {% foreach $sessions as $session %}
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="radio"
                                name="session_{{ $event->getId() }}"
                                id="session_{{ $event->getId() }}_{{ $session->getId() }}"
                                value="{{ $session->getId() }}"
                                    {% if isset($nbSpectatorsPerSession[$session->getId()]['is_full']) and $nbSpectatorsPerSession[$session->getId()]['is_full'] === true %}
                                disabled
                                    {% endif %}
                            >
                            <label class="form-check-label" for="session_{{ $event->getId() }}_{{ $session->getId() }}">
                                {{ $session->getSessionName() ?? '' }} {{ $session->getSessionName() ? ' : ' : '' }}{{ $session->getEventStartAt()->format('d/m/Y H:i') }}
                                {% if isset($nbSpectatorsPerSession[$session->getId()]['is_full']) and $nbSpectatorsPerSession[$session->getId()]['is_full'] === true %}
                                - Complet
                                {% endif %}
                            </label>
                        </div>
                        {% endforeach %}
                        {% endif %}
                    </fieldset>
                    {% else %}
                    <p><strong>Séance :</strong> Non défini</p>
                    {% endif %}

                    {% if !$periodeOuverte || $codeNecessaire %}
                    <div
                            class="alert alert-secondary mt-3"
                            id="event_note_{{ $event->getId() }}"
                            role="status"
                            aria-live="polite"
                    >
                        {% if $periodeOuverte && $codeNecessaire %}
                        <div class="mb-2">
                            <label for="access_code_input_{{ $event->getId() }}"><strong>Code d'accès requis :</strong></label>
                            <input
                                type="text"
                                id="access_code_input_{{ $event->getId() }}"
                                class="form-control d-inline w-auto ms-2"
                                aria-describedby="access_code_status_{{ $event->getId() }}"
                                aria-invalid="false"
                                autocomplete="one-time-code"
                            />
                            <button
                                type="button"
                                class="btn btn-primary ms-2"
                                id="validate_code_btn_{{ $event->getId() }}"
                                aria-controls="access_code_status_{{ $event->getId() }} btn_reserver_{{ $event->getId() }}"
                                aria-describedby="event_note_{{ $event->getId() }}"
                            >
                                Valider le code
                            </button>
                            <br>
                            <span id="access_code_status_{{ $event->getId() }}" class="ms-2" aria-live="polite"></span>
                        </div>
                        {% else %}
                        {% if (isset($periodesCloses[$event->getId()])) %}
                        Les inscriptions sont closes depuis le {{ $periodesCloses[$event->getId()]->getCloseRegistrationAt()->format('d/m/Y H:i') }}.
                        {% else %}
                        Les inscriptions ne sont pas ouvertes pour cet événement.
                        {% endif %}
                        {% endif %}

                        {% if $nextPublic %}
                        <br>Prochaine ouverture publique :
                        <strong>{{ $nextPublic->getStartRegistrationAt()->format('d/m/Y H:i') }}</strong>
                        {% endif %}
                    </div>
                    {% endif %}

                    <button
                        type="button"
                        class="btn btn-success mt-3"
                        id="btn_reserver_{{ $event->getId() }}"
                        {{ $buttonDisabled }}
                        aria-disabled="{{ $buttonDisabled ? 'true' : 'false' }}"
                        aria-describedby="{{ $describedBy }}"
                    >
                        Réserver
                    </button>

                    <div
                        id="form_error_message_{{ $event->getId() }}"
                        class="text-danger mt-2"
                        role="alert"
                        aria-live="assertive"
                    ></div>
                </div>
            </div>
        </div>
        {% endforeach %}
    </div>
    {% endif %}
</div>

<!-- On passe les données des nageuses via un data-attribute -->
<div id="swimmer-data" data-swimmers="{{ htmlspecialchars_decode(json_encode($swimmerPerGroup ?? [])) }}" hidden></div>

<script type="module" src="/assets/js/reservations/etape1.js"></script>
