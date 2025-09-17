document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.place-input');
    const alertDiv = document.getElementById('reservationStep3Alert');
    const placesRestantesSpan = document.getElementById('placesRestantes');
    const limitation = parseInt(window.limitationPerSwimmer) || null;
    const dejaReservees = parseInt(window.placesDejaReservees) || 0;

    function getTotalPlacesDemanded() {
        let total = 0;
        inputs.forEach(input => {
            const nb = parseInt(input.value) || 0;
            const placesParTarif = parseInt(input.dataset.nbPlace) || 1;
            total += nb * placesParTarif;
        });
        return total;
    }

    function checkLimite() {
        alertDiv.innerHTML = '';
        if (limitation === null) return;
        const totalDemanded = getTotalPlacesDemanded();
        const totalWithOld = totalDemanded + dejaReservees;
        if (placesRestantesSpan) {
            placesRestantesSpan.textContent = Math.max(0, limitation - dejaReservees - totalDemanded);
        }
        if (totalWithOld > limitation) {
            alertDiv.innerHTML = `<div class="alert alert-danger">
                Vous ne pouvez pas réserver plus de ${limitation} place(s) pour cette nageuse sur l'ensemble des séances.<br>
                Merci d'ajuster votre sélection.
            </div>`;
            // Corrige la dernière saisie à la valeur max possible
            let reste = limitation - dejaReservees;
            inputs.forEach(input => {
                const nb = parseInt(input.value) || 0;
                const placesParTarif = parseInt(input.dataset.nbPlace) || 1;
                const maxPossible = Math.floor(reste / placesParTarif);
                if (nb * placesParTarif > reste) {
                    input.value = Math.max(0, maxPossible);
                }
                reste -= (parseInt(input.value) || 0) * placesParTarif;
            });
        }
    }

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


    const codeInput = document.getElementById('specialCode');
    const validateBtn = document.getElementById('validateCodeBtn');
    const feedback = document.getElementById('specialCodeFeedback');
    const specialTarifContainer = document.getElementById('specialTarifContainer');
    // Ajout pour préremplir si un tarif spécial est déjà en session
    if (window.specialTarifSession) {
        const t = window.specialTarifSession;
        codeInput.value = t.code;
        codeInput.disabled = true;
        validateBtn.disabled = true;
        specialTarifContainer.innerHTML = `
            <div class="alert alert-success mb-2">
                Tarif spécial reconnu : <strong>${t.libelle}</strong>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="specialTarifCheck" name="specialTarif[${t.id}]" checked>
                <label class="form-check-label" for="specialTarifCheck">
                     Utiliser ce tarif (${t.nb_place} place${t.nb_place > 1 ? 's' : ''} inclus${t.nb_place > 1 ? 'es' : 'e'}) - ${Number(t.price / 100).toFixed(2).replace('.', ',')} €
                 </label>
            </div>
            ${t.description ? `<div class="text-muted small mb-1">${t.description.replace(/\n/g, '<br>')}</div>` : ''}
        `;
    }

    //Pour valider et afficher les tarifs avec code
    validateBtn.addEventListener('click', function () {
        feedback.textContent = '';
        specialTarifContainer.innerHTML = '';
        const code = codeInput.value.trim();
        if (!code) {
            feedback.textContent = "Veuillez saisir un code.";
            return;
        }
        fetch('/reservation/validate-special-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code: code,
                event_id: window.reservation?.event_id,
                csrf_token: window.csrf_token
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.tarif) {
                    const t = data.tarif;
                    window.specialTarifSession = {
                        id: t.id,
                        libelle: t.libelle,
                        description: t.description,
                        nb_place: t.nb_place,
                        price: t.price,
                        code: code
                    };
                    specialTarifContainer.innerHTML = `
                <div class="alert alert-success mb-2">
                    Tarif spécial reconnu : <strong>${t.libelle}</strong>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="specialTarifCheck" name="specialTarif[${t.id}]" checked>
                    <label class="form-check-label" for="specialTarifCheck">
                         Utiliser ce tarif (${t.nb_place} place${t.nb_place > 1 ? 's' : ''} inclus${t.nb_place > 1 ? 'es' : 'e'}) - ${Number(t.price / 100).toFixed(2).replace('.', ',')} €
                     </label>
                </div>
                ${t.description ? `<div class="text-muted small mb-1">${t.description.replace(/\n/g, '<br>')}</div>` : ''}
            `;
                    codeInput.disabled = true;
                    validateBtn.disabled = true;
                } else {
                    feedback.textContent = data.error || "Code invalide ou non reconnu.";
                }
            });
    });

// Gestion suppression du tarif spécial si décoché
    specialTarifContainer.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'specialTarifCheck' && !e.target.checked) {
            const tarifId = window.specialTarifSession?.id;
            if (!tarifId) return;
            fetch('/reservation/remove-special-tarif', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tarif_id: tarifId,
                    csrf_token: window.csrf_token
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.error || "Erreur lors de la suppression du tarif spécial.");
                    }
                });
        }
    });

    const form = document.getElementById('reservationPlacesForm');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        alertDiv.innerHTML = '';

        // Récupérer les quantités par tarif classique
        const tarifs = [];
        document.querySelectorAll('.place-input').forEach(input => {
            const id = parseInt(input.id.replace('tarif_', ''));
            const qty = parseInt(input.value) || 0;
            if (qty > 0) {
                tarifs.push({ id, qty });
            }
        });

        // Ajouter le tarif spécial au tableau des tarifs s'il est sélectionné
        const specialTarifCheckbox = document.getElementById('specialTarifCheck');
        if (window.specialTarifSession && specialTarifCheckbox && specialTarifCheckbox.checked) {
            tarifs.push({
                id: window.specialTarifSession.id,
                qty: 1, // On suppose une quantité de 1 pour un code
                code: window.specialTarifSession.code
            });
        }

        if (tarifs.length === 0) {
            alertDiv.innerHTML = `<div class="alert alert-danger">Veuillez sélectionner au moins une place assise.</div>`;
            return;
        }

        fetch('/reservation/etape3', {
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
                    window.location.href = '/reservation/etape4Display';
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur'}</div>`;
                }
            });
    });

});