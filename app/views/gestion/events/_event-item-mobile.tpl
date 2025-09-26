<!--
Ce template représente une ligne pour un événement à venir (mobile).
Variables attendues :
- $event: L'objet Event à afficher.
-->
<div class="list-group-item d-flex justify-content-between align-items-center">
    <div>
        <h6 class="mb-0">{{ $event->getName() }}</h6>
        <small class="text-muted">{{ $event->getPiscine() ? $event->getPiscine()->getLabel() : 'Lieu non défini' }}</small>
    </div>
    <div>
        <button class="btn btn-sm btn-primary" data-event-id="{{ $event->getId() }}" title="Modifier"><i class="bi bi-pencil-square"></i></button>
        <button class="btn btn-sm btn-danger" data-event-id="{{ $event->getId() }}" title="Supprimer"><i class="bi bi-trash"></i></button>
    </div>
</div>