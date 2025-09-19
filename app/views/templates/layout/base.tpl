<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $_ENV['APP_NAME'] ?? 'fr') }}">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="Description" content="Site de réservation pour les galas du Aqua Reims Artistique" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="content-type" content="no-cache">
    <meta http-equiv="refresh" content="no-cache">
    <meta charset="UTF-8">
    <link rel="icon" href="/assets/images/cropped-logo-AquaReimsArtistique-300-32x32.png" type="image/x-icon" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/generic.css">
    {% if $is_gestion_page %}<link rel="stylesheet" href="/assets/css/admin.css">{% endif %}
    {% if $load_ckeditor %}
    <link rel="stylesheet" href="/assets/css/ckeditor.css">
    <link rel="stylesheet" href="/assets/ckeditor5/ckeditor5.css">
    {% endif %}
    <script type="text/javascript" src="/assets/js/scripts.js" charset="UTF8"></script>
    <title>{{ ($_ENV['APP_NAME'] ?? 'Titre') . ' - ' . ($title ?? '') }}</title>
</head>
<body class="d-flex flex-column min-vh-100">
{% include 'header.tpl' %}
<main id="main-page" class="flex-grow-1">
    {{! $content !}}
</main>
{% include 'footer.tpl' %}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
{% if $load_ckeditor %}
<script type="importmap">
    {
        "imports": {
            "ckeditor5": "/assets/ckeditor5/ckeditor5.js",
            "ckeditor5/": "/assets/ckeditor5/"
        }
    }
</script>
<script type="module" src="/assets/js/ckeditor.js"></script>
{% endif %}
{% if $_ENV['APP_DEBUG'] == "true" %}
<!-- Outils de débogage -->
<div id="debug-bar" style="position: fixed; bottom: 10px; right: 10px; background-color: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 5px; font-family: monospace; z-index: 9999; font-size: 12px; display: flex; flex-direction: row; align-items: center; gap: 10px;">
    <div id="screen-dimensions-display"></div>
    {% if $user_is_authenticated and $session_timeout_duration > 0 %}
    <span style="color: #888;">|</span>
    <div id="session-timeout-display"></div>
    {% endif %}
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Affichage du Viewport ---
        const dimensionsDisplay = document.getElementById('screen-dimensions-display');
        function updateDimensions() {
            if(dimensionsDisplay) dimensionsDisplay.textContent = `Viewport: ${window.innerWidth}px x ${window.innerHeight}px`;
        }
        updateDimensions();
        window.addEventListener('resize', updateDimensions);

        // --- Compte à rebours de la session ---
        const timeoutDisplay = document.getElementById('session-timeout-display');
        if (timeoutDisplay) {
            const timeoutDuration = {{ $session_timeout_duration }}; // en secondes
        const lastActivity = {{ $session_last_activity }}; // timestamp
    const expirationTime = (lastActivity + timeoutDuration) * 1000; // en millisecondes

    const intervalId = setInterval(() => {
        const now = new Date().getTime();
        const remaining = expirationTime - now;

        if (remaining <= 0) {
            timeoutDisplay.textContent = 'Session: Expirée';
            timeoutDisplay.style.color = '#ffc107';
            clearInterval(intervalId);
        } else {
            const minutes = Math.floor(remaining / (1000 * 60));
            const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
            timeoutDisplay.textContent = `Session: ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    }, 1000);
    }
    });
</script>
{% endif %}
</body>
</html>
