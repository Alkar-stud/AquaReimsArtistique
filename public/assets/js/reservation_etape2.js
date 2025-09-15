document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reservationInfosForm');
    const alertDiv = document.getElementById('reservationAlert');
    const nomInput = form.nom;
    const prenomInput = form.prenom;
    const emailInput = form.email;
    const telInput = form.telephone;

    emailInput.addEventListener('blur', function () {
        if (!validateEmail(emailInput.value)) {
            emailInput.classList.add('is-invalid');
            document.getElementById('emailFeedback').textContent = "Adresse mail invalide.";
        } else {
            emailInput.classList.remove('is-invalid');
            document.getElementById('emailFeedback').textContent = "";
        }
    });

    telInput.addEventListener('blur', function () {
        const telephoneValue = telInput.value.trim();
        if (telephoneValue !== '' && !validateTel(telephoneValue)) {
            telInput.classList.add('is-invalid');
            document.getElementById('telFeedback').textContent = "Numéro de téléphone invalide (format attendu : 0X XX XX XX XX).";
        } else {
            telInput.classList.remove('is-invalid');
            document.getElementById('telFeedback').textContent = "";
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        alertDiv.innerHTML = '';

        const nom = nomInput.value.trim();
        const prenom = prenomInput.value.trim();
        const email = emailInput.value.trim();
        const telephone = telInput.value.trim();

        if (nom.toLowerCase() === prenom.toLowerCase()) {
            alertDiv.innerHTML = '<div class="alert alert-danger">Le nom et le prénom ne doivent pas être identiques.</div>';
            return;
        }
        if (!validateEmail(email)) {
            alertDiv.innerHTML = '<div class="alert alert-danger">Adresse mail invalide.</div>';
            return;
        }
        if (telephone !== '' && !validateTel(telephone)) {
            alertDiv.innerHTML = '<div class="alert alert-danger">Numéro de téléphone invalide.</div>';
            return;
        }
        // Vérification si email déjà utilisé dans d'autres réservations du même event
        fetch('/reservation/check-duplicate-email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: email,
                event_id: window.reservation?.event_id || null,
                csrf_token: window.csrf_token
            })
        })
            .then(async r => {
                const text = await r.text();
                try {
                    const data = JSON.parse(text);

                    if (data.exists) {
                        let html = `<div class="alert alert-warning">
                             <p>Vous avez déjà réservé <strong>${data.total_places_reserved} place(s)</strong> en <strong>${data.num_reservations} réservation(s)</strong> pour cet événement :</p><ul>`;
                        data.reservation_summaries.forEach(summary => {
                            html += `<li>${summary.nb_places} place(s) pour la séance du ${summary.session_date}</li>`;
                        });
                        html += `</ul><p>Que souhaitez-vous faire ?</p>
                        <button id="continueBtn" class="btn btn-success me-2">Continuer ma nouvelle réservation</button>
                        <button id="resendBtn" class="btn btn-info">Renvoyer le(s) mail(s) de confirmation</button>
                        <button id="cancelBtn" class="btn btn-secondary">Annuler</button>
                    </div>`;
                        alertDiv.innerHTML = html;

                        document.getElementById('continueBtn').onclick = () => submitEtape2(nom, prenom, email, telephone);
                        document.getElementById('cancelBtn').onclick = () => alertDiv.innerHTML = '';
                        document.getElementById('resendBtn').onclick = () => {
                            fetch('/reservation/resend-confirmation', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    email: email,
                                    event_id: window.reservation?.event_id || null,
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
                                 .then(res => {
                                    if (res.success) {
                                        alertDiv.innerHTML = '<div class="alert alert-success">Mail(s) de confirmation renvoyé(s) !</div>';
                                    } else {
                                        alertDiv.innerHTML = `<div class="alert alert-danger">${res.error}</div>`;
                                    }
                                });
                        };
                    } else {
                        submitEtape2(nom, prenom, email, telephone);
                    }
                } catch (e) {
                    alertDiv.innerHTML = `<div class="alert alert-danger">Réponse serveur invalide :<br><pre>${text}</pre></div>`;
                }
            });
    });

    function submitEtape2(nom, prenom, email, telephone) {
        fetch('/reservation/etape2', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nom, prenom, email, telephone,
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
                if (data.success) {
                    window.location.href = '/reservation/etape3Display';
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur'}</div>`;
                }
            });
    }
});