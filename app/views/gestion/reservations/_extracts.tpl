<h3>Exports</h3>

<div class="row mb-3">
    <div class="col-md-6">
        <label for="event-selector-extracts" class="form-label">Choisir une session :</label>
        <select id="event-selector-extracts" class="form-select">
            <option value="">-- Sélectionnez un événement --</option>
            {% if !empty($events) %}
            {% foreach $events as $event %}
            {% foreach $event->getSessions() as $session %}
            <option value="{{ $session->getId() }}" {% if $selectedSessionId == $session->getId() %}selected{% endif %}>
                {{ $event->getName() }} ({{ $session->getEventStartAt()->format('d/m/Y H:i') }})
            </option>
            {% endforeach %}
            {% endforeach %}
            {% endif %}
        </select>
    </div>
</div>

<!-- Ce conteneur sera rempli par JavaScript après sélection d'une session -->
<div id="export-options-container">
    {% if $selectedSessionId > 0 %}
    {% foreach $events as $event %}
    {% foreach $event->getSessions() as $session %}
    {% if $session->getId() == $selectedSessionId %}
    {% include '/gestion/reservations/_export_options.tpl' with {'tarifs': $event->getTarifs()} %}
    {% endif %}
    {% endforeach %}
    {% endforeach %}
    {% else %}
    <p class="text-muted">Veuillez sélectionner une session pour voir les options d'export.</p>
    {% endif %}
</div>