document.addEventListener('DOMContentLoaded', function () {
    const container = document;

    // Récupère le checkoutId depuis l’URL
    const params = new URLSearchParams(window.location.search);
    const checkoutIntentId = params.get('checkoutIntentId');
    if (!checkoutIntentId) {
        document.getElementById('merci-error').textContent = "Erreur : checkoutId manquant.";
    } else {

        let attempts = 0;
        const maxAttempts = 5; // Tente 5 foiss
        const check = () => {
            const errorContainer = document.getElementById('merci-error');
            const spinner = document.querySelector('.spinner-border');
            const message = document.querySelector('.message');

            apiPost('/reservation/checkPayment', {checkoutIntentId})
                .then((data) => {
                    if (data.success) {
                        window.location.href = '/reservation/merci?token=' + data.token;
                    } else {
                        attempts++;
                        if (data && data.status === 'pending' && attempts < maxAttempts) {
                            // Le paiement n'est pas encore confirmé, on réessaie dans 5 secondes
                            setTimeout(check, 5000);
                        } else if (data && data.status === 'pending' && attempts >= maxAttempts) {
                            // Les tentatives sont épuisées, on lance une vérification manuelle
                            message.textContent = 'La réponse tarde à arriver, nous vérifions directement auprès du service de paiement...';

                        }
                    }
                })
                .catch((err) => {
                    showFlash('danger', err.userMessage || err.message);
                });

        }
        check();
    }

});