<!--
Ce template représente une ligne pour un événement passé (lecture seule).
Variables attendues :
- $event: L'objet Event à afficher.
-->
<div class="list-group-item d-flex justify-content-between align-items-center">
    <div>
        <h6 class="mb-0">{{ $event->getName() }}</h6>
        <small class="text-muted">{{ $event->getPiscine() ? $event->getPiscine()->getLabel() : 'Lieu non défini' }}</small>
    </div>
    <div>
        <button class="btn btn-sm btn-info" data-event-id="{{ $event->getId() }}" title="Voir les détails"><i class="bi bi-eye"></i>&nbsp;Voir</button>
    </div>
</div>