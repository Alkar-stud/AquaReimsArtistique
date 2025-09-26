<!--
Ce template représente une ligne pour un événement à venir.
Il est utilisé à la fois dans la table desktop et dans la liste mobile.
Variables attendues :
- $event: L'objet Event à afficher.
-->
<tr>
    <td>{{ $event->getName() }}</td>
    <td>{{ $event->getPiscine() ? $event->getPiscine()->getLabel() : 'Lieu non défini' }}</td>
    <td class="text-end">
        <button class="btn btn-sm btn-primary" data-event-id="{{ $event->getId() }}" title="Modifier"><i class="bi bi-pencil-square"></i></button>
        <button class="btn btn-sm btn-danger" data-event-id="{{ $event->getId() }}" title="Supprimer"><i class="bi bi-trash"></i></button>
    </td>
</tr>