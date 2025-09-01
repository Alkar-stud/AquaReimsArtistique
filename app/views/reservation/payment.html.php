<div class="container">
    <h2 class="mb-4">Paiement de votre réservation</h2>
    <p>
        <a id="payment-link" href="<?= htmlspecialchars($redirectUrl); ?>"><img src="/assets/images/payer-avec-helloasso.svg" title="Payer avec HelloAsso" alt="Payer avec HelloAsso"></a>
    </p>
    <p>
        Le modèle solidaire de HelloAsso garantit que 100% de votre paiement sera versé à l'association choisie.
        Vous pouvez soutenir l'aide qu'ils apportent aux associations en laissant une contribution volontaire à HelloAsso au moment de votre paiement.
    </p>
    <p>Vous allez être redirigé ou vous pouvez cliquer sur le bouton ci-dessus.</p>

<?php
    if (isset($_ENV['APP_ENV']) && in_array($_ENV['APP_ENV'], ['local', 'dev'])): ?>
        <div class="alert alert-info mt-4">
            <p class="mb-0"><b>Environnement de test :</b> voici la carte bancaire à utiliser : <b>4242424242424242</b>. Validité <b>date supérieure au mois en cours</b>, code : <b>3 chiffres au choix</b>.</p>
            <p class="mb-0">Il faut cliquer sur le lien, la redirection automatique est désactivée en environnement de test.</p>
        </div>
    <?php else: ?>
        <script>
            setTimeout(function() { window.location.href = '<?= addslashes($redirectUrl) ?>'; }, 5000);
        </script>
    <?php endif;
?>
</div>
<script>
    const paymentLink = document.getElementById('payment-link');
    if (paymentLink) {
        paymentLink.addEventListener('click', function(event) {
            event.preventDefault(); // Empêche le lien de naviguer immédiatement

            const redirectUrl = this.href;

            // Envoie une requête au serveur pour vider les variables de session.
            // On ne se soucie pas de la réponse, on redirige l'utilisateur quoi qu'il arrive.
            fetch('/reservation/clear-payment-intent', {
                method: 'POST'
            })
                .finally(() => {
                    // Que la requête réussisse ou échoue, on redirige l'utilisateur vers HelloAsso.
                    window.location.href = redirectUrl;
                });
        });
    }
</script>

<hr>
Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>reservation';
print_r($reservation);
echo '</pre>';
?>
