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
                gridInstance = createBleacherGrid(gridContainer, response.plan, { mode: 'reservation' });
            }

            // Mettre à jour état courant et boutons de navigation
            currentPiscineId = piscineId;
            currentZoneId = zoneId;
            updateNavButtons();

        } catch (err) {
            console.error('Erreur API:', err);
        } finally {
            loading = false;
            refreshBtn?.classList.remove('disabled');
        }
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

    async function goToPrevZone() {
        const zoneIds = getZonesForPiscine(currentPiscineId);
        const idx = zoneIds.indexOf(Number(currentZoneId));
        if (idx > 0) {
            const newZoneId = zoneIds[idx - 1];
            await loadZone(currentPiscineId, newZoneId);
            showBleacher();
        }
    }
    async function goToNextZone() {
        const zoneIds = getZonesForPiscine(currentPiscineId);
        const idx = zoneIds.indexOf(Number(currentZoneId));
        if (idx >= 0 && idx < zoneIds.length - 1) {
            const newZoneId = zoneIds[idx + 1];
            await loadZone(currentPiscineId, newZoneId);
            showBleacher();
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
        const scroller = bleacher.querySelector('[data-bleacher-seats]');
        if (!scroller) return;

        let startX = 0;
        let startY = 0;
        let moved = false;
        let triggered = false; // empêche retrigger avant touchend
        const THRESHOLD = 40; // px nécessaires pour déclencher change zone
        const EDGE_TOLERANCE = 4; // tolérance bord

        scroller.addEventListener('touchstart', (ev) => {
            if (!ev.touches || !ev.touches[0]) return;
            const t = ev.touches[0];
            startX = t.clientX;
            startY = t.clientY;
            moved = false;
            triggered = false;
        }, {passive: true});

        scroller.addEventListener('touchmove', (ev) => {
            if (triggered) return;
            const t = ev.touches && ev.touches[0];
            if (!t) return;
            const dx = t.clientX - startX;
            const dy = t.clientY - startY;
            // ignorer scroll vertical important
            if (Math.abs(dy) > Math.abs(dx)) return;
            moved = true;

            const maxScrollLeft = scroller.scrollWidth - scroller.clientWidth;
            const atLeftEdge = scroller.scrollLeft <= EDGE_TOLERANCE;
            const atRightEdge = scroller.scrollLeft >= Math.max(0, maxScrollLeft - EDGE_TOLERANCE);

            // swipe vers la droite (dx > 0) quand on est déjà à gauche -> prev
            if (atLeftEdge && dx > THRESHOLD) {
                triggered = true;
                goToPrevZone();
            }
            // swipe vers la gauche (dx < 0) quand on est déjà à droite -> next
            else if (atRightEdge && dx < -THRESHOLD) {
                triggered = true;
                goToNextZone();
            }
        }, {passive: true});

        scroller.addEventListener('touchend', () => {
            // reset pour autoriser un nouveau geste
            triggered = false;
            moved = false;
        }, {passive: true});

        // Mettre à jour boutons quand on redimensionne / scroller rebuild
        window.addEventListener('resize', updateNavButtons);
    })();

    // Initial update (au cas où la page a pré-chargé une piscine/zone)
    updateNavButtons();
});