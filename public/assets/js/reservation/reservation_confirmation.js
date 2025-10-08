document.addEventListener('DOMContentLoaded', function () {
    const container = document;
    const form = document.getElementById('reservationPlacesForm');
    const submitButton = document.getElementById('submitButton');
    const eventIdInput = document.getElementById('event_id');

    if (!form) return;

    if (submitButton && eventIdInput) {
        submitButton.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            //On désactive les boutons pendant le traitement
            submitButton.disabled = true;
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Traitement...';



            apiPost('/reservation/payment', { event_id: eventIdInput.value })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showFlash('danger', data.error || "Erreur lors de la soumission au paiement.");
                        //On réactive suite à l'erreur
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText;
                    }
                })
                .catch((err) => {
                    showFlash('danger', err.userMessage || err.message);
                    // On réactive le bouton en cas d'erreur réseau ou serveur
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });

        });
    }







});
