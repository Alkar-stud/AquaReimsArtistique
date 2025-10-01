document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationInfosForm');
    const name = document.getElementById('name');
    const firstname = document.getElementById('firstname');
    const email = document.getElementById('email');
    const phone = document.getElementById('phone');
    const eventId = document.getElementById('event_id').value;
    if (!form || !name || !firstname) return;

    const ensureInvalidFeedback = (input) => {
        // Cherche un .invalid-feedback voisin dédié à cet input, sinon le crée
        let fb = input.parentElement.querySelector(`.invalid-feedback[data-for="${input.id}"]`);
        if (!fb) {
            fb = document.createElement('div');
            fb.className = 'invalid-feedback';
            fb.dataset.for = input.id;
            input.parentElement.appendChild(fb);
        }
        return fb;
    };

    const normalize = (s) => {
        if (!s) return '';
        return s
            .trim()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // retire les accents
            .replace(/[^a-zA-Z]/g, '')                       // retire espaces, tirets, etc.
            .toLocaleLowerCase('fr-FR');
    };

    const setState = (input, ok, message = '') => {
        const fb = ensureInvalidFeedback(input);
        if (ok) {
            input.classList.remove('is-invalid');
            fb.textContent = '';
            if (input.value.trim()) input.classList.add('is-valid'); else input.classList.remove('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            fb.textContent = message;
        }
    };

    const checkNames = () => {
        const n = normalize(name.value);
        const p = normalize(firstname.value);

        // Ne déclenche l’erreur que si les deux champs ont une valeur
        if (n && p && n === p) {
            const msg = 'Le nom et le prénom ne doivent pas être identiques.';
            setState(name, false, msg);
            setState(firstname, false, msg);
            return false;
        }

        // OK : réinitialise l’état des deux champs (avec is-valid si non vide)
        setState(name, true);
        setState(firstname, true);
        return true;
    };

    ['input', 'blur'].forEach(evt => {
        name.addEventListener(evt, checkNames);
        firstname.addEventListener(evt, checkNames);
        email.addEventListener(evt, validateEmailField);
        phone.addEventListener(evt, validatePhoneField);
    });

    form.addEventListener('submit', (e) => {
        const ok = validateEmailField();
        if (!ok) {
            e.preventDefault();
            e.stopPropagation();
        }
    });

    function validateEmailField() {
        const value = email.value.trim();
        const ok = validateEmail(value);
        email.classList.toggle('is-invalid', !ok);
        if (!ok) {
            const msg = 'Adresse mail invalide.'
            setState(email, false, msg);
        } else {
            setState(email, true, '');
        }
        return ok;
    }

    function validatePhoneField() {
        const value = phone.value.trim();
        if (!value) {
            phone.classList.remove('is-invalid');
            return true;
        }
        const ok = validateTel(value);
        phone.classList.toggle('is-invalid', !ok);
        if (!ok) {
            const msg = 'Numéro de téléphone invalide (format attendu : 0X XX XX XX XX ou +33XXXXXXXXX).'
            setState(phone, false, msg);
        } else {
            setState(phone, true, '');
        }
        return ok;
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!checkNames() && !validateEmailField) {
            e.stopPropagation();
            (form.querySelector('.is-invalid') || name).focus();
        } else {
            // Appelle step2Valid avec les valeurs des champs
            step2Valid(
                name.value.trim(),
                firstname.value.trim(),
                email.value.trim(),
                phone.value.trim(),
                eventId
            );
        }
    });
});


function step2Valid(name, firstname, email, phone, eventId) {
    const alertDiv = document.getElementById('reservationAlert');
    // Vérification si email déjà utilisé dans d'autres réservations du même event
    apiPost('/reservation/check-duplicate-email', {
        event_id: eventId,
        email: email
    })
        .then((data) => {
console.log('data duplicate : ', data);
            //On met à jour le token_csrf
            window.csrf_token = data.csrf_token;
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

                document.getElementById('continueBtn').onclick = () => submitEtape2(name, firstname, email, phone, eventId);
                document.getElementById('cancelBtn').onclick = () => alertDiv.innerHTML = '';
                document.getElementById('resendBtn').onclick = () => {
                    apiPost('/reservation/resend-confirmation', {
                        email: email,
                        event_id: eventId,
                        csrf_token: window.csrf_token
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
                submitEtape2(name, firstname, email, phone, eventId);
            }

        })
        .catch((err) => {
            showFlash('danger', err.userMessage || err.message);
        });

}
function submitEtape2(name, firstname, email, phone, eventId) {
    apiPost('/reservation/etape2', {
        name: name,
        firstname: firstname,
        email: email,
        phone: phone
    })
        .then((data) => {
            if (data.success) {
                window.location.href = '/reservation/etape3Display';
            } else {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                const errorDiv = document.getElementById('form_error_message_' + eventId);
                if (errorDiv) {
                    errorDiv.textContent = data.error || 'Une erreur inconnue est survenue.';
                } else {
                    showFlash('danger', data.error || 'Erreur');
                }
            }
        })
        .catch((err) => {
            showFlash('danger', err.userMessage || err.message);
        });
}