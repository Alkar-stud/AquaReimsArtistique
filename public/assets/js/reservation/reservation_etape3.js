document.addEventListener('DOMContentLoaded', function () {
    const container = document;
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

    // Tous les inputs "classiques" seulement (le spécial n'a plus la classe .place-input).
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

    function hasSpecialSelection() {
        const cb = document.getElementById('specialTarifCheck');
        return !!(cb && cb.checked);
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
        if (limitation === null) return;
        const placesParTarif = parseInt(input.dataset.nbPlace, 10) || 1;
        const reste = Math.max(0, limitation - dejaReservees - totalDemanded(input));
        const maxPossible = Math.floor(reste / placesParTarif);
        const current = parseInt(input.value, 10) || 0;

        input.setAttribute('min', '0');
        input.setAttribute('step', '1');
        input.setAttribute('max', String(Math.max(0, maxPossible)));

        if (current > maxPossible) {
            input.value = String(Math.max(0, maxPossible));
            showAlert('Votre sélection a été ajustée pour respecter la limite.');
        } else {
            clearAlert();
        }
        const remaining = Math.max(0, limitation - dejaReservees - totalDemanded());
        refreshRemainingUi(remaining);
    }

    // Active/désactive le bouton: au moins une place classique OU le code spécial coché
    const submitButtonBtn = document.getElementById('submitButton');
    function updateSubmitState() {
        if (!submitButtonBtn) return;
        submitButtonBtn.disabled = !(totalDemanded() > 0 || hasSpecialSelection());
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
        updateSubmitState();
    }, true);

    container.addEventListener('input', (e) => {
        const input = e.target.closest('.place-input');
        if (!input) return;
        const v = input.value.trim();
        if (v !== '' && !/^\d+$/.test(v)) {
            input.value = String(parseInt(v.replace(/[^\d]/g, ''), 10) || 0);
        }
        clampInput(input);
        updateSubmitState();
    });

    // Initialisation UI au chargement
    if (limitation !== null) {
        getInputs().forEach(clampInput);
        const remaining = Math.max(0, limitation - dejaReservees - totalDemanded());
        refreshRemainingUi(remaining);
    } else {
        clearAlert();
    }
    updateSubmitState(); // désactivé par défaut si rien sélectionné

    // --- Gestion du code spécial ---
    const validateCodeBtn = document.getElementById('validateCodeBtn');
    const specialCodeInput = document.getElementById('specialCode');
    const specialCodeFeedback = document.getElementById('specialCodeFeedback');
    const specialTarifContainer = document.getElementById('specialTarifContainer');
    const eventIdInput = document.getElementById('event_id');

    function renderSpecialTarifBlock(t) {
        // Injection sans classe .place-input pour ne pas compter dans les totaux/limites
        specialTarifContainer.innerHTML = `
          <div class="alert alert-success mb-2">
            Tarif spécial reconnu : <strong>${(t.name || 'Tarif spécial')}</strong>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="specialTarifCheck" name="specialTarif[${t.id}]" checked>
            <label class="form-check-label" for="specialTarifCheck">
              Utiliser ce tarif spécial${t.seat_count ? ` (${t.seat_count} place${t.seat_count > 1 ? 's' : ''} incluse${t.seat_count > 1 ? 's' : ''})` : ''}
              ${typeof t.price !== 'undefined' ? ` - ${euroFromCents(t.price)}` : ''}
            </label>
            <input type="hidden"
                   id="tarif_${t.id}"
                   name="tarifs[${t.id}]"
                   value="1">
          </div>
          ${t.description ? `<div class="text-muted small mb-1">${String(t.description).replace(/\n/g, '<br>')}</div>` : ''}
        `;

        // Toggle l'input caché selon la checkbox (pour un éventuel submit natif)
        const cb = document.getElementById('specialTarifCheck');
        const hidden = document.getElementById(`tarif_${t.id}`);
        if (cb && hidden) hidden.disabled = !cb.checked;

        // Recalcule l'état du bouton (le spécial ne compte pas dans les totaux)
        updateSubmitState();
    }

    // Écoute le (dé)cochage du tarif spécial
    container.addEventListener('change', (e) => {
        if (e.target && e.target.id === 'specialTarifCheck') {
            const tarifId = window.specialTarifSession?.id;
            if (!tarifId) return;
            apiPost('/reservation/remove-special-tarif', { tarif_id: tarifId })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.error || "Erreur lors de la suppression du tarif spécial.");
                    }
                })
                .catch((err) => {
                    showFlash('danger', err.userMessage || err.message);
                    window.location.reload();
                });

            const hidden = document.querySelector('#specialTarifContainer input[type="hidden"][id^="tarif_"]');
            if (hidden) hidden.disabled = !e.target.checked;
            // Les "restantes" ne changent pas, car le spécial n'est pas compté
            updateSubmitState();
        }
    });

    // Préremplissage si déjà en session
    //Si un tarif spécial est déjà présent
    if (window.specialTarifSession) {
        const t = window.specialTarifSession;
        if (specialCodeInput) specialCodeInput.value = t.code || '';
        if (specialCodeInput) specialCodeInput.disabled = true;
        if (validateCodeBtn) validateCodeBtn.disabled = true;
        if (specialCodeFeedback) {
            specialCodeFeedback.classList.remove('text-danger');
            specialCodeFeedback.classList.add('text-success');
            specialCodeFeedback.textContent = 'Code validé.';
        }
        renderSpecialTarifBlock(t);
    }
    // Validation du code spécial (AJAX)
    if (validateCodeBtn && specialCodeInput && eventIdInput) {
        validateCodeBtn.addEventListener('click', async () => {
            const code = specialCodeInput.value.trim();
            const event_id = parseInt(eventIdInput.value, 10) || 0;
            if (!code || !event_id) {
                if (specialCodeFeedback) {
                    specialCodeFeedback.classList.remove('text-success');
                    specialCodeFeedback.classList.add('text-danger');
                    specialCodeFeedback.textContent = 'Veuillez saisir un code et avoir un événement sélectionné.';
                }
                return;
            }

            validateCodeBtn.disabled = true;
            specialCodeFeedback.textContent = '';

            try {
                const res = await apiPost('/reservation/validate-special-code', { event_id, code });
                if (!res || !res.success) {
                    throw new Error(res?.error || 'Code invalide.');
                }

                // Mémorise côté fenêtre pour la page
                window.specialTarifSession = {
                    id: res.tarif.id,
                    name: res.tarif.name,
                    description: res.tarif.description,
                    seat_count: res.tarif.seat_count,
                    price: res.tarif.price,
                    code
                };

                // Désactive le champ code + message
                specialCodeInput.disabled = true;
                if (specialCodeFeedback) {
                    specialCodeFeedback.classList.remove('text-danger');
                    specialCodeFeedback.classList.add('text-success');
                    specialCodeFeedback.textContent = 'Code validé.';
                }

                renderSpecialTarifBlock(window.specialTarifSession);
            } catch (e) {
                validateCodeBtn.disabled = false;
                if (specialCodeFeedback) {
                    specialCodeFeedback.classList.remove('text-success');
                    specialCodeFeedback.classList.add('text-danger');
                    specialCodeFeedback.textContent = e.message || 'Erreur lors de la validation du code.';
                }
            }
        });
    }

    // Construit le tableau à envoyer (classiques + spéciaux cochés)
    function buildReservationPayload() {
        const eventIdInput = document.getElementById('event_id');
        const event_id = parseInt(eventIdInput?.value, 10) || 0;
        const tarifs = {};

        // 1) Tarifs "classiques" (tous les inputs .place-input)
        getInputs().forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            if (qty <= 0) return;

            // Récupère l'id de tarif (priorité au name, fallback sur l'id DOM)
            const idFromName = parseTarifIdFromName(input.name);
            const idFromDom = (input.id && input.id.startsWith('tarif_')) ? input.id.slice('tarif_'.length) : null;
            const tarifId = idFromName || idFromDom;
            if (!tarifId) return;

            tarifs[tarifId] = (tarifs[tarifId] || 0) + qty;
        });

        // Tarifs spéciaux (ne comptent pas dans les totaux, mais doivent partir au backend)
        // Tarif spécial (clé = id, valeur = code)
        const specialCb = document.getElementById('specialTarifCheck');
        let special = null;
        if (specialCb && specialCb.checked && window.specialTarifSession) {
            const s = window.specialTarifSession;
            const sid = String(s.id);

            // On conserve aussi la quantité côté 'tarifs'
            tarifs[sid] = (tarifs[sid] || 0) + 1;

            // Clé calculée: { [sid]: codeOuNull }
            special = { [sid]: (s.code || null) };
        }

        return { event_id, tarifs, special };
    }

        const form = document.getElementById('reservationPlacesForm');
        const submitButton = document.getElementById('submitButton');

        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            submitButton && (submitButton.disabled = true);

            try {
                const payload = buildReservationPayload();

                await submitEtape3(payload);
            } catch (err) {
                showFlash('danger', err.userMessage || err.message || 'Erreur lors de la validation de l’étape 3.');
                submitButton && (submitButton.disabled = false);
            }
        });

    function submitEtape3(payload) {
        apiPost('/reservation/valid/3', payload)
            .then((data) => {
                if (data.success) {
                    window.location.href = '/reservation/etape4Display';
                } else {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showFlash('danger', data.error || 'Erreur');
                }
            })
            .catch((err) => {
                showFlash('danger', err.userMessage || err.message);
                submitButton && (submitButton.disabled = false);
            });
    }


});
