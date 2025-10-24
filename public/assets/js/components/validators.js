// Validations

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateTel(tel) {
    const v = String(tel || '').replace(/\s+/g, '');
    // Accepte 0XXXXXXXXX (10 chiffres) ou +33XXXXXXXXX (9 chiffres sans le 0)
    return /^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/.test(v);
}

export {
    validateEmail,
    validateTel
}