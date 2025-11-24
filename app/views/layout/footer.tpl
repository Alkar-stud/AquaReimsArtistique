<footer class="mt-auto py-3 text-center text-white">
    <div class="container">
        <small>
            <address class="mb-0">
                Contact pour le gala uniquement :
                {% if defined('EMAIL_GALA') and EMAIL_GALA !== '' %}
                <a href="mailto:{{ EMAIL_GALA }}" class="link-light"
                   aria-label="Envoyer un courriel au contact du gala {{ EMAIL_GALA }}">
                    {{ EMAIL_GALA }}<span class="visually-hidden">, contact gala</span>
                </a>
                {% else %}
                <span class="text-muted">non renseigné</span>
                {% endif %}

                <br class="d-sm-none">
                <span class="mx-2 d-none d-sm-inline">|</span>

                Contact pour le club hors gala :
                {% if defined('EMAIL_CLUB') and EMAIL_CLUB !== '' %}
                <a href="mailto:{{ EMAIL_CLUB }}" class="link-light"
                   aria-label="Envoyer un courriel au contact du club {{ EMAIL_CLUB }}">
                    {{ EMAIL_CLUB }}<span class="visually-hidden">, contact club</span>
                </a>
                {% else %}
                <span class="text-muted">non renseigné</span>
                {% endif %}

                &nbsp;- &copy; {{ date('Y') }} - {{ $_ENV['APP_NAME'] ?? 'ARA' }} - Tous droits réservés.
            </address>
        </small>
    </div>
</footer>
