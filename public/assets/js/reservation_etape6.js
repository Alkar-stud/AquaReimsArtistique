document.getElementById('tarifsSansPlacesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const tarifs = [];
    form.querySelectorAll('input[type="number"]').forEach(input => {
        const id = parseInt(input.id.replace('tarif_', ''));
        const qty = parseInt(input.value) || 0;
        if (qty > 0) {
            tarifs.push({ id, qty });
        }
    });
    fetch('/reservation/etape6', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tarifs,
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
                window.location.href = '/reservation/confirmation';
            } else {
                document.getElementById('tarifsSansPlacesAlert').innerHTML =
                    `<div class="alert alert-danger">${data.error || 'Erreur'}</div>`;
            }
        });
});

const inputs = document.querySelectorAll('.complement-input');

inputs.forEach(input => {
    // Quand on clique dans le champ, s'il vaut 0, on le vide.
    input.addEventListener('focus', function() {
        if (this.value === '0') {
            this.value = '';
        }
    });

    // Quand on quitte le champ, s'il est vide, on remet 0.
    input.addEventListener('blur', function() {
        if (this.value === '') {
            this.value = '0';
        }
    });

    input.addEventListener('blur', checkLimite);
    input.addEventListener('input', checkLimite);
});