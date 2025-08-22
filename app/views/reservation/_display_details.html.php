<?php
$event = $event ?? null;
$session = $session ?? null;
$nageuse = $nageuse ?? null;
$tarifsById = $tarifsById ?? [];
?>
<div class="alert alert-info d-flex align-items-center justify-content-between mb-3" id="reservationSummary">
    <div>
        <strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? 'Non défini') ?>
        <?php if ($session): ?>
            <span class="ms-3"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?> </span>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleDetailsBtn">Détail</button>
</div>
<div id="reservationDetails" class="card mb-3" style="display:none;">
    <div class="card-body">
        <h5 class="card-title">Détail de votre réservation</h5>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Événement :</strong> <?= htmlspecialchars($event->getLibelle() ?? '') ?></li>
            <li class="list-group-item"><strong>Séance :</strong> <?= htmlspecialchars($session->getEventStartAt()->format('d/m/Y H:i')) ?></li>
            <?php if ($nageuse): ?>
                <li class="list-group-item"><strong>Nageuse :</strong> <?= htmlspecialchars($nageuse->getName() ?? '') ?></li>
            <?php endif; ?>
            <?php if (!empty($reservation['user'])): ?>
                <li class="list-group-item"><strong>Réservant :</strong> <?= htmlspecialchars($reservation['user']['prenom'] ?? '') ?> <?= htmlspecialchars($reservation['user']['nom'] ?? '') ?> (<?= htmlspecialchars($reservation['user']['email'] ?? '') ?>)</li>
            <?php endif; ?>
            <?php if (!empty($reservation['reservation_detail'])): ?>
                <li class="list-group-item">
                    <strong>Participants :</strong>
                    <ul>
                        <?php foreach ($reservation['reservation_detail'] as $i => $detail): ?>
                            <li>
                                <?= htmlspecialchars(($detail['prenom'] ?? '') . ' ' . ($detail['nom'] ?? '')) ?>
                                <?php if (!empty($detail['tarif_id'])): ?>
                                    (Tarif : <em><?= htmlspecialchars($tarifsById[$detail['tarif_id']] ?? $detail['tarif_id']) ?></em> )
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
        <div class="d-flex justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="toggleDetailsBtnBottom">Masquer</button>
        </div>
    </div>
</div>
<script>
    const btn = document.getElementById('toggleDetailsBtn');
    const btnBottom = document.getElementById('toggleDetailsBtnBottom');
    const details = document.getElementById('reservationDetails');
    function toggleDetails() {
        const isVisible = details.style.display === 'block';
        details.style.display = isVisible ? 'none' : 'block';
        btn.textContent = isVisible ? 'Détail' : 'Masquer';
        btnBottom.textContent = isVisible ? 'Détail' : 'Masquer';
    }
    btn.onclick = toggleDetails;
    btnBottom.onclick = toggleDetails;
</script>