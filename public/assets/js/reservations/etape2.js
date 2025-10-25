'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';
import {
    validateNameAndFirstname,
    validateEmailField,
    validatePhoneField
} from '../components/formContactValidator.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationInfosForm');
    if (!form) return;

    const nameInput = document.getElementById('name');
    const firstnameInput = document.getElementById('firstname');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const eventIdInput = document.getElementById('event_id');

    if (!nameInput || !firstnameInput || !emailInput || !phoneInput || !eventIdInput) {
        console.error("Un ou plusieurs champs du formulaire de l'étape 2 sont manquants.");
        return;
    }

    ['input', 'blur'].forEach(evt => {
        nameInput.addEventListener(evt, () => validateNameAndFirstname(nameInput, firstnameInput));
        firstnameInput.addEventListener(evt, () => validateNameAndFirstname(nameInput, firstnameInput));
        emailInput.addEventListener(evt, () => validateEmailField(emailInput));
        phoneInput.addEventListener(evt, () => validatePhoneField(phoneInput));
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        // Exécute toutes les validations
        const isNameValid = validateNameAndFirstname(nameInput, firstnameInput);
        const isEmailValid = validateEmailField(emailInput);
        const isPhoneValid = validatePhoneField(phoneInput);

        // La validation HTML5 s'occupe des champs requis
        if (!isNameValid || !isEmailValid || !isPhoneValid || !form.checkValidity()) {
            e.stopPropagation();
            (form.querySelector('.is-invalid') || nameInput).focus();
            showFlashMessage('danger', 'Veuillez corriger les erreurs dans le formulaire.');
        } else {
            step2Valid(
                nameInput.value.trim(),
                firstnameInput.value.trim(),
                emailInput.value.trim(),
                phoneInput.value.trim(),
                eventIdInput.value
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
                        event_id: eventId
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
            showFlashMessage('danger', err.userMessage || err.message);
        });

}
function submitEtape2(name, firstname, email, phone, eventId) {
    apiPost('/reservation/valid/2', {
        name: name,
        firstname: firstname,
        email: email,
        phone: phone,
        event_id: eventId
    })
        .then((data) => {
            if (data.success) {
                window.location.href = '/reservation/etape3Display';
            } else {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                // On construit un message d'erreur détaillé à partir de l'objet 'errors'
                let errorHtml = '<ul>';
                if (data.errors && typeof data.errors === 'object') {
                    for (const key in data.errors) {
                        // On échappe le message pour la sécurité
                        const safeMessage = data.errors[key].replace(/</g, "&lt;").replace(/>/g, "&gt;");
                        errorHtml += `<li>${safeMessage}</li>`;
                    }
                } else {
                    errorHtml += '<li>Une erreur inattendue est survenue.</li>';
                }
                errorHtml += '</ul>';
                showFlashMessage('danger', errorHtml);
            }
        })
        .catch((err) => {
            // On vérifie si l'erreur contient un objet 'data' avec des 'errors' détaillés
            if (err.data && err.data.errors && typeof err.data.errors === 'object') {
                let errorHtml = '<ul>';
                for (const key in err.data.errors) {
                    const safeMessage = String(err.data.errors[key]).replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    errorHtml += `<li>${safeMessage}</li>`;
                }
                errorHtml += '</ul>';
                showFlashMessage('danger', errorHtml);
            } else {
                // Sinon, on utilise le message d'erreur générique fourni par apiPost
                showFlashMessage('danger', err.userMessage || err.message);
            }
        });
}