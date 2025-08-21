document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reservationInfosForm');
    const alertDiv = document.getElementById('reservationAlert');
    const nomInput = form.nom;
    const prenomInput = form.prenom;
    const emailInput = form.email;
    const telInput = form.telephone;

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    function validateTel(tel) {
        return /^0[1-9](\d{8})$/.test(tel.replace(/\s+/g, ''));
    }

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
        if (!validateTel(telInput.value)) {
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
        if (!validateTel(telephone)) {
            alertDiv.innerHTML = '<div class="alert alert-danger">Numéro de téléphone invalide.</div>';
            return;
        }
        // Vérification email déjà utilisé
        fetch('/reservation/check-email', {
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
                        let html = `<div class="alert alert-warning">Il existe déjà ${data.reservations.length} réservation(s) pour cette adresse mail :<ul>`;
                        data.reservations.forEach(r => {
                            html += `<li>${r.nb_places} place(s) pour la séance du ${r.session_date}</li>`;
                        });
                        html += `</ul>
                    <button id="continueBtn" class="btn btn-success me-2">Oui, je continue quand même</button>
                    <button id="checkMailBtn" class="btn btn-secondary me-2">Oups, je vais vérifier dans mes mails</button>
                    <button id="resendBtn" class="btn btn-info">Renvoyer le(s) mail(s) de confirmation</button>
                </div>`;
                        alertDiv.innerHTML = html;

                        document.getElementById('continueBtn').onclick = () => submitEtape2(nom, prenom, email, telephone);
                        document.getElementById('checkMailBtn').onclick = () => window.location.href = '/';
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
                                .then(r => r.json())
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
console.log('début submitEtape2');
        fetch('/reservation/etape2', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nom, prenom, email, telephone,
                csrf_token: window.csrf_token
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/reservation/etape3Display';
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur'}</div>`;
                }
            });
    }
});