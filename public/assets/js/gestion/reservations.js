document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('reservations-tabs');
    const subtitle = document.getElementById('reservations-subtitle');
    const map = {
        upcoming: {
            title: 'Réservations à venir',
            pane: document.getElementById('tab-upcoming')
        },
        past: {
            title: 'Réservations passées',
            pane: document.getElementById('tab-past')
        }
    };

    tabsContainer.querySelectorAll('button[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab');
            if (!map[target]) return;

            // Onglets
            tabsContainer.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Panneaux
            Object.values(map).forEach(o => {
                o.pane.classList.add('d-none');
                o.pane.classList.remove('active');
            });
            map[target].pane.classList.remove('d-none');
            map[target].pane.classList.add('active');

            // Sous-titre
            subtitle.textContent = map[target].title;
        });
    });
});