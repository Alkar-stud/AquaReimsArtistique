{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2>Gestion des réservations</h2>

    <ul class="nav nav-tabs" id="reservations-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-tab="upcoming" type="button" role="tab">
                À venir
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-tab="past" type="button" role="tab">
                Passées
            </button>
        </li>
    </ul>

    <div class="mt-3">
        <h3 id="reservations-subtitle">Réservations à venir</h3>
    </div>

    <div class="tab-content mt-3">
        <div id="tab-upcoming" class="tab-pane active">
            <!-- Desktop -->
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead>
                        <tr>
                            <th>ID réservation</th>
                            <th>Nom Prénom</th>
                            <th>Nageuse</th>
                            <th>Nb places au total</th>
                            <th>Statut du paiement</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <!-- Exemple de ligne, à remplacer par une boucle -->
                        <tr>
                            <td>RES-00123</td>
                            <td>Jean Dupont</td>
                            <td>Marie Dupont</td>
                            <td>3</td>
                            <td><span class="badge bg-success">Payé</span></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reservationDetailModal">
                                    Consulter / Modifier
                                </button>
                            </td>
                        </tr>
                        <!-- Fin de l'exemple -->
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Mobile -->
            <div class="d-block d-md-none">
                <!-- Exemple de carte, à remplacer par une boucle -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Réservation RES-00123</h5>
                        <p class="card-text mb-1"><strong>Acheteur :</strong> Jean Dupont</p>
                        <p class="card-text mb-1"><strong>Nageuse :</strong> Marie Dupont</p>
                        <p class="card-text mb-1"><strong>Places :</strong> 3</p>
                        <p class="card-text mb-2"><strong>Paiement :</strong> <span class="badge bg-success">Payé</span></p>
                        <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#reservationDetailModal">
                            Consulter / Modifier
                        </button>
                    </div>
                </div>
                <!-- Fin de l'exemple -->
            </div>
        </div>
        <div id="tab-past" class="tab-pane d-none">
            <!-- Desktop -->
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead>
                        <tr>
                            <th>ID réservation</th>
                            <th>Nom Prénom</th>
                            <th>Nageuse</th>
                            <th>Nb places au total</th>
                            <th>Statut du paiement</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <!-- Exemple de ligne, à remplacer par une boucle -->
                        <tr class="text-muted">
                            <td>RES-00098</td>
                            <td>Alice Martin</td>
                            <td>Clara Martin</td>
                            <td>2</td>
                            <td><span class="badge bg-success">Payé</span></td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reservationDetailModal">
                                    Consulter
                                </button>
                            </td>
                        </tr>
                        <!-- Fin de l'exemple -->
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Mobile -->
            <div class="d-block d-md-none">
                <!-- Exemple de carte, à remplacer par une boucle -->
                <div class="card mb-3 text-muted bg-light">
                    <div class="card-body">
                        <h5 class="card-title">Réservation RES-00098</h5>
                        <p class="card-text mb-1"><strong>Acheteur :</strong> Alice Martin</p>
                        <p class="card-text mb-1"><strong>Nageuse :</strong> Clara Martin</p>
                        <p class="card-text mb-1"><strong>Places :</strong> 2</p>
                        <p class="card-text mb-2"><strong>Paiement :</strong> <span class="badge bg-success">Payé</span></p>
                        <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#reservationDetailModal">
                            Consulter
                        </button>
                    </div>
                </div>
                <!-- Fin de l'exemple -->
            </div>
        </div>
    </div>

</div>


<!-- Modal Détails Réservation -->
<div class="modal fade" id="reservationDetailModal" tabindex="-1" aria-labelledby="reservationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationDetailModalLabel">Détails de la réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Le contenu détaillé de la réservation sera chargé ici (via JS ou un rechargement de page) -->
                <p>Chargement des détails...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </div>
    </div>
</div>


<script src="/assets/js/gestion/reservations.js" defer></script>
