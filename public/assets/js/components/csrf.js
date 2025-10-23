(function (global) {
    'use strict';
    const App = global.App || (global.App = {});

    function get() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : null;
    }
    function update(token) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && token) meta.content = token;
    }

    App.register('CSRF', { get, update });
})(window);