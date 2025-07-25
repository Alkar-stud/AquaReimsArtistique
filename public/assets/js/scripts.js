//Pour le changement de mot de passe
document.addEventListener('DOMContentLoaded', function () {
    const current = document.getElementById('current_password');
    const nouveau = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    const btn = document.querySelector('form[action="/account/password"] button[type="submit"]');

    function checkFields() {
        const allFilled = current.value && nouveau.value && confirm.value;
        const same = nouveau.value === confirm.value;
        btn.disabled = !(allFilled && same);
    }

    if (current) {
        current.addEventListener('input', checkFields);
        nouveau.addEventListener('input', checkFields);
        confirm.addEventListener('input', checkFields);
        btn.disabled = true; // Désactivé par défaut
    }

});

/*
 * Gestion de la modale d'ajout des tarifs
 */
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-ajout-tarif');

    // Si la modale n'existe pas sur cette page, on arrête l'exécution de ce bloc.
    if (!modal) {
        return;
    }

    // Le reste du code ne s'exécutera que si la modale a été trouvée
    const form = modal.querySelector('form');

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            form.reset();
            modal.style.display = 'none';
        }
    });
    // Reset aussi lors de fermeture par bouton
    modal.querySelector('.btn-close').addEventListener('click', function() {
        form.reset();
    });
    // Reset aussi lors du bouton Annuler
    modal.querySelector('button[type="button"]').addEventListener('click', function() {
        form.reset();
    });
});

function showTab(tab) {
    // Cacher/afficher les contenus
    document.getElementById('content-places').style.display = tab === 'places' ? '' : 'none';
    document.getElementById('content-autres').style.display = tab === 'autres' ? '' : 'none';

    // Gérer les classes actives sur les onglets
    const tabs = ['all', 'places', 'autres'];
    tabs.forEach(function(name) {
        const el = document.getElementById('tab-' + name);
        if (el) {
            el.classList.toggle('active', tab === name);
        }
    });
}