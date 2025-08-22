<?php include __DIR__ . '/_display_details.html.php'; ?>


<script>
    window.csrf_token = <?= json_encode($csrf_token ?? '') ?>;
</script>
<script src="/assets/js/reservation_etape6.js" defer></script>

Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>