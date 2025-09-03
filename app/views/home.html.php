<style>
    .homepage-content {
        text-align: center; /* Centre tout le contenu (texte, images) */
    }
    /* Cible tous les enfants directs (paragraphes, figures, etc.)
       pour les centrer horizontalement. */
    .homepage-content > * {
        margin-left: auto;
        margin-right: auto;
    }
    .homepage-content img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s ease-in-out;
    }
    .homepage-content a:hover img {
        transform: scale(1.03);
    }
</style>

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