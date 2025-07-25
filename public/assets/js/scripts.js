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