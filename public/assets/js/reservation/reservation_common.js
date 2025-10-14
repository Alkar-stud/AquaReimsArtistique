// Utils de validation existants
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function validateTel(tel) {
    const v = String(tel || '').replace(/\s+/g, '');
    // Accepte 0XXXXXXXXX (10 chiffres) ou +33XXXXXXXXX (9 chiffres sans le 0)
    return /^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/.test(v);
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


