document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la sélection des groupes et nageuses
    const groupeSelects = document.querySelectorAll('.groupe-select');

    groupeSelects.forEach(select => {
        select.addEventListener('change', function() {
            const eventId = this.dataset.eventId;
            const groupeId = this.value;
            const nageuseSelect = this.closest('form').querySelector('.nageuse-select');

            if (!groupeId) {
                nageuseSelect.disabled = true;
                nageuseSelect.innerHTML = '<option value="">Sélectionnez d\'abord un groupe</option>';
                return;
            }

            // Récupérer les nageuses du groupe sélectionné via une requête AJAX
            fetch(`/api/nageuses/groupe/${groupeId}`)
                .then(response => response.json())
                .then(data => {
                    nageuseSelect.disabled = false;
                    nageuseSelect.innerHTML = '<option value="">Sélectionnez une nageuse</option>';

                    data.forEach(nageuse => {
                        const option = document.createElement('option');
                        option.value = nageuse.id;
                        option.textContent = nageuse.name;
                        nageuseSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des nageuses:', error);
                    nageuseSelect.disabled = true;
                    nageuseSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
                });
        });
    });

    // Validation du formulaire
    const reservationForms = document.querySelectorAll('.reservation-form');

    reservationForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const hasLimitation = this.querySelector('input[name="has_limitation"]').value === '1';

            if (hasLimitation) {
                const nageuseSelect = this.querySelector('.nageuse-select');

                if (!nageuseSelect.value) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une nageuse avant de continuer.');
                }
            }
        });
    });
});