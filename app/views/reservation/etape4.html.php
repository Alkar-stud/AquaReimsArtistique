<?php include __DIR__ . '/_display_details.html.php';
$tarifsById = [];
foreach ($tarifs as $tarif) {
    $tarifsById[$tarif->getId()] = $tarif->getLibelle();
}
$participants = [];
foreach (($reservation['reservation_detail'] ?? []) as $detail) {
    $libelle = $tarifsById[$detail['tarif_id']] ?? '';
    $qty = $detail['qty'] ?? 1;
    for ($j = 0; $j < $qty; $j++) {
        $participants[] = $libelle;
    }
}

$nbPlaces = 0;
foreach (($reservation['reservation_detail'] ?? []) as $detail) {
    $nbPlaces += $detail['qty'] ?? 1;
}
?>
    <div class="container">
        <h2 class="mb-4">Informations des participants</h2>
        <form id="form_etape4" enctype="multipart/form-data">
            <?php foreach (($reservation['reservation_detail'] ?? []) as $i => $detail):
                $tarif = null;
                foreach ($tarifs as $t) {
                    if ($t->getId() == $detail['tarif_id']) {
                        $tarif = $t;
                        break;
                    }
                }
                $nom = $detail['nom'] ?? '';
                $prenom = $detail['prenom'] ?? '';
                $justif = $detail['justificatif_name'] ?? '';
                ?>
                <div class="mb-3">
                    <label>
                        <strong>Participant <?= $i + 1 ?> pour le tarif</strong>
                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($tarif ? $tarif->getLibelle() : '') ?></span>
                    </label>
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" name="noms[]" class="form-control" placeholder="Nom" required value="<?= htmlspecialchars($nom) ?>">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="prenoms[]" class="form-control" placeholder="Prénom" required value="<?= htmlspecialchars($prenom) ?>">
                        </div>
                    </div>
                    <?php if ($tarif && $tarif->getIsProofRequired()): ?>
                        <div class="mt-2">
                            <label>Justificatif (PDF ou image) :</label>
                            <input type="file" name="justificatifs[]" accept=".pdf,image/*" class="form-control" <?= $justif ? '' : 'required' ?>>
                            <?php if ($justif): ?>
                                <div class="mt-1 text-success">Déjà fourni, pour le changer, il suffit d'envoyer le nouveau fichier</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="justificatifs[]" value="">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div id="form_error_message" class="text-danger mb-3"></div>

            <a href="/reservation/etape3Display" class="btn btn-secondary ms-2">Modifier mon choix précédent</a>
            <button type="submit" class="btn btn-primary">Valider et continuer</button>
        </form>
    </div>
    <script>
        window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
    </script>
    <script src="/assets/js/reservation_common.js" defer></script>
    <script src="/assets/js/reservation_etape4.js" defer></script>

Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>
