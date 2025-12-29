import {apiGet, apiPost} from "../components/apiClient.js";
import {createBleacherGrid, applySeatStates} from "../components/bleacherGrid.js";
import {showFlashMessage} from "../components/ui.js";

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationPlacesForm');
    if (!form) return;
    const zonesList = document.querySelector('[data-component="zones-list"]');
    const bleacher = document.querySelector('[data-component="bleacher"]');
    if (!zonesList || !bleacher) {
        return;
    }

    const submitButtons = document.querySelectorAll('[data-role="submit-reservation"]');

    const submitButton = document.getElementById('submitButton');

    const zoneNameEl = bleacher.querySelector('[data-bleacher-zone-name]');
    const backBtn = bleacher.querySelector('[data-action="back-zones"]');
    const refreshBtn = bleacher.querySelector('[data-action="refresh-bleacher"]');

    // Nouveaux boutons navigation
    const prevBtn = bleacher.querySelector('[data-action="prev-zone"]');
    const nextBtn = bleacher.querySelector('[data-action="next-zone"]');

    const container = zonesList.closest('.container-fluid');
    const eventSessionId = container?.dataset.eventSessionId;
    if (!eventSessionId || !container) {
        console.error("L'ID de la session de l'événement (eventSessionId) ou le conteneur principal est manquant.");
        return;
    }

    let currentPiscineId = null;
    let currentZoneId = null;
    let loading = false;
    let allParticipantsSeated = false; // Nouvel état pour savoir si toutes les places sont prises
    let participants = []; // Pour stocker les détails des participants

    /**
     * Met à jour l'état du bouton "Valider et continuer".
     */
    function updateContinueButtonState() {
        submitButtons.forEach(btn => {
            btn.disabled = !allParticipantsSeated;
        });
    }

    /**
     * Affiche la liste des participants et leur siège attribué.
     */
    function renderParticipantsList(participantsData) {
        const container = document.getElementById('participants-list');
        if (!container) return;

        // Mettre à jour la variable globale des participants
        participants = participantsData;

        // Déterminer si tous les participants ont une place et mettre à jour l'état global
        const unseatedCount = participants.filter(p => !p.placeNumber).length;
        allParticipantsSeated = (unseatedCount === 0 && participants.length > 0);
        updateContinueButtonState();

        if (participants.length === 0) {
            container.innerHTML = '<li class="list-group-item text-muted">Aucun participant avec place assise.</li>';
            return;
        }

        container.innerHTML = ''; // On vide la liste
        participants.forEach(p => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.dataset.participantId = p.id;

            const seatInfo = p.fullPlaceName
                ? `<span class="badge bg-primary">${p.fullPlaceName}</span>`
                : `<span class="badge bg-secondary">Place non choisie</span>`;

            li.innerHTML = `
                 <span>${p.firstname} ${p.name}</span>
                 ${seatInfo}
             `;
            container.appendChild(li);
        });
    }


    /**
     * Gère le clic sur un siège disponible.
     * @param {object} seat - L'objet représentant le siège cliqué.
     * @param {HTMLButtonElement} btn - Le bouton du siège.
     */
    async function handleSeatClick(seat, btn) {
        // Le statut est 'available' pour une place vide, ou 'in_cart_session' pour une place sélectionnée.
        const currentStatus = btn.dataset.status || 'available';

        // Si on essaie de sélectionner une nouvelle place alors que tout le monde est déjà placé, on bloque.
        if (currentStatus === 'available' && allParticipantsSeated) {
            showFlashMessage('info', 'Tous les participants ont déjà une place. Vous ne pouvez pas en sélectionner plus.');
            // Retour en haut de la page
            window.scrollTo(0, 0);
            return;
        }

        let urlPath;
        if (currentStatus === 'available') {
            urlPath = `/reservation/etape5AddSeat/${seat.seatId}`;
        } else {
            urlPath = `/reservation/etape5RemoveSeat/${seat.seatId}`;
        }

        if (loading) return;
        loading = true;
        btn.disabled = true; // Désactive le bouton pendant l'appel

        try {
            // Appel API pour ajouter/retirer la place
            const response = await apiPost(urlPath, {});

            if (response.success && response.seatStates && response.participants && typeof response.allParticipantsSeated === 'boolean') {
                // Le backend a renvoyé le nouvel état, on met à jour la vue sans refaire d'appel
                const gridContainer = bleacher.querySelector('[data-bleacher-seats]');
                applySeatStates(gridContainer, response.seatStates);
                // On met à jour la liste des participants et l'état du bouton "Valider"
                renderParticipantsList(response.participants);
            } else {
                // En cas d'échec, on recharge toute la zone pour être sûr de l'état
                console.warn("L'ajout/retrait a échoué ou n'a pas renvoyé d'état, rechargement complet.");
                await loadZone(currentPiscineId, currentZoneId);
            }
        } catch (err) {
            console.error("Erreur lors de l'ajout/retrait de la place:", err);
            // On recharge pour être sûr
            await loadZone(currentPiscineId, currentZoneId);
        } finally {
            loading = false;
            // L'état du bouton sera géré par applySeatStates
        }
    }

    /**
     * Met à jour la vue des gradins avec les données fournies.
     * @param {object} structurePlan - Le plan de structure de la zone.
     * @param {object} seatStates - L'état des sièges.
     */
    function updateBleacherView(structurePlan, seatStates) {
        const z = structurePlan.zone;
        bleacher.dataset.zoneId = z.id ?? currentZoneId;
        zoneNameEl.textContent = z.zoneName ?? 'Zone';

        const gridContainer = bleacher.querySelector('[data-bleacher-seats]');
        gridInstance = createBleacherGrid(gridContainer, structurePlan, {
            mode: 'reservation',
            onSeatClick: handleSeatClick
        });
        applySeatStates(gridContainer, seatStates);
    }

    function showZones() {
        bleacher.classList.add('d-none');
        zonesList.classList.remove('d-none');
    }
    function showBleacher() {
        zonesList.classList.add('d-none');
        bleacher.classList.remove('d-none');
    }

    let gridInstance = null;
    async function loadZone(piscineId, zoneId, preloadedData = null) {
        if (loading) {
            return;
        }
        loading = true;
        refreshBtn?.classList.add('disabled');

        try {
            let structurePlan, seatStates;

            if (preloadedData) {
                structurePlan = preloadedData.structurePlan;
                seatStates = preloadedData.seatStates;
            } else {
                // Exécute les deux appels en parallèle pour plus de rapidité
                const [structureResponse, stateResponse] = await Promise.all([
                    apiGet(`/piscine/gradins/${piscineId}/${zoneId}`),
                    apiGet(`/reservation/seat-states/${eventSessionId}`).catch(err => {
                        console.warn("Impossible de charger l'état des sièges, affichage du plan vierge.", err);
                        return { success: false }; // Retourne un objet d'échec pour ne pas bloquer Promise.all
                    })
                ]);

                if (!structureResponse.success || !structureResponse.plan) {
                    console.error('Plan de structure invalide', structureResponse);
                    return;
                }
                structurePlan = structureResponse.plan;
                seatStates = (stateResponse.success && stateResponse.seatStates) ? stateResponse.seatStates : {};
            }

            updateBleacherView(structurePlan, seatStates);

            // Mettre à jour état courant et boutons de navigation
            currentPiscineId = piscineId;
            currentZoneId = zoneId;
            updateNavButtons();

            // Retourne le conteneur de la grille pour référence future
            return bleacher.querySelector('[data-bleacher-seats]');

        } catch (err) {
            console.error('Erreur API:', err);
        } finally {
            loading = false;
            refreshBtn?.classList.remove('disabled');
        }
        return null;
    }

    // Récupère la liste ordonnée des zones DOM pour une piscine donnée
    function getZonesForPiscine(piscineId) {
        const nodes = Array.from(zonesList.querySelectorAll('[data-zone-id]'));
        return nodes
            .filter(n => Number(n.dataset.piscineId) === Number(piscineId))
            .map(n => Number(n.dataset.zoneId));
    }

    function updateNavButtons() {
        if (!prevBtn || !nextBtn) return;
        if (!currentPiscineId || !currentZoneId) {
            prevBtn.disabled = nextBtn.disabled = true;
            return;
        }
        const zoneIds = getZonesForPiscine(currentPiscineId);
        const idx = zoneIds.indexOf(Number(currentZoneId));
        prevBtn.disabled = !(idx > 0);
        nextBtn.disabled = !(idx >= 0 && idx < zoneIds.length - 1);
    }

    // Positionne le scroller réellement scrollable après rebuild (double rAF)
    function setBleacherScrollerEdge(scroller, position = 'start') {
        if (!scroller) {
            console.warn('setBleacherScrollerEdge: scroller introuvable');
            return;
        }

        const min = 0;
        const max = Math.max(0, scroller.scrollWidth - scroller.clientWidth);

        // Positionner le scroller après rebuild (double rAF + setTimeout pour sécurité)
        const setScroll = () => {
            if (position === 'start') {
                scroller.scrollLeft = min;
            } else if (position === 'end') {
                scroller.scrollLeft = max;
            }
        };

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                setTimeout(setScroll, 10);
            });
        });
    }

    async function goToPrevZone() {
        const zoneIds = getZonesForPiscine(currentPiscineId);
        const idx = zoneIds.indexOf(Number(currentZoneId));
        if (idx > 0) {
            const newZoneId = zoneIds[idx - 1];
            const scroller = await loadZone(currentPiscineId, newZoneId);
            showBleacher();
            // positionner à la fin après rendu
            setBleacherScrollerEdge(scroller, 'end');
        }
    }
    async function goToNextZone() {
        const zoneIds = getZonesForPiscine(currentPiscineId);
        const idx = zoneIds.indexOf(Number(currentZoneId));
        if (idx >= 0 && idx < zoneIds.length - 1) {
            const newZoneId = zoneIds[idx + 1];
            const scroller = await loadZone(currentPiscineId, newZoneId);
            showBleacher();
            // positionner au début après rendu
            setBleacherScrollerEdge(scroller, 'start');
        }
    }

    // Click zones -> load
    zonesList.addEventListener('click', async (e) => {
        const piscineEl = e.target.closest('[data-piscine-id]');
        const zoneEl = e.target.closest('[data-zone-id]');
        if (!piscineEl || !zoneEl) return;
        if (zoneEl.getAttribute('aria-disabled') === 'true') {
            e.preventDefault();
            return;
        }
        const piscineId = Number(piscineEl.dataset.piscineId);
        const zoneId = Number(zoneEl.dataset.zoneId);
        if (!Number.isInteger(zoneId) || zoneId <= 0) return;
        if (!Number.isInteger(piscineId) || piscineId <= 0) return;

        currentPiscineId = piscineId;
        currentZoneId = zoneId;

        await loadZone(piscineId, zoneId);
        showBleacher();
    });

    // Afficher la liste des participants au chargement de la page
    const initialParticipantsData = JSON.parse(document.getElementById('reservation-details-data')?.textContent || '[]');
    renderParticipantsList(initialParticipantsData);
    // On met à jour le bouton une première fois au cas où toutes les places seraient déjà choisies au chargement.
    updateContinueButtonState();

    backBtn.addEventListener('click', (e) => {
        e.preventDefault();
        showZones();
    });

    refreshBtn?.addEventListener('click', async (e) => {
        e.preventDefault();
        if (currentPiscineId && currentZoneId) {
            await loadZone(currentPiscineId, currentZoneId);
        }
    });

    // Attacher actions prev/next boutons
    prevBtn?.addEventListener('click', async (e) => {
        e.preventDefault();
        await goToPrevZone();
    });
    nextBtn?.addEventListener('click', async (e) => {
        e.preventDefault();
        await goToNextZone();
    });

    // --- Comportement mobile : swipe à l'extrémité pour changer de zone ---
    (function attachEdgeSwipe() {
        if (!bleacher) return;

        let startX = 0;
        let startY = 0;
        let triggered = false;
        let edgeStart = false;
        let activeScroller = null;

        const THRESHOLD = 80;      // distance horizontale minimale (px)
        const EDGE_TOLERANCE = 24; // tolérance sur le scrollLeft pour considérer qu'on est "à l'extrémité"
        const EDGE_ZONE = 92;      // tolérance en px depuis le bord du scroller

        // Résout le scroller (ici l'élément [data-bleacher-seats])
        function resolveScroller() {
            return bleacher.querySelector('[data-bleacher-seats]');
        }

        // Attache au conteneur bleacher pour rester valide même si le contenu change
        bleacher.addEventListener('touchstart', (ev) => {
            const t = ev.touches && ev.touches[0];
            if (!t) return;
            startX = t.clientX;
            startY = t.clientY;
            triggered = false;

            activeScroller = resolveScroller();
            if (!activeScroller) {
                edgeStart = false;
                return;
            }
            const rect = activeScroller.getBoundingClientRect();
            // Détecte démarrage du geste près du bord du scroller (gauche ou droite)
            edgeStart = (startX <= rect.left + EDGE_ZONE) || (startX >= rect.right - EDGE_ZONE);
        }, {passive: true});

        bleacher.addEventListener('touchmove', (ev) => {
            if (triggered) return;
            const t = ev.touches && ev.touches[0];
            if (!t || !activeScroller) return;
            const dx = t.clientX - startX;
            const dy = t.clientY - startY;

            // Ignorer si c'est majoritairement un scroll vertical
            if (Math.abs(dy) > Math.abs(dx)) return;

            const scroller = activeScroller;
            const maxScrollLeft = scroller.scrollWidth - scroller.clientWidth;
            const atLeftEdge = scroller.scrollLeft <= EDGE_TOLERANCE;
            const atRightEdge = scroller.scrollLeft >= Math.max(0, maxScrollLeft - EDGE_TOLERANCE);

            if (atLeftEdge && edgeStart && dx > THRESHOLD) {
                triggered = true;
                goToPrevZone();
            } else if (atRightEdge && edgeStart && dx < -THRESHOLD) {
                triggered = true;
                goToNextZone();
            }
        }, {passive: true});

        bleacher.addEventListener('touchend', () => {
            triggered = false;
            edgeStart = false;
            activeScroller = null;
        }, {passive: true});

        window.addEventListener('resize', updateNavButtons);
    })();

    // Initial update
    updateNavButtons();

    //on écoute le bouton Valider
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        submitButtons.forEach(btn => btn.disabled = true);

        apiPost('/reservation/valid/5', {})
            .then((data) => {
                if (data.success) {
                    window.location.href = '/reservation/etape6Display';
                } else {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showFlashMessage('danger', data.error || 'Erreur lors de la validation de l’étape 5.');
                    submitButtons.forEach(btn => btn.disabled = false);
                }
            })
            .catch((err) => {
                showFlashMessage('danger', err.userMessage || err.message);
                submitButtons.forEach(btn => btn.disabled = false);
            });
    });


});
