import { updateReservationField } from './reservationUpdater.js';
import { showFlashMessage } from '../utils/flashMessages.js';

document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('reservations-tabs');
    const contentContainer = document.getElementById('reservations-content');
    const flashContainer = document.getElementById('flash-message-container');

    const tabsConfig = {
        upcoming: {
            title: 'Réservations à venir',
            url: '/gestion/reservations/upcoming',
        },
        past: {
            title: 'Réservations passées',
            url: '/gestion/reservations/past',
        }
    };

    /**
     * Charge et affiche le contenu d'un onglet.
     * @param {string} targetKey La clé de l'onglet à charger (ex : 'upcoming').
     * @param {URLSearchParams} [params] Les paramètres de l'URL (session, page, etc.).
     * @param {boolean} updateHistory Faut-il mettre à jour l'URL du navigateur ?
     */
    const loadTabContent = async (targetKey, params = new URLSearchParams(), updateHistory = false) => {
        const config = tabsConfig[targetKey];
        if (!config) return;

        // Afficher l'onglet comme actif
        tabsContainer.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
        tabsContainer.querySelector(`[data-tab="${targetKey}"]`).classList.add('active');

        // Afficher un spinner de chargement
        contentContainer.innerHTML = '<div class="d-flex justify-content-center align-items-center" style="min-height: 150px;"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        const url = `${config.url}?${params.toString()}`;

        if (updateHistory) {
            const newUrl = new URL(window.location);
            // On reconstruit les searchParams pour garder le contrôle
            newUrl.search = ''; // On nettoie
            newUrl.searchParams.set('tab', targetKey);
            params.forEach((value, key) => newUrl.searchParams.set(key, value));
            history.pushState({}, '', newUrl);
        }

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`Erreur réseau : ${response.statusText}`);
            const html = await response.text();
            contentContainer.innerHTML = html;
        } catch (error) {
            console.error('Erreur lors du chargement du contenu de l\'onglet:', error);
            contentContainer.innerHTML = '<div class="alert alert-danger">Impossible de charger les données. Veuillez réessayer.</div>';
        }
    };

    // --- GESTION DES EVENEMENTS ---

    // Clic sur les onglets
    tabsContainer.querySelectorAll('button[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetKey = btn.getAttribute('data-tab');
            loadTabContent(targetKey, null, true);
        });
    });

    // Délégation d'événements pour la pagination, sélection de session, etc.
    contentContainer.addEventListener('click', (event) => {
        const paginationLink = event.target.closest('a.page-link');
        if (paginationLink && !paginationLink.closest('.disabled')) {
            event.preventDefault();
            const params = new URLSearchParams(window.location.search);
            params.set('page', paginationLink.dataset.page);
            loadTabContent(params.get('tab') || 'upcoming', params, true);
        }
    });

    contentContainer.addEventListener('change', (event) => {
        const target = event.target;
        const params = new URLSearchParams(window.location.search);

        if (target.matches('#event-selector-upcoming')) {
            params.set('session', target.value);
            params.set('page', '1'); // Reset à la page 1
            loadTabContent(params.get('tab') || 'upcoming', params, true);
        }

        if (target.matches('#items-per-page')) {
            params.set('per_page', target.value);
            params.set('page', '1'); // Reset à la page 1
            loadTabContent(params.get('tab') || 'upcoming', params, true);
        }
    });

    // --- GESTION DE LA MODALE ---

    const detailModal = document.getElementById('reservationDetailModal');
    if (detailModal) {
        detailModal.addEventListener('show.bs.modal', async (event) => {
            const button = event.relatedTarget;
            const reservationId = button.dataset.reservationId;
            const tabKey = tabsContainer.querySelector('.nav-link.active').dataset.tab;

            const modalTitle = detailModal.querySelector('.modal-title');
            const modalBody = detailModal.querySelector('.modal-body');

            modalTitle.textContent = `Détails de la réservation ARA-${String(reservationId).padStart(5, '0')}`;
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

            try {
                const url = `/gestion/reservations/details/${reservationId}?context=${tabKey}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error('La récupération des détails a échoué.');
                const html = await response.text();
                modalBody.innerHTML = html;
            } catch (error) {
                console.error("Erreur lors du chargement des détails de la réservation:", error);
                modalBody.innerHTML = '<div class="alert alert-danger">Impossible de charger les détails. Veuillez réessayer.</div>';
            }
        });

        // Délégation pour les champs éditables dans la modale
        detailModal.addEventListener('blur', async (event) => {
            if (event.target && event.target.matches('.editable-field')) {
                const input = event.target;
                const data = {
                    typeField: input.dataset.type, // 'contact' ou 'detail'
                    reservationId: input.dataset.reservationId,
                    detailId: input.dataset.detailId, // Sera undefined pour les champs 'contact'
                    field: input.dataset.field,
                    value: input.value,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content,
                    feedbackSpan: input.parentElement.querySelector('.feedback-span')
                };

                try {
                    const result = await updateReservationField(data);
                    if (result.success && result.flash_message) {
                        showFlashMessage(result.flash_message.type, result.flash_message.message, flashContainer);
                        // Recharger le contenu de l'onglet pour voir les changements
                        const currentParams = new URLSearchParams(window.location.search);
                        loadTabContent(currentParams.get('tab') || 'upcoming', currentParams);
                    }
                } catch (error) {
                    // L'erreur est déjà gérée et logguée dans `updateReservationField`
                }
            }
        }, true); // Use capture phase
    }

    // --- INITIALISATION ---

    const initialParams = new URLSearchParams(window.location.search);
    const initialTabKey = initialParams.get('tab') || 'upcoming';
    loadTabContent(initialTabKey, initialParams);
});