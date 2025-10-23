(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    // Utilitaires légers
    function buttonLoading(btn, on) {
        if (!btn) return;
        let spinner = btn._spinner;
        if (on) {
            btn.disabled = true;
            if (!spinner) {
                spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm ms-2 align-middle';
                spinner.setAttribute('role', 'status');
                btn.parentNode && btn.parentNode.appendChild(spinner);
                btn._spinner = spinner;
            }
        } else {
            btn.disabled = false;
            if (spinner && spinner.parentNode) spinner.parentNode.removeChild(spinner);
            btn._spinner = null;
        }
    }

    function euroStr(cents) {
        // Réutilise l’alias global s’il existe, sinon fallback simple
        if (typeof global.euroFromCents === 'function') return global.euroFromCents(cents);
        const v = (parseInt(cents, 10) || 0) / 100;
        return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function renderSpecialTarifBlock(t, containerEl) {
        if (!containerEl || !t) return;
        const seatInfo = t.seat_count ? ` (${t.seat_count} place${t.seat_count > 1 ? 's' : ''} incluse${t.seat_count > 1 ? 's' : ''})` : '';
        const priceInfo = typeof t.price !== 'undefined' ? ` - ${euroStr(t.price)}` : '';
        const desc = t.description ? `<div class="text-muted small mb-1">${String(t.description).replace(/\n/g, '<br>')}</div>` : '';

        containerEl.innerHTML = `
          <div class="alert alert-success mb-2">
            Tarif spécial reconnu : <strong>${(t.name || 'Tarif spécial')}</strong>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="specialTarifCheck" name="specialTarif[${t.id}]" checked>
            <label class="form-check-label" for="specialTarifCheck">
              Utiliser ce tarif spécial${seatInfo}${priceInfo}
            </label>
            <input type="hidden" id="tarif_${t.id}" name="tarifs[${t.id}]" value="1">
          </div>
          ${desc}
        `;

        // Synchronise l’input caché avec la checkbox
        const cb = containerEl.querySelector('#specialTarifCheck');
        const hidden = containerEl.querySelector(`#tarif_${t.id}`);
        if (cb && hidden) hidden.disabled = !cb.checked;
    }

    function buildReservationPayload(container, eventIdInput) {
        const event_id = parseInt((typeof eventIdInput === 'string' ? container.querySelector(eventIdInput) : eventIdInput)?.value, 10) || 0;
        const tarifs = {};

        const parseTarifIdFromName = global.parseTarifIdFromName || function (name) {
            const m = String(name || '').match(/^tarifs\[(\d+)]$/);
            return m ? m[1] : null;
        };

        // Tarifs classiques
        container.querySelectorAll('.place-input').forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            if (qty <= 0) return;

            const idFromName = parseTarifIdFromName(input.name);
            const idFromDom = (input.id && input.id.startsWith('tarif_')) ? input.id.slice('tarif_'.length) : null;
            const tarifId = idFromName || idFromDom;
            if (!tarifId) return;

            tarifs[tarifId] = (tarifs[tarifId] || 0) + qty;
        });

        // Tarif spécial (clé calculée { [id]: codeOuNull })
        const cb = container.querySelector('#specialTarifCheck');
        let special = null;
        if (cb && cb.checked && global.specialTarifSession) {
            const s = global.specialTarifSession;
            const sid = String(s.id);
            tarifs[sid] = (tarifs[sid] || 0) + 1; // conserve aussi la quantité
            special = { [sid]: (s.code || null) };
        }

        return { event_id, tarifs, special };
    }

    function initSpecialCode(opts) {
        const {
            withSeat,                // true pour etape3, false pour etape6
            eventIdInput,
            codeInput,
            validateBtn,
            feedbackEl,
            targetContainer,         // element ou sélecteur où rendre le bloc
            onRendered,              // callback après rendu (ex: maj bouton submit)
            onToggle,                // callback au (dé)cochage (ex: maj bouton submit)
        } = opts || {};

        const getEl = (x) => (typeof x === 'string' ? document.querySelector(x) : x);
        const $eventId = getEl(eventIdInput);
        const $code = getEl(codeInput);
        const $btn = getEl(validateBtn);
        const $fb = getEl(feedbackEl);
        const $target = getEl(targetContainer);

        // Préremplissage si déjà en session
        if (global.specialTarifSession) {
            if ($code) { $code.value = global.specialTarifSession.code || ''; $code.disabled = true; }
            if ($btn) $btn.disabled = true;
            if ($fb) {
                $fb.classList.remove('text-danger'); $fb.classList.add('text-success');
                $fb.textContent = 'Code validé.';
            }
            renderSpecialTarifBlock(global.specialTarifSession, $target);
            if (typeof onRendered === 'function') onRendered(global.specialTarifSession);
        }

        // Validation du code
        if ($btn && $code && $eventId) {
            $btn.addEventListener('click', async () => {
                const code = $code.value.trim();
                const event_id = parseInt($eventId.value, 10) || 0;
                if (!code || !event_id) {
                    if ($fb) {
                        $fb.classList.remove('text-success');
                        $fb.classList.add('text-danger');
                        $fb.textContent = 'Veuillez saisir un code et avoir un événement sélectionné.';
                    }
                    return;
                }
                buttonLoading($btn, true);
                if ($fb) $fb.textContent = '';
                try {
                    const res = await global.apiPost('/reservation/validate-special-code', { event_id, code, with_seat: !!withSeat });
                    if (!res || !res.success) throw new Error(res?.error || 'Code invalide.');

                    global.specialTarifSession = {
                        id: res.tarif.id,
                        name: res.tarif.name,
                        description: res.tarif.description,
                        seat_count: res.tarif.seat_count,
                        price: res.tarif.price,
                        code
                    };

                    if ($code) $code.disabled = true;
                    if ($fb) {
                        $fb.classList.remove('text-danger');
                        $fb.classList.add('text-success');
                        $fb.textContent = 'Code validé.';
                    }
                    renderSpecialTarifBlock(global.specialTarifSession, $target);
                    if (typeof onRendered === 'function') onRendered(global.specialTarifSession);
                } catch (e) {
                    if ($fb) {
                        $fb.classList.remove('text-success');
                        $fb.classList.add('text-danger');
                        $fb.textContent = e.message || 'Erreur lors de la validation du code.';
                    }
                } finally {
                    buttonLoading($btn, false);
                }
            });
        }

        // Suppression côté backend lors du (dé)cochage
        document.addEventListener('change', (e) => {
            if (e.target && e.target.id === 'specialTarifCheck') {
                const tarifId = global.specialTarifSession?.id;
                if (!tarifId) return;

                global.apiPost('/reservation/remove-special-tarif', { tarif_id: tarifId })
                    .then((data) => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.error || 'Erreur lors de la suppression du tarif spécial.');
                        }
                    })
                    .catch((err) => {
                        global.showFlash && global.showFlash('danger', err.userMessage || err.message);
                        window.location.reload();
                    });

                if (typeof onToggle === 'function') onToggle(!!e.target.checked);
            }
        });
    }

    // Enregistrement module
    const api = {
        buttonLoading,
        renderSpecialTarifBlock,
        buildReservationPayload,
        initSpecialCode
    };
    if (typeof App.register === 'function') App.register('ReservationCommon', api);
    global.ReservationCommon = api;

})(window);