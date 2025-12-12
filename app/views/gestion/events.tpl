<div class="container-fluid">
    <h2 class="mb-4">Gestion des événements</h2>

    <!-- ================================================================== -->
    <!-- ONGLET POUR LES LISTES -->
    <!-- ================================================================== -->
    <ul class="nav nav-tabs mt-5" id="events-list-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upcoming-events-tab" data-bs-toggle="tab" data-bs-target="#upcoming-events-pane" type="button" role="tab" aria-controls="upcoming-events-pane" aria-selected="true">
                Événements à venir
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="past-events-tab" data-bs-toggle="tab" data-bs-target="#past-events-pane" type="button" role="tab" aria-controls="past-events-pane" aria-selected="false">Événements passés</button>
        </li>
    </ul>
    <div class="tab-content" id="events-list-tabs-content">
        <div class="tab-pane fade show active" id="upcoming-events-pane" role="tabpanel" aria-labelledby="upcoming-events-tab">
            {% if $eventsUpcoming %}
                <!-- Liste des événements à venir (Mobile) -->
                <div class="list-group d-md-none mt-3">
                    {% foreach $eventsUpcoming as $event %}
                    {% include 'events/_event-item-mobile.tpl' with ['event' => $event] %}
                    {% endforeach %}
                </div>
            {% else %}
                <div class="alert alert-info mt-3">Aucun événement à venir pour le moment.</div>
            {% endif %}

            <!-- Table des événements à venir (Desktop) -->
            <div class="d-none d-md-block" id="upcoming-events-table-container">
                <table class="table align-middle mt-3">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Lieu</th>
                            <th style="width: 250px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Ligne d'ajout rapide pour desktop (toujours visible) -->
                        <tr class="table-light">
                            <td><input type="text" class="form-control" id="desktop_add_name" placeholder="Libellé du nouvel événement"></td>
                            <td>
                                <select class="form-select" id="desktop_add_place">
                                    <option value="">Sélectionner un lieu</option>
                                    {% foreach $allPiscines as $piscine %}
                                    <option value="{{ $piscine->getId() }}">{{ $piscine->getLabel() }}</option>
                                    {% endforeach %}
                                </select>
                            </td>
                            <td>
                                <button type="button" class="btn btn-success w-100" id="desktop-add-btn">
                                    <i class="bi bi-plus-circle"></i>&nbsp;Continuer l'ajout...
                                </button>
                            </td>
                        </tr>
                        <!-- Boucle pour les événements à venir (desktop) -->
                        {% foreach $eventsUpcoming as $event %}
                        {% include 'events/_event-item.tpl' with ['event' => $event] %}
                        {% endforeach %}
                    </tbody>
                </table>
            </div>

            <!-- Bouton d'ajout pour mobile -->
            <div class="d-md-none my-3">
                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#eventModal">
                    <i class="bi bi-plus-circle"></i>&nbsp;Ajouter un événement
                </button>
            </div>
        </div>
        <div class="tab-pane fade" id="past-events-pane" role="tabpanel" aria-labelledby="past-events-tab">
            {% if $eventsPast %}
            <div class="list-group mt-3">
                {% foreach $eventsPast as $event %}
                {% include 'events/_event-item-readonly.tpl' with ['event' => $event] %}
                {% endforeach %}
            </div>
            {% else %}
            <div class="alert alert-secondary mt-3">Aucun événement passé à afficher.</div>
            {% endif %}
        </div>
    </div>


    <!-- ================================================================== -->
    <!-- MODALE D'AJOUT / ÉDITION -->
    <!-- ================================================================== -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Ajouter un événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form id="eventForm" method="POST" action="/gestion/events/add" novalidate>
                    <input type="hidden" name="event_id" id="event_id" value="">
                    <div class="modal-body">
                        <!-- Zone pour les messages d'erreur de validation -->
                        <div id="validation-errors" class="alert alert-danger d-none" role="alert"></div>

                        <!-- Onglets de navigation -->
                        <ul class="nav nav-tabs nav-tabs-sm mb-3" id="event-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-info" data-bs-toggle="tab" data-bs-target="#pane-info" type="button" role="tab">1. Informations</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-tarifs" data-bs-toggle="tab" data-bs-target="#pane-tarifs" type="button" role="tab">2. Tarifs</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-sessions" data-bs-toggle="tab" data-bs-target="#pane-sessions" type="button" role="tab">3. Séances</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-inscriptions" data-bs-toggle="tab" data-bs-target="#pane-inscriptions" type="button" role="tab">4. Inscriptions</button>
                            </li>
                        </ul>

                        <!-- Contenu des onglets -->
                        <div class="tab-content" id="event-tabs-content">

                            <!-- Onglet 1: Informations générales -->
                            <div class="tab-pane fade show active" id="pane-info" role="tabpanel">
                                <div class="mb-3">
                                    <label for="event_name" class="form-label">Libellé de l'événement</label>
                                    <input type="text" name="name" id="event_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="event_place" class="form-label">Lieu</label>
                                    <select name="place" id="event_place" class="form-select" required>
                                        <option value="">Sélectionner</option>
                                        {% foreach $allPiscines as $piscine %}
                                        <option value="{{ $piscine->getId() }}">{{ $piscine->getLabel() }}</option>
                                        {% endforeach %}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="event_limitation_per_swimmer" class="form-label">Limitation par nageur</label>
                                    <input type="number" class="form-control" id="event_limitation_per_swimmer" name="limitation_per_swimmer" min="0" placeholder="0 ou vide = aucune limitation">
                                    <div class="form-text">0 ou vide = aucune limitation</div>
                                </div>
                            </div>

                            <!-- Onglet 2: Tarifs -->
                            <div class="tab-pane fade" id="pane-tarifs" role="tabpanel">
                                <p class="text-muted">Sélectionnez les tarifs applicables à cet événement. Au moins un tarif avec place est requis.</p>

                                <h6><i class="bi bi-person-workspace"></i> Tarifs avec places</h6>
                                <div class="list-group mb-4">
                                    {% foreach $allActiveTarifs as $tarif %}
                                    {% if $tarif->getSeatCount() > 0 %}
                                    <label class="list-group-item">
                                        <input class="form-check-input me-2" type="checkbox" name="tarifs[]" value="{{ $tarif->getId() }}">
                                        {{ $tarif->getName() }}
                                        <span class="text-muted float-end">{{ number_format($tarif->getPrice() / 100, 2, ',', ' ') }} €</span>
                                    </label>
                                    {% endif %}
                                    {% endforeach %}
                                </div>

                                <h6><i class="bi bi-plus-slash-minus"></i> Compléments</h6>
                                <div class="list-group">
                                    {% foreach $allActiveTarifs as $tarif %}
                                    {% if $tarif->getSeatCount() === null || $tarif->getSeatCount() == 0 %}
                                    <label class="list-group-item">
                                        <input class="form-check-input me-2" type="checkbox" name="tarifs[]" value="{{ $tarif->getId() }}">
                                        {{ $tarif->getName() }}
                                        <span class="text-muted float-end">{{ number_format($tarif->getPrice() / 100, 2, ',', ' ') }} €</span>
                                    </label>
                                    {% endif %}
                                    {% endforeach %}
                                </div>
                            </div>

                            <!-- Onglet 3: Séances -->
                            <div class="tab-pane fade" id="pane-sessions" role="tabpanel">
                                <p class="text-muted">Définissez au moins une séance pour cet événement.</p>
                                <div id="sessions-container">
                                    <!-- Les séances ajoutées dynamiquement apparaîtront ici -->
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" id="add-session-btn">
                                    <i class="bi bi-plus-circle"></i> Ajouter une séance
                                </button>
                            </div>

                            <!-- Onglet 4: Périodes d'inscription -->
                            <div class="tab-pane fade" id="pane-inscriptions" role="tabpanel">
                                <p class="text-muted">Définissez au moins une période d'inscription.</p>
                                <div id="inscription-dates-container">
                                    <!-- Les périodes ajoutées dynamiquement apparaîtront ici -->
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" id="add-inscription-btn">
                                    <i class="bi bi-plus-circle"></i>&nbsp;Ajouter une période
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div>
                            <button type="button" class="btn btn-danger btn-sm d-none" id="event-delete-btn">
                                <i class="bi bi-trash"></i>&nbsp;Supprimer
                            </button>
                            <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>&nbsp;Annuler
                            </button>
                            <button type="submit" class="btn btn-secondary">
                                <i class="bi bi-save"></i>&nbsp;Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- TEMPLATES POUR L'AJOUT DYNAMIQUE (JS) -->
<!-- ================================================================== -->

<!-- Template pour une séance -->
<template id="session-template">
    <div class="card mb-3 dynamic-item">
        <div class="card-body">
            <input type="hidden" name="sessions[__INDEX__][id]" value="">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="card-title mb-0">Nouvelle Séance</h6>
                <button type="button" class="btn-close remove-item-btn" aria-label="Supprimer la séance"></button>
            </div>
            <div class="mb-3">
                <label for="sessions___INDEX___session_name" class="form-label">Libellé de la séance</label>
                <input type="text" class="form-control" name="sessions[__INDEX__][session_name]" id="sessions___INDEX___session_name" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label for="sessions___INDEX___event_start_at" class="form-label">Début de la séance</label>
                    <input type="datetime-local" class="form-control session-start-date" name="sessions[__INDEX__][event_start_at]" id="sessions___INDEX___event_start_at" required>
                </div>
                <div class="col-md-6">
                    <label for="sessions___INDEX___opening_doors_at" class="form-label">Ouverture des portes</label>
                    <input type="datetime-local" class="form-control" name="sessions[__INDEX__][opening_doors_at]" id="sessions___INDEX___opening_doors_at" required>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Template pour une période d'inscription -->
<template id="inscription-period-template">
    <div class="card mb-3 dynamic-item">
        <div class="card-body">
            <input type="hidden" name="inscription_dates[__INDEX__][id]" value="">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="card-title mb-0">Nouvelle Période d'Inscription</h6>
                <button type="button" class="btn-close remove-item-btn" aria-label="Supprimer la période"></button>
            </div>
            <div class="mb-3">
                <label for="inscription_dates___INDEX___name" class="form-label">Libellé de la période</label>
                <input type="text" class="form-control" name="inscription_dates[__INDEX__][name]" id="inscription_dates___INDEX___name" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label for="inscription_dates___INDEX___start_registration_at" class="form-label">Ouverture des inscriptions</gabel>
                        <input type="datetime-local" class="form-control" name="inscription_dates[__INDEX__][start_registration_at]" id="inscription_dates___INDEX___start_registration_at" required>
                </div>
                <div class="col-md-6">
                    <label for="inscription_dates___INDEX___close_registration_at" class="form-label">Clôture des inscriptions</label>
                    <input type="datetime-local" class="form-control" name="inscription_dates[__INDEX__][close_registration_at]" id="inscription_dates___INDEX___close_registration_at" required>
                </div>
            </div>
            <div class="mt-3">
                <label for="inscription_dates___INDEX___access_code" class="form-label">Code d'accès (optionnel)</label>
                <input type="text" class="form-control" name="inscription_dates[__INDEX__][access_code]" id="inscription_dates___INDEX___access_code">
            </div>
        </div>
    </div>
</template>

<script type="module" src="/assets/js/gestion/events.js"></script>
