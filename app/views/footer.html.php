    <footer class="mt-auto py-3 text-center bg-secondary text-white">
        <div class="container">
            <small>
                Contact pour le gala uniquement : <a href="mailto:<?= htmlspecialchars(EMAIL_GALA ?? ''); ?>" class="link-light"><?= htmlspecialchars(EMAIL_GALA ?? ''); ?></a>
                <br class="d-sm-none"> <!-- Saut de ligne uniquement sur mobile -->
                <span class="mx-2 d-none d-sm-inline">|</span> <!-- Séparateur visible sur écran plus grand -->
                Contact pour le club hors gala : <a href="mailto:<?= htmlspecialchars( EMAIL_CLUB ?? ''); ?>" class="link-light"><?= htmlspecialchars(EMAIL_CLUB ?? ''); ?></a>
            </small>
        </div>
    </footer>