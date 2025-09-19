<div class="d-none d-md-block">
    <div class="table-responsive">
        <table class="table align-middle table-hover">
            <thead>
            <tr>
                <th>ID réservation</th>
                <th>Nom Prénom</th>
                <th>Nageuse</th>
                <th>Nb places</th>
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