<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

    <div class="container">
        <h2 class="mb-4">Accueil de l'administration</h2>

        Affichera des liens vers les parties accessibles, éventuellement des messages pratiques

    </div>
<?php
//Page d'accueil de la partie gestion