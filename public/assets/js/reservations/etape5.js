import {apiGet} from "../components/apiClient.js";
import {createBleacherGrid, applySeatStates} from "../components/bleacherGrid.js";

document.addEventListener('DOMContentLoaded', () => {
    const zonesList = document.querySelector('[data-component="zones-list"]');
    const bleacher = document.querySelector('[data-component="bleacher"]');
    if (!zonesList || !bleacher) {
        return;
    }

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

    /**
     * Gère le clic sur un siège disponible.
     * Pour l'instant, affiche les informations en console.
     * C'est ici que sera implémentée la logique d'ajout/retrait de la sélection.
     * @param {object} seat - L'objet représentant le siège cliqué.
     * @param {HTMLButtonElement} btn - Le bouton du siège.
     */
    function handleSeatClick(seat, btn) {
        // Le statut est 'available' pour une place vide, ou 'in_cart_session' pour une place sélectionnée.
        const currentStatus = btn.dataset.status || 'available';

        console.log(`Clic sur une place avec le statut : ${currentStatus}. Prêt pour la sélection/désélection !`, {
            id: seat.seatId,
            code: seat.code,
            status: currentStatus
        });
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
    async function loadZone(piscineId, zoneId) {
        if (loading) return;
        loading = true;
        refreshBtn?.classList.add('disabled');

        try {
            // --- Appel 1: Récupérer la structure du plan ---
            const structureResponse = await apiGet(`/piscine/gradins/${piscineId}/${zoneId}`);
            if (!structureResponse.success || !structureResponse.plan || !structureResponse.plan.zone) {
                console.warn('Plan de structure invalide', structureResponse);
                return;
            }

            // --- Appel 2: Récupérer l'état des sièges ---
            let seatStates = {};
            try {
                const stateResponse = await apiGet(`/reservation/seat-states/${eventSessionId}`);
                if (stateResponse.success && stateResponse.seatStates) {
                    seatStates = stateResponse.seatStates;
                }
            } catch (stateErr) {
                console.warn("Impossible de charger l'état des sièges, affichage du plan vierge.", stateErr);
            }

            const z = structureResponse.plan.zone;
            bleacher.dataset.zoneId = z.id ?? zoneId;
            zoneNameEl.textContent = z.zoneName ?? 'Zone';

            if (structureResponse.plan) {
                const gridContainer = bleacher.querySelector('[data-bleacher-seats]');
                // On crée la grille "vierge"
                gridInstance = createBleacherGrid(gridContainer, structureResponse.plan, {
                    mode: 'reservation',
                    onSeatClick: handleSeatClick
                });
                // On applique les états dynamiques sur la grille qui vient d'être créée
                applySeatStates(gridContainer, seatStates);
            }

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
});
