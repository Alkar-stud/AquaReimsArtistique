(function (global) {
    'use strict';
    const App = global.App || (global.App = {});

    // NumberFormat FR avec 2 décimales
    const nf2 = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Convertit des centimes en "12,34 €"
    function euroFromCents(cents) {
        const v = (parseInt(cents, 10) || 0) / 100;
        return nf2.format(v) + ' €';
    }

    // Convertit "12,34", "12.34", "12,34 €", 12.34 en centimes
    function toCents(input) {
        if (typeof input === 'number') {
            return Math.round(input * 100);
        }
        if (input == null) {
            return 0;
        }
        let s = String(input).trim()
            .replace(/\s/g, '')
            .replace(/€/g, '')
            .replace(/,/g, '.');
        const n = parseFloat(s);
        return Number.isFinite(n) ? Math.round(n * 100) : 0;
    }

    // Formate un nombre en FR (utilité générique)
    function numberFR(n, min = 0, max = 2) {
        const nf = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: min, maximumFractionDigits: max });
        const v = typeof n === 'number' ? n : parseFloat(n);
        return Number.isFinite(v) ? nf.format(v) : '';
    }

    // "12,34 € x N"
    function unitTimes(quantity, unitCents) {
        const qty = parseInt(quantity, 10) || 0;
        return `${nf2.format((unitCents || 0) / 100)} € × ${qty}`;
    }

    // Total "qty × unitCents" -> "12,34 €"
    function totalFromQty(qty, unitCents) {
        const q = parseInt(qty, 10) || 0;
        const u = parseInt(unitCents, 10) || 0;
        return euroFromCents(q * u);
    }

    // Dates FR
    function dateFR(value) {
        const d = new Date(value);
        return isNaN(d) ? '' : d.toLocaleDateString('fr-FR');
    }
    function dateTimeFR(value) {
        const d = new Date(value);
        return isNaN(d) ? '' : d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    }

    // Enregistrement module
    const api = {
        euroFromCents,
        toCents,
        numberFR,
        unitTimes,
        totalFromQty,
        dateFR,
        dateTimeFR
    };
    App.register('Format', api);

    // Alias globaux légers (optionnels)
    global.formatEuroCents = euroFromCents;
    global.toCents = toCents;
})(window);