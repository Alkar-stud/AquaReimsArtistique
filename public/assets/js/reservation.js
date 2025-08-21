
//Pour mettre à jour la liste déroulante des nageuses
function updateNageuses(groupeId, eventId) {
    const select = document.getElementById('nageuse_' + eventId);
    const container = document.getElementById('nageuse_container_' + eventId);
    select.innerHTML = '<option value="">Sélectionner une nageuse</option>';
    if (window.nageusesParGroupe && window.nageusesParGroupe[groupeId]) {
        window.nageusesParGroupe[groupeId].forEach(function(nageuse) {
            const opt = document.createElement('option');
            opt.value = nageuse.id;
            opt.textContent = nageuse.nom;
            select.appendChild(opt);
        });
        container.style.display = '';
    } else {
        container.style.display = 'none';
    }
}

function validerFormulaireReservation(eventId) {
    // Vérification des champs obligatoires
    let erreur = '';

    // Vérif séance
    const radios = document.querySelectorAll('input[name="session_' + eventId + '"]');
    let sessionChoisie = null;
    if (radios.length > 0) {
        radios.forEach(radio => { if (radio.checked) sessionChoisie = radio.value; });
        if (!sessionChoisie) erreur += "- Veuillez choisir une séance.\n";
    } else {
        // Cas séance unique (input hidden)
        const hidden = document.getElementById('session_' + eventId + '_1');
        if (!hidden) erreur += "- Séance non définie.\n";
    }

    // Vérif limitation nageuse
    const groupeSelect = document.getElementById('groupe_nageuses_' + eventId);
    let nageuseId = null;
    if (groupeSelect) {
        if (!groupeSelect.value) erreur += "- Veuillez choisir un groupe.\n";
        const nageuseSelect = document.getElementById('nageuse_' + eventId);
        if (!nageuseSelect || !nageuseSelect.value) erreur += "- Veuillez choisir une nageuse.\n";
        else nageuseId = nageuseSelect.value;
    }

    if (erreur) {
        alert("Merci de corriger les points suivants :\n" + erreur);
        return;
    }

    // Si limitation, contrôle côté serveur avant d'afficher le formulaire
    if (groupeSelect && nageuseId) {
        fetch(`/reservation/check-nageuse-limit?event_id=${eventId}&nageuse_id=${nageuseId}&csrf_token=${window.csrf_token}`)
            .then(async r => {
                const text = await r.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        if (data.limiteAtteinte) {
                            alert("Le quota de spectateurs pour cette nageuse est atteint.");
                        } else {
                            step1Valid(eventId, sessionChoisie, nageuseId);
                        }
                    } else {
                        alert(data.error || 'Erreur');
                    }
                } catch (e) {
                    alert("Réponse serveur invalide :\n" + text);
                }
            });
    } else {
        step1Valid(eventId, sessionChoisie, nageuseId);
    }

}

function step1Valid(eventId, sessionChoisie, nageuseId) {
    sessionChoisie = parseInt(sessionChoisie);
    nageuseId != null ? nageuseId = parseInt(nageuseId) : nageuseId = null;

    // Envoi au serveur (POST)
    fetch('/reservation/etape1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            event_id: eventId,
            event_session_id: sessionChoisie,
            nageuse_id: nageuseId || null,
            csrf_token: window.csrf_token
        })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Rediriger ou afficher l’étape suivante
                window.location.href = '/reservation/etape2Display';
            } else {
                alert(data.error || 'Erreur');
            }
        });
}