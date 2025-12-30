// javascript
'use strict';

import { apiPost } from '../components/apiClient.js';
import { showFlashMessage } from '../components/ui.js';
import {
    validateNameAndFirstname,
    validateEmailField,
    validatePhoneField
} from '../components/formContactValidator.js';

// Met seulement aria-invalid et, si fourni, un message personnalisé.
function syncAriaFromResult(input, errorEl, isValid, customMessage) {
    input.setAttribute('aria-invalid', isValid ? 'false' : 'true');
    if (!errorEl) return;
    if (isValid) {
        errorEl.textContent = '';
    } else if (customMessage) {
        errorEl.textContent = String(customMessage);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationInfosForm');
    if (!form) return;

    const nameInput = document.getElementById('name');
    const firstnameInput = document.getElementById('firstname');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const eventIdInput = document.getElementById('event_id');
    const eventSessionIdInput = document.getElementById('event_session_id');
    const rgpdConsentInput = document.getElementById('rgpd_consent');

    const nameErr = document.getElementById('name_error');
    const firstnameErr = document.getElementById('firstname_error');
    const emailErr = document.getElementById('email_error');
    const phoneErr = document.getElementById('phone_error');

    if (!nameInput || !firstnameInput || !emailInput || !phoneInput || !eventIdInput || !eventSessionIdInput || !rgpdConsentInput) {
        console.error("Un ou plusieurs champs du formulaire de l'étape 2 sont manquants.");
        return;
    }

    const updateNamePair = () => {
        const ok = validateNameAndFirstname(nameInput, firstnameInput);
        syncAriaFromResult(nameInput, nameErr, ok);
        syncAriaFromResult(firstnameInput, firstnameErr, ok);
    };

    const updateEmail = () => {
        const ok = validateEmailField(emailInput);
        syncAriaFromResult(emailInput, emailErr, ok);
    };

    const updatePhone = () => {
        const ok = validatePhoneField(phoneInput);
        syncAriaFromResult(phoneInput, phoneErr, ok);
    };

    ['blur'].forEach(evt => {
        nameInput.addEventListener(evt, updateNamePair);
        firstnameInput.addEventListener(evt, updateNamePair);
        emailInput.addEventListener(evt, updateEmail);
        phoneInput.addEventListener(evt, updatePhone);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const isNameValid = validateNameAndFirstname(nameInput, firstnameInput);
        const isEmailValid = validateEmailField(emailInput);
        const isPhoneValid = validatePhoneField(phoneInput);

        syncAriaFromResult(nameInput, nameErr, isNameValid);
        syncAriaFromResult(firstnameInput, firstnameErr, isNameValid);
        syncAriaFromResult(emailInput, emailErr, isEmailValid);
        syncAriaFromResult(phoneInput, phoneErr, isPhoneValid);

        if (!isNameValid || !isEmailValid || !isPhoneValid || !form.checkValidity()) {
            e.stopPropagation();
            const firstInvalid = form.querySelector('.is-invalid,[aria-invalid="true"]') || nameInput;
            firstInvalid.focus();
            showFlashMessage('danger', 'Veuillez corriger les erreurs dans le formulaire.');
            const region = document.getElementById('reservationAlert');
            if (region) region.focus();
            return;
        }

        step2Valid(
            nameInput.value.trim(),
            firstnameInput.value.trim(),
            emailInput.value.trim(),
            phoneInput.value.trim(),
            eventIdInput.value,
            eventSessionIdInput.value,
            rgpdConsentInput.checked ? 1 : 0
        );
    });
});

function step2Valid(name, firstname, email, phone, eventId, eventSessionId, rgpdConsent) {
    const alertDiv = document.getElementById('reservationAlert');
    apiPost('/reservation/check-duplicate-email', {
        event_id: eventId,
        event_session_id: eventSessionId,
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
                        <button id="continueBtn" class="btn btn-success me-2 mb-2">Continuer ma nouvelle réservation</button>
                        <button id="resendBtn" class="btn btn-info me-2 mb-2">Renvoyer le(s) mail(s) de confirmation</button>
                        <button id="cancelBtn" class="btn btn-secondary mb-2">Annuler</button>
                    </div>`;
                alertDiv.innerHTML = html;
                alertDiv.focus();

                document.getElementById('continueBtn').onclick = () => submitEtape2(name, firstname, email, phone, eventId, eventSessionId, rgpdConsent);
                document.getElementById('cancelBtn').onclick = () => alertDiv.innerHTML = '';
                document.getElementById('resendBtn').onclick = () => {
                    apiPost('/reservation/resend-confirmation', { email, event_id: eventId, event_session_id: eventSessionId })
                        .then(res => {
                            if (res.success) {
                                alertDiv.innerHTML = '<div class="alert alert-success">Mail(s) de confirmation renvoyé(s) \!</div>';
                            } else {
                                alertDiv.innerHTML = `<div class="alert alert-danger">${res.error}</div>`;
                            }
                            alertDiv.focus();
                        });
                };
            } else {
                submitEtape2(name, firstname, email, phone, eventId, eventSessionId, rgpdConsent);
            }
        })
        .catch((err) => {
            showFlashMessage('danger', err.userMessage || err.message);
            const region = document.getElementById('reservationAlert');
            if (region) region.focus();
        });
}

function submitEtape2(name, firstname, email, phone, eventId, eventSessionId, rgpdConsent) {
    apiPost('/reservation/valid/2', {
        name,
        firstname,
        email,
        phone,
        event_id: eventId,
        event_session_id: eventSessionId,
        rgpd_consent: rgpdConsent
    })
        .then((data) => {
            if (data.success) {
                window.location.href = '/reservation/etape3Display';
            } else {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                let errorHtml = '<ul>';
                if (data.errors && typeof data.errors === 'object') {
                    for (const key in data.errors) {
                        const safeMessage = String(data.errors[key]).replace(/</g, "&lt;").replace(/>/g, "&gt;");
                        errorHtml += `<li>${safeMessage}</li>`;
                    }
                } else {
                    errorHtml += '<li>Une erreur inattendue est survenue.</li>';
                }
                errorHtml += '</ul>';
                showFlashMessage('danger', errorHtml);
                const region = document.getElementById('reservationAlert');
                if (region) region.focus();
            }
        })
        .catch((err) => {
            if (err.data && err.data.errors && typeof err.data.errors === 'object') {
                let errorHtml = '<ul>';
                for (const key in err.data.errors) {
                    const safeMessage = String(err.data.errors[key]).replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    errorHtml += `<li>${safeMessage}</li>`;
                }
                errorHtml += '</ul>';
                showFlashMessage('danger', errorHtml);
            } else {
                showFlashMessage('danger', err.userMessage || err.message);
            }
            const region = document.getElementById('reservationAlert');
            if (region) region.focus();
        });
}
