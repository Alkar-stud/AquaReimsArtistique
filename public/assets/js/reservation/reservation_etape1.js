// Met à jour la liste déroulante des nageurs
function updateSwimmer(groupeId, eventId) {
    const select = document.getElementById('swimmer_' + eventId);
    const container = document.getElementById('swimmer_container_' + eventId);
    select.innerHTML = '<option value="">Sélectionner une nageuse</option>';
    if (window.swimmerPerGroup && window.swimmerPerGroup[groupeId]) {
        window.swimmerPerGroup[groupeId].forEach(function(swimmer) {
            const opt = document.createElement('option');
            opt.value = swimmer.id;
            opt.textContent = swimmer.name;
            select.appendChild(opt);
        });
        container.style.display = '';
    } else {
        container.style.display = 'none';
    }
}

function validerFormulaireReservation(eventId) {
    let erreur = '';

    // Séance
    const radios = document.querySelectorAll('input[name="session_' + eventId + '"]');
    let sessionChoisie = null;
    if (radios.length > 0) {
        radios.forEach(radio => { if (radio.checked) sessionChoisie = radio.value; });
        if (!sessionChoisie) erreur += "- Veuillez choisir une séance.\n";
    } else {
        const hidden = document.getElementById('session_' + eventId + '_1');
        if (!hidden) erreur += "- Séance non définie.\n";
    }

    // Limitation par nageur.
    const groupSelect = document.getElementById('swimmer_group_' + eventId);
    let swimmerId = null;
    if (groupSelect) {
        if (!groupSelect.value) erreur += "- Veuillez choisir un groupe.\n";
        const swimmerSelect = document.getElementById('swimmer_' + eventId);
        if (!swimmerSelect || !swimmerSelect.value) erreur += "- Veuillez choisir un nageuse.\n";
        else swimmerId = swimmerSelect.value;
    }

    //Si besoin d'un code accès
    const codeAccessInput = document.getElementById('access_code_input_' + eventId);
    let codeAccess = null;
    if (codeAccessInput) {
        codeAccess = codeAccessInput.value.trim();
        if (!codeAccess) erreur += "- Veuillez saisir un code d'accès.\n";
    }


    if (erreur) {
        const errorDiv = document.getElementById('form_error_message_' + eventId);
        if (errorDiv) {
            errorDiv.innerHTML = "Merci de corriger les points suivants :<br>" + erreur.replace(/\n/g, "<br>");
        } else {
            alert("Merci de corriger les points suivants :\n" + erreur);
        }
        return;
    }

    // Contrôle serveur (limite nageur) si nécessaire
    if (groupSelect && swimmerId) {
        apiPost('/reservation/check-swimmer-limit', {
            event_id: eventId,
            swimmer_id: parseInt(swimmerId, 10)
        })
            .then((data) => {
                if (data.success) {
                    if (data.limiteAtteinte) {
                        let limitPerSwimmer = '';
                        if (data.limitPerSwimmer) {
                            limitPerSwimmer = ' (max : ' + data.limitPerSwimmer + ')';
                        }
                        showFlash('warning', "Le quota de spectateurs pour cette nageuse est atteint" + limitPerSwimmer + ".");
                    } else {
                        step1Valid(eventId, sessionChoisie, swimmerId, codeAccess);
                    }
                } else {
                    showFlash('danger', data.error || 'Erreur lors du contrôle de la limite.');
                }
            })
            .catch((err) => {
                showFlash('danger', err.userMessage || err.message);
            });
    } else {
        step1Valid(eventId, sessionChoisie, swimmerId, codeAccess);
    }
}

function validerCodeAcces(eventId) {
    const code = document.getElementById('access_code_input_' + eventId).value.trim();
    const status = document.getElementById('access_code_status_' + eventId);
    if (!code) {
        status.textContent = "Veuillez saisir un code.";
        return;
    }

    apiPost('/reservation/validate-access-code', {
        event_id: eventId,
        code: code
    })
        .then((data) => {
            if (data.success) {
                status.textContent = "Code valide !";
                status.classList.remove('text-danger');
                status.classList.add('text-success');
                const btn = document.getElementById('btn_reserver_' + eventId);
                if (btn) btn.disabled = false;
            } else {
                status.textContent = data.error || "Code invalide.";
                status.classList.remove('text-success');
                status.classList.add('text-danger');
                const btn = document.getElementById('btn_reserver_' + eventId);
                if (btn) btn.disabled = true;
            }
        })
        .catch((err) => {
            // Le corps complet non-JSON est déjà loggé par apiPost
            showFlash('danger', err.userMessage || err.message);
        });
}

function step1Valid(eventId, sessionChoisie, swimmerId, codeAccess) {
    sessionChoisie = parseInt(sessionChoisie, 10);
    swimmerId = (swimmerId != null) ? parseInt(swimmerId, 10) : null;

    apiPost('/reservation/valid/1', {
        event_id: eventId,
        event_session_id: sessionChoisie,
        swimmer_id: swimmerId,
        access_code_used: codeAccess || null
    })
        .then((data) => {
            if (data.success) {
                window.location.href = '/reservation/etape2Display';
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
