<!--
Ce template représente une ligne pour un événement à venir (mobile).
Variables attendues :
- $event : L'objet Event à afficher.
-->
<div class="list-group-item d-flex justify-content-between align-items-center">
    <div>
        <h6 class="mb-0">{{ $event->getName() }}</h6>
        <small class="text-muted">{{ $event->getPiscine() ? $event->getPiscine()->getLabel() : 'Lieu non défini' }}</small>
    </div>
    <div>
        <button class="btn btn-sm btn-secondary edit-event-btn"
                data-event-id="{{ $event->getId() }}" data-event-json="{% php %}
                $eventData = [
                'id' => $event->getId(),
                'name' => $event->getName(),
                'place' => $event->getPlace(),
                'limitation_per_swimmer' => $event->getLimitationPerSwimmer(),
                'tarifs' => array_map(fn($t) => $t->getId(), $event->getTarifs()),
                'sessions' => array_map(fn($s) => ['id' => $s->getId(), 'session_name' => $s->getSessionName(), 'event_start_at' => $s->getEventStartAt()->format('Y-m-d\TH:i'), 'opening_doors_at' => $s->getOpeningDoorsAt()->format('Y-m-d\TH:i')], $event->getSessions() ?? []),
                'inscription_dates' => array_map(fn($d) => ['id' => $d->getId(), 'name' => $d->getName(), 'start_registration_at' => $d->getStartRegistrationAt()->format('Y-m-d\TH:i'), 'close_registration_at' => $d->getCloseRegistrationAt()->format('Y-m-d\TH:i'), 'access_code' => $d->getAccessCode()], $event->getInscriptionDates() ?? [])
                ];
                echo htmlspecialchars(json_encode($eventData), ENT_QUOTES, 'UTF-8');
                {% endphp %}"
                title="Modifier"><i class="bi bi-pencil-square"></i></button>
        <form action="/gestion/events/delete" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');" class="d-inline">
            <input type="hidden" name="event_id" value="{{ $event->getId() }}">
            <button type="submit" class="btn btn-sm btn-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>