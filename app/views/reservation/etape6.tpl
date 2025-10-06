{% if $flash_message %}
<div class="alert alert-{{ $flash_message['type'] ?? 'danger' }}" id="ajax_flash_container">
    {{ $flash_message['message'] ?? '' }}
</div>
{% endif %}

<div class="container-fluid">
    <h2 class="mb-4">Choix des compléments</h2>

</div>

<script src="/assets/js/reservation/reservation_common.js" defer></script>
<script src="/assets/js/reservation/reservation_etape6.js" defer></script>
Ici pour la suite, on a déjà enregistré ça :
{% php %}
echo '<pre>';
print_r($_SESSION['reservation']);
echo '</pre>';
{% endphp %}
