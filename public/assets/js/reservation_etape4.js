document.getElementById('form_etape4').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    formData.append('csrf_token', window.csrf_token);
    const noms = Array.from(document.getElementsByName('noms[]')).map(i => i.value.trim());
    const prenoms = Array.from(document.getElementsByName('prenoms[]')).map(i => i.value.trim());
    let erreur = '';
    const couples = new Set();

    for (let i = 0; i < noms.length; i++) {
        if (!noms[i]) erreur += `- Nom du participant ${i+1} manquant.<br>`;
        if (!prenoms[i]) erreur += `- Prénom du participant ${i+1} manquant.<br>`;
        if (noms[i] && prenoms[i] && noms[i].toLowerCase() === prenoms[i].toLowerCase()) {
            erreur += `- Le nom et le prénom du participant ${i+1} doivent être différents.<br>`;
        }
        const key = (noms[i] + '|' + prenoms[i]).toLowerCase();
        if (couples.has(key)) {
            erreur += `- Le couple nom/prénom du participant ${i+1} est déjà utilisé.<br>`;
        }
        couples.add(key);
    }

    if (erreur) {
        document.getElementById('form_error_message').innerHTML = "Merci de corriger les points suivants :<br>" + erreur;
        return;
    }

    fetch('/reservation/etape4', {
        method: 'POST',
        body: formData
    })
        .then(async r => {
            const text = await r.text();
            try {
                const data = JSON.parse(text);
                if (data.success) {

                    if (data.numberedSeats === false) {
                        window.location.href = '/reservation/etape6Display';
                    } else {
                        window.location.href = '/reservation/etape5Display';
                    }
                } else {
                    document.getElementById('form_error_message').innerHTML = data.error || 'Erreur';
                }
            } catch (e) {
                document.getElementById('form_error_message').innerHTML =
                    `<div class="alert alert-danger">Réponse serveur invalide :<br><pre>${text}</pre></div>`;
            }
        });
});

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form_etape4');
    const nomsInputs = Array.from(document.getElementsByName('noms[]'));
    const prenomsInputs = Array.from(document.getElementsByName('prenoms[]'));
    const errorDiv = document.getElementById('form_error_message');
    const submitBtn = form.querySelector('button[type="submit"]');

    function checkDuplicates() {
        let erreur = '';
        const couples = new Set();
        for (let i = 0; i < nomsInputs.length; i++) {
            const nom = nomsInputs[i].value.trim();
            const prenom = prenomsInputs[i].value.trim();
            if (!nom || !prenom) continue;
            const key = (nom + '|' + prenom).toLowerCase();
            if (couples.has(key)) {
                erreur = `- Le couple nom/prénom du participant ${i+1} est déjà utilisé.<br>`;
                break;
            }
            couples.add(key);
        }
        errorDiv.innerHTML = erreur;
        submitBtn.disabled = !!erreur;
        return !erreur;
    }

    nomsInputs.forEach((input, idx) => {
        input.addEventListener('input', checkDuplicates);
        prenomsInputs[idx].addEventListener('input', checkDuplicates);
    });

    form.addEventListener('submit', function(e) {
        if (!checkDuplicates()) {
            e.preventDefault();
        }
    });

    // Initial check au chargement
    checkDuplicates();
});