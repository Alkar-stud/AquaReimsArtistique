<div class="container">
    <h2 class="mb-4">Récapitulatif de votre réservation</h2>
    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? '') ?></li>
        <?php if ($session): ?>
            <li class="list-group-item"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?></li>
        <?php endif; ?>

        <li class="list-group-item"><strong>Réservant :</strong>
            <?= htmlspecialchars($reservation->getPrenom() ?? '') ?>
            <?= htmlspecialchars($reservation->getNom() ?? '') ?>
            (
                <?= htmlspecialchars($reservation->getEmail() ?? '') ?>
                <?= ($reservation->getPhone() !== null && $reservation->getPhone() !== '') ? ' - ' . htmlspecialchars($reservation->getPhone()) : '' ?>
            )
        </li>
    </ul>
</div>