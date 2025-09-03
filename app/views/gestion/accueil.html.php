<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>


<div class="container-fluid">
    <h2 class="mb-4">Gestion de la page d'accueil</h2>
    <div class="mb-4">
        <button type="button" class="btn btn-success btn-sm mb-3 w-100 d-md-none" onclick="openEventModal('add')">
            Ajouter une nouvelle page d'accueil
        </button>
        <button type="button" class="btn btn-success btn-sm mb-3 d-none d-md-inline-block" onclick="openEventModal('add')">
            Ajouter une nouvelle page d'accueil
        </button>
    </div>
</div>