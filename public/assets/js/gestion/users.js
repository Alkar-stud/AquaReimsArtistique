import { showFlashMessage } from '/assets/js/components/ui.js';
document.addEventListener('DOMContentLoaded', () => {
    //Mettre à jour le toggle seul dynamiquement
    document.querySelectorAll('.user-status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const el = this;
            const id = this.dataset.id;
            const actif = this.checked;
            el.disabled = true;

            const container = el.closest('tr, .card');
            const val = name => {
                const input = container.querySelector(`[name="${name}"]`);
                return input ? input.value : '';
            };

            const formData = new FormData();
            formData.append('user_id', id);
            formData.append('username', val('username'));
            formData.append('email', val('email'));
            formData.append('display_name', val('display_name'));
            formData.append('role', val('role'));
            formData.append('is_active', actif ? '1' : '0');

            fetch('/gestion/users/edit', {
                method: 'POST',
                body: formData,
                redirect: 'follow'
            })
                .then(async resp => {
                    const rawText = await resp.text();
                    if (resp.status >= 400) {
                        const err = new Error('HTTP ' + resp.status);
                        err._rawBody = rawText;
                        throw err;
                    }
                    showFlashMessage('success', 'Statut utilisateur (et uniquement le statut) mis à jour.');
                })
                .catch(err => {
                    console.group('User active toggle error');
                    console.error('ID:', id);
                    console.error('Demande actif:', actif);
                    console.error('Message:', err.message);
                    if (err._rawBody !== undefined) {
                        console.error('Corps réponse:');
                        console.error(err._rawBody === '' ? '(vide)' : err._rawBody);
                    }
                    console.groupEnd();
                    showFlashMessage('danger', err.message || 'Erreur mise à jour');
                    el.checked = !actif;
                })
                .finally(() => {
                    el.disabled = false;
                });
        });
    });

});
