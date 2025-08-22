<?php include __DIR__ . '/_display_details.html.php'; ?>
<div class="container">
    <h2 class="mb-4">Informations personnelles</h2>
    <form id="reservationInfosForm" class="mb-4">
        <div class="mb-3">
            <label for="nom" class="form-label">Nom *</label>
            <input type="text" class="form-control" id="nom" name="nom" required
                   value="<?= htmlspecialchars($reservation['user']['nom'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="prenom" class="form-label">Prénom *</label>
            <input type="text" class="form-control" id="prenom" name="prenom" required
                   value="<?= htmlspecialchars($reservation['user']['prenom'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Adresse mail *</label>
            <input type="email" class="form-control" id="email" name="email" required
                   value="<?= htmlspecialchars($reservation['user']['email'] ?? '') ?>">
            <div id="emailFeedback" class="invalid-feedback"></div>
        </div>
        <div class="mb-3">
            <label for="telephone" class="form-label">Téléphone *</label>
            <input type="tel" class="form-control" id="telephone" name="telephone" required
                   value="<?= htmlspecialchars($reservation['user']['telephone'] ?? '') ?>">
            <div id="telFeedback" class="invalid-feedback"></div>
        </div>
        <a href="/reservation" class="btn btn-secondary ms-2">Modifier mon choix précédent</a>
        <button type="submit" class="btn btn-primary">Valider et continuer</button>
    </form>
    <div id="reservationAlert"></div>
</div>
    <script>
        window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
        window.reservation = <?= json_encode($reservation ?? []) ?>;
    </script>
    <script src="/assets/js/reservation_etape2.js" defer></script>

Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>