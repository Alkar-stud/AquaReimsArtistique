<div class="container d-flex flex-column align-items-center justify-content-center" style="min-height: 60vh;">
    <div class="spinner-border text-primary mb-4" style="width: 6rem; height: 6rem;" role="status">
        <span class="visually-hidden">Chargement...</span>
    </div>
    <div class="message text-center fs-4 mb-2">Vérification du paiement en cours...</div>
    <div class="text-danger" id="merci-error"></div>
</div>
<script>
    // Récupère le checkoutId depuis l’URL
    const params = new URLSearchParams(window.location.search);
    const checkoutIntentId = params.get('checkoutIntentId');

    if (!checkoutIntentId) {
        document.getElementById('merci-error').textContent = "Erreur : checkoutId manquant.";
    } else {
        let attempts = 0;
        const maxAttempts = 5; // Tente 5 fois (20 secondes au total)
        const check = () => {
            const errorContainer = document.getElementById('merci-error');
            const spinner = document.querySelector('.spinner-border');
            const message = document.querySelector('.message');
console.log('avant fetch');
            fetch('/reservation/checkPayment', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({checkoutIntentId})
            })
                .then(async response => {
                    const responseText = await response.text();
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        // Réponse non-JSON
                        spinner.style.display = 'none';
                        message.style.display = 'none';
                        errorContainer.innerHTML = `Erreur lors de la vérification du paiement (Code: ${response.status}).<br>Réponse du serveur : <pre>${responseText}</pre>`;
                        return; // Arrête les tentatives
                    }
                    attempts++;
                    // Réponse JSON
                    if (data && data.success === true) {
                        window.location.href = '/reservation/success?uuid=' + data.reservationUuid;
                    } else if (data && data.status === 'pending' && attempts < maxAttempts) {
                        // Le paiement n'est pas encore confirmé, on réessaie dans 5 secondes
                        setTimeout(check, 5000);
                    } else if (data && data.status === 'pending' && attempts >= maxAttempts) {
                        // Les tentatives sont épuisées, on lance une vérification manuelle
                        message.textContent = 'La réponse tarde à arriver, nous vérifions directement auprès du service de paiement...';
                        fetch('/reservation/forceCheckPayment', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({checkoutIntentId})
                        })
                            .then(response => {
                                // Récupérer d'abord le texte brut
                                return response.text().then(text => {
                                    console.log("Réponse brute:", text);

                                    // Ensuite essayer de parser en JSON si possible
                                    try {
                                        return JSON.parse(text);
                                    } catch (error) {
                                        console.error("Erreur de parsing JSON:", error);
                                        console.log("Contenu non parsable:", text);
                                        throw new Error("Réponse non valide du serveur");
                                    }
                                });
                            })
                            .then(forceData => {
                                if (forceData && forceData.success === true) {
                                    window.location.href = '/reservation/success?uuid=' + data.reservationUuid;
                                } else {
                                    spinner.style.display = 'none';
                                    message.style.display = 'none';
                                    errorContainer.innerHTML = `La vérification finale a échoué. Détails : <pre>${JSON.stringify(forceData, null, 2)}</pre>`;
                                }
                            });
                    } else {
                        // Le paiement n'est pas trouvé après plusieurs tentatives, ou autre erreur
                        spinner.style.display = 'none';
                        message.style.display = 'none';
                        errorContainer.innerHTML = `Détails de l'erreur : <pre>${JSON.stringify(data, null, 2)}</pre>`;
                    }
                })
                .catch((error) => {
                    spinner.style.display = 'none';
                    message.style.display = 'none';
                    errorContainer.textContent = `Erreur réseau lors de la vérification du paiement : ${error.message}`;
                });
        };
        check();
    }
</script>

<hr>
Ici pour la suite, on a déjà enregistré ça :
<?php
echo '<pre>';
print_r($reservation);
echo '</pre>';
?>
