import {apiGet} from "../components/apiClient.js";
import {createBleacherGrid} from "../components/bleacherGrid.js";

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

    let currentPiscineId = null;
    let currentZoneId = null;
    let loading = false;

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
            const response = await apiGet(`/piscine/gradins/${piscineId}/${zoneId}`, {});
            if (!response.success || !response.plan || !response.plan.zone) {
                console.warn('Plan invalide', response);
                return;
            }
            const z = response.plan.zone;

            bleacher.dataset.zoneId = z.id ?? zoneId;
            zoneNameEl.textContent = z.zoneName ?? z.getZoneName ?? 'Zone';

            if (response.plan) {
                const gridContainer = bleacher.querySelector('[data-bleacher-seats]');
                gridInstance = createBleacherGrid(gridContainer, response.plan, {
                    mode: 'reservation',
                    onSeatClick: (seat, btn) => {
                        console.log('Seat ID:', seat.seatId);
                    }
                });
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
        console.log('ok position : ', position);
        if (!scroller) {
            console.warn('setBleacherScrollerEdge: scroller introuvable');
            return;
        }

        const min = 0;
        const max = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
        console.log('Bleacher scroll min:', min, 'max:', max);

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
