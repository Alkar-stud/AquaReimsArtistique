<?php
$redirectUrl = (isset($uri) && strpos($uri, '/gestion') === 0) ? '/gestion' : '/';
?>
<div class="container text-center py-5">
    <img src="/assets/images/404.png" alt="Logo Aqua Reims Artistique" class="img-fluid mb-4" style="max-width: 220px; height: auto;">
    <h1 class="display-4 mt-4">404 - Page non trouvée</h1>
    <p class="lead">Oups ! Vous avez coulé en tentant le Barracuda !<br>
        Plongez dans le menu pour retrouver votre chemin !</p>
    <a href="<?= $redirectUrl ?>" class="btn btn-primary mt-3">Retour à l’accueil</a>
</div>