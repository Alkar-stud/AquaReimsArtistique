// Utils de validation existants
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function validateTel(tel) {
    const v = String(tel || '').replace(/\s+/g, '');
    // Accepte 0XXXXXXXXX (10 chiffres) ou +33XXXXXXXXX (9 chiffres sans le 0)
    return /^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/.test(v);
}



// Affichage "flash" côté client (similaire à $flash_message serveur)
function showFlash(type, message, containerId = 'ajax_flash_container') {
    const allowed = new Set(['success', 'info', 'warning', 'danger']);
    const level = allowed.has(type) ? type : 'danger';

    let container = document.getElementById(containerId);
    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        // Par défaut, on l'insère en haut du <main> s'il existe, sinon en body
        const main = document.querySelector('main') || document.body;
        main.prepend(container);
    }
    container.innerHTML = `
<div class="alert alert-${level}" role="alert">
  ${message ? String(message) : 'Une erreur est survenue.'}
</div>`;
}

// Extrait l'id de tarif à partir de name="tarifs[123]"
function parseTarifIdFromName(name) {
    const m = String(name || '').match(/^tarifs\[(\d+)]$/);
    return m ? m[1] : null;
}

function euroFromCents(cents) {
    const n = (parseInt(cents, 10) || 0) / 100;
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

// Exposer globalement
window.showFlash = showFlash;

