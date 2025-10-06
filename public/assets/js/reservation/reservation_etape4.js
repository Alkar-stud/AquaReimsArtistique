document.addEventListener('DOMContentLoaded', function () {

    document.getElementById('reservationPlacesForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const names = Array.from(document.getElementsByName('names[]')).map(i => i.value.trim());
        const firstnames = Array.from(document.getElementsByName('firstnames[]')).map(i => i.value.trim());
        const justificatifs = Array.from(document.getElementsByName('justificatifs[]'));
        const participants = [];
        let erreur = '';
        const couples = new Set();

        //Contrôle des données du formulaire
        for (let i = 0; i < names.length; i++) {
            if (!names[i]) erreur += `- Nom du participant ${i+1} manquant.<br>`;
            if (!firstnames[i]) erreur += `- Prénom du participant ${i+1} manquant.<br>`;
            if (names[i] && firstnames[i] && names[i].toLowerCase() === firstnames[i].toLowerCase()) {
                erreur += `- Le nom et le prénom du participant ${i+1} doivent être différents.<br>`;
            }
            const key = (names[i] + '|' + firstnames[i]).toLowerCase();
            if (couples.has(key)) {
                erreur += `- Le couple nom/prénom du participant ${i+1} est déjà utilisé.<br>`;
            }
            couples.add(key);
            participants.push({
                name: names[i],
                firstname: firstnames[i],
                justificatif: justificatifs[i].files && justificatifs[i].files[0] ? justificatifs[i].files[0].name : ''
            });
        }

        const formData = new FormData();
        formData.append('participants', JSON.stringify(participants));
        // Ajouter les fichiers justificatifs
        justificatifs.forEach((input, i) => {
            if (input.files && input.files[0]) {
                formData.append(`justificatif_${i}`, input.files[0]);
            }
        });

        apiPost('/reservation/valid/4', formData)
            .then((data) => {
                if (data.success) {
                    if (data.numerated_seat === true) {
                        window.location.href = '/reservation/etape5Display';
                    } else {
                        window.location.href = '/reservation/etape6Display';
                    }
                } else {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showFlash('danger', data.error || 'Erreur');
                }
            })
            .catch((err) => {
                showFlash('danger', err.userMessage || err.message);
            });

    });


});
