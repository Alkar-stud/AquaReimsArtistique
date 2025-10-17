<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $_ENV['APP_NAME'] ?? 'fr') }}">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="Description" content="Site de réservation pour les galas du Aqua Reims Artistique" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="content-type" content="no-cache">
    <meta http-equiv="refresh" content="no-cache">
    <meta name="csrf-token" content="{{ $csrf_token ?? '' }}">
    <meta name="csrf-context" content="{{ $csrf_context ?? '' }}">
    <meta charset="UTF-8">
    <link rel="icon" href="/assets/images/favicon.ico" type="image/x-icon" />
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
<body class="d-flex flex-column min-vh-100"
        {% if isset($js_data) %}
      data-js-vars='{{! json_encode($js_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !}}'
        {% endif %}
>
{% include 'header.tpl' %}
<main id="main-page" class="flex-grow-1">
    {% if $flash_message %}
    <div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container">
        {{ $flash_message['message'] ?? '' }}
    </div>
    {% endif %}

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
<link rel="stylesheet" href="/assets/css/debug-bar.css">
<div id="debug-container">
    <div id="debug-toggle" title="Afficher/Masquer le bandeau de débogage">
        <i class="bi bi-bug-fill"></i>
    </div>
    <div id="debug-bar">
        <div id="screen-dimensions-display"></div>
        {% if ($user_is_authenticated ?? false) || ($reservation_session_active ?? false) %}
        <span style="color: #666;">|</span>
        {% if ($user_is_authenticated ?? false) %}
        <div>
            <span style="color: #aaa;">User:</span> {{ $debug_user_info['name'] }} ({{ $debug_user_info['id'] }})
        </div>
        <span style="color: #666;">|</span>
        <div>
            <span style="color: #aaa;">Role:</span> {{ $debug_user_info['role_label'] }} ({{ $debug_user_info['role_id'] }})
        </div>
        <span style="color: #666;">|</span>
        {% endif %}
        <div id="session-timeout-display"></div>
        {% endif %}
    </div>
</div>
<script src="/assets/js/debug-bar.js" defer></script>
{% endif %}

</body>
</html>
