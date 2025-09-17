<div class="container py-4">
    {% if !empty($contents) %}
    {% foreach $contents_loop as $loop %}
    <div class="homepage-content">
        {{! $loop['item']->getContent() !}}
    </div>
    {% if !$loop['last'] %}<hr class="my-5">{% endif %}
    {% endforeach %}
    {% else %}
    <div class="text-center">
        <h1>Bienvenue sur le site de réservation de l'{{ $_ENV['APP_NAME'] }}</h1>
        <p class="lead">Aucune information à afficher pour le moment. Revenez bientôt !</p>
    </div>
    {% endif %}
</div>