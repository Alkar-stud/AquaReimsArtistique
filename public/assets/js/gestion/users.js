// Log et gestion du switch actif/inactif des utilisateurs
document.addEventListener('DOMContentLoaded', () => {

    function showInlineError(inputEl, message) {
        let box = inputEl.parentElement.querySelector('.inline-error-switch');
        if (!box) {
            box = document.createElement('div');
            box.className = 'inline-error-switch small text-danger mt-1';
            inputEl.parentElement.appendChild(box);
        }
        box.textContent = message;
        clearTimeout(box._timer);
        box._timer = setTimeout(() => {
            box.remove();
        }, 6000);
    }

    document.querySelectorAll('.user-status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const id = this.dataset.id;
            const actif = this.checked;
            const el = this;
            el.disabled = true;

            fetch('/gestion/users/suspend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id, actif })
            })
                .then(async resp => {
                    // Lecture du corps brut
                    const rawText = await resp.text();

                    // Tentative de parsing JSON
                    let json = null;
                    try {
                        if (rawText.trim() !== '') {
                            json = JSON.parse(rawText);
                        }
                    } catch (e) {
                        console.warn('Echec parsing JSON:', e.message);
                    }

                    // Gestion des erreurs HTTP
                    if (!resp.ok) {
                        const err = new Error((json && json.message) ? json.message : 'HTTP ' + resp.status);
                        err._status = resp.status;
                        err._rawBody = rawText;
                        throw err;
                    }

                    if (json && json.success === false) {
                        const err = new Error(json.message || 'Réponse serveur négative');
                        err._rawBody = rawText;
                        throw err;
                    }

                    return json || { success: true, fallback: true };
                })
                .catch(err => {
                    console.group('User suspend error');
                    console.error('ID ciblé:', id);
                    console.error('Nouvel état demandé (actif):', actif);
                    console.error('Message:', err.message);
                    if (err._status) console.error('Statut HTTP:', err._status);
                    if (err._rawBody !== undefined) {
                        console.error('Corps brut associé à l\'erreur:');
                        console.error(err._rawBody === '' ? '(vide)' : err._rawBody);
                    }
                    console.error('Objet erreur complet:', err);
                    console.groupEnd();

                    showInlineError(el, err.message || 'Erreur mise à jour');
                    el.checked = !actif;
                })
                .finally(() => {
                    el.disabled = false;
                });
        });
    });
});
