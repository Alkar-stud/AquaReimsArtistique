document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('button[type="submit"].btn-primary');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        const button = document.getElementById('submit-confirmation-btn');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Traitement...';

        // On récupère le montant total depuis la variable globale injectée par PHP
        const totalAmount = window.totalAmount || 0;

        // Récupère le token CSRF si besoin
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        fetch('/reservation/saveCart', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: window.csrf_token
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (totalAmount > 0) {
                        window.location.href = '/reservation/payment';
                    } else {
                        // Pour les réservations gratuites, on appelle l'endpoint AJAX dédié.
                        fetch('/reservation/finalize-free', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                csrf_token: window.csrf_token
                            })
                        })
                            .then(response => {
                                // Récupérer d'abord le texte brut
                                return response.text().then(text => {
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
                            .then(data => {
                                if (data.success && data.reservationUuid) {
                                    // Si c'est un succès, on redirige vers la page de confirmation finale.
                                    window.location.href = '/reservation/success?uuid=' + data.reservationUuid;
                                } else {
                                    // En cas d'erreur, on réactive le bouton et on affiche le message.
                                    alert(data.error || 'Une erreur est survenue lors de la finalisation de la réservation.');
                                    button.disabled = false;
                                    button.innerHTML = 'Valider ma réservation';
                                }
                            })
                            .catch(error => {
                                console.error('Erreur:', error);
                                alert('Une erreur de communication est survenue.');
                                button.disabled = false;
                                button.innerHTML = 'Valider ma réservation';
                            });
                    }
                } else {
                    alert(data.error || 'Erreur lors de l\'enregistrement du panier.');
                }
            })
            .catch((err) => {
                console.error('Erreur réseau :', err);
                alert('Erreur réseau.');
            });
    });
});
