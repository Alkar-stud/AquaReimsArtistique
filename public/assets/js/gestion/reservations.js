document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('reservations-tabs');

    const tabsConfig = {
        upcoming: {
            title: 'Réservations à venir',
            url: '/gestion/reservations/upcoming',
            paneId: 'tab-upcoming',
            loaded: false // Pour suivre si le contenu a été chargé
        },
        past: {
            title: 'Réservations passées',
            url: '/gestion/reservations/past',
            paneId: 'tab-past',
            loaded: false
        }
    };

    /**
     * Charge et affiche le contenu d'un onglet.
     * @param {string} targetKey La clé de l'onglet à charger (ex : 'upcoming').
     * @param {URLSearchParams} params Les paramètres de l'URL (session, page, etc.).
     * @param {boolean} updateHistory Faut-il mettre à jour l'URL du navigateur ?
     */
    const loadTabContent = async (targetKey, params, updateHistory = false) => {
        const config = tabsConfig[targetKey];
        if (!config) return;

        const pane = document.getElementById(config.paneId);
        if (!pane) return;

        // Afficher l'onglet et le panneau
        if (!params.has('session') && !params.has('page')) { // Ne changer l'onglet actif que lors du premier chargement
            tabsContainer.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
            tabsContainer.querySelector(`[data-tab="${targetKey}"]`).classList.add('active');
            Object.values(tabsConfig).forEach(c => {
                const p = document.getElementById(c.paneId);
                if (p) p.classList.remove('active', 'show');
            });
            pane.classList.add('active', 'show');
        }

        // Charger le contenu via fetch si ce n'est pas déjà fait
        // On recharge toujours si des paramètres sont présents pour refléter l'état
        pane.innerHTML = '<div class="d-flex justify-content-center align-items-center" style="min-height: 150px;"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        const url = `${config.url}?${params.toString()}`;

        if (updateHistory) {
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', targetKey);
            // Nettoyer les anciens params avant d'ajouter les nouveaux
            newUrl.searchParams.delete('session');
            newUrl.searchParams.delete('page');
            newUrl.searchParams.delete('per_page');
            params.forEach((value, key) => newUrl.searchParams.set(key, value));
            history.pushState({}, '', newUrl);
        }

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`Erreur réseau: ${response.statusText}`);
            const html = await response.text();
            pane.innerHTML = html;
        } catch (error) {
            console.error('Erreur lors du chargement du contenu de l\'onglet:', error);
            pane.innerHTML = '<div class="alert alert-danger">Impossible de charger les données. Veuillez réessayer.</div>';
        }
    };

    // Gérer le clic sur les onglets
    tabsContainer.querySelectorAll('button[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetKey = btn.getAttribute('data-tab');
            // Au clic sur un onglet, on repart de zéro (pas de session/page).
            loadTabContent(targetKey, new URLSearchParams(), true);
        });
    });

    // Délégation d'événements sur le conteneur de contenu
    document.querySelector('.tab-content').addEventListener('click', (event) => {
        // Clic sur un lien de pagination
        const paginationLink = event.target.closest('a.page-link');
        if (paginationLink) {
            event.preventDefault();
            const page = paginationLink.dataset.page;
            const sessionSelect = document.querySelector('#event-selector-upcoming');
            const perPageSelect = document.querySelector('#items-per-page');

            if (page && sessionSelect && sessionSelect.value) {
                const params = new URLSearchParams();
                params.set('session', sessionSelect.value);
                params.set('page', page);
                if (perPageSelect) {
                    params.set('per_page', perPageSelect.value);
                }
                loadTabContent(sessionSelect.dataset.tabKey, params, true);
            }
        }
    });

    document.querySelector('.tab-content').addEventListener('change', (event) => {
        // Changement de sélection dans la liste déroulante
        if (event.target.matches('select[data-tab-key]')) {
            const select = event.target;
            const tabKey = select.dataset.tabKey;
            const sessionId = select.value;
            if (sessionId) {
                const params = new URLSearchParams();
                params.set('session', sessionId);
                // On repart à la page 1 quand on change de session
                params.set('page', '1');
                const perPageSelect = document.querySelector('#items-per-page');
                if (perPageSelect) {
                    params.set('per_page', perPageSelect.value);
                }
                loadTabContent(tabKey, params, true);
            }
        }

        // Changement du nombre d'éléments par page
        if (event.target.matches('#items-per-page')) {
            const sessionSelect = document.querySelector('#event-selector-upcoming');
            if (sessionSelect && sessionSelect.value) {
                const params = new URLSearchParams();
                params.set('session', sessionSelect.value);
                params.set('page', '1'); // On retourne à la page 1.
                params.set('per_page', event.target.value);
                loadTabContent(sessionSelect.dataset.tabKey, params, true);
            }
        }
    });

    // Gérer l'ouverture de la modale de détails
    const detailModal = document.getElementById('reservationDetailModal');
    if (detailModal) {
        detailModal.addEventListener('show.bs.modal', async (event) => {
            const button = event.relatedTarget; // Le bouton qui a déclenché la modale
            const reservationId = button.dataset.reservationId;
            const tabKey = button.closest('.tab-pane').id.replace('tab-', ''); // 'upcoming' ou 'past'

            // Mettre à jour le titre de la modale avec le numéro de réservation
            const modalTitle = detailModal.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = `Détails de la réservation ARA-${reservationId.padStart(5, '0')}`;
            }

            const modalBody = detailModal.querySelector('.modal-body');
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

            try {
                const url = `/gestion/reservations/details/${reservationId}?context=${tabKey}`;
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error('La récupération des détails a échoué.');
                }

                const html = await response.text();
                modalBody.innerHTML = html;

            } catch (error) {
                console.error("Erreur lors du chargement des détails de la réservation:", error);
                modalBody.innerHTML = '<div class="alert alert-danger">Impossible de charger les détails. Veuillez réessayer.</div>';
            }
        });
    }

    // Charger le contenu de l'onglet actif par défaut au chargement de la page
    const initialParams = new URLSearchParams(window.location.search);
    const initialTabKey = initialParams.get('tab') || 'upcoming';
    loadTabContent(initialTabKey, initialParams);


    document.body.addEventListener('click', function (e) {
        if (e.target.matches('.view-details-btn')) {
            e.preventDefault();
            const detailsModal = new bootstrap.Modal(document.getElementById('reservation-details-modal'));
            const modalBody = document.getElementById('reservation-details-modal-body');
            const reservationId = e.target.dataset.id;
            const context = e.target.dataset.context;

            fetch(`/gestion/reservations/details/${reservationId}?context=${context}`)
                .then(response => response.text())
                .then(html => {
                    modalBody.innerHTML = html;
                    detailsModal.show();

                    // Initialise les écouteurs pour les champs de contact
                    // Cette fonction est définie dans reservation_modif_data.js
                    if (typeof initContactFieldListeners === 'function') {
                        initContactFieldListeners();
                    }
                    // Vous pouvez faire de même pour les autres types de champs (details, complements)
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des détails:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Impossible de charger les détails.</div>';
                    detailsModal.show();
                });
        }
    });
});