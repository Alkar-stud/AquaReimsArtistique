document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('tarifsContainer') || document;
    const alertDiv = document.getElementById('reservationStep3Alert');
    const placesRestantesSpan = document.getElementById('placesRestantes');

    const limitation =
        (typeof window.limitationPerSwimmer === 'number' || typeof window.limitationPerSwimmer === 'string')
            ? (String(window.limitationPerSwimmer).length ? parseInt(window.limitationPerSwimmer, 10) : null)
            : null;

    const dejaReservees =
        (typeof window.placesDejaReservees === 'number' || typeof window.placesDejaReservees === 'string')
            ? (parseInt(window.placesDejaReservees, 10) || 0)
            : 0;

    const getInputs = () => container.querySelectorAll('.place-input');

    function totalDemanded(except = null) {
        let total = 0;
        getInputs().forEach(input => {
            if (input === except) return;
            const nb = parseInt(input.value, 10) || 0;
            const placesParTarif = parseInt(input.dataset.nbPlace, 10) || 1;
            total += nb * placesParTarif;
        });
        return total;
    }

    function refreshRemainingUi(remaining) {
        if (placesRestantesSpan) {
            placesRestantesSpan.textContent = Math.max(0, remaining);
        }
    }

    function clearAlert() {
        if (alertDiv) alertDiv.innerHTML = '';
    }

    function showAlert(msg) {
        if (!alertDiv) return;
        alertDiv.innerHTML = `<div class="alert alert-danger">${msg}</div>`;
    }

    function clampInput(input) {
        // Si aucune limitation, pas de borne ni d'alerte
        if (limitation === null) return;

        const placesParTarif = parseInt(input.dataset.nbPlace, 10) || 1;

        // Reste disponible (en excluant le champ courant)
        const reste = Math.max(0, limitation - dejaReservees - totalDemanded(input));
        const maxPossible = Math.floor(reste / placesParTarif);

        // Valeur courante
        const current = parseInt(input.value, 10) || 0;

        // Borne et fixe l'attribut max
        input.setAttribute('min', '0');
        input.setAttribute('step', '1');
        input.setAttribute('max', String(Math.max(0, maxPossible)));

        if (current > maxPossible) {
            input.value = String(Math.max(0, maxPossible));
            // Message uniquement si dépassement tenté
            showAlert(`Vous ne pouvez pas réserver plus de ${limitation} place(s) pour cette nageuse sur l'ensemble des séances.`);
        } else {
            clearAlert();
        }

        // Met à jour le compteur "Restantes à réserver"
        const remaining = Math.max(0, limitation - dejaReservees - totalDemanded());
        refreshRemainingUi(remaining);
    }

    // Délégation d’événements pour couvrir les champs dynamiques
    container.addEventListener('focus', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        if (input.value === '0') input.value = '';
    }, true);

    container.addEventListener('blur', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        if (input.value === '') input.value = '0';
        clampInput(input);
    }, true);

    container.addEventListener('input', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        // Nettoyage valeurs non numériques
        const v = input.value.trim();
        if (v !== '' && !/^\d+$/.test(v)) {
            input.value = String(parseInt(v.replace(/[^\d]/g, ''), 10) || 0);
        }
        clampInput(input);
    });

    // Initialisation UI au chargement
    if (limitation !== null) {
        // Fixe l'état initial et borne chaque champ
        getInputs().forEach(clampInput);
        // Valeur initiale "restantes"
        const remaining = Math.max(0, limitation - dejaReservees - totalDemanded());
        refreshRemainingUi(remaining);
    } else {
        clearAlert();
    }


    // Gestion du code spécial (validation côté client + affichage Tarif spécial)
    const validateCodeBtn = document.getElementById('validateCodeBtn');
    const specialCodeInput = document.getElementById('specialCode');
    const specialCodeFeedback = document.getElementById('specialCodeFeedback');
    const specialTarifContainer = document.getElementById('specialTarifContainer');
    const eventIdInput = document.getElementById('event_id');
    if (validateCodeBtn && specialCodeInput && eventIdInput) {
        validateCodeBtn.addEventListener('click', () => {
            const code = specialCodeInput.value.trim();
console.log('specialCodeInput', eventIdInput);
            const event_id = parseInt(eventIdInput.value, 10) || 0;
            if (!code || !event_id) {
                specialCodeFeedback.textContent = 'Veuillez saisir un code et vérifier l\'événement.';
                return;
            }
            apiPost('/reservation/validate-special-code', { event_id, code })
                .then((data) => {
                    if (data.success) {
                        specialCodeFeedback.textContent = '';
                        // Injecter dynamiquement un nouveau champ .place-input si nécessaire...
                        // Pensez à définir data-nb-place sur ce nouvel input pour que la borne fonctionne.
                    } else {
                        specialCodeFeedback.textContent = data.error || 'Code invalide.';
                    }
                })
                .catch((e) => {
                    specialCodeFeedback.textContent = e.userMessage || 'Erreur lors de la validation du code.';
                });
        });
    }



    /* ----------------------------------------------------------------------
     * Le code ci-dessous est encore à vérifier car le back ou la vue n'ont pas encore été implémentés
     * ---------------------------------------------------------------------- */



     // Gestion suppression du Tarif spécial si décoché
     container.addEventListener('click', (e) => {
       const btn = e.target.closest('[data-remove-Tarif]');
       if (!btn) return;
       const row = btn.closest('.special-Tarif-row');
       if (row) row.remove();
       // Recalcule la limitation après suppression
       if (limitation !== null) {
         clearAlert();
         const remaining = Math.max(0, limitation - dejaReservees - totalDemanded());
         refreshRemainingUi(remaining);
       }
     });

     // Soumission du formulaire (étape suivante)
     const form = document.getElementById('reservationPlacesForm');
     if (form) {
       form.addEventListener('submit', function (e) {
         // Exemple: validation locale avant envoi AJAX
         if (limitation !== null) {
           const remaining = limitation - dejaReservees - totalDemanded();
           if (remaining < 0) {
             e.preventDefault();
             showFlash('danger', 'Votre sélection dépasse la limite autorisée.');
             return;
           }
         }
         // Envoyer en AJAX si requis…
       });
     }
});
