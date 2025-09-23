// Active le bouton "Changer mon mot de passe" quand le formulaire est valide
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action="/account/password"]');
    if (!form) return;

    const current = form.querySelector('#current_password');
    const newPwd = form.querySelector('#new_password');
    const confirm = form.querySelector('#confirm_password');
    const submit = form.querySelector('button[type="submit"]');

    const validate = () => {
        const cur = current.value.trim();
        const newVal = newPwd.value;
        const confVal = confirm.value;

        // Conditions côté client en cohérence avec le contrôleur
        const allFilled = cur.length > 0 && newVal.length > 0 && confVal.length > 0;
        const match = newVal === confVal;
        const differentFromCurrent = newVal !== cur;

        // Feedback visuel Bootstrap
        if (newVal.length === 0 && confVal.length === 0) {
            newPwd.classList.remove('is-valid', 'is-invalid');
            confirm.classList.remove('is-valid', 'is-invalid');
        } else {
            newPwd.classList.toggle('is-invalid', !match);
            confirm.classList.toggle('is-invalid', !match);
            newPwd.classList.toggle('is-valid', match && newVal.length > 0);
            confirm.classList.toggle('is-valid', match && confVal.length > 0);
        }

        // Messages de validité pour le HTML5 validation UI
        newPwd.setCustomValidity(differentFromCurrent ? '' : "Le nouveau mot de passe doit être différent de l'actuel.");
        confirm.setCustomValidity(match ? '' : 'Les mots de passe ne correspondent pas.');

        submit.disabled = !(allFilled && match && differentFromCurrent);
    };

    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        current.addEventListener(evt, validate);
        newPwd.addEventListener(evt, validate);
        confirm.addEventListener(evt, validate);
    });

    // Initialisation
    validate();
});
