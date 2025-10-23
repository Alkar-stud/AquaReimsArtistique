(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const registry = {};
    let readyResolve;
    App.ready = new Promise(r => (readyResolve = r));

    // Enregistre un module dans le namespace
    App.register = function register(name, api) {
        if (!name || registry[name]) return registry[name];
        registry[name] = api || {};
        App[name] = registry[name];
        return registry[name];
    };

    // Charge un script dynamiquement (séquentiel garanti si chainé)
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src;
            // Important: pas d'async pour respecter l'ordre (les dynamiques sont async par défaut),
            // on force l'ordre en attendant onload avant de passer au suivant.
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('Echec chargement: ' + src));
            document.head.appendChild(s);
        });
    }

    // Charge une liste de scripts en série
    async function loadInOrder(urls) {
        for (const u of urls) {
            await loadScript(u);
        }
    }

    // Boot: charger les modules cœur, puis publier les alias globaux
    (async function boot() {
        const coreModules = [
            '/assets/js/components/csrf.js',
            '/assets/js/components/api_client.js',
            '/assets/js/components/ui.js',
            '/assets/js/components/scroll_manager.js',
            '/assets/js/components/validators.js',
            '/assets/js/components/format.js',
            '/assets/js/app/reservations_api.js',
            '/assets/js/components/reservations/contact_component.js',
            '/assets/js/components/reservations/participants_component.js',
            '/assets/js/components/reservations/complements_component.js',

        ];

        // Option: ajoutez ici des scripts de page pour garantir que tout attend App.ready
        // ex: coreModules.push('/assets/js/scripts.js', '/assets/js/gestion/reservations.js');

        await loadInOrder(coreModules);

        // Pont ScrollManager -> App
        if (!App.ScrollManager && global.ScrollManager) {
            App.ScrollManager = global.ScrollManager;
        }

        // Alias globaux rétrocompatibles
        if (App.Api) {
            global.apiPost = App.Api.post;
            global.apiGet = App.Api.get;
        }
        if (App.Feedback) {
            global.showFeedback = App.Feedback.show;
        }
        if (App.UI) {
            global.showFlash = App.UI.showFlash;
        }
    if (App.Format) {
        global.formatEuroCents = App.Format.euroFromCents;
        global.toCents = App.Format.toCents;
    }

        readyResolve(true);
    })();

})(window);
