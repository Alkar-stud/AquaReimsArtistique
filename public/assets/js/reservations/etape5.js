import {apiGet} from "../components/apiClient.js";
import {createBleacherGrid} from "../components/bleacherGrid.js";
document.addEventListener('DOMContentLoaded', () => {
    //Pour gérer les click sur les zones
    const zonesList = document.querySelector('[data-component="zones-list"]');
    const bleacher = document.querySelector('[data-component="bleacher"]');
    if (!zonesList || !bleacher) {
        return;
    }

    const zoneNameEl = bleacher.querySelector('[data-bleacher-zone-name]');
    const backBtn = bleacher.querySelector('[data-action="back-zones"]');
    const refreshBtn = bleacher.querySelector('[data-action="refresh-bleacher"]');

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
        if (loading) {
            return;
        }
        loading = true;
        refreshBtn?.classList.add('disabled');
        try {
            const response = await apiGet(`/piscine/gradins/${piscineId}/${zoneId}`, {});
console.log('reçu : ', response.plan.zone);
            if (!response.success || !response.plan || !response.plan.zone) {
                console.warn('Plan invalide', response);
                return;
            }
            const z = response.plan.zone;
            const nbRows = response.plan.rows?.length ?? 0;
            const nbCols = response.plan.cols ?? 0;

            bleacher.dataset.zoneId = z.id ?? zoneId;
            zoneNameEl.textContent = z.zoneName ?? z.getZoneName ?? 'Zone';

           if (response.plan) {
               const gridContainer = bleacher.querySelector('[data-bleacher-seats]');
               gridInstance = createBleacherGrid(gridContainer, response.plan, { mode: 'reservation' });
           }

        } catch (err) {
            console.error('Erreur API:', err);
        } finally {
            loading = false;
            refreshBtn?.classList.remove('disabled');
        }
    }

    // Délégation de clic pour gérer aussi des ajouts dynamiques éventuels
    zonesList.addEventListener('click', async (e) => {

        const piscineEl = e.target.closest('[data-piscine-id]');
        const zoneEl = e.target.closest('[data-zone-id]');
        if (!piscineEl || !zoneEl) {
            return;
        }
        if (zoneEl.getAttribute('aria-disabled') === 'true') {
            e.preventDefault();
            return;
        }
        const piscineId = Number(piscineEl.dataset.piscineId);
        const zoneId = Number(zoneEl.dataset.zoneId);
        if (!Number.isInteger(zoneId) || zoneId <= 0) {
            return;
        }
        if (!Number.isInteger(piscineId) || piscineId <= 0) {
            return;
        }

        currentPiscineId = piscineId;
        currentZoneId = zoneId;

        console.log('Piscine ID:', piscineEl.dataset.piscineId);
        console.log('Zone ID:', zoneEl.dataset.zoneId);
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
});