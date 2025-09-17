
<div class="container py-4">
    <?php if (!empty($contents)): ?>
        <?php foreach ($contents as $index => $content): ?>
            <div class="homepage-content">
                <?= $content->getContent() ?>
            </div>
            <?php if ($index < count($contents) - 1): ?>
                <hr class="my-5">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center">
            <h1>Bienvenue sur le site de réservation de l'<?= $_ENV['APP_NAME']; ?></h1>
            <p class="lead">Aucune information à afficher pour le moment. Revenez bientôt !</p>
        </div>
    <?php endif; ?>
</div>