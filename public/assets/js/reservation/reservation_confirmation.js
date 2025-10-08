document.addEventListener('DOMContentLoaded', function () {
    const container = document;
    const form = document.getElementById('reservationPlacesForm');
    const submitButton = document.getElementById('submitButton');
    const eventIdInput = document.getElementById('event_id');

    if (!form) return;

    if (submitButton && eventIdInput) {
        submitButton.addEventListener('click', async () => {
            //On désactive les boutons pendant le traitement
            submitButton && (submitButton.disabled = false);

alert('en cours');



        });
    }





});
