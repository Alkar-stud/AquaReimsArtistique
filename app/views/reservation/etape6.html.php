<?php include __DIR__ . '/_display_details.html.php'; ?>

<?php
$reservationComplement = $reservationComplement ?? [];
?>

    <div class="container">
        <h2 class="mb-4">Choix des tarifs sans places assises</h2>
        <form id="tarifsSansPlacesForm">
            <?php if (!empty($tarifsSansPlaces)): ?>
                <?php foreach ($tarifsSansPlaces as $tarif): ?>
                    <div class="mb-3">
                        <label for="tarif_<?= $tarif->getId() ?>" class="form-label">
                            <?= htmlspecialchars($tarif->getLibelle()) ?> - <?= number_format($tarif->getPrice(), 2, ',', ' ') ?> €
                        </label>
                        <?php if ($tarif->getDescription()): ?>
                            <div class="text-muted small mb-1"><?= nl2br(htmlspecialchars($tarif->getDescription())) ?></div>
                        <?php endif; ?>
                        <?php
                        $defaultQty = $reservationComplement[$tarif->getId()] ?? 0;
                        ?>
                        <input type="number"
                               class="form-control"
                               id="tarif_<?= $tarif->getId() ?>"
                               name="tarifs[<?= $tarif->getId() ?>]"
                               min="0"
                               value="<?= htmlspecialchars($defaultQty) ?>">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">Aucun tarif de cette catégorie.</div>
            <?php endif; ?>
            <a href="/reservation/etape5Display" class="btn btn-secondary ms-2">Modifier mon choix précédent</a>
            <button type="submit" class="btn btn-primary">Valider et continuer</button>
        </form>
        <div id="tarifsSansPlacesAlert"></div>
    </div>


<script>
    window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
</script>
<script src="/assets/js/reservation_common.js" defer></script>
<script src="/assets/js/reservation_etape6.js" defer></script>

Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>
