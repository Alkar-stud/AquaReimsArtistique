(function (global) {
    'use strict';
    const App = global.App || (global.App = {});

    // Petit helper pour centraliser le feedback visuel
    function withFeedback(promise, span) {
        if (span && global.showFeedback) global.showFeedback(span, 'pending', '');
        return promise
            .then((res) => {
                if (span && global.showFeedback) global.showFeedback(span, 'success', 'OK');
                return res;
            })
            .catch((err) => {
                if (span && global.showFeedback) {
                    const msg = err?.userMessage || err?.message || 'Erreur';
                    global.showFeedback(span, 'error', msg);
                }
                throw err;
            });
    }

    async function updateField(feedbackSpan, data, successCallback = null, opts = {}) {
        const headers = {
            'X-CSRF-Context': opts.csrfContext || '/reservation',
            ...(opts.headers || {}),
        };
        const p = App.Api.post('/modifData/update', data, { headers });
        const res = await withFeedback(p, feedbackSpan);
        console.log('res : ', res);
        if (typeof successCallback === 'function') {
            try { successCallback(res); } catch (_) { /* noop */ }
        }
        // Pour les appels via updateField (utilisé par modifData), on recharge toujours la page en cas de succès.
        if (res && res.success) {
            if (global.ScrollManager) global.ScrollManager.save();
            window.location.reload();
        }
        return res;
    }

    // Actions complémentaires réutilisables depuis la modale de gestion
    function updateComplementQuantity({ token, reservationId, id, action }, opts = {}) {
        const headers = { 'X-CSRF-Context': opts.csrfContext || '/reservation' };
        return App.Api.post('/modifData/update', {
            typeField: 'complement',
            token, reservationId, id, action
        }, { headers });
    }

    function checkPaymentState(paymentId) {
        return App.Api.get('/reservation/checkPaymentState', { id: paymentId });
    }

    App.register('Reservations', {
        updateField,
        updateComplementQuantity,
        checkPaymentState,
    });

    // Alias global pour ne pas modifier votre code existant
    global.updateField = updateField;
})(window);
