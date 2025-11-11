<footer class="mt-auto py-3 text-center bg-secondary text-white">
    <div class="container">
        <small>
            Contact pour le gala uniquement : <a href="mailto:{{ EMAIL_GALA ?? '' }}" class="link-light">{{ EMAIL_GALA ?? '' }}</a>
            <br class="d-sm-none"> <!-- Saut de ligne uniquement sur mobile -->
            <span class="mx-2 d-none d-sm-inline">|</span> <!-- Séparateur visible sur écran plus grand -->
            Contact pour le club hors gala : <a href="mailto:{{ EMAIL_CLUB ?? '' }}" class="link-light">{{ EMAIL_CLUB ?? '' }}</a>
            &nbsp;- &copy; {{ date('Y') }} - {{ $_ENV['APP_NAME'] ?? 'ARA' }} - Tous droits réservés.
        </small>
    </div>
</footer>
