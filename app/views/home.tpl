{% php %}
$page_css = ['/assets/css/pages/home.css'];
{% endphp %}
<div class="container">
    {% if !empty($contents) %}
    {% foreach $contents as $content %}
    <div class="homepage-content">
        {{! $content->getContent() !}}
    </div>
    <hr>
    {% endforeach %}
    {% else %}
    <div class="text-center">
        <h1>Bienvenue sur le site de réservation !</h1>
        <p class="lead">Aucune information à afficher pour le moment. Revenez bientôt !</p>
    </div>
    {% endif %}
</div>