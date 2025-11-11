// Validations
/**
 * Valide une adresse e‑mail avec une règle simple de type `local@domaine.tld`.
 * - Fonction pure: aucune interaction DOM, aucun effet de bord.
 * @param {string} email - Adresse e‑mail à valider.
 * @returns {boolean} `true` si le format correspond, sinon `false`.
 */
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Valide un numéro de téléphone FR:
 * - Formats acceptés (espaces ignorés): `0XXXXXXXXX` ou `+33XXXXXXXXX` (sans le 0).
 * - Fonction pure: aucune interaction DOM, aucun effet de bord.
 * @param {string} tel - Numéro à valider.
 * @returns {boolean} `true` si conforme, sinon `false`.
 */
function validateTel(tel) {
    const v = String(tel || '').replace(/\s+/g, '');
    return /^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/.test(v);
}

export {
    validateEmail,
    validateTel
}
